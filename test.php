<?php
include_once __DIR__ . '/config/db.php';
$cols = $pdo->query('SHOW COLUMNS FROM bookings')->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
$colsP = $pdo->query('SHOW COLUMNS FROM proposals')->fetchAll(PDO::FETCH_COLUMN);
print_r($colsP);
?>
