<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
if (!(canEdit('proposals'))) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit proposals.']);
    exit;
}
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/trash_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();

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
    // Get proposal_id to update totals later if needed
    $stmt = $pdo->prepare("SELECT proposal_id FROM proposal_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    $proposalId = $item['proposal_id'];

    // Move proposal item to trash instead of permanent delete
    $trashId = move_row_to_trash($pdo, 'proposal_items', 'id', $id, $_SESSION['user_id'] ?? null, 'Deleted via UI');
    if (!$trashId) {
        echo json_encode(['success' => false, 'message' => 'Failed to move item to trash']);
        exit;
    }

    // Recalculate proposal totals
    recalculateProposalTotals($pdo, $proposalId);

    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    if (!$isAdmin) {
        $stmt = $pdo->prepare("SELECT proposal_number FROM proposals WHERE id = ?");
        $stmt->execute([$proposalId]);
        $propRef = $stmt->fetchColumn();
        revertToPendingOnEdit($pdo, 'proposals', $proposalId, 'proposal', $propRef, $_SESSION['user_id'] ?? 0);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
