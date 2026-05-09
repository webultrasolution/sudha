<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE sites");
header('Content-Type: text/plain');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
