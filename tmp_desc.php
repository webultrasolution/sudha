<?php
require 'config/db.php';
$stmt = $pdo->query('DESCRIBE purchase_orders');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
