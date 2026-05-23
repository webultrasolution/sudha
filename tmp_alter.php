<?php
require 'config/db.php';
try {
    $pdo->exec('ALTER TABLE purchase_orders ADD COLUMN entity_id INT DEFAULT NULL');
    $pdo->exec('ALTER TABLE purchase_orders ADD CONSTRAINT fk_po_entity FOREIGN KEY (entity_id) REFERENCES entities(id)');
    echo "Database altered successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
