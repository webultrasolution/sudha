<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = clean($_POST['po_number'] ?? '');
    $client_id = intval($_POST['client_id'] ?? 0);
    $rate_ids_str = clean($_POST['rate_ids'] ?? '');
    $type = clean($_POST['confirmation_type'] ?? 'po');
    $po_no = clean($_POST['customer_po_no'] ?? '');
    $po_date = clean($_POST['customer_po_date'] ?? '');
    $email_date = clean($_POST['email_date'] ?? '');
    
    if (!$client_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid Client ID']);
        exit;
    }

    $file_path = null;
    if (isset($_FILES['customer_po_file']) && $_FILES['customer_po_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/customer_pos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extension = pathinfo($_FILES['customer_po_file']['name'], PATHINFO_EXTENSION);
        $prefix = ($type === 'email') ? 'PRINT_EMAIL_' : 'PRINT_PO_';
        $filename = $prefix . $client_id . '_' . time() . '.' . $extension;
        $target = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['customer_po_file']['tmp_name'], $target)) {
            $file_path = 'uploads/customer_pos/' . $filename;
        }
    }

    // Determine the update condition: by group po_number or individual rate IDs
    if (!empty($po_number)) {
        $sql = "UPDATE client_printing_rates 
                SET confirmation_type = ?, customer_po_no = ?, customer_po_date = ?, email_date = ?, is_final_invoice = 1" 
                . ($file_path ? ", customer_po_file = ?" : "") . 
                " WHERE po_number = ? AND client_id = ?";
        
        $params = [$type, $po_no ?: null, $po_date ?: null, $email_date ?: null];
        if ($file_path) $params[] = $file_path;
        $params[] = $po_number;
        $params[] = $client_id;
    } else {
        $rate_ids = array_filter(array_map('intval', explode(',', $rate_ids_str)));
        if (empty($rate_ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected to update']);
            exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($rate_ids), '?'));
        $sql = "UPDATE client_printing_rates 
                SET confirmation_type = ?, customer_po_no = ?, customer_po_date = ?, email_date = ?, is_final_invoice = 1" 
                . ($file_path ? ", customer_po_file = ?" : "") . 
                " WHERE id IN ($placeholders)";
        
        $params = [$type, $po_no ?: null, $po_date ?: null, $email_date ?: null];
        if ($file_path) $params[] = $file_path;
        $params = array_merge($params, $rate_ids);
    }

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Update Failed']);
    }
}
?>
