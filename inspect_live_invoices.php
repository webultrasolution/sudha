<?php
include_once __DIR__ . '/config/db.php';
try {
    $rows = $pdo->query("SELECT id, invoice_number, sub_total, cgst, sgst, igst, total_amount FROM invoices ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Live Invoice Check</h3><pre>";
    print_r($rows);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
@unlink(__FILE__);
?>
