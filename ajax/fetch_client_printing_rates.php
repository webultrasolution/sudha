<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$vendor_id = isset($_GET['filter_vendor_id']) ? intval($_GET['filter_vendor_id']) : 0;

if ($client_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid client ID', 'rates' => []]);
    exit;
}

$rateParams = [$client_id];
$rateWhere = "WHERE r.client_id = ?";
if ($vendor_id > 0) {
    $rateWhere .= " AND s.vendor_id = ?";
    $rateParams[] = $vendor_id;
}

try {
    $stmtR = $pdo->prepare("
        SELECT r.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.type as media_type_site, s.hsn_code, s.vendor_gst, v.name as vendor_name
        FROM client_printing_rates r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN partners v ON s.vendor_id = v.id
        $rateWhere
        ORDER BY s.site_code ASC
    ");
    $stmtR->execute($rateParams);
    $rates = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rates' => $rates]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'rates' => []]);
}
