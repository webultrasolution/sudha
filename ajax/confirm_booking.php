<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $proposalId = intval($data['proposal_id']);

    if (!$proposalId) {
        echo json_encode(['success' => false, 'message' => 'Invalid Proposal ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Fetch Proposal Data
        $stmtP = $pdo->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmtP->execute([$proposalId]);
        $proposal = $stmtP->fetch();

        $itemIds = $data['item_ids'] ?? [];
        if (empty($itemIds)) {
            echo json_encode(['success' => false, 'message' => 'No items selected for campaign conversion.']);
            exit;
        }

        // 1. Update Proposal Status
        $stmt = $pdo->prepare("UPDATE proposals SET status = 'confirmed' WHERE id = ?");
        $stmt->execute([$proposalId]);

        // Admin activates booking immediately; non-admin goes to pending_approval
        $bookingStatus   = $isAdmin ? 'active' : 'pending';
        $approvalStatus  = $isAdmin ? 'approved' : 'pending_approval';

        // 2. Create Booking
        $stmtBooking = $pdo->prepare("INSERT INTO bookings (proposal_id, client_id, campaign_name, billing_gstin, start_date, end_date, total_amount, tax_amount, grand_total, status, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtBooking->execute([
            $proposalId,
            $proposal['client_id'],
            $proposal['campaign_name'],
            $proposal['billing_gstin'],
            $proposal['start_date'],
            $proposal['end_date'],
            $proposal['total_amount'],
            $proposal['tax_amount'],
            $proposal['grand_total'],
            $bookingStatus,
            $approvalStatus
        ]);
        $bookingId = $pdo->lastInsertId();

        // 3. Create Booking Items and Operations ONLY for selected items
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        
        $stmtItems = $pdo->prepare("SELECT * FROM proposal_items WHERE id IN ($placeholders)");
        $stmtItems->execute($itemIds);
        $items = $stmtItems->fetchAll();

        $stmtBI = $pdo->prepare("INSERT INTO booking_items (booking_id, proposal_item_id, site_id, purchase_rate, sale_rate, start_date, end_date, days, purchase_amount, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");
        
        foreach ($items as $item) {
            // Respect custom item start/end dates if set, otherwise fallback to global proposal dates
            $itemStartDate = (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00') ? $item['start_date'] : $proposal['start_date'];
            $itemEndDate = (!empty($item['end_date']) && $item['end_date'] !== '0000-00-00') ? $item['end_date'] : $proposal['end_date'];

            // Save snapshot to booking_items
            $stmtBI->execute([
                $bookingId, 
                $item['id'], 
                $item['site_id'], 
                $item['purchase_rate'],
                $item['sale_rate'], 
                $itemStartDate, 
                $itemEndDate, 
                $item['days'], 
                0, // Set purchase_amount to 0 by default (user must confirm manually)
                $item['amount']
            ]);
            
            // Create operation task
            $stmtOps->execute([$bookingId, $item['site_id']]);
        }

        // Create approval request for booking (non-admin)
        if (!$isAdmin) {
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('booking', ?, ?, ?, 'pending')");
            $stmtAR->execute([$bookingId, "Booking #$bookingId (from Proposal #$proposalId)", $_SESSION['user_id']]);
        }

        logActivity('converted proposal to booking', 'bookings', $bookingId, "Booking ID: $bookingId");

        $pdo->commit();
        echo json_encode([
            'success'         => true,
            'approval_status' => $approvalStatus,
            'message'         => $isAdmin
                ? 'Booking confirmed successfully.'
                : 'Booking submitted for admin approval.'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
