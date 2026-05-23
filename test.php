<?php
include_once __DIR__ . '/config/db.php';
$res = $pdo->query('SELECT id, type, approval_status FROM invoices WHERE booking_id = 15')->fetchAll();
print_r($res);
?>
