<?php
include_once __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->query("SELECT * FROM proposal_items");
    $items = $stmt->fetchAll();
    echo "Proposal Items:\n\n";
    foreach ($items as $item) {
        echo "ID: " . $item['id'] . " | Proposal ID: " . $item['proposal_id'] . " | Site ID: " . $item['site_id'] . " | Sale Rate: " . $item['sale_rate'] . " | Days: " . ($item['days'] ?? 'NULL') . " | Amount: " . $item['amount'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
