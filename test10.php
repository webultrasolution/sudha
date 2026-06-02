<?php
require 'd:/xampp/htdocs/easy-outdoor-crm/config/db.php';
$pdo->exec("ALTER TABLE client_printing_rates ADD COLUMN custom_invoice_number VARCHAR(100) DEFAULT NULL, ADD COLUMN custom_invoice_date DATE DEFAULT NULL");
echo "Done";
