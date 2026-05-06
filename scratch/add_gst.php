<?php
include_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("ALTER TABLE partners ADD COLUMN additional_gst TEXT AFTER gstin");
    echo "Success: additional_gst column added.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
