<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = floatval($_POST['value'] ?? 0);

    // Allowed fields for security
    $allowed = ['discounting_pct', 'pricing_pct', 'printing_cost', 'mounting_cost'];
    if (in_array($field, $allowed)) {
        $stmt = $pdo->prepare("UPDATE proposals SET $field = ? WHERE id = ?");
        $stmt->execute([$value, $id]);

        recalculateProposalTotals($pdo, $id);

        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $stmt = $pdo->prepare("SELECT proposal_number FROM proposals WHERE id = ?");
            $stmt->execute([$id]);
            $propRef = $stmt->fetchColumn();
            revertToPendingOnEdit($pdo, 'proposals', $id, 'proposal', $propRef, $_SESSION['user_id'] ?? 0);
        }
        
        echo json_encode(['success' => true]);
    }
}
