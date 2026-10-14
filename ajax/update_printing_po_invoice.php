<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('vendors')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_invoice') {
        $po_number = isset($_POST['po_number']) ? clean($_POST['po_number']) : '';
        if (empty($po_number)) {
            echo json_encode(['success' => false, 'message' => 'PO number missing']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            // Reset vendor_printing_rates
            $stmtRates = $pdo->prepare("UPDATE vendor_printing_rates SET vendor_invoice_no = NULL, vendor_invoice_date = NULL WHERE po_number = ?");
            $stmtRates->execute([$po_number]);
            
            // Reset purchase_orders
            $stmtPO = $pdo->prepare("UPDATE purchase_orders SET vendor_invoice_no = NULL, vendor_invoice_date = NULL, vendor_invoice_amount = NULL, vendor_invoice_approval_status = NULL WHERE po_number = ?");
            $stmtPO->execute([$po_number]);
            
            // Clean pending approval requests
            $stmtPOId = $pdo->prepare("SELECT id FROM purchase_orders WHERE po_number = ? LIMIT 1");
            $stmtPOId->execute([$po_number]);
            $poId = $stmtPOId->fetchColumn();
            if ($poId) {
                $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'vendor_invoice' AND entity_id = ?")->execute([$poId]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    $po_number = isset($_POST['po_number']) ? clean($_POST['po_number']) : '';
    $no = isset($_POST['no']) ? clean($_POST['no']) : '';
    $date = isset($_POST['date']) ? clean($_POST['date']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;

    if (empty($po_number) || empty($no) || empty($date) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters. Please provide invoice number, date and amount.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Fetch PO ID from purchase_orders
        $stmtPO = $pdo->prepare("SELECT id FROM purchase_orders WHERE po_number = ? LIMIT 1");
        $stmtPO->execute([$po_number]);
        $poId = $stmtPO->fetchColumn();
        if (!$poId) {
            throw new Exception("Purchase Order not found.");
        }

        $isAdmin = (($_SESSION['user_role'] ?? '') === 'admin');

        // Update vendor_printing_rates
        $stmtRates = $pdo->prepare("UPDATE vendor_printing_rates SET vendor_invoice_no = ?, vendor_invoice_date = ? WHERE po_number = ?");
        $stmtRates->execute([$no, $date, $po_number]);

        // Update purchase_orders
        if ($isAdmin) {
            $stmt = $pdo->prepare("
                UPDATE purchase_orders 
                SET vendor_invoice_no = ?, 
                    vendor_invoice_date = ?, 
                    vendor_invoice_amount = ?, 
                    vendor_invoice_approval_status = 'approved',
                    approval_status = 'approved',
                    status = 'approved'
                WHERE id = ?
            ");
            $stmt->execute([$no, $date, $amount, $poId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE purchase_orders 
                SET vendor_invoice_no = ?, 
                    vendor_invoice_date = ?, 
                    vendor_invoice_amount = ?, 
                    vendor_invoice_approval_status = 'pending' 
                WHERE id = ?
            ");
            $stmt->execute([$no, $date, $amount, $poId]);
        }

        // Approval request
        $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'vendor_invoice' AND entity_id = ?")->execute([$poId]);
        
        if (!$isAdmin) {
            $stmtAR = $pdo->prepare("
                INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) 
                VALUES ('vendor_invoice', ?, ?, ?, 'pending')
            ");
            $stmtAR->execute([$poId, $po_number, $_SESSION['user_id'] ?? 0]);
            logActivity('submitted vendor invoice for approval (printing)', 'purchase_orders', $poId, "PO: $po_number, Invoice: $no, Amount: Rs " . number_format($amount, 2));
        } else {
            logActivity('approved vendor invoice (auto - printing)', 'purchase_orders', $poId, "PO: $po_number, Invoice: $no, Amount: Rs " . number_format($amount, 2));
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'auto_approved' => $isAdmin]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
