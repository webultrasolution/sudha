<?php
include_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("ALTER TABLE proposals ADD COLUMN media_type VARCHAR(100) NULL AFTER campaign_name");
    $pdo->exec("ALTER TABLE proposals ADD COLUMN inventory_type VARCHAR(100) NULL AFTER media_type");
    echo "Columns added successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
