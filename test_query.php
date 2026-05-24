<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM bookings");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
