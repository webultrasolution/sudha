<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('bookings') && !canAdd('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to confirm bookings or generate invoices.']);
        exit;
    }
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $type = clean($_POST['confirmation_type'] ?? 'po');
    $po_no = clean($_POST['customer_po_no'] ?? '');
    $po_date = clean($_POST['customer_po_date'] ?? '');
    $email_date = clean($_POST['email_date'] ?? '');
    
    if (!$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid Booking ID']);
        exit;
    }

    $file_path = null;
    if (isset($_FILES['customer_po_file']) && $_FILES['customer_po_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/customer_pos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extension = pathinfo($_FILES['customer_po_file']['name'], PATHINFO_EXTENSION);
        $prefix = ($type === 'email') ? 'EMAIL_' : 'PO_';
        $filename = $prefix . $booking_id . '_' . time() . '.' . $extension;
        $target = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['customer_po_file']['tmp_name'], $target)) {
            $file_path = 'uploads/customer_pos/' . $filename;
        }
    }

    $sql = "UPDATE bookings SET confirmation_type = ?, customer_po_no = ?, customer_po_date = ?, email_date = ?" . ($file_path ? ", customer_po_file = ?" : "") . " WHERE id = ?";
    $params = [$type, $po_no ?: null, $po_date ?: null, $email_date ?: null];
    if ($file_path) $params[] = $file_path;
    $params[] = $booking_id;

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        // Automatically create record in 'invoices' table if it doesn't exist
        $checkInvoice = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ?");
        $checkInvoice->execute([$booking_id]);
        if (!$checkInvoice->fetch()) {
            // Fetch booking totals for the invoice record
            $stmtBooking = $pdo->prepare("SELECT total_amount, grand_total FROM bookings WHERE id = ?");
            $stmtBooking->execute([$booking_id]);
            $bookingData = $stmtBooking->fetch();
            
            $invNo = 'INV/' . date('Y') . '/' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
            
            $stmtInsert = $pdo->prepare("INSERT INTO invoices (invoice_number, booking_id, type, sub_total, total_amount, payment_status) VALUES (?, ?, 'tax', ?, ?, 'unpaid')");
            $stmtInsert->execute([$invNo, $booking_id, $bookingData['total_amount'], $bookingData['grand_total']]);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Update Failed']);
    }
}
?>
