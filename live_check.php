<?php
include_once __DIR__ . '/config/db.php';
try {
    $stmt = $pdo->query("SELECT * FROM trash ORDER BY deleted_at DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "JSON_START\n";
    echo json_encode($rows);
    echo "\nJSON_END";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
