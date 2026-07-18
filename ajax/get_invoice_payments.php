<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$ref = $_GET['ref'] ?? '';
if (empty($ref)) {
    echo json_encode(['success' => false, 'message' => 'Reference number is required.']);
    exit;
}

try {
    // 1. Search in invoices (Client side)
    $stmt = $pdo->prepare("SELECT id, total_amount, invoice_number FROM invoices WHERE invoice_number = ?");
    $stmt->execute([$ref]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($inv) {
        $stmtPay = $pdo->prepare("
            SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.transaction_id, p.notes, p.approval_status, COALESCE(p.rejection_reason, ar.remarks) as rejection_reason
            FROM payments p
            LEFT JOIN approval_requests ar ON (ar.entity_type = 'payment' AND ar.entity_id = p.id)
            WHERE p.invoice_id = ?
            ORDER BY p.payment_date ASC
        ");
        $stmtPay->execute([$inv['id']]);
        $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

        $stmtPayNotes = $pdo->prepare("
            SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.transaction_id, p.notes, p.approval_status, COALESCE(p.rejection_reason, ar.remarks) as rejection_reason
            FROM payments p
            LEFT JOIN approval_requests ar ON (ar.entity_type = 'payment' AND ar.entity_id = p.id)
            WHERE p.invoice_id IS NULL AND p.notes LIKE ?
            ORDER BY p.payment_date ASC
        ");
        $stmtPayNotes->execute(['%' . $ref . '%']);
        $notePayments = $stmtPayNotes->fetchAll(PDO::FETCH_ASSOC);
        
        $merged = array_merge($payments, $notePayments);
        // Remove duplicates if any
        $uniquePayments = [];
        $ids = [];
        foreach ($merged as $p) {
            if (!in_array($p['id'], $ids)) {
                $ids[] = $p['id'];
                $uniquePayments[] = $p;
            }
        }
        
        // Sort by date
        usort($uniquePayments, function($a, $b) {
            return strtotime($a['payment_date']) - strtotime($b['payment_date']);
        });

        echo json_encode([
            'success' => true,
            'doc_type' => 'Invoice',
            'doc_number' => $inv['invoice_number'],
            'doc_total' => floatval($inv['total_amount']),
            'payments' => $uniquePayments
        ]);
        exit;
    }

    // 2. Search in purchase_orders (Vendor side)
    $stmtPO = $pdo->prepare("SELECT id, total_amount, po_number FROM purchase_orders WHERE po_number = ?");
    $stmtPO->execute([$ref]);
    $po = $stmtPO->fetch(PDO::FETCH_ASSOC);
    if ($po) {
        // Fetch payments made against this PO
        $stmtPay = $pdo->prepare("
            SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.transaction_id, p.notes, p.approval_status, COALESCE(p.rejection_reason, ar.remarks) as rejection_reason
            FROM payments p
            LEFT JOIN approval_requests ar ON (ar.entity_type = 'payment' AND ar.entity_id = p.id)
            WHERE p.proposal_id = ?
            ORDER BY p.payment_date ASC
        ");
        $stmtPay->execute([$po['id']]);
        $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

        // Fallback: Also search notes for "Against <po_number>"
        $stmtPayNotes = $pdo->prepare("
            SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.transaction_id, p.notes, p.approval_status, COALESCE(p.rejection_reason, ar.remarks) as rejection_reason
            FROM payments p
            LEFT JOIN approval_requests ar ON (ar.entity_type = 'payment' AND ar.entity_id = p.id)
            WHERE p.proposal_id IS NULL AND p.notes LIKE ?
            ORDER BY p.payment_date ASC
        ");
        $stmtPayNotes->execute(['%' . $ref . '%']);
        $notePayments = $stmtPayNotes->fetchAll(PDO::FETCH_ASSOC);
        
        $merged = array_merge($payments, $notePayments);
        $uniquePayments = [];
        $ids = [];
        foreach ($merged as $p) {
            if (!in_array($p['id'], $ids)) {
                $ids[] = $p['id'];
                $uniquePayments[] = $p;
            }
        }
        usort($uniquePayments, function($a, $b) {
            return strtotime($a['payment_date']) - strtotime($b['payment_date']);
        });

        echo json_encode([
            'success' => true,
            'doc_type' => 'Purchase Order',
            'doc_number' => $po['po_number'],
            'doc_total' => floatval($po['total_amount']),
            'payments' => $uniquePayments
        ]);
        exit;
    }

    // 3. Fallback: Search all payments whose notes mention this ref
    $stmtNotes = $pdo->prepare("
        SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.transaction_id, p.notes, p.approval_status, COALESCE(p.rejection_reason, ar.remarks) as rejection_reason
        FROM payments p
        LEFT JOIN approval_requests ar ON (ar.entity_type = 'payment' AND ar.entity_id = p.id)
        WHERE p.notes LIKE ?
        ORDER BY p.payment_date ASC
    ");
    $stmtNotes->execute(['%' . $ref . '%']);
    $payments = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($payments)) {
        echo json_encode([
            'success' => true,
            'doc_type' => 'Reference',
            'doc_number' => $ref,
            'doc_total' => null,
            'payments' => $payments
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'No payments found for this reference.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
