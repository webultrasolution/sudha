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

        $itemIds = $data['item_ids'] ?? [];
        if (empty($itemIds)) {
            echo json_encode(['success' => false, 'message' => 'No items selected for campaign conversion.']);
            exit;
        }

        // 1. Update Proposal Status
        $stmt = $pdo->prepare("UPDATE proposals SET status = 'confirmed' WHERE id = ?");
        $stmt->execute([$proposalId]);

        // 2. Create Booking
        $stmtBooking = $pdo->prepare("INSERT INTO bookings (proposal_id, status) VALUES (?, 'active')");
        $stmtBooking->execute([$proposalId]);
        $bookingId = $pdo->lastInsertId();

        // 3. Create Operations (Mounting Tasks) ONLY for selected items
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $params = array_merge($itemIds, [$proposalId]);
        
        $stmtItems = $pdo->prepare("SELECT site_id FROM proposal_items WHERE id IN ($placeholders) AND proposal_id = ?");
        $stmtItems->execute($params);
        $items = $stmtItems->fetchAll();

        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");
        foreach ($items as $item) {
            $stmtOps->execute([$bookingId, $item['site_id']]);
        }

        logActivity('converted proposal to booking', 'bookings', $bookingId, "Booking ID: $bookingId");

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
