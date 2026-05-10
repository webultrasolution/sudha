<?php
include 'config/db.php';
try {
    $pdo->exec("ALTER TABLE bookings ADD COLUMN customer_po_no VARCHAR(50) DEFAULT NULL, ADD COLUMN customer_po_date DATE DEFAULT NULL, ADD COLUMN customer_po_file VARCHAR(255) DEFAULT NULL");
    echo "Columns added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
