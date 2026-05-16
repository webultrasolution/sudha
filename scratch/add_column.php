<?php
include_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("ALTER TABLE vendor_printing_rates ADD COLUMN po_number VARCHAR(50) NULL AFTER vendor_id");
    echo "Column added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
