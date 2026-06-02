<?php
require 'd:/xampp/htdocs/easy-outdoor-crm/config/db.php';
$stmt = $pdo->query('DESCRIBE proposal_items');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
