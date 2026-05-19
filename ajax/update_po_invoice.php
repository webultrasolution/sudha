<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to update PO invoice.']);
        exit;
    }
    $id = intval($_POST['id']);
    $no = clean($_POST['no']);
    $date = clean($_POST['date']);

    $stmt = $pdo->prepare("UPDATE purchase_orders SET vendor_invoice_no = ?, vendor_invoice_date = ? WHERE id = ?");
    if ($stmt->execute([$no, $date, $id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
