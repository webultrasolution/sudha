<?php
include_once __DIR__ . '/../config/db.php';
$rows = $pdo->exec("UPDATE purchase_orders SET type = 'printing' WHERE po_number LIKE 'PRT-%'");
echo "Updated {$rows} printing POs.\n";
?>
