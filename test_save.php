<?php
include_once __DIR__ . '/config/db.php';

try {
    $partner_id = 2;
    $amount = 232;
    $date = '2026-05-11';
    $mode = 'NEFT';
    $ref = '';
    $db_type = 'credit';
    $invoice_id = 3;
    $proposal_id = null;
    $notes = '';

    $stmt = $pdo->prepare("INSERT INTO payments (partner_id, amount, payment_date, payment_mode, transaction_id, type, invoice_id, proposal_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $params = [$partner_id, $amount, $date, $mode, $ref, $db_type, $invoice_id, $proposal_id, $notes];

    if ($stmt->execute($params)) {
        echo "Success!";
    } else {
        print_r($stmt->errorInfo());
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
