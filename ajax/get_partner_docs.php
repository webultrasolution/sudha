<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

$partner_id = intval($_GET['id'] ?? 0);
if (!$partner_id) {
    echo json_encode(['invoices' => [], 'pos' => []]);
    exit;
}

// Fetch Client Invoices
$invoices = $pdo->prepare("SELECT id, invoice_number, total_amount FROM invoices WHERE booking_id IN (SELECT id FROM bookings WHERE proposal_id IN (SELECT id FROM proposals WHERE client_id = ?)) ORDER BY id DESC");
$invoices->execute([$partner_id]);

// Fetch Vendor POs (Proposals/POs assigned to this vendor)
$pos = $pdo->prepare("SELECT p.id, p.proposal_number as po_number, p.grand_total FROM proposals p WHERE p.id IN (SELECT proposal_id FROM proposal_items WHERE site_id IN (SELECT id FROM sites WHERE vendor_id = ?)) ORDER BY p.id DESC");
$pos->execute([$partner_id]);

echo json_encode([
    'invoices' => $invoices->fetchAll(PDO::FETCH_ASSOC),
    'pos' => $pos->fetchAll(PDO::FETCH_ASSOC)
]);
