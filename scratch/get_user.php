<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query('SELECT username FROM users LIMIT 1');
echo "Username: " . $stmt->fetchColumn();
?>
