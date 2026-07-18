<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $po_number = clean($_POST['po_number'] ?? '');
        $client_id = intval($_POST['client_id'] ?? 0);
        $rate_ids_str = clean($_POST['rate_ids'] ?? '');
        $type = clean($_POST['confirmation_type'] ?? 'po');
        $po_no = clean($_POST['customer_po_no'] ?? '');
        
        // Normalize dates to Y-m-d to prevent database format exceptions
        $raw_po_date = clean($_POST['customer_po_date'] ?? '');
        $po_date = !empty($raw_po_date) ? date('Y-m-d', strtotime(str_replace('/', '-', $raw_po_date))) : null;
        
        $raw_email_date = clean($_POST['email_date'] ?? '');
        $email_date = !empty($raw_email_date) ? date('Y-m-d', strtotime(str_replace('/', '-', $raw_email_date))) : null;
        
        $custom_invoice_number = clean($_POST['custom_invoice_number'] ?? '');
        if (!empty($custom_invoice_number)) {
            $preview = getPreviewSequenceNumber($pdo, 'client_printing_po');
            if ($custom_invoice_number === $preview) {
                generateSequenceNumber($pdo, 'client_printing_po');
            }
        } else {
            $custom_invoice_number = generateSequenceNumber($pdo, 'client_printing_po');
        }
        
        $raw_custom_invoice_date = clean($_POST['custom_invoice_date'] ?? '');
        $custom_invoice_date = !empty($raw_custom_invoice_date) ? date('Y-m-d', strtotime(str_replace('/', '-', $raw_custom_invoice_date))) : date('Y-m-d');

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

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!canView('clients')) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to finalize client printing tax invoices.']);
            exit;
        }
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';
        $isFinalInvoice = $isAdmin ? 1 : 0;

        $setStatus = "is_final_invoice = ?, approval_status = ?";

        // Determine the update condition: by group po_number or individual rate IDs
        if (!empty($po_number)) {
            $sql = "UPDATE client_printing_rates 
                    SET confirmation_type = ?, customer_po_no = ?, customer_po_date = ?, email_date = ?, custom_invoice_number = ?, custom_invoice_date = ?, " . $setStatus 
                    . ($file_path ? ", customer_po_file = ?" : "") . 
                    " WHERE po_number = ? AND client_id = ?";
            
            $params = [$type, $po_no ?: null, $po_date ?: null, $email_date ?: null, $custom_invoice_number ?: null, $custom_invoice_date ?: null, $isFinalInvoice, $approvalStatus];
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
                    SET confirmation_type = ?, customer_po_no = ?, customer_po_date = ?, email_date = ?, custom_invoice_number = ?, custom_invoice_date = ?, " . $setStatus 
                    . ($file_path ? ", customer_po_file = ?" : "") . 
                    " WHERE id IN ($placeholders)";
            
            $params = [$type, $po_no ?: null, $po_date ?: null, $email_date ?: null, $custom_invoice_number ?: null, $custom_invoice_date ?: null, $isFinalInvoice, $approvalStatus];
            if ($file_path) $params[] = $file_path;
            $params = array_merge($params, $rate_ids);
        }

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            if (!$isAdmin) {
                // Find the actual first rate ID to use as entity_id in approval_requests
                if (!empty($po_number)) {
                    $stmtId = $pdo->prepare("SELECT id FROM client_printing_rates WHERE po_number = ? LIMIT 1");
                    $stmtId->execute([$po_number]);
                    $rateId = $stmtId->fetchColumn();
                    $actualPoNum = $po_number;
                } else {
                    $rateId = $rate_ids[0];
                    $stmtPoNum = $pdo->prepare("SELECT po_number FROM client_printing_rates WHERE id = ?");
                    $stmtPoNum->execute([$rateId]);
                    $actualPoNum = $stmtPoNum->fetchColumn();
                }
                if ($rateId) {
                    // Delete old pending request if any
                    $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'client_printing' AND entity_id = ? AND status = 'pending'")->execute([$rateId]);
                    $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('client_printing', ?, ?, ?, 'pending')");
                    $stmtAR->execute([$rateId, $actualPoNum, $_SESSION['user_id'] ?? 0]);
                }
            }
            echo json_encode(['success' => true, 'approval_status' => $approvalStatus]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database Update Failed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'PHP Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()]);
    }
}
?>
