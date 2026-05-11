<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $db_type = ($type === 'receivable') ? 'credit' : 'debit';
    $invoice_id = ($type === 'receivable') ? $doc_id : null;
    $proposal_id = ($type === 'payable') ? $doc_id : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO payments (partner_id, amount, payment_date, payment_mode, transaction_id, type, invoice_id, proposal_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $params = [
            $partner_id, 
            $amount, 
            $date, 
            $mode, 
            $ref, 
            $db_type, 
            $invoice_id, 
            $proposal_id, 
            $notes
        ];

        if ($stmt->execute($params)) {
            // Update Invoice Status if linked
            if ($invoice_id) {
                $paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE invoice_id = ?");
                $paidStmt->execute([$invoice_id]);
                $totalPaid = floatval($paidStmt->fetchColumn());

                $invStmt = $pdo->prepare("SELECT total_amount FROM invoices WHERE id = ?");
                $invStmt->execute([$invoice_id]);
                $invTotal = floatval($invStmt->fetchColumn());

                $status = ($totalPaid >= $invTotal) ? 'paid' : 'partially_paid';
                $upd = $pdo->prepare("UPDATE invoices SET payment_status = ? WHERE id = ?");
                $upd->execute([$status, $invoice_id]);
            }
            echo json_encode(['success' => true]);
        } else {
            $err = $stmt->errorInfo();
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $err[2]]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
    }
}
?>
