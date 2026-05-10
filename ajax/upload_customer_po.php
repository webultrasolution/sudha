<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Update Failed']);
    }
}
?>
