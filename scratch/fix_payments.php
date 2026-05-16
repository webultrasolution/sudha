<?php
include_once __DIR__ . '/../config/db.php';
$pdo->query("UPDATE payments SET type='receivable' WHERE type='' OR type IS NULL");
echo "Updated";
?>
