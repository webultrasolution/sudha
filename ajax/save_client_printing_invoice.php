<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We are now using FormData, so data is in $_POST and $_FILES
    
    if (empty($_POST['rate_ids'])) {
        echo json_encode(['success' => false, 'message' => 'Incomplete data. Please select rates.']);
        exit;
    }

    $client_id = intval($_POST['client_id']);
    
    // Format remarks with ownership
    $base_remark = trim($_POST['remark'] ?? '');
    $ownership = $_POST['ownership'] ?? 'Self';
    $remarks = $base_remark;
    if ($ownership) {
        $remarks = $remarks ? $remarks . " | Ownership: " . $ownership : "Ownership: " . $ownership;
    }
    
    // Handle File Upload if `attachment` column is added or we just want to save it
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/invoices/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['attachment']['name']));
        $dest = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
            $attachment_path = $filename;
            // Append attachment info to remarks since we don't know if attachment column exists
            $remarks .= " | Attachment: " . $filename;
        }
    }


    try {
        $pdo->beginTransaction();

        // 1. Calculate Total Amount
        $net_total = 0;
        $items = [];

        foreach ($_POST['rate_ids'] as $rate_id) {
            $rate_id = intval($rate_id);
            $stmt = $pdo->prepare("
                SELECT r.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.type as media_type_site, s.hsn_code, v.name as vendor_name
                FROM client_printing_rates r
                LEFT JOIN sites s ON r.site_id = s.id
                LEFT JOIN partners v ON s.vendor_id = v.id
                WHERE r.id = ?
            ");
            $stmt->execute([$rate_id]);
            $r = $stmt->fetch();
            if ($r) {
                $sqft = ($r['width'] && $r['height']) ? floatval($r['width']) * floatval($r['height']) : 0;
                $total = $sqft * floatval($r['rate_per_sqft']);
                $net_total += $total;
                $items[] = [
                    'rate_id' => $r['id'],
                    'site_id' => $r['site_id'],
                    'vendor_id' => $r['vendor_id'] ?? 0,
                    'rate' => $r['rate_per_sqft'],
                    'total' => $total,
                    'details' => json_encode($r)
                ];
            }
        }

        $billing_gstin = trim($_POST['billing_gstin'] ?? '');
        $client_state = '';
        if ($client_id) {
            $stmtC = $pdo->prepare("SELECT gstin, state, additional_gst FROM partners WHERE id = ?");
            $stmtC->execute([$client_id]);
            $partner = $stmtC->fetch();
            if ($partner) {
                if (!empty($billing_gstin) && $partner['gstin'] !== $billing_gstin) {
                    $found = false;
                    if (!empty($partner['additional_gst'])) {
                        $extra = json_decode($partner['additional_gst'], true);
                        if (is_array($extra)) {
                            foreach ($extra as $item) {
                                if (isset($item['gstin']) && $item['gstin'] === $billing_gstin) {
                                    $client_state = trim($item['state'] ?? '');
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    }
                    if (!$found) {
                        $client_state = trim($partner['state'] ?? '');
                    }
                } else {
                    $client_state = trim($partner['state'] ?? '');
                }
            }
        }

        $isClientInterstate = true;
        if (!empty($client_state)) {
            $isClientInterstate = (strcasecmp($client_state, 'West Bengal') !== 0);
        }
        if (!empty($billing_gstin)) {
            $isClientInterstate = (strcasecmp($client_state, 'West Bengal') !== 0 && substr($billing_gstin, 0, 2) !== '19');
        }

        $cgst = 0; $sgst = 0; $igst = 0;
        if ($isClientInterstate) {
            $igst = $net_total * 0.18;
        } else {
            $cgst = $net_total * 0.09;
            $sgst = $net_total * 0.09;
        }
        $grandTotal = $net_total + $cgst + $sgst + $igst;

        $prefix = 'SCRP/' . getFinancialYear() . '/';
        $invoiceNum = generateSequentialReference($pdo, 'invoices', 'invoice_number', $prefix, 4);
        
        $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

        // 2. Insert into invoices (using entity_id, remarks, billing_gstin, approval_status)
        $stmtInv = $pdo->prepare("
            INSERT INTO invoices (booking_id, entity_id, invoice_number, invoice_date, sub_total, cgst, sgst, igst, total_amount, payment_status, approval_status, type, remarks, billing_gstin) 
            VALUES (0, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'unpaid', ?, 'printing', ?, ?)
        ");
        $stmtInv->execute([
            $client_id, 
            $invoiceNum, 
            $net_total, 
            $cgst, 
            $sgst, 
            $igst, 
            $grandTotal,
            $approvalStatus,
            $remarks,
            $billing_gstin
        ]);
        $invoiceId = $pdo->lastInsertId();

        // 3. Insert into invoice_items
        $stmtItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, amount) VALUES (?, ?, ?)");
        foreach ($items as $item) {
            $stmtItem->execute([
                $invoiceId, 
                $item['details'], // Saving all JSON details to description so we can render it later!
                $item['total']
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'invoice_id' => $invoiceId, 'message' => 'Invoice saved successfully.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
