<?php
include_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id) {
    $stmt = $pdo->prepare("SELECT id, filename FROM site_images WHERE site_id = ? ORDER BY id DESC");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll());
} else {
    echo json_encode([]);
}
