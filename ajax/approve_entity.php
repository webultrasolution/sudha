<?php
/**
 * approve_entity.php
 * Centralized endpoint for admin to approve or reject any entity.
 * Supports: proposal, purchase_order, booking, invoice
 */
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// Only admin can approve/reject
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied. Only admin can approve or reject.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$entityType = $data['entity_type'] ?? '';
$entityId   = intval($data['entity_id'] ?? 0);
$action     = $data['action'] ?? ''; // 'approve' or 'reject'
$reason     = trim($data['reason'] ?? '');
$adminId    = $_SESSION['user_id'];

if (!$entityId && $entityType === 'client_printing') {
    // Note: for client printing, entityId is passed as po_number string in the payload from approvals.php, so we handle it below
} elseif (!$entityId && $entityType !== 'client_printing') {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

if (!in_array($action, ['approve', 'reject']) || !in_array($entityType, ['proposal', 'purchase_order', 'booking', 'invoice', 'client_printing'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

$newApprovalStatus = ($action === 'approve') ? 'approved' : 'rejected';

try {
    $pdo->beginTransaction();

    switch ($entityType) {

        case 'proposal':
            // When approved: set proposal status to 'sent'
            // When rejected: keep status as 'draft'
            $newStatus = ($action === 'approve') ? 'sent' : 'draft';
            $stmt = $pdo->prepare("UPDATE proposals SET approval_status = ?, status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
            $stmt->execute([$newApprovalStatus, $newStatus, $adminId, $reason ?: null, $entityId]);
            $ref = $pdo->query("SELECT proposal_number FROM proposals WHERE id = $entityId")->fetchColumn();
            break;

        case 'purchase_order':
            // When approved: set PO status to 'approved'
            // When rejected: set PO status to 'cancelled'
            $newStatus = ($action === 'approve') ? 'approved' : 'cancelled';
            $stmt = $pdo->prepare("UPDATE purchase_orders SET approval_status = ?, status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
            $stmt->execute([$newApprovalStatus, $newStatus, $adminId, $reason ?: null, $entityId]);
            $ref = $pdo->query("SELECT po_number FROM purchase_orders WHERE id = $entityId")->fetchColumn();
            break;

        case 'booking':
            // When approved: set booking status to 'active'
            // When rejected: set booking status to 'cancelled'
            $newStatus = ($action === 'approve') ? 'active' : 'cancelled';
            $stmt = $pdo->prepare("UPDATE bookings SET approval_status = ?, status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
            $stmt->execute([$newApprovalStatus, $newStatus, $adminId, $reason ?: null, $entityId]);
            $ref = "Booking #$entityId";
            break;

        case 'invoice':
            $stmt = $pdo->prepare("UPDATE invoices SET approval_status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
            $stmt->execute([$newApprovalStatus, $adminId, $reason ?: null, $entityId]);
            $ref = $pdo->query("SELECT invoice_number FROM invoices WHERE id = $entityId")->fetchColumn();
            break;

        case 'client_printing':
            // In approvals.php we pass po_number as entityId string for client_printing
            $poNumber = $data['entity_id'] ?? '';
            $stmt = $pdo->prepare("UPDATE client_printing_rates SET approval_status = ? WHERE po_number = ?");
            $stmt->execute([$newApprovalStatus, $poNumber]);
            $ref = $poNumber;
            // Get the actual first ID to update approval_requests
            $rateId = $pdo->query("SELECT id FROM client_printing_rates WHERE po_number = '$poNumber' LIMIT 1")->fetchColumn();
            $entityId = $rateId; 
            break;

        default:
            throw new Exception("Unknown entity type: $entityType");
    }

    // Update the approval_requests record
    $stmtAR = $pdo->prepare("UPDATE approval_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), remarks = ? WHERE entity_type = ? AND entity_id = ?");
    $stmtAR->execute([$newApprovalStatus, $adminId, $reason ?: null, $entityType, $entityId]);

    $actionLabel = ($action === 'approve') ? 'approved' : 'rejected';
    logActivity("$actionLabel $entityType", $entityType . 's', $entityId, "Admin $actionLabel: " . ($ref ?? "#$entityId") . ($reason ? ". Reason: $reason" : ''));

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => ucfirst($entityType) . ' ' . $actionLabel . ' successfully.',
        'new_status' => $newApprovalStatus
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
