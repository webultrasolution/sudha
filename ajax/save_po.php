<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['campaignId']) || empty($data['vendorId']) || empty($data['entityId'])) {
        echo json_encode(['success' => false, 'message' => 'Incomplete data']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Calculate Totals
        $subtotal = 0;
        foreach ($data['sites'] as $site) {
            $subtotal += floatval($site['purchase_rate']);
        }
        // Check if vendor has GSTIN and state in database
        $stmtGst = $pdo->prepare("SELECT gstin, state FROM partners WHERE id = ?");
        $stmtGst->execute([$data['vendorId']]);
        $vendorRow = $stmtGst->fetch(PDO::FETCH_ASSOC);
        $db_vendor_gst = trim($vendorRow['gstin'] ?? '');
        $vendor_state = trim($vendorRow['state'] ?? '');
        $vendor_has_gst = vendorHasGST($db_vendor_gst);

        $cgst = 0; $sgst = 0; $igst = 0;
        if ($vendor_has_gst) {
            $isVendorInterstate = (strcasecmp($vendor_state, 'West Bengal') !== 0 && substr($db_vendor_gst, 0, 2) !== '19');
            if ($isVendorInterstate) {
                $igst = $subtotal * 0.18;
            } else {
                $cgst = $subtotal * 0.09;
                $sgst = $subtotal * 0.09;
            }
        }
        $grandTotal = $subtotal + $cgst + $sgst + $igst;

        // Generate PO Number
        $poNum = 'PO-' . date('Ymd') . '-' . rand(100, 999);

        // Admin approves instantly; non-admin goes to queue
        $poStatus       = $isAdmin ? 'approved' : 'pending';
        $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

        // 2. Insert PO
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders (campaign_id, vendor_id, entity_id, employee_id, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, approval_status, type) 
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 'system')
        ");
        $stmt->execute([
            $data['campaignId'],
            $data['vendorId'],
            $data['entityId'],
            $_SESSION['user_id'],
            $poNum,
            $subtotal,
            $cgst,
            $sgst,
            $igst,
            $grandTotal,
            $poStatus,
            $approvalStatus
        ]);
        
        $poId = $pdo->lastInsertId();
        
        // Create approval request for non-admin
        if (!$isAdmin) {
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
            $stmtAR->execute([$poId, $poNum, $_SESSION['user_id']]);
        }
        
        logActivity('generated a purchase order', 'purchase_orders', $poId, "PO Number: $poNum");

        // 3. Insert Items
        $stmtItem = $pdo->prepare("
            INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['sites'] as $site) {
            $days = 30;
            if (!empty($site['start_date']) && !empty($site['end_date'])) {
                $d1 = new DateTime($site['start_date']);
                $d2 = new DateTime($site['end_date']);
                $days = $d1->diff($d2)->days + 1;
            }
            $stmtItem->execute([
                $poId,
                $site['id'],
                !empty($site['start_date']) ? $site['start_date'] : null,
                !empty($site['end_date']) ? $site['end_date'] : null,
                $days,
                $site['purchase_rate'],
                $site['purchase_rate']
            ]);
        }

        $pdo->commit();
        echo json_encode([
            'success'         => true,
            'po_number'       => $poNum,
            'approval_status' => $approvalStatus,
            'message'         => $isAdmin ? "PO $poNum generated." : "PO $poNum submitted for admin approval."
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
