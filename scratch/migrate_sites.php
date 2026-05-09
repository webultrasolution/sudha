<?php
include_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE sites ADD COLUMN IF NOT EXISTS state VARCHAR(100) NULL AFTER city");
    $pdo->exec("ALTER TABLE sites ADD COLUMN IF NOT EXISTS genre VARCHAR(100) NULL AFTER type");
    echo "Sites table updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
