<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['entityId']) || empty($data['amount'])) {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO payments (type, entity_id, amount, payment_mode, payment_date, reference_no) 
            VALUES (?, ?, ?, ?, CURDATE(), ?)
        ");
        $stmt->execute([
            $data['type'],
            $data['entityId'],
            $data['amount'],
            $data['mode'],
            $data['ref']
        ]);
        
        logActivity('recorded a payment', 'payments', $pdo->lastInsertId(), "Amount: " . $data['amount']);
        
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
