<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("DESCRIBE sites");
header('Content-Type: text/plain');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
