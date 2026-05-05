<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_id = intval($_POST['po_id']);
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/pos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $filename = time() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $stmt = $pdo->prepare("INSERT INTO po_attachments (po_id, filename) VALUES (?, ?)");
            $stmt->execute([$po_id, $filename]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Move failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload error or no file']);
    }
}
