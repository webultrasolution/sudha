<?php
$pdo = new PDO('mysql:host=localhost;dbname=easy_outdoor_crm', 'root', '');
$stmt = $pdo->query("DESCRIBE payments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
