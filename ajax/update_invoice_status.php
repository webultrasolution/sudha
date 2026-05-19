<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to update invoice status.']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $invoiceId = intval($data['invoice_id']);
    $status = clean($data['status']);

    if (!$invoiceId) {
        echo json_encode(['success' => false, 'message' => 'Invalid Invoice ID']);
        exit;
    }

    $allowedStatuses = ['unpaid', 'partially_paid', 'paid'];
    if (!in_array($status, $allowedStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE invoices SET payment_status = ? WHERE id = ?");
        $stmt->execute([$status, $invoiceId]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
