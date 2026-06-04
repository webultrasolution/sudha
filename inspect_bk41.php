<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once __DIR__ . '/config/db.php';

try {
    // 1. Fetch latest invoices
    $invoices = $pdo->query("SELECT * FROM invoices ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Latest Invoices</h3><pre>";
    print_r($invoices);
    echo "</pre>";

    // 2. Fetch booking details for BK-0041 or the latest booking
    $bookings = $pdo->query("SELECT * FROM bookings ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Latest Bookings</h3><pre>";
    print_r($bookings);
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Self-destruct
@unlink(__FILE__);
?>
