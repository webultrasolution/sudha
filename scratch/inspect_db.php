<?php
include_once __DIR__ . '/../config/db.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n";
print_r($tables);

foreach ($tables as $table) {
    echo "\nStructure for $table:\n";
    $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
}
?>
