<?php
include_once __DIR__ . '/../config/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/migrate_bookings_table.sql');
    $pdo->exec($sql);
    echo "Migration successful.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
