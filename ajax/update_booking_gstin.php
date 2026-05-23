<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('bookings') && !canAdd('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to edit bookings.']);
        exit;
    }
    
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $billing_gstin = clean($_POST['billing_gstin'] ?? '');
    
    if (!$booking_id || !$billing_gstin) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE bookings SET billing_gstin = ? WHERE id = ?");
    if ($stmt->execute([$billing_gstin, $booking_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
    }
}
?>
