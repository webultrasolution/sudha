<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$amount = floatval($_POST['amount'] ?? 0);
$date = clean($_POST['payment_date'] ?? date('Y-m-d'));
$ref = clean($_POST['reference_no'] ?? '');
$notes = clean($_POST['notes'] ?? '');
$mode = clean($_POST['payment_mode'] ?? 'NEFT');

if (!$id || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID or Amount']);
    exit;
}

try {
    // Fetch original payment to get invoice_id or proposal_id
    $stmtOrig = $pdo->prepare("SELECT invoice_id, proposal_id, approval_status FROM payments WHERE id = ?");
    $stmtOrig->execute([$id]);
    $orig = $stmtOrig->fetch(PDO::FETCH_ASSOC);
    if (!$orig) {
        echo json_encode(['success' => false, 'message' => 'Payment record not found']);
        exit;
    }

    if (!canEdit('financials')) {
        if ($orig['approval_status'] === 'approved') {
            echo json_encode(['success' => false, 'message' => 'Access Denied: Approved payments can only be edited by authorized staff.']);
            exit;
        }
    }

    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    $userId = $_SESSION['user_id'] ?? 0;
    
    // If not admin, we might need approval, but for updates let's keep it simple:
    // If it's already approved, editing it might set it back to pending_approval or just update.
    // Let's align with the save_payment logic: admin changes take effect immediately.
    // Non-admin changes could go to pending_approval or require admin.
    // Let's check how save_payment handles it: non-admin saves go to pending_approval.
    // Let's set approval_status to 'approved' for admin, or 'pending_approval' for non-admin.
    $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

    $stmt = $pdo->prepare("
        UPDATE payments 
        SET amount = ?, payment_date = ?, payment_mode = ?, transaction_id = ?, notes = ?, approval_status = ? 
        WHERE id = ?
    ");
    
    if ($stmt->execute([$amount, $date, $mode, $ref, $notes, $approvalStatus, $id])) {
        if ($approvalStatus === 'approved') {
            updateDocumentPaymentStatus($pdo, $orig['invoice_id'], $orig['proposal_id']);
        } else {
            // Reset approval request back to pending
            $stmtAR = $pdo->prepare("UPDATE approval_requests SET status = 'pending', reviewed_by = NULL, reviewed_at = NULL, remarks = NULL WHERE entity_type = 'payment' AND entity_id = ?");
            $stmtAR->execute([$id]);
        }

        echo json_encode(['success' => true, 'approval_status' => $approvalStatus]);
    } else {
        $err = $stmt->errorInfo();
        echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $err[2]]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
