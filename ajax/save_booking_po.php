<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
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
$booking_id = intval($data['booking_id'] ?? 0);
$vendor_id = intval($data['vendor_id'] ?? 0);

if (!$booking_id || !$vendor_id) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID or vendor ID.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch booking details
    $stmtB = $pdo->prepare("SELECT campaign_name, client_id, start_date, end_date, entity_id FROM bookings WHERE id = ?");
    $stmtB->execute([$booking_id]);
    $booking = $stmtB->fetch();
    
    if (!$booking) {
        throw new Exception("Booking not found.");
    }

    // 2. Check if PO already exists
    $stmtCheck = $pdo->prepare("SELECT id FROM purchase_orders WHERE campaign_id = ? AND vendor_id = ?");
    $stmtCheck->execute([$booking_id, $vendor_id]);
    $existingPoId = $stmtCheck->fetchColumn();

    // 3. Fetch all sites for this vendor on this booking
    $stmtItems = $pdo->prepare("
        SELECT bi.site_id, bi.start_date, bi.end_date, bi.days, bi.purchase_amount, bi.amount, bi.client_print_rate, bi.client_mount_rate
        FROM booking_items bi
        JOIN sites s ON bi.site_id = s.id
        WHERE bi.booking_id = ? AND s.vendor_id = ?
    ");
    $stmtItems->execute([$booking_id, $vendor_id]);
    $rawItems = $stmtItems->fetchAll();

    $items = [];
    foreach ($rawItems as $it) {
        $hasPrint = floatval($it['client_print_rate'] ?? 0) > 0;
        $hasMount = floatval($it['client_mount_rate'] ?? 0) > 0;
        $isRental = floatval($it['amount'] ?? 0) > 0 || (!$hasPrint && !$hasMount);
        if ($isRental) {
            $items[] = $it;
        }
    }

    if (empty($items)) {
        throw new Exception("No rental sites found for this vendor on this booking. Vendor PO is only applicable for site rental.");
    }

    // 4. Calculate Totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += floatval($item['purchase_amount'] ?? 0);
    }
    
    // Check if vendor has GSTIN and state in database
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
            $igst = $subtotal * 0.18;
        } else {
            $cgst = $subtotal * 0.09;
            $sgst = $subtotal * 0.09;
        }
    }
    $grandTotal = $subtotal + $cgst + $sgst + $igst;

    // Entity ID from request, booking, or session
    $entityId = !empty($data['entity_id']) ? intval($data['entity_id']) : ($booking['entity_id'] ?? ($_SESSION['active_entity_id'] ?? null));

    // Admin approves instantly; non-admin goes to queue
    $poStatus       = $isAdmin ? 'approved' : 'pending';
    $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

    if ($existingPoId) {
        $poId = $existingPoId;
        $stmtPOUpdate = $pdo->prepare("
            UPDATE purchase_orders 
            SET po_amount = ?, cgst_amount = ?, sgst_amount = ?, igst_amount = ?, total_amount = ?, status = ?, approval_status = ?, entity_id = COALESCE(?, entity_id)
            WHERE id = ?
        ");
        $stmtPOUpdate->execute([
            $subtotal,
            $cgst,
            $sgst,
            $igst,
            $grandTotal,
            $poStatus,
            $approvalStatus,
            $entityId,
            $poId
        ]);
        
        // Delete old items to replace them
        $pdo->prepare("DELETE FROM po_items WHERE po_id = ?")->execute([$poId]);

        $stmt = $pdo->prepare("SELECT po_number FROM purchase_orders WHERE id = ?");
        $stmt->execute([$poId]);
        $poNum = $stmt->fetchColumn();
        if (empty($poNum) || stripos($poNum, 'BPO/') === 0) {
            $ref_date = date('Y-m-d');
            $fy_str = date('y', strtotime($ref_date)) . '-' . date('y', strtotime($ref_date . ' +1 year'));
            $poNum = "PO/" . $fy_str . "/" . str_pad((string)$poId, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE purchase_orders SET po_number = ? WHERE id = ?")->execute([$poNum, $poId]);
        }

        // Create/Refresh approval request for non-admin on edit/re-save
        if (!$isAdmin) {
            $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'purchase_order' AND entity_id = ? AND status = 'pending'")
                ->execute([$poId]);
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
            $stmtAR->execute([$poId, $poNum, $_SESSION['user_id'] ?? 0]);
        }
    } else {
        // 5. Insert PO
        $stmtPO = $pdo->prepare("
            INSERT INTO purchase_orders 
            (campaign_id, vendor_id, customer_id, employee_id, entity_id, campaign_name, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, approval_status, type, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, '', CURDATE(), ?, ?, ?, ?, ?, ?, ?, 'system', ?)
        ");
        $stmtPO->execute([
            $booking_id,
            $vendor_id,
            $booking['client_id'],
            $_SESSION['user_id'] ?? 0,
            $entityId,
            $booking['campaign_name'],
            $subtotal,
            $cgst,
            $sgst,
            $igst,
            $grandTotal,
            $poStatus,
            $approvalStatus,
            $_SESSION['user_id'] ?? null
        ]);
        
        $poId = $pdo->lastInsertId();

        // 6. Generate PO Number matching PDF format (e.g. PO/26-27/116)
        $ref_date = date('Y-m-d');
        $fy_str = date('y', strtotime($ref_date)) . '-' . date('y', strtotime($ref_date . ' +1 year'));
        $poNum = "PO/" . $fy_str . "/" . str_pad((string)$poId, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE purchase_orders SET po_number = ? WHERE id = ?")->execute([$poNum, $poId]);

        // Create approval request for non-admin
        if (!$isAdmin) {
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
            $stmtAR->execute([$poId, $poNum, $_SESSION['user_id'] ?? 0]);
        }
    }

    // 7. Insert Items — always use booking-level dates as fallback
    $bookingStart = $booking['start_date'] ?: date('Y-m-d');
    $bookingEnd   = $booking['end_date']   ?: date('Y-m-d', strtotime('+30 days'));
    $d1 = new DateTime($bookingStart);
    $d2 = new DateTime($bookingEnd);
    $bookingDays = $d1->diff($d2)->days + 1;

    $stmtPOItem = $pdo->prepare("
        INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $sDate = (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00') ? $item['start_date'] : $bookingStart;
        $eDate = (!empty($item['end_date'])   && $item['end_date']   !== '0000-00-00') ? $item['end_date']   : $bookingEnd;
        $days  = (!empty($item['days'])       && $item['days'] > 0)                   ? $item['days']       : $bookingDays;

        $stmtPOItem->execute([
            $poId,
            $item['site_id'],
            $sDate,
            $eDate,
            $days,
            $item['purchase_amount'],
            $item['purchase_amount']
        ]);
    }

    logActivity('generated a purchase order for booking', 'purchase_orders', $poId, "PO Number: $poNum");

    $pdo->commit();
    echo json_encode([
        'success'         => true,
        'po_id'           => $poId,
        'po_number'       => $poNum,
        'approval_status' => $approvalStatus,
        'message'         => $isAdmin ? "PO $poNum generated." : "PO $poNum submitted for admin approval."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
