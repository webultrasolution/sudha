<?php
$pdo = new PDO('mysql:host=localhost;dbname=easy_outdoor_crm', 'root', '');
$stmt = $pdo->query("SHOW TABLES LIKE '%payment%'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
