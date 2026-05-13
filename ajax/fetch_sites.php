<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;

$q = isset($_GET['q']) ? $_GET['q'] : '';
$media = isset($_GET['media']) ? $_GET['media'] : '';
$state = isset($_GET['state']) ? $_GET['state'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';
$vendor = isset($_GET['vendor']) ? $_GET['vendor'] : '';
$availability = isset($_GET['availability']) ? $_GET['availability'] : 'available';
$ownership = isset($_GET['ownership']) ? $_GET['ownership'] : 'all';
$size = isset($_GET['size']) ? $_GET['size'] : '';

$params = [];
$where = ["1=1"];

if ($q) {
    $where[] = "(s.site_code LIKE ? OR s.location LIKE ? OR s.name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($media) { $where[] = "s.type = ?"; $params[] = $media; }
if ($state) { $where[] = "s.state = ?"; $params[] = $state; }
if ($city) { $where[] = "s.city = ?"; $params[] = $city; }
if ($vendor) { $where[] = "s.vendor_id = ?"; $params[] = $vendor; }
if ($availability === 'available') { $where[] = "s.status = 'available'"; }
if ($ownership !== 'all') { $where[] = "s.owner_type = ?"; $params[] = $ownership; }
if ($size) {
    $parts = explode('x', $size);
    if (count($parts) == 2) {
        $where[] = "s.width = ? AND s.height = ?";
        $params[] = $parts[0];
        $params[] = $parts[1];
    }
}

$whereClause = implode(" AND ", $where);

// Count total for pagination
$countQuery = "SELECT COUNT(*) FROM sites s WHERE $whereClause";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();

// Fetch Data
$query = "
    SELECT 
        s.id, s.site_code, s.name, s.location, s.city, s.state, s.type, s.light_type, 
        s.width, s.height, s.card_rate, s.purchase_rate, s.owner_type, s.vendor_id, s.status,
        p.name as vendor_name,
        (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail 
    FROM sites s 
    LEFT JOIN partners p ON s.vendor_id = p.id
    WHERE $whereClause
    ORDER BY s.site_code ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sites = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'total' => intval($total),
    'sites' => $sites,
    'page' => $page,
    'limit' => $limit
]);
?>
