<?php
include_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("ALTER TABLE proposal_items ADD COLUMN selected_image VARCHAR(255) NULL AFTER amount");
    echo "Table altered successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
