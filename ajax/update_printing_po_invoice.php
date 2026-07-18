<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('vendors')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    $po_number = isset($_POST['po_number']) ? clean($_POST['po_number']) : '';
    $no = isset($_POST['no']) ? clean($_POST['no']) : '';
    $date = isset($_POST['date']) ? clean($_POST['date']) : '';

    if ($po_number && $no && $date) {
        $stmt = $pdo->prepare("UPDATE vendor_printing_rates SET vendor_invoice_no = ?, vendor_invoice_date = ? WHERE po_number = ?");
        $stmt->execute([$no, $date, $po_number]);
        
        $stmtPO = $pdo->prepare("UPDATE purchase_orders SET vendor_invoice_no = ?, vendor_invoice_date = ? WHERE po_number = ?");
        $stmtPO->execute([$no, $date, $po_number]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing fields or invalid PO number']);
    }
}
