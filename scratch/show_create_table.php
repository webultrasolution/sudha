<?php
include 'd:/xampp/htdocs/easy-outdoor-crm/config/db.php';
$stmt = $pdo->query('SHOW CREATE TABLE booking_items');
print_r($stmt->fetch());
?>
