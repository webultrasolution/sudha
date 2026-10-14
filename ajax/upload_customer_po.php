<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/upload_validator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!canEdit('bookings') && !canAdd('financials')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to confirm bookings or generate invoices.']);
            exit;
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $type = clean($_POST['confirmation_type'] ?? 'po');
        $po_no = clean($_POST['customer_po_no'] ?? '');
        $po_date = clean($_POST['customer_po_date'] ?? '');
        $email_date = clean($_POST['email_date'] ?? '');
        $billing_gstin = clean($_POST['billing_gstin'] ?? '');
        $invoice_type = clean($_POST['invoice_type'] ?? 'tax');

        if (!in_array($invoice_type, ['tax', 'ro'])) {
            $invoice_type = 'tax';
        }

        if (!$booking_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Booking ID']);
            exit;
        }

        $uploadedPaths = [];
        $fileKey = isset($_FILES['customer_po_file']) ? 'customer_po_file' : (isset($_FILES['customer_po_files']) ? 'customer_po_files' : null);

        if ($fileKey && !empty($_FILES[$fileKey]['name'])) {
            $upload_dir = __DIR__ . '/../uploads/customer_pos/';
            UploadValidator::ensureUploadDir($upload_dir);
            $prefix = ($type === 'email') ? 'EMAIL_' : 'PO_';

            $names = is_array($_FILES[$fileKey]['name']) ? $_FILES[$fileKey]['name'] : [$_FILES[$fileKey]['name']];
            $tmpNames = is_array($_FILES[$fileKey]['tmp_name']) ? $_FILES[$fileKey]['tmp_name'] : [$_FILES[$fileKey]['tmp_name']];
            $sizes = is_array($_FILES[$fileKey]['size']) ? $_FILES[$fileKey]['size'] : [$_FILES[$fileKey]['size']];
            $errors = is_array($_FILES[$fileKey]['error']) ? $_FILES[$fileKey]['error'] : [$_FILES[$fileKey]['error']];

            for ($i = 0; $i < count($names); $i++) {
                if ($errors[$i] !== UPLOAD_ERR_OK || empty($tmpNames[$i])) {
                    continue;
                }

                $singleFile = [
                    'name'     => $names[$i],
                    'tmp_name' => $tmpNames[$i],
                    'size'     => $sizes[$i],
                    'error'    => $errors[$i]
                ];

                $ext = UploadValidator::validate($singleFile);
                $safeFilename = $prefix . $booking_id . '_' . time() . '_' . $i . '.' . $ext;
                $target = $upload_dir . $safeFilename;

                if (move_uploaded_file($tmpNames[$i], $target)) {
                    $uploadedPaths[] = 'uploads/customer_pos/' . $safeFilename;
                } else {
                    throw new Exception('Failed to save file: ' . htmlspecialchars($names[$i]));
                }
            }
        }

        // Fetch current attachments to merge
        $stmtCurr = $pdo->prepare("SELECT customer_po_file FROM bookings WHERE id = ?");
        $stmtCurr->execute([$booking_id]);
        $currentPoFile = $stmtCurr->fetchColumn();
        $existingPaths = getAttachmentUrls($currentPoFile);

        $mergedPaths = array_values(array_unique(array_merge($existingPaths, $uploadedPaths)));
        $file_path = null;
        if (!empty($uploadedPaths)) {
            $file_path = count($mergedPaths) > 1 ? json_encode($mergedPaths) : ($mergedPaths[0] ?? null);
        }

    $sql = "UPDATE bookings SET confirmation_type = ?, customer_po_no = ?, customer_po_date = ?, email_date = ?" . ($file_path !== null ? ", customer_po_file = ?" : "");
    $params = [$type, $po_no ?: null, $po_date ?: null, $email_date ?: null];
    if ($file_path !== null) $params[] = $file_path;
    
    if (!empty($billing_gstin)) {
        $sql .= ", billing_gstin = ?";
        $params[] = $billing_gstin;
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $booking_id;

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        // Automatically create record in 'invoices' table if it doesn't exist
        $checkInvoice = $pdo->prepare("SELECT id, approval_status FROM invoices WHERE booking_id = ? AND type = ?");
        $checkInvoice->execute([$booking_id, $invoice_type]);
        $existing = $checkInvoice->fetch();

        if (session_status() === PHP_SESSION_NONE) session_start();
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

        if (!$existing) {
            // Fetch booking totals for the invoice record
            $stmtBooking = $pdo->prepare("SELECT total_amount, tax_amount, grand_total, tax_type FROM bookings WHERE id = ?");
            $stmtBooking->execute([$booking_id]);
            $bookingData = $stmtBooking->fetch();
            
            $entityId = $_SESSION['active_entity_id'] ?? null;
            if (!$entityId) {
                $stmt = $pdo->query("SELECT id FROM entities LIMIT 1");
                $entityId = $stmt->fetchColumn() ?: null;
            }

            $invNo = clean($_POST['custom_invoice_number'] ?? '');
            if (empty($invNo)) {
                echo json_encode(['success' => false, 'message' => 'Invoice Number is mandatory.']);
                exit;
            }
            
            if ($invoice_type === 'tax') {
                syncSequenceNextValue($pdo, 'invoice', $invNo, $entityId);
            }
            
            $custom_invoice_date = clean($_POST['custom_invoice_date'] ?? date('Y-m-d'));
            $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';
            
            $tax_amount = floatval($bookingData['tax_amount'] ?? 0);
            $tax_type = $bookingData['tax_type'] ?? 'igst';
            
            $cgst = 0;
            $sgst = 0;
            $igst = 0;
            
            if ($tax_type === 'cgst_sgst') {
                $cgst = $tax_amount / 2;
                $sgst = $tax_amount / 2;
            } else {
                $igst = $tax_amount;
            }
            
            $stmtInsert = $pdo->prepare("INSERT INTO invoices (invoice_number, booking_id, entity_id, type, sub_total, cgst, sgst, igst, total_amount, payment_status, approval_status, invoice_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?, ?)");
            $stmtInsert->execute([$invNo, $booking_id, $entityId, $invoice_type, $bookingData['total_amount'], $cgst, $sgst, $igst, $bookingData['grand_total'], $approvalStatus, $custom_invoice_date]);
            
            $invoiceId = $pdo->lastInsertId();
            
            if (!$isAdmin) {
                $userId = $_SESSION['user_id'] ?? 0;
                $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('invoice', ?, ?, ?, 'pending')");
                $stmtAR->execute([$invoiceId, $invNo, $userId]);
            }
        } else {
            $approvalStatus = $existing['approval_status'];
            if ($isAdmin || $approvalStatus !== 'approved') {
                if ($isAdmin) {
                    $approvalStatus = 'approved';
                }
                
                $entityId = $_SESSION['active_entity_id'] ?? null;
                $custom_invoice_number = clean($_POST['custom_invoice_number'] ?? '');
                $custom_invoice_date = clean($_POST['custom_invoice_date'] ?? date('Y-m-d'));
                
                if (!empty($custom_invoice_number) && $invoice_type === 'tax') {
                    syncSequenceNextValue($pdo, 'invoice', $custom_invoice_number, $entityId);
                }
                
                // Fetch booking totals to update the invoice
                $stmtBooking = $pdo->prepare("SELECT total_amount, tax_amount, grand_total, tax_type FROM bookings WHERE id = ?");
                $stmtBooking->execute([$booking_id]);
                $bookingData = $stmtBooking->fetch();
                
                $tax_amount = floatval($bookingData['tax_amount'] ?? 0);
                $tax_type = $bookingData['tax_type'] ?? 'igst';
                
                $cgst = 0;
                $sgst = 0;
                $igst = 0;
                
                if ($tax_type === 'cgst_sgst') {
                    $cgst = $tax_amount / 2;
                    $sgst = $tax_amount / 2;
                } else {
                    $igst = $tax_amount;
                }
                
                // Update existing invoice
                if (!empty($custom_invoice_number)) {
                    $stmtUpdate = $pdo->prepare("UPDATE invoices SET invoice_number = ?, sub_total = ?, cgst = ?, sgst = ?, igst = ?, total_amount = ?, approval_status = ?, invoice_date = ? WHERE id = ?");
                    $stmtUpdate->execute([$custom_invoice_number, $bookingData['total_amount'], $cgst, $sgst, $igst, $bookingData['grand_total'], $approvalStatus, $custom_invoice_date, $existing['id']]);
                } else {
                    $stmtUpdate = $pdo->prepare("UPDATE invoices SET sub_total = ?, cgst = ?, sgst = ?, igst = ?, total_amount = ?, approval_status = ?, invoice_date = ? WHERE id = ?");
                    $stmtUpdate->execute([$bookingData['total_amount'], $cgst, $sgst, $igst, $bookingData['grand_total'], $approvalStatus, $custom_invoice_date, $existing['id']]);
                }
                
                if (!$isAdmin && $approvalStatus !== 'approved') {
                    $userId = $_SESSION['user_id'] ?? 0;
                    
                    // Check if an approval request already exists
                    $checkAR = $pdo->prepare("SELECT id FROM approval_requests WHERE entity_type = 'invoice' AND entity_id = ?");
                    $checkAR->execute([$existing['id']]);
                    $arExists = $checkAR->fetchColumn();
                    
                    if ($arExists) {
                        $stmtAR = $pdo->prepare("UPDATE approval_requests SET status = 'pending', requested_by = ?, reviewed_by = NULL, reviewed_at = NULL, remarks = NULL WHERE id = ?");
                        $stmtAR->execute([$userId, $arExists]);
                    } else {
                        // Get the invoice number to use as ref
                        $invNo = $pdo->query("SELECT invoice_number FROM invoices WHERE id = {$existing['id']}")->fetchColumn();
                        $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('invoice', ?, ?, ?, 'pending')");
                        $stmtAR->execute([$existing['id'], $invNo, $userId]);
                    }
                }
            }
            syncBookingInvoiceFinancials($pdo, $booking_id);
        }
        
        echo json_encode(['success' => true, 'approval_status' => $approvalStatus, 'is_admin' => $isAdmin]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Update Failed']);
    }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
