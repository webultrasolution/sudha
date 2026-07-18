<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $bookingId = intval($data['booking_id']);
    $type = clean($data['type']);
    $entityId = !empty($data['entity_id']) ? intval($data['entity_id']) : null;

    try {
        // Fetch Booking details directly for financials
        $stmt = $pdo->prepare("
            SELECT total_amount, tax_amount, grand_total, tax_type 
            FROM bookings 
            WHERE id = ?
        ");
        $stmt->execute([$bookingId]);
        $fin = $stmt->fetch();

        if (!$fin) {
            throw new Exception("Booking details not found.");
        }

        // Read Invoice Number manually supplied by user
        $invNum = clean($data['invoice_number'] ?? '');
        if (empty($invNum)) {
            if ($type === 'tax') {
                throw new Exception("Invoice Number is mandatory.");
            } else {
                $prefix = ($type === 'proforma') ? 'PI-' : 'EST-';
                $invNum = generateSequentialReference($pdo, 'invoices', 'invoice_number', $prefix, 4);
            }
        }

        // Sync sequence registry for manual entry if it's tax invoice
        if ($type === 'tax') {
            syncSequenceNextValue($pdo, 'invoice', $invNum, $entityId);
        }

        // Check if the booking has any vendor sites
        $stmtVendorSites = $pdo->prepare("
            SELECT COUNT(*) 
            FROM booking_items bi
            JOIN sites s ON bi.site_id = s.id
            WHERE bi.booking_id = ? AND s.owner_type = 'TA'
        ");
        $stmtVendorSites->execute([$bookingId]);
        $hasVendorSites = ($stmtVendorSites->fetchColumn() > 0);

        // Auto approve if admin, if it is NOT a final tax invoice (type === 'tax'), or if it has NO vendor sites
        $needsApproval = (!$isAdmin && $type === 'tax' && $hasVendorSites);
        $approvalStatus = $needsApproval ? 'pending_approval' : 'approved';

        $invoice_date = !empty($data['date']) ? $data['date'] : date('Y-m-d');

        $stmtInv = $pdo->prepare("
            INSERT INTO invoices (invoice_number, booking_id, entity_id, invoice_date, type, sub_total, cgst, sgst, igst, total_amount, payment_status, approval_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)
        ");
        
        $tax_amount = floatval($fin['tax_amount'] ?? 0);
        $tax_type = $fin['tax_type'] ?? 'igst';
        
        $cgst = 0;
        $sgst = 0;
        $igst = 0;
        
        if ($tax_type === 'cgst_sgst') {
            $cgst = $tax_amount / 2;
            $sgst = $tax_amount / 2;
        } else {
            $igst = $tax_amount;
        }

        $stmtInv->execute([
            $invNum,
            $bookingId,
            $entityId,
            $invoice_date,
            $type,
            $fin['total_amount'],
            $cgst,
            $sgst,
            $igst,
            $fin['grand_total'],
            $approvalStatus
        ]);

        $invoiceId = $pdo->lastInsertId();

        // Create approval request for non-admin invoice
        if ($needsApproval) {
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('invoice', ?, ?, ?, 'pending')");
            $stmtAR->execute([$invoiceId, $invNum, $_SESSION['user_id']]);
        }

        // If it is a final tax invoice with vendor sites, trigger vendor PO approvals
        if ($type === 'tax' && $hasVendorSites) {
            $poStmt = $pdo->prepare("SELECT id, po_number, approval_status FROM purchase_orders WHERE campaign_id = ?");
            $poStmt->execute([$bookingId]);
            $pos = $poStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($pos as $po) {
                if (in_array($po['approval_status'], ['draft', 'rejected'])) {
                    $newStatus = $isAdmin ? 'approved' : 'pending';
                    $newAppStatus = $isAdmin ? 'approved' : 'pending_approval';
                    
                    $upStmt = $pdo->prepare("UPDATE purchase_orders SET status = ?, approval_status = ? WHERE id = ?");
                    $upStmt->execute([$newStatus, $newAppStatus, $po['id']]);
                    
                    if (!$isAdmin) {
                        $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'purchase_order' AND entity_id = ? AND status = 'pending'")
                            ->execute([$po['id']]);
                        $arStmt = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
                        $arStmt->execute([$po['id'], $po['po_number'], $_SESSION['user_id']]);
                    }
                }
            }
        }

        echo json_encode([
            'success'         => true,
            'invoice_number'  => $invNum,
            'approval_status' => $approvalStatus,
            'message'         => $isAdmin
                ? "Invoice $invNum generated."
                : "Invoice $invNum submitted for admin approval."
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
