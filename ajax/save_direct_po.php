<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['site_ids'])) {
        echo json_encode(['success' => false, 'message' => 'Incomplete data. Please select sites.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $client_id = (!empty($data['client_id'])) ? intval($data['client_id']) : null;
        $campaign_name = $data['campaign_name'] ?? 'Direct Booking';
        $brand_name = $data['brand_name'] ?? '';
        $external_po = $data['external_po'] ?? '';
        $contact_person = $data['contact_person'] ?? '';
        $remarks = $data['remark'] ?? '';
        $billing_gstin = $data['billing_gstin'] ?? '';
        $tax_type = $data['tax_type'] ?? 'igst';
        $start_date = $data['start_date'] ?? date('Y-m-d');
        $end_date = $data['end_date'] ?? date('Y-m-d', strtotime('+1 month'));
        $status = $data['status'] ?? 'active';

        // 1. Group Sites by Vendor & Collect Printing Info
        $vendorSites = [];
        $printingVendorItems = [];
        $overallSubtotal = 0;
        $overallPrinting = 0;
        
        foreach ($data['site_ids'] as $sid) {
            // Fetch vendor_id for each site
            $stmtV = $pdo->prepare("SELECT vendor_id FROM sites WHERE id = ?");
            $stmtV->execute([$sid]);
            $vid = $stmtV->fetchColumn();
            
            if ($vid) {
                if (!isset($vendorSites[$vid])) $vendorSites[$vid] = [];
                $rate = floatval($data['rates'][$sid] ?? 0);
                $vendorSites[$vid][] = ['site_id' => $sid, 'rate' => $rate];
                $overallSubtotal += $rate;
            }

            // Handle Printing Info
            if (!empty($data['printing_info'][$sid])) {
                $pInfo = $data['printing_info'][$sid];
                $pVendorId = intval($pInfo['vendor_id']);
                if ($pVendorId) {
                    if (!isset($printingVendorItems[$pVendorId])) $printingVendorItems[$pVendorId] = [];
                    $pTotal = floatval($pInfo['total']);
                    $printingVendorItems[$pVendorId][] = [
                        'site_id' => $sid,
                        'rate' => floatval($pInfo['rate']),
                        'total' => $pTotal
                    ];
                    $overallPrinting += $pTotal;
                }
            }
        }

        if (empty($vendorSites) && empty($printingVendorItems)) {
            throw new Exception("No valid sites or printing info found.");
        }

        $lastPoId = 0;
        $poCount = 0;

        // Admin approves instantly; non-admin goes to queue
        $poStatus       = $isAdmin ? 'approved' : 'pending';
        $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

        // 2. Create PO for Each Site Vendor
        foreach ($vendorSites as $vid => $vItems) {
            $vSubtotal = 0;
            foreach ($vItems as $item) $vSubtotal += $item['rate'];
            
            // Check if vendor has GSTIN in database
            $stmtGst = $pdo->prepare("SELECT gstin FROM partners WHERE id = ?");
            $stmtGst->execute([$vid]);
            $db_vendor_gst = trim($stmtGst->fetchColumn() ?: '');
            $vendor_has_gst = vendorHasGST($db_vendor_gst);

            $cgst = 0; $sgst = 0; $igst = 0;
            if ($vendor_has_gst) {
                if ($tax_type === 'cgst_sgst') {
                    $cgst = $vSubtotal * 0.09;
                    $sgst = $vSubtotal * 0.09;
                } else {
                    $igst = $vSubtotal * 0.18;
                }
            }
            $vGrandTotal = $vSubtotal + $cgst + $sgst + $igst;
            
            $poNum = 'PO-' . date('Ymd') . '-' . rand(100, 999);
            
            $stmtPO = $pdo->prepare("
                INSERT INTO purchase_orders (vendor_id, customer_id, employee_id, campaign_name, brand_name, external_po, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, approval_status, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtPO->execute([$vid, $client_id, $_SESSION['user_id'] ?? 0, $campaign_name, $brand_name, $external_po, $poNum, $vSubtotal, $cgst, $sgst, $igst, $vGrandTotal, $poStatus, $approvalStatus, $remarks]);
            $poId = $pdo->lastInsertId();
            $lastPoId = $poId;
            $poCount++;

            // Create approval request for non-admin
            if (!$isAdmin) {
                $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
                $stmtAR->execute([$poId, $poNum, $_SESSION['user_id'] ?? 0]);
            }

            $stmtPOItem = $pdo->prepare("INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($vItems as $item) {
                $stmtPOItem->execute([$poId, $item['site_id'], $start_date, $end_date, 30, $item['rate'], $item['rate']]);
            }
        }

        // 2.5 Create PO for Each Printing Vendor
        foreach ($printingVendorItems as $pvid => $pItems) {
            $pSubtotal = 0;
            foreach ($pItems as $item) $pSubtotal += $item['total'];
            
            // Check if vendor has GSTIN in database
            $stmtGst = $pdo->prepare("SELECT gstin FROM partners WHERE id = ?");
            $stmtGst->execute([$pvid]);
            $db_vendor_gst = trim($stmtGst->fetchColumn() ?: '');
            $vendor_has_gst = vendorHasGST($db_vendor_gst);

            $cgst = 0; $sgst = 0; $igst = 0;
            if ($vendor_has_gst) {
                if ($tax_type === 'cgst_sgst') {
                    $cgst = $pSubtotal * 0.09; $sgst = $pSubtotal * 0.09;
                } else {
                    $igst = $pSubtotal * 0.18;
                }
            }
            $pGrandTotal = $pSubtotal + $cgst + $sgst + $igst;
            
            $poNum = 'PRT-' . date('Ymd') . '-' . rand(100, 999);
            
            $stmtPO = $pdo->prepare("
                INSERT INTO purchase_orders (vendor_id, customer_id, employee_id, campaign_name, brand_name, external_po, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, approval_status, remarks, type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'printing')
            ");
            $stmtPO->execute([$pvid, $client_id, $_SESSION['user_id'] ?? 0, $campaign_name, $brand_name, $external_po, $poNum, $pSubtotal, $cgst, $sgst, $igst, $pGrandTotal, $poStatus, $approvalStatus, "Printing PO: " . $remarks]);
            $poId = $pdo->lastInsertId();
            $lastPoId = $poId;
            $poCount++;

            // Create approval request for non-admin
            if (!$isAdmin) {
                $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
                $stmtAR->execute([$poId, $poNum, $_SESSION['user_id'] ?? 0]);
            }

            $stmtPOItem = $pdo->prepare("INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($pItems as $item) {
                $stmtPOItem->execute([$poId, $item['site_id'], $start_date, $end_date, 30, $item['rate'], $item['total'], "Printing Cost"]);
            }
        }

        // 3. Create Single Booking (Direct)
        $bookingSubtotal = $overallSubtotal + $overallPrinting;
        $overallTax = $bookingSubtotal * 0.18;
        $overallGrand = $bookingSubtotal + $overallTax;

        $bookingStatus         = $isAdmin ? 'active' : 'pending';
        $bookingApprovalStatus = $isAdmin ? 'approved' : 'pending_approval';

        $stmtBooking = $pdo->prepare("
            INSERT INTO bookings (client_id, campaign_name, brand_name, external_po, contact_person, billing_gstin, tax_type, start_date, end_date, total_amount, tax_amount, grand_total, status, approval_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtBooking->execute([$client_id, $campaign_name, $brand_name, $external_po, $contact_person, $billing_gstin, $tax_type, $start_date, $end_date, $bookingSubtotal, $overallTax, $overallGrand, $bookingStatus, $bookingApprovalStatus]);
        $bookingId = $pdo->lastInsertId();

        // Create approval request for booking (non-admin)
        if (!$isAdmin) {
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('booking', ?, ?, ?, 'pending')");
            $stmtAR->execute([$bookingId, "Booking #$bookingId", $_SESSION['user_id'] ?? 0]);
        }

        // 4. Create Operational Tasks & Booking Items
        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");
        $stmtBI = $pdo->prepare("INSERT INTO booking_items (booking_id, site_id, start_date, end_date, days, purchase_amount, amount, printing_vendor_id, printing_rate, printing_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($data['site_ids'] as $sid) {
            $stmtOps->execute([$bookingId, $sid]);
            
            $rate = floatval($data['rates'][$sid] ?? 0);
            $pInfo = $data['printing_info'][$sid] ?? null;

            $stmtBI->execute([
                $bookingId,
                $sid,
                $start_date,
                $end_date,
                30,
                $rate,
                $rate,
                $pInfo ? $pInfo['vendor_id'] : null,
                $pInfo ? $pInfo['rate'] : 0,
                $pInfo ? $pInfo['total'] : 0
            ]);
        }

        logActivity('generated a direct booking and purchase order(s)', 'bookings', $bookingId, "Booking ID: $bookingId, $poCount POs generated.");

        $pdo->commit();
        
        echo json_encode([
            'success'         => true, 
            'po_id'           => ($poCount === 1) ? $lastPoId : null,
            'approval_status' => $approvalStatus,
            'message'         => $isAdmin
                ? "$poCount Purchase Orders generated successfully."
                : "$poCount Purchase Orders submitted for admin approval."
        ]);

    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
