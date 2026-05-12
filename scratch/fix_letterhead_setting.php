<?php
include_once __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute(['letterhead.jpg', 'company_letterhead']);
    echo "Settings updated to letterhead.jpg successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
