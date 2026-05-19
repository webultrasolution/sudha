<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['campaignId']) || empty($data['vendorId'])) {
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
        // Check if vendor has GSTIN in database
        $stmtGst = $pdo->prepare("SELECT gstin FROM partners WHERE id = ?");
        $stmtGst->execute([$data['vendorId']]);
        $db_vendor_gst = trim($stmtGst->fetchColumn() ?: '');
        $vendor_has_gst = vendorHasGST($db_vendor_gst);

        $cgst = 0;
        $sgst = 0;
        if ($vendor_has_gst) {
            $cgst = $subtotal * 0.09;
            $sgst = $subtotal * 0.09;
        }
        $grandTotal = $subtotal + $cgst + $sgst;

        // Generate PO Number
        $poNum = 'PO-' . date('Ymd') . '-' . rand(100, 999);

        // 2. Insert PO
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders (campaign_id, vendor_id, employee_id, po_number, po_date, po_amount, cgst_amount, sgst_amount, total_amount, status) 
            VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, 'approved')
        ");
        $stmt->execute([
            $data['campaignId'],
            $data['vendorId'],
            $_SESSION['user_id'],
            $poNum,
            $subtotal,
            $cgst,
            $sgst,
            $grandTotal
        ]);
        
        $poId = $pdo->lastInsertId();
        
        logActivity('generated a purchase order', 'purchase_orders', $poId, "PO Number: $poNum");

        // 3. Insert Items
        $stmtItem = $pdo->prepare("
            INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['sites'] as $site) {
            $stmtItem->execute([
                $poId,
                $site['id'],
                $site['start_date'],
                $site['end_date'],
                30, // Default days
                $site['purchase_rate'],
                $site['purchase_rate']
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'po_number' => $poNum]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
