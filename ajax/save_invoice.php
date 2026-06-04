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

        // Generate Invoice Number using sequential document numbering rather than random ids
        $prefix = ($type === 'tax') ? 'INV-' : (($type === 'proforma') ? 'PI-' : 'EST-');
        $invNum = generateSequentialReference($pdo, 'invoices', 'invoice_number', $prefix, 5);

        // Insert Invoice
        $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

        $stmtInv = $pdo->prepare("
            INSERT INTO invoices (invoice_number, booking_id, entity_id, type, sub_total, cgst, sgst, igst, total_amount, payment_status, approval_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)
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
