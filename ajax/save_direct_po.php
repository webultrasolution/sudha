<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['vendor_id']) || empty($data['site_ids'])) {
        echo json_encode(['success' => false, 'message' => 'Incomplete data. Please select sites.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $vendor_id = intval($data['vendor_id']);
        $client_id = (!empty($data['client_id'])) ? intval($data['client_id']) : null;
        $campaign_name = $data['campaign_name'] ?? 'Direct Booking';
        $remarks = $data['remark'] ?? '';
        $start_date = $data['start_date'] ?? date('Y-m-d');
        $end_date = $data['end_date'] ?? date('Y-m-d', strtotime('+1 month'));

        // 1. Calculate Totals
        $subtotal = 0;
        foreach ($data['site_ids'] as $sid) {
            $rate = floatval($data['rates'][$sid] ?? 0);
            $subtotal += $rate;
        }
        $cgst = $subtotal * 0.09;
        $sgst = $subtotal * 0.09;
        $grandTotal = $subtotal + $cgst + $sgst;

        // Generate PO Number
        $poNum = 'PO-' . date('Ymd') . '-' . rand(100, 999);

        // 2. Insert PO
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders (vendor_id, customer_id, employee_id, campaign_name, po_number, po_date, po_amount, cgst_amount, sgst_amount, total_amount, status, remarks) 
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, 'approved', ?)
        ");
        $stmt->execute([
            $vendor_id,
            $client_id,
            $_SESSION['user_id'] ?? 0,
            $campaign_name,
            $poNum,
            $subtotal,
            $cgst,
            $sgst,
            $grandTotal,
            $remarks
        ]);
        
        $poId = $pdo->lastInsertId();
        
        // 3. Insert Items
        $stmtItem = $pdo->prepare("
            INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['site_ids'] as $sid) {
            $rate = floatval($data['rates'][$sid] ?? 0);
            $stmtItem->execute([
                $poId,
                $sid,
                $start_date,
                $end_date,
                30,
                $rate,
                $rate
            ]);
        }

        // 4. Create Booking (Direct)
        $stmtBooking = $pdo->prepare("
            INSERT INTO bookings (client_id, campaign_name, start_date, end_date, total_amount, tax_amount, grand_total, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmtBooking->execute([
            $client_id,
            $campaign_name,
            $start_date,
            $end_date,
            $subtotal,
            ($cgst + $sgst),
            $grandTotal
        ]);
        $bookingId = $pdo->lastInsertId();

        // 5. Create Operational Tasks
        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");
        foreach ($data['site_ids'] as $sid) {
            $stmtOps->execute([$bookingId, $sid]);
        }

        logActivity('generated a direct booking and purchase order', 'bookings', $bookingId, "Booking ID: $bookingId, PO Number: $poNum");

        $pdo->commit();
        echo json_encode(['success' => true, 'po_id' => $poId, 'po_number' => $poNum]);

    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
