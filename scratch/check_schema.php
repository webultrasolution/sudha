<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE invoices");
$res = $stmt->fetch();
print_r($res);
