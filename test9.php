<?php
require 'd:/xampp/htdocs/easy-outdoor-crm/config/db.php';
$stmt = $pdo->query('DESCRIBE client_printing_rates');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
