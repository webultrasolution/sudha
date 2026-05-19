<?php
include_once __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->query("DESCRIBE proposal_items");
    $columns = $stmt->fetchAll();
    echo "Columns in 'proposal_items' table:\n\n";
    foreach ($columns as $col) {
        echo "- Field: " . $col['Field'] . " | Type: " . $col['Type'] . " | Null: " . $col['Null'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
