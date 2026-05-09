<?php
include_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE proposals ADD COLUMN IF NOT EXISTS delivery_date DATE NULL AFTER end_date");
    $pdo->exec("ALTER TABLE proposals ADD COLUMN IF NOT EXISTS total_days INT NULL AFTER delivery_date");
    $pdo->exec("ALTER TABLE proposals ADD COLUMN IF NOT EXISTS remark TEXT NULL AFTER total_days");
    $pdo->exec("ALTER TABLE proposals ADD COLUMN IF NOT EXISTS contact_person VARCHAR(255) NULL AFTER client_id");
    echo "Database updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
