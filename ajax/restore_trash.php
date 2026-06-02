<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/trash_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$trashId = isset($data['trash_id']) ? intval($data['trash_id']) : 0;
if (!$trashId) {
    echo json_encode(['success' => false, 'message' => 'Missing trash_id']);
    exit;
}

try {
    $ok = restore_trash_item($pdo, $trashId);
    if ($ok) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Failed to restore']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
