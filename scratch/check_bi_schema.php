<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("DESCRIBE booking_items");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "BOOKING_ITEMS:\n";
foreach($cols as $c) echo $c['Field'] . " (" . $c['Type'] . ")\n";
?>
