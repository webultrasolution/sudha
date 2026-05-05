<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $bookingId = intval($data['booking_id']);
    $type = clean($data['type']);

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
        $stmtInv = $pdo->prepare("
            INSERT INTO invoices (invoice_number, booking_id, type, sub_total, cgst, sgst, igst, total_amount, payment_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')
        ");
        
        // Simplified GST logic: Split 18% into 9/9 for Intra-state
        $cgst = $fin['tax_amount'] / 2;
        $sgst = $fin['tax_amount'] / 2;
        $igst = 0;

        $stmtInv->execute([
            $invNum,
            $bookingId,
            $type,
            $fin['total_amount'],
            $cgst,
            $sgst,
            $igst,
            $fin['grand_total']
        ]);

        echo json_encode(['success' => true, 'invoice_number' => $invNum]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
