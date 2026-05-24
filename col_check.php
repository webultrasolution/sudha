<?php
require 'config/db.php';
$stmt = $pdo->query('SHOW COLUMNS FROM partner_gst_records');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt = $pdo->query('SHOW COLUMNS FROM bookings');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt = $pdo->query('SHOW COLUMNS FROM invoices');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
