<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
if (!(($_SESSION['user_role'] ?? '') === 'admin')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Only admin can delete a purchase order.']);
    exit;
}
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/trash_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    $itemStmt = $pdo->prepare("SELECT id FROM po_items WHERE po_id = ?");
    $itemStmt->execute([$id]);
    while ($item = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
        move_row_to_trash($pdo, 'po_items', 'id', $item['id'], $_SESSION['user_id'] ?? null, 'PO deleted - item moved to trash');
    }
    $trashId = move_row_to_trash($pdo, 'purchase_orders', 'id', $id, $_SESSION['user_id'] ?? null, 'Purchase order deleted via UI');
    if (!$trashId) {
        throw new Exception('Failed to move purchase order to trash');
    }
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
