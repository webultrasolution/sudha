<?php
include_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
include_once __DIR__ . '/../includes/trash_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id) {
    try {
        $trashId = move_row_to_trash($pdo, 'site_images', 'id', $id, $_SESSION['user_id'] ?? null, 'Image removed');
        if ($trashId) echo json_encode(['success' => true, 'trash_id' => $trashId]);
        else echo json_encode(['success' => false, 'message' => 'Not found']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
}
