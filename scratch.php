<?php
require 'config/db.php';
$tables = ['purchase_orders', 'bookings', 'invoices', 'campaigns'];
foreach ($tables as $t) {
    echo "Table $t:\n";
    try {
        $res = $pdo->query("DESCRIBE $t");
        foreach ($res as $row) {
            echo $row['Field'] . ' ';
        }
    } catch (Exception $e) {
        echo 'not found';
    }
    echo "\n\n";
}
