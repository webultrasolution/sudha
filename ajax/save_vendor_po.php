<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['vendor_id']) || empty($data['sites'])) {
    echo json_encode(['success' => false, 'message' => 'Incomplete data. Please select sites.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $vendor_id = intval($data['vendor_id']);
    $campaign_name = $data['campaign_name'] ?? 'Vendor PO';
    $start_date = $data['start_date'] ?? date('Y-m-d');
    $end_date = $data['end_date'] ?? date('Y-m-d', strtotime('+1 month'));
    $tax_type = $data['tax_type'] ?? 'igst';
    $remarks = $data['remarks'] ?? '';
    $vendor_gst = $data['vendor_gst'] ?? '';

    // Calculate totals
    $subtotal = 0;
    foreach ($data['sites'] as $site) {
        $subtotal += floatval($site['rate']);
    }

    // Check if vendor has GSTIN in database
    $stmtGst = $pdo->prepare("SELECT gstin FROM partners WHERE id = ?");
    $stmtGst->execute([$vendor_id]);
    $db_vendor_gst = trim($stmtGst->fetchColumn() ?: '');
    $vendor_has_gst = vendorHasGST($db_vendor_gst);

    $cgst = 0;
    $sgst = 0;
    $igst = 0;
    if ($vendor_has_gst) {
        if ($tax_type === 'cgst_sgst') {
            $cgst = $subtotal * 0.09;
            $sgst = $subtotal * 0.09;
        } elseif ($tax_type === 'igst') {
            $igst = $subtotal * 0.18;
        }
    }
    $grand_total = $subtotal + $cgst + $sgst + $igst;

    // Generate PO Number
    $po_number = 'VPO-' . date('Ymd') . '-' . rand(100, 999);

    // Admin approves instantly; non-admin goes to queue
    $poStatus       = $isAdmin ? 'approved' : 'pending';
    $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

    // Insert PO
    $stmtPO = $pdo->prepare("
        INSERT INTO purchase_orders 
        (vendor_id, employee_id, campaign_name, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, approval_status, remarks) 
        VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtPO->execute([
        $vendor_id,
        $_SESSION['user_id'] ?? 0,
        $campaign_name,
        $po_number,
        $subtotal,
        $cgst,
        $sgst,
        $igst,
        $grand_total,
        $poStatus,
        $approvalStatus,
        $remarks
    ]);
    $po_id = $pdo->lastInsertId();

    // Create approval request for non-admin
    if (!$isAdmin) {
        $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
        $stmtAR->execute([$po_id, $po_number, $_SESSION['user_id'] ?? 0]);
    }

    // Insert PO Items
    $stmtItem = $pdo->prepare("
        INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // Calculate days
    $d1 = new DateTime($start_date);
    $d2 = new DateTime($end_date);
    $days = $d1->diff($d2)->days + 1;

    foreach ($data['sites'] as $site) {
        $rate = floatval($site['rate']);
        $stmtItem->execute([
            $po_id,
            intval($site['id']),
            $start_date,
            $end_date,
            $days,
            $rate,
            $rate
        ]);
    }

    logActivity('generated a vendor purchase order', 'purchase_orders', $po_id, "PO Number: $po_number for Vendor ID: $vendor_id with " . count($data['sites']) . " sites.");

    $pdo->commit();

    echo json_encode([
        'success'         => true,
        'po_id'           => $po_id,
        'po_number'       => $po_number,
        'approval_status' => $approvalStatus,
        'message'         => $isAdmin
            ? "Purchase Order $po_number generated with " . count($data['sites']) . " site(s)."
            : "Purchase Order $po_number submitted for admin approval."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
