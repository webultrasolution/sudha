<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("DESCRIBE purchase_orders");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "PURCHASE_ORDERS:\n";
foreach($cols as $c) echo $c['Field'] . " (" . $c['Type'] . ")\n";

$stmt = $pdo->query("DESCRIBE po_items");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nPO_ITEMS:\n";
foreach($cols as $c) echo $c['Field'] . " (" . $c['Type'] . ")\n";
?>
