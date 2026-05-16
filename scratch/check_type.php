<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM payments LIKE 'type'");
print_r($stmt->fetchAll());
?>
