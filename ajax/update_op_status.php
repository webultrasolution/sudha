<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $opId = intval($data['op_id']);
    $status = isset($data['status']) ? clean($data['status']) : null;
    $mounterId = isset($data['assigned_mounter_id']) ? intval($data['assigned_mounter_id']) : null;

    if (!$opId) {
        echo json_encode(['success' => false, 'message' => 'Invalid Operation ID']);
        exit;
    }

    try {
        if ($mounterId !== null && $status !== null) {
            $stmt = $pdo->prepare("UPDATE operations SET assigned_mounter_id = ?, status = ? WHERE id = ?");
            $stmt->execute([$mounterId, $status, $opId]);
        } elseif ($status !== null) {
            $stmt = $pdo->prepare("UPDATE operations SET status = ? WHERE id = ?");
            $stmt->execute([$status, $opId]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
