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
    SELECT i.id, CONVERT(i.invoice_number USING utf8mb4) COLLATE utf8mb4_unicode_ci as invoice_number, i.total_amount 
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    WHERE b.client_id = ? 
    
    UNION ALL
    
    SELECT MIN(r.id) as id, COALESCE(CONVERT(r.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('RATE-', MIN(r.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as invoice_number, 
           SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as total_amount
    FROM client_printing_rates r
    LEFT JOIN sites s ON r.site_id = s.id
    WHERE r.client_id = ?
    GROUP BY COALESCE(r.po_number, r.id)
    
    ORDER BY id DESC
");
$invoices->execute([$partner_id, $partner_id]);

// Fetch Vendor POs
$pos = $pdo->prepare("
    SELECT id, CONVERT(po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number, COALESCE(total_amount, 0) as grand_total 
    FROM purchase_orders 
    WHERE vendor_id = ? 
    
    UNION ALL
    
    SELECT MIN(r.id) as id, COALESCE(CONVERT(r.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('RATE-', MIN(r.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as po_number, 
           SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as grand_total
    FROM vendor_printing_rates r
    LEFT JOIN sites s ON r.site_id = s.id
    WHERE r.vendor_id = ?
    GROUP BY COALESCE(r.po_number, r.id)
    
    ORDER BY id DESC
");
$pos->execute([$partner_id, $partner_id]);

echo json_encode([
    'invoices' => $invoices->fetchAll(PDO::FETCH_ASSOC),
    'pos' => $pos->fetchAll(PDO::FETCH_ASSOC)
]);
