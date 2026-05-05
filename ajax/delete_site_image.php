<?php
include_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id) {
    // Get filename to delete from disk
    $stmt = $pdo->prepare("SELECT filename FROM site_images WHERE id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetch();
    
    if($img) {
        $path = __DIR__ . '/../uploads/sites/' . $img['filename'];
        if(file_exists($path)) @unlink($path);
        
        $pdo->prepare("DELETE FROM site_images WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    }
} else {
    echo json_encode(['success' => false]);
}
