<?php
include_once __DIR__ . '/../config/db.php';
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

        // 1. Update booking_items
        $stmt = $pdo->prepare("UPDATE booking_items SET purchase_amount = ? WHERE id = ?");
        $stmt->execute([$purchase_cost, $item_id]);

        // 2. Fetch the booking_id and site_id for this item
        $stmtFetch = $pdo->prepare("SELECT booking_id, site_id FROM booking_items WHERE id = ?");
        $stmtFetch->execute([$item_id]);
        $row = $stmtFetch->fetch();

        if ($row) {
            // 3. Sync to po_items if a PO exists for this booking + site
            $stmtSync = $pdo->prepare("
                UPDATE po_items pi
                JOIN purchase_orders po ON pi.po_id = po.id
                SET pi.cost = ?, pi.monthly_rate = ?
                WHERE pi.site_id = ?
                  AND po.campaign_id = ?
            ");
            $stmtSync->execute([$purchase_cost, $purchase_cost, $row['site_id'], $row['booking_id']]);

            // 4. Recalculate PO header totals from all its items
            $stmtPOid = $pdo->prepare("SELECT id FROM purchase_orders WHERE campaign_id = ? AND vendor_id = (SELECT vendor_id FROM sites WHERE id = ?) LIMIT 1");
            $stmtPOid->execute([$row['booking_id'], $row['site_id']]);
            $poId = $stmtPOid->fetchColumn();

            if ($poId) {
                // Fetch vendor ID
                $stmtVendor = $pdo->prepare("SELECT vendor_id FROM sites WHERE id = ?");
                $stmtVendor->execute([$row['site_id']]);
                $vendorId = $stmtVendor->fetchColumn();

                // Check if vendor has GSTIN in database
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
                    $newCgst = $newSubtotal * 0.09;
                    $newSgst = $newSubtotal * 0.09;
                }
                $newTotal = $newSubtotal + $newCgst + $newSgst;

                $stmtUpPO = $pdo->prepare("UPDATE purchase_orders SET po_amount = ?, cgst_amount = ?, sgst_amount = ?, total_amount = ? WHERE id = ?");
                $stmtUpPO->execute([$newSubtotal, $newCgst, $newSgst, $newTotal, $poId]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'synced_to_po' => ($row ? true : false)]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
