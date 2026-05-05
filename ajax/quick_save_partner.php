<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['name']) || empty($data['type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO partners (type, name, contact_person, phone, email, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $data['type'],
        $data['name'],
        $data['contact'] ?? '',
        $data['phone'] ?? '',
        $data['email'] ?? ''
    ]);
    
    $newId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $newId]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
