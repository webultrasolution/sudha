<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $field = $_POST['field'];
    $value = floatval($_POST['value']);

    if ($field === 'sale_rate') {
        // Update the item
        $stmt = $pdo->prepare("UPDATE proposal_items SET sale_rate = ?, amount = sale_rate * days / 30 WHERE id = ?");
        $stmt->execute([$value, $id]);
        
        // Fetch proposal_id
        $propId = $pdo->query("SELECT proposal_id FROM proposal_items WHERE id = $id")->fetchColumn();
        
        // Recalculate proposal total
        $newTotal = $pdo->query("SELECT SUM(amount) FROM proposal_items WHERE proposal_id = $propId")->fetchColumn();
        $tax = $newTotal * 0.18;
        
        $p = $pdo->query("SELECT printing_cost, mounting_cost, discounting_pct FROM proposals WHERE id = $propId")->fetch();
        $base = $newTotal - ($newTotal * ($p['discounting_pct'] / 100));
        $newGrand = $base + $tax + $p['printing_cost'] + $p['mounting_cost'];
        
        $pdo->prepare("UPDATE proposals SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?")
            ->execute([$newTotal, $tax, $newGrand, $propId]);
            
        echo json_encode(['success' => true]);
    }
}
