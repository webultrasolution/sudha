<?php
include_once __DIR__ . '/config/db.php';
$cols = $pdo->query("DESCRIBE payments")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>"; print_r($cols); echo "</pre>";
