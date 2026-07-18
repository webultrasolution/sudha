<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canAdd('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to record payments.']);
        exit;
    }
    file_put_contents(__DIR__ . '/../pay_debug.log', date('Y-m-d H:i:s') . ' - POST: ' . print_r($_POST, true) . PHP_EOL, FILE_APPEND);
    
    $partner_id = intval($_POST['client_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $date = clean($_POST['payment_date'] ?? date('Y-m-d'));
    $ref = clean($_POST['reference_no'] ?? '');
    $notes = clean($_POST['notes'] ?? '');
    $type = clean($_POST['type'] ?? 'receivable'); 
    $mode = clean($_POST['payment_mode'] ?? 'NEFT');
    $doc_id = !empty($_POST['doc_id']) ? intval($_POST['doc_id']) : null;
    
    if (!$partner_id || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Partner or Amount']);
        exit;
    }

    $db_type = ($type === 'receivable') ? 'receivable' : 'payable';
    $invoice_id = ($type === 'receivable') ? $doc_id : null;
    $proposal_id = ($type === 'payable') ? $doc_id : null;

    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    $userId = $_SESSION['user_id'] ?? 0;
    $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

    $entityId = null;
    if ($type === 'receivable' && $invoice_id) {
        $stmtEnt = $pdo->prepare("SELECT entity_id FROM invoices WHERE id = ?");
        $stmtEnt->execute([$invoice_id]);
        $entityId = $stmtEnt->fetchColumn() ?: null;
    } elseif ($type === 'payable' && $proposal_id) {
        $stmtEnt = $pdo->prepare("SELECT entity_id FROM purchase_orders WHERE id = ?");
        $stmtEnt->execute([$proposal_id]);
        $entityId = $stmtEnt->fetchColumn() ?: null;
    }
    
    if (!$entityId) {
        $entityId = $_SESSION['active_entity_id'] ?? null;
    }
    if (!$entityId) {
        $stmtEnt = $pdo->query("SELECT id FROM entities LIMIT 1");
        $entityId = $stmtEnt->fetchColumn() ?: null;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO payments (partner_id, entity_id, amount, payment_date, payment_mode, transaction_id, type, invoice_id, proposal_id, notes, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $params = [
            $partner_id, 
            $entityId,
            $amount, 
            $date, 
            $mode, 
            $ref, 
            $db_type, 
            $invoice_id, 
            $proposal_id, 
            $notes,
            $approvalStatus
        ];

        if ($stmt->execute($params)) {
            $paymentId = $pdo->lastInsertId();
            file_put_contents(__DIR__ . '/../pay_debug.log', "INSERT Success. ID: " . $paymentId . PHP_EOL, FILE_APPEND);
            
            if (!$isAdmin) {
                // Insert approval request
                $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('payment', ?, ?, ?, 'pending')");
                $refName = $db_type === 'receivable' ? "Cust Pmt #$paymentId" : "Vendor Pmt #$paymentId";
                $stmtAR->execute([$paymentId, $refName, $userId]);
            }
            
            // Recalculate status if approved (when user is admin)
            if ($isAdmin) {
                file_put_contents(__DIR__ . '/../pay_debug.log', "Recalculating statuses: Inv=$invoice_id, PO=$proposal_id" . PHP_EOL, FILE_APPEND);
                updateDocumentPaymentStatus($pdo, $invoice_id, $proposal_id);
            }
            
            echo json_encode(['success' => true, 'approval_status' => $approvalStatus]);
        } else {
            $err = $stmt->errorInfo();
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $err[2]]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
    }
}
?>
