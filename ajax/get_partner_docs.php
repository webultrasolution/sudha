<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

$partner_id = intval($_GET['id'] ?? 0);
if (!$partner_id) {
    echo json_encode(['invoices' => [], 'pos' => []]);
    exit;
}

// Fetch Client Invoices
$invoices = $pdo->prepare("
    SELECT i.id, i.invoice_number, i.total_amount 
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    WHERE b.client_id = ? 
    ORDER BY i.id DESC
");
$invoices->execute([$partner_id]);

// Fetch Vendor POs
$pos = $pdo->prepare("
    SELECT id, po_number, total_amount as grand_total 
    FROM purchase_orders 
    WHERE vendor_id = ? 
    ORDER BY id DESC
");
$pos->execute([$partner_id]);

echo json_encode([
    'invoices' => $invoices->fetchAll(PDO::FETCH_ASSOC),
    'pos' => $pos->fetchAll(PDO::FETCH_ASSOC)
]);
