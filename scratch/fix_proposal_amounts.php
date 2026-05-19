<?php
include_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Recalculate amount for all items using COALESCE(days, 30)
    $pdo->exec("UPDATE proposal_items SET amount = sale_rate * COALESCE(days, 30) / 30");
    echo "Successfully updated and calculated all proposal_items amounts.\n";

    // 2. Fetch distinct proposal_ids
    $stmt = $pdo->query("SELECT DISTINCT proposal_id FROM proposal_items");
    $proposals = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($proposals as $propId) {
        // Recalculate proposal totals
        $newTotal = $pdo->query("SELECT SUM(amount) FROM proposal_items WHERE proposal_id = $propId")->fetchColumn();
        $tax = $newTotal * 0.18;
        
        $p = $pdo->query("SELECT printing_cost, mounting_cost, discounting_pct FROM proposals WHERE id = $propId")->fetch();
        if ($p) {
            $base = $newTotal - ($newTotal * ($p['discounting_pct'] / 100));
            $newGrand = $base + $tax + $p['printing_cost'] + $p['mounting_cost'];
            
            $pdo->prepare("UPDATE proposals SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?")
                ->execute([$newTotal, $tax, $newGrand, $propId]);
            echo "Successfully updated totals for Proposal ID: $propId\n";
        }
    }

    $pdo->commit();
    echo "\nAll proposal data has been successfully repaired and synchronized!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
