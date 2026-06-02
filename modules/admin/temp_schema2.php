<?php
require_once __DIR__ . '/../../config/db.php';
$pdo->exec("ALTER TABLE invoices ADD COLUMN entity_id INT(11) NULL AFTER booking_id");
$pdo->exec("ALTER TABLE invoices ADD CONSTRAINT fk_invoice_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE SET NULL");
echo "Done invoices alteration";
