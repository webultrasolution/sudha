<?php
include_once __DIR__ . '/config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM payments");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $cols);
?>
