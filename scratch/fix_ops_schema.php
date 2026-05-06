<?php
include 'config/db.php';
try {
    $pdo->exec("ALTER TABLE operations ADD COLUMN assigned_mounter_id INT NULL AFTER site_id");
    $pdo->exec("ALTER TABLE operations ADD COLUMN mounting_date DATE NULL AFTER assigned_mounter_id");
    echo "Columns added to operations table.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
