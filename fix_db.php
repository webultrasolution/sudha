<?php
include_once __DIR__ . '/config/db.php';

try {
    // Force fix the payments table structure
    $pdo->exec("ALTER TABLE payments MODIFY COLUMN type ENUM('credit', 'debit') NOT NULL");
    $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_mode ENUM('Cash', 'Cheque', 'NEFT', 'RTGS', 'UPI', 'Other') DEFAULT 'NEFT'");
    
    // Add columns if they don't exist (extra safety)
    $columns = $pdo->query("DESCRIBE payments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('transaction_id', $columns)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN transaction_id VARCHAR(100)");
    }
    if (!in_array('notes', $columns)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN notes TEXT");
    }
    
    echo "DB structure verified and fixed.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
