<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo json_encode(['sites' => []]); exit; }

// Fetch sites linked to the campaign via its booking's line items
$stmt = $pdo->prepare("
    SELECT s.id, s.site_code, s.name, COALESCE(NULLIF(bi.purchase_rate, 0), s.purchase_rate, 0) AS purchase_rate,
           bi.start_date, bi.end_date
    FROM campaigns c
    JOIN booking_items bi ON bi.booking_id = c.booking_id
    JOIN sites s ON bi.site_id = s.id
    WHERE c.id = ?
    ORDER BY bi.sort_order, bi.id
");
$stmt->execute([$id]);
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['sites' => $sites]);
