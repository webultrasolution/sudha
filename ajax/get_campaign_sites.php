<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo json_encode(['sites' => []]); exit; }

// Fetch sites linked to the campaign via operations/bookings
// Assuming campaigns are linked to operations
$stmt = $pdo->prepare("
    SELECT s.id, s.site_code, s.name, s.purchase_rate, o.start_date, o.end_date 
    FROM operations o 
    JOIN sites s ON o.site_id = s.id 
    WHERE o.campaign_id = ?
");
$stmt->execute([$id]);
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['sites' => $sites]);
