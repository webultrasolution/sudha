<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $date = clean($_POST['payment_date'] ?? date('Y-m-d'));
    $ref = clean($_POST['reference_no'] ?? '');
    $notes = clean($_POST['notes'] ?? '');
    $type = clean($_POST['type'] ?? 'receivable');
    
    if (!$client_id || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Client or Amount']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO payments (entity_id, amount, payment_date, reference_no, notes, type) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$client_id, $amount, $date, $ref, $notes, $type])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save payment']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
