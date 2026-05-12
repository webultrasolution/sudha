<?php
include_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN campaign_name VARCHAR(255) NULL AFTER campaign_id");
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN remarks TEXT NULL AFTER status");
    echo "Table purchase_orders altered successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
