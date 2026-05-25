<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit('bookings') && !canAdd('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to confirm bookings or generate invoices.']);
        exit;
    }
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $type = clean($_POST['confirmation_type'] ?? 'po');
    $po_no = clean($_POST['customer_po_no'] ?? '');
    $po_date = clean($_POST['customer_po_date'] ?? '');
    $email_date = clean($_POST['email_date'] ?? '');
    $billing_gstin = clean($_POST['billing_gstin'] ?? '');
    
    if (!$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid Booking ID']);
        exit;
    }

    $file_path = null;
    if (isset($_FILES['customer_po_file']) && $_FILES['customer_po_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/customer_pos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extension = pathinfo($_FILES['customer_po_file']['name'], PATHINFO_EXTENSION);
        $prefix = ($type === 'email') ? 'EMAIL_' : 'PO_';
        $filename = $prefix . $booking_id . '_' . time() . '.' . $extension;
        $target = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['customer_po_file']['tmp_name'], $target)) {
            $file_path = 'uploads/customer_pos/' . $filename;
        }
    }

    $sql = "UPDATE bookings SET confirmation_type = ?, customer_po_no = ?, customer_po_date = ?, email_date = ?" . ($file_path ? ", customer_po_file = ?" : "");
    $params = [$type, $po_no ?: null, $po_date ?: null, $email_date ?: null];
    if ($file_path) $params[] = $file_path;
    
    if (!empty($billing_gstin)) {
        $sql .= ", billing_gstin = ?";
        $params[] = $billing_gstin;
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $booking_id;

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        // Automatically create record in 'invoices' table if it doesn't exist
        $checkInvoice = $pdo->prepare("SELECT id, approval_status FROM invoices WHERE booking_id = ?");
        $checkInvoice->execute([$booking_id]);
        $existing = $checkInvoice->fetch();

        if (session_status() === PHP_SESSION_NONE) session_start();
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

        if (!$existing) {
            // Fetch booking totals for the invoice record
            $stmtBooking = $pdo->prepare("SELECT total_amount, grand_total FROM bookings WHERE id = ?");
            $stmtBooking->execute([$booking_id]);
            $bookingData = $stmtBooking->fetch();
            
            $custom_invoice_number = clean($_POST['custom_invoice_number'] ?? '');
            if (!empty($custom_invoice_number)) {
                $invNo = $custom_invoice_number;
            } else {
                // Calculate Indian Financial Year (Apr to Mar)
                $currentMonth = (int)date('m');
                $currentYearShort = (int)date('y');
                
                if ($currentMonth >= 4) {
                    $fy = $currentYearShort . '-' . str_pad($currentYearShort + 1, 2, '0', STR_PAD_LEFT);
                } else {
                    $fy = str_pad($currentYearShort - 1, 2, '0', STR_PAD_LEFT) . '-' . $currentYearShort;
                }
                
                $prefix = 'SCR/' . $fy . '/';
                
                // Fetch highest serial number for this financial year
                $stmtMax = $pdo->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
                $stmtMax->execute([$prefix . '%']);
                $lastInvoice = $stmtMax->fetchColumn();
                
                if ($lastInvoice) {
                    $parts = explode('/', $lastInvoice);
                    $lastNum = (int)end($parts);
                    $nextNum = $lastNum + 1;
                } else {
                    $nextNum = 1;
                }
                
                $invNo = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            }
            
            $custom_invoice_date = clean($_POST['custom_invoice_date'] ?? date('Y-m-d'));
            $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';
            
            $stmtInsert = $pdo->prepare("INSERT INTO invoices (invoice_number, booking_id, type, sub_total, total_amount, payment_status, approval_status, invoice_date) VALUES (?, ?, 'tax', ?, ?, 'unpaid', ?, ?)");
            $stmtInsert->execute([$invNo, $booking_id, $bookingData['total_amount'], $bookingData['grand_total'], $approvalStatus, $custom_invoice_date]);
            
            $invoiceId = $pdo->lastInsertId();
            
            if (!$isAdmin) {
                $userId = $_SESSION['user_id'] ?? 0;
                $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('invoice', ?, ?, ?, 'pending')");
                $stmtAR->execute([$invoiceId, $invNo, $userId]);
            }
        } else {
            $approvalStatus = $existing['approval_status'] ?? 'approved';
        }
        
        echo json_encode(['success' => true, 'approval_status' => $approvalStatus, 'is_admin' => $isAdmin]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Update Failed']);
    }
}
?>
