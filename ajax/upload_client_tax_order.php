<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to upload client tax orders.']);
        exit;
    }
    $po_id = intval($_POST['po_id']);
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/pos/tax_orders/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        // Delete old file if exists
        $stmt = $pdo->prepare("SELECT client_tax_order FROM purchase_orders WHERE id = ?");
        $stmt->execute([$po_id]);
        $existing = $stmt->fetchColumn();
        if ($existing && file_exists($uploadDir . $existing)) {
            unlink($uploadDir . $existing);
        }
        
        $filename = 'cto_' . $po_id . '_' . time() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET client_tax_order = ? WHERE id = ?");
            $stmt->execute([$filename, $po_id]);
            echo json_encode(['success' => true, 'filename' => $filename]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File move failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload error or no file selected']);
    }
}
