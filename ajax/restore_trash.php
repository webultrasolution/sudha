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
$trashIdsInput = isset($data['trash_ids']) ? $data['trash_ids'] : null;

$trashIds = [];
if (is_array($trashIdsInput)) {
    $trashIds = array_map('intval', $trashIdsInput);
} elseif (!empty($trashIdsInput)) {
    if (is_string($trashIdsInput)) {
        $trashIds = array_map('intval', explode(',', $trashIdsInput));
    } else {
        $trashIds = [intval($trashIdsInput)];
    }
} elseif ($trashId > 0) {
    $trashIds = [$trashId];
}

$trashIds = array_filter($trashIds);

if (empty($trashIds)) {
    echo json_encode(['success' => false, 'message' => 'Missing trash_id(s)']);
    exit;
}

try {
    $success = true;
    foreach ($trashIds as $id) {
        $ok = restore_trash_item($pdo, $id);
        if (!$ok) {
            $success = false;
        }
    }
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to restore one or more items']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
