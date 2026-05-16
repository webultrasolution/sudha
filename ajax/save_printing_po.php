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

if (!$data || empty($data['rate_ids'])) {
    echo json_encode(['success' => false, 'message' => 'No Printing POs selected.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $rateIds = array_map('intval', $data['rate_ids']);
    $tax_type = $data['tax_type'] ?? 'igst';
    $remarks = $data['remarks'] ?? '';

    // Fetch all selected rates with site and vendor info
    $placeholders = implode(',', array_fill(0, count($rateIds), '?'));
    $stmt = $pdo->prepare("
        SELECT r.*, s.name as site_name, s.site_code, s.width, s.height, v.name as vendor_name
        FROM vendor_printing_rates r
        JOIN partners v ON r.vendor_id = v.id
        LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.id IN ($placeholders)
    ");
    $stmt->execute($rateIds);
    $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rates)) {
        throw new Exception("No valid Printing POs found.");
    }

    // Group by vendor
    $vendorGroups = [];
    foreach ($rates as $r) {
        $vid = $r['vendor_id'];
        if (!isset($vendorGroups[$vid])) {
            $vendorGroups[$vid] = ['vendor_name' => $r['vendor_name'], 'items' => []];
        }
        $sqft = ($r['width'] && $r['height']) ? floatval($r['width']) * floatval($r['height']) : 0;
        $cost = $sqft * floatval($r['rate_per_sqft']);
        $vendorGroups[$vid]['items'][] = [
            'site_id' => $r['site_id'],
            'site_name' => $r['site_name'] ?? 'Generic',
            'site_code' => $r['site_code'] ?? '',
            'sqft' => $sqft,
            'rate' => floatval($r['rate_per_sqft']),
            'cost' => $cost,
            'media_type' => $r['media_type']
        ];
    }

    $poCount = 0;
    $lastPoId = 0;
    $poNumbers = [];

    foreach ($vendorGroups as $vid => $group) {
        $subtotal = 0;
        foreach ($group['items'] as $item) {
            $subtotal += $item['cost'];
        }

        $cgst = 0; $sgst = 0; $igst = 0;
        if ($tax_type === 'cgst_sgst') {
            $cgst = $subtotal * 0.09;
            $sgst = $subtotal * 0.09;
        } else {
            $igst = $subtotal * 0.18;
        }
        $grandTotal = $subtotal + $cgst + $sgst + $igst;

        $poNum = 'PRT-' . date('Ymd') . '-' . rand(100, 999);

        $stmtPO = $pdo->prepare("
            INSERT INTO purchase_orders (vendor_id, employee_id, campaign_name, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, remarks) 
            VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'approved', ?)
        ");
        $stmtPO->execute([
            $vid,
            $_SESSION['user_id'] ?? 0,
            'Printing PO',
            $poNum,
            $subtotal, $cgst, $sgst, $igst, $grandTotal,
            "Printing PO - " . $group['vendor_name'] . ($remarks ? ". " . $remarks : "")
        ]);
        $poId = $pdo->lastInsertId();
        $lastPoId = $poId;
        $poCount++;
        $poNumbers[] = $poNum;

        // Insert PO Items
        $stmtItem = $pdo->prepare("INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost, description) VALUES (?, ?, CURDATE(), CURDATE(), 1, ?, ?, ?)");
        foreach ($group['items'] as $item) {
            $desc = "Printing: {$item['media_type']} - {$item['site_code']} {$item['site_name']} ({$item['sqft']} SQFT × ₹{$item['rate']}/sqft)";
            $stmtItem->execute([$poId, $item['site_id'], $item['rate'], $item['cost'], $desc]);
        }
    }

    logActivity('generated printing purchase order(s)', 'purchase_orders', $lastPoId, "$poCount Printing PO(s): " . implode(', ', $poNumbers));

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'po_id' => ($poCount === 1) ? $lastPoId : null,
        'po_count' => $poCount,
        'po_numbers' => $poNumbers,
        'message' => "$poCount Printing PO(s) generated: " . implode(', ', $poNumbers)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
