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
        // Fetch Booking/Proposal details for financials
        $stmt = $pdo->prepare("
            SELECT p.total_amount, p.tax_amount, p.grand_total 
            FROM bookings b 
            JOIN proposals p ON b.proposal_id = p.id 
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $fin = $stmt->fetch();

        if (!$fin) {
            throw new Exception("Booking details not found.");
        }

        // Generate Invoice Number
        $prefix = ($type === 'tax') ? 'INV' : (($type === 'proforma') ? 'PI' : 'EST');
        $invNum = $prefix . '-' . date('Ymd') . '-' . rand(100, 999);

        // Insert Invoice
        $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

        $stmtInv = $pdo->prepare("
            INSERT INTO invoices (invoice_number, booking_id, entity_id, type, sub_total, cgst, sgst, igst, total_amount, payment_status, approval_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)
        ");
        
        // Simplified GST logic: Split 18% into 9/9 for Intra-state
        $cgst = $fin['tax_amount'] / 2;
        $sgst = $fin['tax_amount'] / 2;
        $igst = 0;

        $stmtInv->execute([
            $invNum,
            $bookingId,
            $entityId,
            $type,
            $fin['total_amount'],
            $cgst,
            $sgst,
            $igst,
            $fin['grand_total'],
            $approvalStatus
        ]);

        $invoiceId = $pdo->lastInsertId();

        // Create approval request for non-admin
        if (!$isAdmin) {
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('invoice', ?, ?, ?, 'pending')");
            $stmtAR->execute([$invoiceId, $invNum, $_SESSION['user_id']]);
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
