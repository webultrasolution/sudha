<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../config/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, gstin, pan, additional_gst, business_type FROM partners WHERE id = ?");
    $stmt->execute([$id]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($partner) {
        echo json_encode(['success' => true, 'data' => $partner]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Partner not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
