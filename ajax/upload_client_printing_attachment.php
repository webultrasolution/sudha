<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = $_POST['po_number'];
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/pos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $filename = 'cprint_' . time() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $stmt = $pdo->prepare("SELECT attachments FROM client_printing_rates WHERE po_number = ? LIMIT 1");
            $stmt->execute([$po_number]);
            $existing = $stmt->fetchColumn();
            
            $newAttachments = $existing ? $existing . '||' . $filename : $filename;
            
            $updateStmt = $pdo->prepare("UPDATE client_printing_rates SET attachments = ? WHERE po_number = ?");
            $updateStmt->execute([$newAttachments, $po_number]);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Move failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload error or no file']);
    }
}
