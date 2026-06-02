<?php
require_once __DIR__ . '/../../config/db.php';
$stmt = $pdo->query("DESCRIBE bookings");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("DESCRIBE invoices");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
