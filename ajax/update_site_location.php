<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!canEdit('bookings') && !canEdit('inventory')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_id = intval($_POST['site_id'] ?? 0);
    $location = trim($_POST['location'] ?? '');

    if ($site_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid site ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE sites SET location = ? WHERE id = ?");
        $stmt->execute([$location, $site_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
