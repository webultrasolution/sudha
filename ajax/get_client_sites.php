<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../config/db.php';

$clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($clientId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid client id']);
    exit;
}

try {
    // Sites referenced by proposals for this client (excluding cancelled)
    $stmt = $pdo->prepare("SELECT pi.site_id, p.proposal_number, p.status FROM proposal_items pi JOIN proposals p ON pi.proposal_id = p.id WHERE p.client_id = ? AND p.status != 'cancelled'");
    $stmt->execute([$clientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sites = [];
    foreach ($rows as $r) {
        $sid = intval($r['site_id']);
        if (!isset($sites[$sid]) || ($sites[$sid]['type'] === 'proposal' && $r['status'] === 'confirmed')) {
            $sites[$sid] = ['site_id' => $sid, 'type' => 'proposal', 'ref' => $r['proposal_number'], 'status' => $r['status']];
        }
    }

    // Also check booking_items (active bookings) and mark as booking which is higher priority
    $stmtB = $pdo->prepare("SELECT bi.site_id, b.id as booking_id, b.status as booking_status, p.proposal_number FROM booking_items bi JOIN bookings b ON bi.booking_id = b.id JOIN proposals p ON b.proposal_id = p.id WHERE p.client_id = ?");
    $stmtB->execute([$clientId]);
    $rowsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rowsB as $rb) {
        $sid = intval($rb['site_id']);
        $sites[$sid] = ['site_id' => $sid, 'type' => 'booking', 'ref' => $rb['booking_id'], 'status' => $rb['booking_status']];
    }

    // Return as list
    $out = array_values($sites);
    echo json_encode(['success' => true, 'sites' => $out]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
