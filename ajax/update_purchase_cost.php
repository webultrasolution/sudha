<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $purchase_cost = floatval($_POST['purchase_cost'] ?? 0);

    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Item ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE booking_items SET purchase_amount = ? WHERE id = ?");
        $stmt->execute([$purchase_cost, $item_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
