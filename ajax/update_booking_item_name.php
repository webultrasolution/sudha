<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!canEdit('bookings')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $site_name = trim($_POST['site_name'] ?? '');

    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking item ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE booking_items SET custom_site_name = ? WHERE id = ?");
        $stmt->execute([$site_name, $item_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
