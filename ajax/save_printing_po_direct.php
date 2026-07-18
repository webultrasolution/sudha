<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['rate_ids'])) {
        echo json_encode(['success' => false, 'message' => 'Incomplete data. Please select rates.']);
        exit;
    }

    $vendor_id = intval($data['vendor_id']);
    $remarks = $data['remark'] ?? '';

    try {
        $pdo->beginTransaction();

        // 1. Calculate Total Amount
        $net_total = 0;
        $items = [];

        foreach ($data['rate_ids'] as $rate_id) {
            $rate_id = intval($rate_id);
            $stmt = $pdo->prepare("
                SELECT r.site_id, r.rate_per_sqft, s.width, s.height, s.name as site_name 
                FROM vendor_printing_rates r 
                LEFT JOIN sites s ON r.site_id = s.id 
                WHERE r.id = ?
            ");
            $stmt->execute([$rate_id]);
            $r = $stmt->fetch();
            if ($r) {
                $sqft = ($r['width'] && $r['height']) ? floatval($r['width']) * floatval($r['height']) : 0;
                $total = $sqft * floatval($r['rate_per_sqft']);
                $net_total += $total;
                $items[] = [
                    'site_id' => $r['site_id'],
                    'rate' => $r['rate_per_sqft'],
                    'total' => $total
                ];
            }
        }

        // Check GST and state in database
        $stmtGst = $pdo->prepare("SELECT gstin, state FROM partners WHERE id = ?");
        $stmtGst->execute([$vendor_id]);
        $vendorRow = $stmtGst->fetch(PDO::FETCH_ASSOC);
        $db_vendor_gst = trim($vendorRow['gstin'] ?? '');
        $vendor_state = trim($vendorRow['state'] ?? '');
        $vendor_has_gst = vendorHasGST($db_vendor_gst);

        $cgst = 0; $sgst = 0; $igst = 0;
        if ($vendor_has_gst) {
            $isVendorInterstate = (strcasecmp($vendor_state, 'West Bengal') !== 0 && substr($db_vendor_gst, 0, 2) !== '19');
            if ($isVendorInterstate) {
                $igst = $net_total * 0.18;
            } else {
                $cgst = $net_total * 0.09;
                $sgst = $net_total * 0.09;
            }
        }
        $grandTotal = $net_total + $cgst + $sgst + $igst;

        $poNum = generateSequenceNumber($pdo, 'vendor_printing_po');
        $poStatus = $isAdmin ? 'approved' : 'pending';
        $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

        // 2. Insert into purchase_orders
        $stmtPO = $pdo->prepare("
            INSERT INTO purchase_orders (vendor_id, employee_id, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, approval_status, remarks, type) 
            VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'printing')
        ");
        $stmtPO->execute([
            $vendor_id, 
            $_SESSION['user_id'] ?? 0, 
            $poNum, 
            $net_total, 
            $cgst, 
            $sgst, 
            $igst, 
            $grandTotal, 
            $poStatus, 
            $approvalStatus, 
            $remarks
        ]);
        $poId = $pdo->lastInsertId();

        // Create approval request for non-admin
        if (!$isAdmin) {
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
            $stmtAR->execute([$poId, $poNum, $_SESSION['user_id'] ?? 0]);
        }

        // 3. Insert into po_items
        $stmtPOItem = $pdo->prepare("INSERT INTO po_items (po_id, site_id, monthly_rate, cost) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmtPOItem->execute([
                $poId, 
                $item['site_id'], 
                $item['rate'], 
                $item['total']
            ]);
        }

        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'po_id' => $poId, 
            'approval_status' => $approvalStatus,
            'message' => $isAdmin ? 'PO saved successfully.' : 'PO submitted for admin approval.'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
