<?php
include_once __DIR__ . '/config/db.php';

echo "Running Database Alterations...<br>";

$queries = [
    "ALTER TABLE client_printing_rates ADD COLUMN custom_invoice_number VARCHAR(100) NULL;",
    "ALTER TABLE client_printing_rates ADD COLUMN custom_invoice_date DATE NULL;",
    "ALTER TABLE vendor_printing_rates ADD COLUMN vendor_invoice_no VARCHAR(100) NULL;",
    "ALTER TABLE vendor_printing_rates ADD COLUMN vendor_invoice_date DATE NULL;",
    "ALTER TABLE sites ADD COLUMN mounting_hsn VARCHAR(50) DEFAULT NULL AFTER hsn_code;",
    "ALTER TABLE proposal_items ADD COLUMN start_date DATE DEFAULT NULL AFTER days;",
    "ALTER TABLE proposal_items ADD COLUMN end_date DATE DEFAULT NULL AFTER start_date;"
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
