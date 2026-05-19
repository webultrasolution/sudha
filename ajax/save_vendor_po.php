<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

    // Insert PO
    $stmtPO = $pdo->prepare("
        INSERT INTO purchase_orders 
        (vendor_id, employee_id, campaign_name, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, remarks) 
        VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'approved', ?)
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
        $remarks
    ]);
    $po_id = $pdo->lastInsertId();

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
        'success' => true,
        'po_id' => $po_id,
        'po_number' => $po_number,
        'message' => "Purchase Order $po_number generated with " . count($data['sites']) . " site(s)."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
