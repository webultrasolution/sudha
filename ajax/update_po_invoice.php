<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to update PO invoice.']);
        exit;
    }
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $no = isset($_POST['no']) ? clean($_POST['no']) : '';
    $date = isset($_POST['date']) ? clean($_POST['date']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;

    if (!$id || empty($no) || empty($date) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters. Please provide invoice number, date and amount.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Fetch PO Number to use as ref
        $stmtPO = $pdo->prepare("SELECT po_number FROM purchase_orders WHERE id = ?");
        $stmtPO->execute([$id]);
        $poNumber = $stmtPO->fetchColumn();
        if (!$poNumber) {
            throw new Exception("Purchase Order not found.");
        }

        // Enforce that at least 1 attachment exists for this PO
        $attCount = $pdo->prepare("SELECT COUNT(*) FROM po_attachments WHERE po_id = ?");
        $attCount->execute([$id]);
        if (intval($attCount->fetchColumn()) === 0) {
            throw new Exception("Upload Attachment(s) is required. Please upload at least one invoice document/file before saving.");
        }

        // 2. Determine approval status based on role (Admin auto-approves)
        $isAdmin = (($_SESSION['user_role'] ?? '') === 'admin');
        
        // 3. Update PO details and status
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
            $stmt->execute([$no, $date, $amount, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE purchase_orders 
                SET vendor_invoice_no = ?, 
                    vendor_invoice_date = ?, 
                    vendor_invoice_amount = ?, 
                    vendor_invoice_approval_status = 'pending' 
                WHERE id = ?
            ");
            $stmt->execute([$no, $date, $amount, $id]);
        }

        // 4. Create or Refresh approval request
        $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'vendor_invoice' AND entity_id = ?")->execute([$id]);
        
        if (!$isAdmin) {
            $stmtAR = $pdo->prepare("
                INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) 
                VALUES ('vendor_invoice', ?, ?, ?, 'pending')
            ");
            $stmtAR->execute([$id, $poNumber, $_SESSION['user_id'] ?? 0]);
            logActivity('submitted vendor invoice for approval', 'purchase_orders', $id, "PO: $poNumber, Invoice: $no, Amount: ₹" . number_format($amount, 2));
        } else {
            logActivity('approved vendor invoice (auto)', 'purchase_orders', $id, "PO: $poNumber, Invoice: $no, Amount: ₹" . number_format($amount, 2));
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
