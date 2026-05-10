<?php
include 'config/db.php';
try {
    $pdo->exec("ALTER TABLE bookings 
        ADD COLUMN IF NOT EXISTS confirmation_type ENUM('po', 'email') DEFAULT 'po' AFTER status,
        ADD COLUMN IF NOT EXISTS email_date DATE DEFAULT NULL AFTER customer_po_date");
    echo "Columns added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
