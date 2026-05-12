<?php
include_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("ALTER TABLE proposals ADD COLUMN billing_gstin VARCHAR(15) AFTER client_id");
    echo "Column billing_gstin added to proposals table successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
