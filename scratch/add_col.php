<?php
include_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("ALTER TABLE partners ADD COLUMN business_type VARCHAR(50) AFTER type");
    echo "Success: business_type column added.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
