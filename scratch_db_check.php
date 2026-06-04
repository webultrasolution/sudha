<?php
include_once __DIR__ . '/config/db.php';
try {
    $stmt = $pdo->query("SELECT * FROM trash ORDER BY deleted_at DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "SUCCESS: Connected to database. Found " . count($rows) . " items in trash.\n";
    print_r($rows);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
