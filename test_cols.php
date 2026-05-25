<?php
require 'config/db.php';
$pdo->exec("ALTER TABLE vendor_printing_rates ADD COLUMN vendor_invoice_no VARCHAR(255) NULL");
$pdo->exec("ALTER TABLE vendor_printing_rates ADD COLUMN vendor_invoice_date DATE NULL");
echo "Added";
