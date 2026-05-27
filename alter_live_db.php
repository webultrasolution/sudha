<?php
include_once __DIR__ . '/config/db.php';

echo "Running Database Alterations...<br>";

$queries = [
    "ALTER TABLE client_printing_rates ADD COLUMN custom_invoice_number VARCHAR(100) NULL;",
    "ALTER TABLE client_printing_rates ADD COLUMN custom_invoice_date DATE NULL;",
    "ALTER TABLE vendor_printing_rates ADD COLUMN vendor_invoice_no VARCHAR(100) NULL;",
    "ALTER TABLE vendor_printing_rates ADD COLUMN vendor_invoice_date DATE NULL;"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        echo "Success: $q <br>";
    } catch (Exception $e) {
        echo "Notice (Could be already exists): " . $e->getMessage() . " <br>";
    }
}
echo "Done.";
?>
