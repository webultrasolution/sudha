<?php
include_once __DIR__ . '/config/db.php';
try {
    $r = $pdo->query("SELECT * FROM invoices WHERE id = 25")->fetch();
    if ($r) {
        echo "ID 25 GST Details: CGST=" . ($r['cgst'] ?? 'NULL') . " | SGST=" . ($r['sgst'] ?? 'NULL') . " | IGST=" . ($r['igst'] ?? 'NULL') . "\n";
    } else {
        echo "Invoice 25 not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
@unlink(__FILE__);
?>
