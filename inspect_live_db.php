<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once __DIR__ . '/config/db.php';

try {
    // Describe the approval_requests table
    $stmtDesc = $pdo->query("DESCRIBE approval_requests");
    $fields = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Table Structure: approval_requests</h3><pre>";
    print_r($fields);
    echo "</pre>";
    
    // Check some rows
    $rows = $pdo->query("SELECT * FROM approval_requests LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Sample Rows</h3><pre>";
    print_r($rows);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Self-destruct
@unlink(__FILE__);
?>
