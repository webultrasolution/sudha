<?php
include 'd:/xampp/htdocs/easy-outdoor-crm/config/db.php';
$stmt = $pdo->query('SHOW COLUMNS FROM client_printing_rates');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
