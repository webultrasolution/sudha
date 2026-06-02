<?php
require 'd:/xampp/htdocs/easy-outdoor-crm/config/db.php';
$stmt = $pdo->query('DESCRIBE partners');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
