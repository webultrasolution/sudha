<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/upload_validator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check permission
        if (!canView('vendors_printing_po')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access Denied']);
            exit;
        }

        $po_number = trim($_POST['po_number'] ?? '');
        $invoice_no = isset($_POST['invoice_no']) ? trim($_POST['invoice_no']) : null;
        $invoice_date = isset($_POST['invoice_date']) && $_POST['invoice_date'] !== '' ? trim($_POST['invoice_date']) : null;
        $invoice_amount = isset($_POST['invoice_amount']) && $_POST['invoice_amount'] !== '' ? floatval($_POST['invoice_amount']) : null;

        if (!$po_number) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid PO number']);
            exit;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Upload error or no file']);
            exit;
        }

        // Validate file
        $ext = UploadValidator::validate($_FILES['file']);

        // Ensure upload directory is secure
        $uploadDir = __DIR__ . '/../uploads/pos/';
        UploadValidator::ensureUploadDir($uploadDir);

        // Generate safe filename
        $baseFilename = UploadValidator::generateSafeFilename($_FILES['file']['name'], $ext);
        $filename = 'print_' . $baseFilename;
        $targetFile = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save file']);
            exit;
        }

        // Update database - vendor_printing_rates
        $stmt = $pdo->prepare("SELECT attachments FROM vendor_printing_rates WHERE po_number = ? LIMIT 1");
        $stmt->execute([$po_number]);
        $existing = $stmt->fetchColumn();

        $newAttachments = $existing ? $existing . '||' . $filename : $filename;

        // Only update invoice details in vendor_printing_rates if they are passed
        if ($invoice_no !== null && $invoice_date !== null) {
            $updateStmt = $pdo->prepare("UPDATE vendor_printing_rates SET attachments = ?, vendor_invoice_no = ?, vendor_invoice_date = ? WHERE po_number = ?");
            $updateStmt->execute([$newAttachments, $invoice_no, $invoice_date, $po_number]);
        } else {
            $updateStmt = $pdo->prepare("UPDATE vendor_printing_rates SET attachments = ? WHERE po_number = ?");
            $updateStmt->execute([$newAttachments, $po_number]);
        }

        // Only update invoice details in purchase_orders if they are passed
        if ($invoice_no !== null && $invoice_date !== null && $invoice_amount !== null) {
            $updatePO = $pdo->prepare("UPDATE purchase_orders SET vendor_invoice_no = ?, vendor_invoice_date = ?, vendor_invoice_amount = ?, vendor_invoice_approval_status = 'approved' WHERE po_number = ?");
            $updatePO->execute([$invoice_no, $invoice_date, $invoice_amount, $po_number]);
        }

        echo json_encode(['success' => true, 'filename' => $filename]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
