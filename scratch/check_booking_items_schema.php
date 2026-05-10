<?php
include_once __DIR__ . '/../config/db.php';

$tables = ['booking_items'];
foreach ($tables as $table) {
    echo "Table: $table\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "-------------------\n";
}
