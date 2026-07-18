<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/trash_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$ids_input = isset($_POST['ids']) ? $_POST['ids'] : null;
$id_input = isset($_POST['id']) ? intval($_POST['id']) : 0;

$ids = [];
if (is_array($ids_input)) {
    $ids = array_map('intval', $ids_input);
} elseif (!empty($ids_input)) {
    $ids = array_map('intval', explode(',', $ids_input));
} elseif ($id_input > 0) {
    $ids = [$id_input];
}

$ids = array_filter($ids);

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Missing ID(s)']);
    exit;
}

if (!canDelete('financials')) {
    $inClause = str_repeat('?,', count($ids) - 1) . '?';
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE id IN ($inClause) AND approval_status = 'approved'");
    $stmtCheck->execute($ids);
    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: Approved payments can only be deleted by authorized staff.']);
        exit;
    }
}

try {
    // Fetch original payments to update document status later
    $inClause = str_repeat('?,', count($ids) - 1) . '?';
    $stmtOrig = $pdo->prepare("SELECT invoice_id, proposal_id FROM payments WHERE id IN ($inClause)");
    $stmtOrig->execute($ids);
    $origs = $stmtOrig->fetchAll(PDO::FETCH_ASSOC);

    $success = move_multiple_rows_to_trash($pdo, 'payments', 'id', $ids, $_SESSION['user_id'] ?? null, 'Payments deleted via UI');
    if (!$success) {
        throw new Exception('Failed to move payment(s) to trash');
    }

    foreach ($origs as $orig) {
        updateDocumentPaymentStatus($pdo, $orig['invoice_id'], $orig['proposal_id']);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
