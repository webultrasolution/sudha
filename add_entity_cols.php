<?php
// Migration: Add missing columns to entities table
include_once __DIR__ . '/config/db.php';

$queries = [
    "ALTER TABLE entities ADD COLUMN IF NOT EXISTS letterhead VARCHAR(255) DEFAULT NULL AFTER logo",
    "ALTER TABLE entities ADD COLUMN IF NOT EXISTS signature VARCHAR(255) DEFAULT NULL AFTER letterhead",
    "ALTER TABLE entities ADD COLUMN IF NOT EXISTS bank_details TEXT DEFAULT NULL AFTER address",
    "ALTER TABLE entities ADD COLUMN IF NOT EXISTS terms_conditions TEXT DEFAULT NULL AFTER bank_details",
    "ALTER TABLE entities ADD COLUMN IF NOT EXISTS msme_number VARCHAR(50) DEFAULT NULL AFTER terms_conditions",
];

echo "<pre>Running entity column migrations...\n";
foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        echo "✅ OK: $q\n";
    } catch (Exception $e) {
        echo "ℹ️  Notice: " . $e->getMessage() . "\n";
    }
}
echo "\nDone! You can delete this file now.\n</pre>";
?>
