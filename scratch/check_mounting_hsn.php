<?php
require __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM sites LIKE 'mounting_hsn'");
    $res = $stmt->fetch();
    if ($res) {
        echo "mounting_hsn exists: " . print_r($res, true) . "\n";
    } else {
        echo "mounting_hsn DOES NOT exist.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
