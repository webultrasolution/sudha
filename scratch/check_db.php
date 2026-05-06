<?php
include 'config/db.php';
echo "TABLES:\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

foreach ($tables as $table) {
    echo "\nCOLUMNS FOR $table:\n";
    print_r($pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN));
}
?>
