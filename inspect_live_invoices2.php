<?php
include_once __DIR__ . '/config/db.php';
try {
    $rows = $pdo->query("SELECT id, invoice_number, sub_total, cgst, sgst, igst, total_amount FROM invoices ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "ID: " . $r['id'] . " | No: " . $r['invoice_number'] . " | Sub: " . $r['sub_total'] . " | CGST: " . ($r['cgst'] ?? 'NULL') . " | SGST: " . ($r['sgst'] ?? 'NULL') . " | IGST: " . ($r['igst'] ?? 'NULL') . " | Tot: " . $r['total_amount'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
@unlink(__FILE__);
?>
