<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

        // 1. Update Proposal Status
        $stmt = $pdo->prepare("UPDATE proposals SET status = 'confirmed' WHERE id = ?");
        $stmt->execute([$proposalId]);

        // 2. Create Booking
        $stmtBooking = $pdo->prepare("INSERT INTO bookings (proposal_id, status) VALUES (?, 'active')");
        $stmtBooking->execute([$proposalId]);
        $bookingId = $pdo->lastInsertId();

        // 3. Create Campaign (As per CRS flow)
        $projId = 'P' . str_pad($bookingId, 5, '0', STR_PAD_LEFT);
        $stmtCamp = $pdo->prepare("
            INSERT INTO campaigns (project_id, booking_id, client_id, employee_id, display_name, from_date, to_date, days, sqft, amount, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'running')
        ");
        
        $diff = strtotime($proposal['end_date']) - strtotime($proposal['start_date']);
        $days = round($diff / (60 * 60 * 24));

        $stmtCamp->execute([
            $projId,
            $bookingId,
            $proposal['client_id'],
            $_SESSION['user_id'],
            'Campaign for ' . $proposal['proposal_number'],
            $proposal['start_date'],
            $proposal['end_date'],
            $days,
            $proposal['total_sqft'],
            $proposal['grand_total']
        ]);

        // 4. Create Operations (Mounting Tasks)
        $stmtItems = $pdo->prepare("SELECT site_id FROM proposal_items WHERE proposal_id = ?");
        $stmtItems->execute([$proposalId]);
        $items = $stmtItems->fetchAll();

        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");
        foreach ($items as $item) {
            $stmtOps->execute([$bookingId, $item['site_id']]);
        }

        logActivity('confirmed a booking and started campaign', 'campaigns', $pdo->lastInsertId(), "Project ID: $projId");

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
