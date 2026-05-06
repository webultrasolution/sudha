<?php
include 'config/db.php';
$stmt = $pdo->query("DESCRIBE sites");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $cols);
?>
