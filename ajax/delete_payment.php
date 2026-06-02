<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/trash_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!canDelete('financials')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to delete payments.']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

try {
    $trashId = move_row_to_trash($pdo, 'payments', 'id', $id, $_SESSION['user_id'] ?? null, 'Payment deleted via UI');
    if (!$trashId) {
        throw new Exception('Failed to move payment to trash');
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
