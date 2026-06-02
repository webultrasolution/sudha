<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../config/db.php';
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
    $stmt = $pdo->prepare("DELETE FROM trash WHERE id = ?");
    $stmt->execute([$trashId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
