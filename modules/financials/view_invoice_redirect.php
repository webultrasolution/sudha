<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

checkAuth();

$ref = $_GET['ref'] ?? '';

if (empty($ref)) {
    die("Invalid reference number.");
}

// 1. Search in invoices
$stmt = $pdo->prepare("SELECT id, booking_id, type FROM invoices WHERE invoice_number = ?");
$stmt->execute([$ref]);
$inv = $stmt->fetch();
if ($inv) {
    if ($inv['type'] === 'printing') {
        header("Location: ../operations/print_client_printing.php?invoice_id=" . $inv['id']);
    } else {
        $redirectUrl = "../operations/" . ($inv['type'] === 'ro' ? 'generate_ro_invoice.php' : 'generate_invoice.php') . "?booking_id=" . $inv['booking_id'];
        header("Location: $redirectUrl");
    }
    exit;
}

// 2. Search in vendor printing POs (vendor_printing_rates)
$stmt = $pdo->prepare("SELECT vendor_id FROM vendor_printing_rates WHERE po_number = ? LIMIT 1");
$stmt->execute([$ref]);
$vendor_id = $stmt->fetchColumn();
if ($vendor_id) {
    header("Location: ../partners/view_vendor_printing_po.php?vendor_id=$vendor_id&po_number=" . urlencode($ref));
    exit;
}

// 3. Search in vendor purchase_orders table
$stmt = $pdo->prepare("SELECT id, type FROM purchase_orders WHERE po_number = ?");
$stmt->execute([$ref]);
$po = $stmt->fetch();
if ($po) {
    if ($po['type'] === 'printing') {
        header("Location: ../operations/generate_printing_po.php?po_id=" . $po['id']);
    } else {
        header("Location: ../financials/po_view.php?id=" . $po['id']);
    }
    exit;
}

// 4. Search in client mounting POs (client_mounting_rates)
$stmt = $pdo->prepare("SELECT client_id FROM client_mounting_rates WHERE po_number = ? LIMIT 1");
$stmt->execute([$ref]);
$client_id = $stmt->fetchColumn();
if ($client_id) {
    header("Location: ../operations/view_mounting_invoice.php?client_id=$client_id&po_number=" . urlencode($ref));
    exit;
}

// 5. Search in client printing POs (client_printing_rates)
$stmt = $pdo->prepare("SELECT client_id FROM client_printing_rates WHERE po_number = ? LIMIT 1");
$stmt->execute([$ref]);
$client_id = $stmt->fetchColumn();
if ($client_id) {
    header("Location: ../partners/view_client_printing_po.php?client_id=$client_id&po_number=" . urlencode($ref));
    exit;
}

echo "<div style='font-family:sans-serif; padding:3rem; text-align:center; color:#64748b;'>";
echo "<h3>Document Not Found</h3>";
echo "<p>Could not locate invoice or purchase order with reference: <strong>" . htmlspecialchars($ref) . "</strong></p>";
echo "</div>";
