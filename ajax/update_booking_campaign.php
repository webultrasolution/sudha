<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requirePermission('bookings', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = intval($_POST['booking_id'] ?? 0);
    $campaignName = trim($_POST['campaign_name'] ?? '');

    if (!$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE bookings SET campaign_name = ? WHERE id = ?");
        $stmt->execute([$campaignName, $bookingId]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
