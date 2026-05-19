<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $field = $_POST['field'];
    $value = $_POST['value'];

    $success = false;

    if ($field === 'sale_rate') {
        $rateVal = floatval($value);
        // Update the item
        $stmt = $pdo->prepare("UPDATE proposal_items SET sale_rate = ?, amount = sale_rate * COALESCE(days, 30) / 30 WHERE id = ?");
        $stmt->execute([$rateVal, $id]);
        $success = true;
    } elseif ($field === 'start_date') {
        $dateVal = clean($value);
        if (!empty($dateVal)) {
            // Fetch item days and calculate end_date
            $stmt = $pdo->prepare("SELECT days FROM proposal_items WHERE id = ?");
            $stmt->execute([$id]);
            $days = intval($stmt->fetchColumn() ?: 30);
            
            $endDate = date('Y-m-d', strtotime($dateVal . " + " . ($days - 1) . " days"));
            
            $stmt = $pdo->prepare("UPDATE proposal_items SET start_date = ?, end_date = ? WHERE id = ?");
            $stmt->execute([$dateVal, $endDate, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE proposal_items SET start_date = NULL, end_date = NULL WHERE id = ?");
            $stmt->execute([$id]);
        }
        $success = true;
    }

    if ($success) {
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
    } else {
        echo json_encode(['success' => false, 'message' => 'Field not supported']);
    }
}
?>
