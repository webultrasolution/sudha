<?php
include 'config/db.php';
$stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
$stmt->execute(['letterhead.jpg', 'company_letterhead']);
echo "Setting updated to letterhead.jpg";
?>
