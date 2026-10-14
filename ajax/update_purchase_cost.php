<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id      = intval($_POST['item_id'] ?? 0);
    $purchase_cost = floatval($_POST['purchase_cost'] ?? 0);

    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Item ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Fetch unit
        $stmtFetchUnit = $pdo->prepare("SELECT unit FROM booking_items WHERE id = ?");
        $stmtFetchUnit->execute([$item_id]);
        $u = max(1, intval($stmtFetchUnit->fetchColumn() ?: 1));
        $calculatedPurchaseRate = round($purchase_cost / $u, 2);

        // 1. Update booking_items (both purchase_amount and purchase_rate)
        $stmt = $pdo->prepare("UPDATE booking_items SET purchase_amount = ?, purchase_rate = ? WHERE id = ?");
        $stmt->execute([$purchase_cost, $calculatedPurchaseRate, $item_id]);

        // 2. Fetch the booking_id and site_id for this item
        $stmtFetch = $pdo->prepare("SELECT booking_id, site_id FROM booking_items WHERE id = ?");
        $stmtFetch->execute([$item_id]);
        $row = $stmtFetch->fetch();

        $syncedToPo = false;
        $poLocked = false;

        if ($row) {
            $bookingId = intval($row['booking_id']);
            $siteId    = intval($row['site_id']);

            // 3. Find PO specifically linked to THIS booking (campaign_id = bookingId) AND this site's vendor
            $stmtPO = $pdo->prepare("
                SELECT po.id, po.approval_status, po.vendor_invoice_approval_status, po.vendor_invoice_no, s.vendor_id
                FROM purchase_orders po
                JOIN sites s ON s.id = ?
                WHERE po.campaign_id = ? AND po.vendor_id = s.vendor_id
                LIMIT 1
            ");
            $stmtPO->execute([$siteId, $bookingId]);
            $poData = $stmtPO->fetch();

            if ($poData) {
                $isApproved = (strtolower($poData['approval_status'] ?? '') === 'approved');
                $isInvoiceApproved = (strtolower($poData['vendor_invoice_approval_status'] ?? '') === 'approved');
                $hasInvoice = !empty($poData['vendor_invoice_no']);

                // Requirement: If already approved or vendor invoice approved/uploaded, do NOT alter the PO
                if ($isApproved || $isInvoiceApproved || $hasInvoice) {
                    $poLocked = true;
                } else {
                    $poId = intval($poData['id']);

                    // Update the specific po_items row for this exact PO and this site
                    $stmtSync = $pdo->prepare("
                        UPDATE po_items
                        SET cost = ?, monthly_rate = ?
                        WHERE po_id = ? AND site_id = ?
                    ");
                    $stmtSync->execute([$purchase_cost, $purchase_cost, $poId, $siteId]);

                    // Recalculate PO header totals
                    $vendorId = intval($poData['vendor_id']);
                    $stmtGst = $pdo->prepare("SELECT gstin FROM partners WHERE id = ?");
                    $stmtGst->execute([$vendorId]);
                    $db_vendor_gst = trim($stmtGst->fetchColumn() ?: '');
                    $vendor_has_gst = vendorHasGST($db_vendor_gst);

                    $stmtSum = $pdo->prepare("SELECT COALESCE(SUM(cost), 0) FROM po_items WHERE po_id = ?");
                    $stmtSum->execute([$poId]);
                    $newSubtotal = floatval($stmtSum->fetchColumn());

                    $newCgst = 0;
                    $newSgst = 0;
                    if ($vendor_has_gst) {
                        $newCgst = round($newSubtotal * 0.09, 2);
                        $newSgst = round($newSubtotal * 0.09, 2);
                    }
                    $newTotal = $newSubtotal + $newCgst + $newSgst;

                    $stmtUpPO = $pdo->prepare("UPDATE purchase_orders SET po_amount = ?, cgst_amount = ?, sgst_amount = ?, total_amount = ? WHERE id = ?");
                    $stmtUpPO->execute([$newSubtotal, $newCgst, $newSgst, $newTotal, $poId]);
                    $syncedToPo = true;
                }
            }
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'synced_to_po' => $syncedToPo,
            'po_locked' => $poLocked,
            'purchase_rate' => $calculatedPurchaseRate
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
