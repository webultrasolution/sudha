<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('bookings') && !canEdit('clients')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to upload client PO.']);
        exit;
    }
    $client_id = intval($_POST['client_id']);
    $booking_id = intval($_POST['booking_id']);
    $po_no = clean($_POST['po_no']);
    $po_date = clean($_POST['po_date']);
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/client_pos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $filename = time() . '_client_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $stmt = $pdo->prepare("INSERT INTO client_pos (client_id, booking_id, po_number, po_date, filename, status) VALUES (?, ?, ?, ?, ?, 'approved')");
            $stmt->execute([$client_id, $booking_id, $po_no, $po_date, $filename]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Move failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload error or no file']);
    }
}
