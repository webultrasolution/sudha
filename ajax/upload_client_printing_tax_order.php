<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = $_POST['po_number'];
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/pos/tax_orders/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $stmt = $pdo->prepare("SELECT client_tax_order FROM client_printing_rates WHERE po_number = ? LIMIT 1");
        $stmt->execute([$po_number]);
        $existing = $stmt->fetchColumn();
        if ($existing && file_exists($uploadDir . $existing)) {
            unlink($uploadDir . $existing);
        }
        
        $filename = 'cto_cprint_' . time() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $updateStmt = $pdo->prepare("UPDATE client_printing_rates SET client_tax_order = ? WHERE po_number = ?");
            $updateStmt->execute([$filename, $po_number]);
            echo json_encode(['success' => true, 'filename' => $filename]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File move failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload error or no file selected']);
    }
}
