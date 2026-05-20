<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $field = $_POST['field'];
    $value = floatval($_POST['value']);

    // Allowed fields for security
    $allowed = ['discounting_pct', 'pricing_pct', 'printing_cost', 'mounting_cost'];
    if (in_array($field, $allowed)) {
        $stmt = $pdo->prepare("UPDATE proposals SET $field = ? WHERE id = ?");
        $stmt->execute([$value, $id]);
        
        // Recalculate grand total
        $p = $pdo->query("SELECT total_amount, tax_amount, printing_cost, mounting_cost, discounting_pct FROM proposals WHERE id = $id")->fetch();
        $base = $p['total_amount'] - ($p['total_amount'] * ($p['discounting_pct'] / 100));
        $newGrand = $base + $p['tax_amount'] + $p['printing_cost'] + $p['mounting_cost'];
        
        $pdo->prepare("UPDATE proposals SET grand_total = ? WHERE id = ?")->execute([$newGrand, $id]);
        
        // Revert proposal to pending_approval if non-admin edited an approved proposal
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $propRef = $pdo->query("SELECT proposal_number FROM proposals WHERE id = $id")->fetchColumn();
            revertToPendingOnEdit($pdo, 'proposals', $id, 'proposal', $propRef, $_SESSION['user_id'] ?? 0);
        }
        
        echo json_encode(['success' => true]);
    }
}
