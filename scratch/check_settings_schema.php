<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("DESCRIBE settings");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "SETTINGS TABLE:\n";
foreach($cols as $c) echo $c['Field'] . " (" . $c['Type'] . ")\n";
?>
