<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_signature'");
$stmt->execute(['signature.png']);
echo "Signature updated to signature.png\n";
