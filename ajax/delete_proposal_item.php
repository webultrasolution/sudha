<?php
include_once __DIR__ . '/../config/db.php';
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
    $stmtSums = $pdo->prepare("SELECT SUM(amount) as subtotal FROM proposal_items WHERE proposal_id = ?");
    $stmtSums->execute([$proposalId]);
    $sums = $stmtSums->fetch();
    $newSubtotal = $sums['subtotal'] ?: 0;
    
    // Get existing proposal to keep discount pct
    $stmtP = $pdo->prepare("SELECT discounting_pct, printing_cost, mounting_cost FROM proposals WHERE id = ?");
    $stmtP->execute([$proposalId]);
    $p = $stmtP->fetch();
    
    $discountVal = $newSubtotal * ($p['discounting_pct'] / 100);
    $taxable = $newSubtotal - $discountVal + $p['printing_cost'] + $p['mounting_cost'];
    $tax = $taxable * 0.18;
    $grand = $taxable + $tax;

    $stmtUpdate = $pdo->prepare("UPDATE proposals SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?");
    $stmtUpdate->execute([$newSubtotal, $tax, $grand, $proposalId]);

    // Revert proposal to pending_approval if non-admin deleted an item from an approved proposal
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    if (!$isAdmin) {
        $propRef = $pdo->query("SELECT proposal_number FROM proposals WHERE id = $proposalId")->fetchColumn();
        revertToPendingOnEdit($pdo, 'proposals', $proposalId, 'proposal', $propRef, $_SESSION['user_id'] ?? 0);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
