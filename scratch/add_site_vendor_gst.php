<?php
include_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE sites ADD COLUMN vendor_gst VARCHAR(20) DEFAULT NULL AFTER vendor_id");
    echo "vendor_gst column added to sites table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
