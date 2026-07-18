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
        
        $client_state = '';
        if ($client_id) {
            $stmtC = $pdo->prepare("SELECT state FROM partners WHERE id = ?");
            $stmtC->execute([$client_id]);
            $client_state = trim($stmtC->fetchColumn() ?: '');
        }
        $isClientInterstate = (strcasecmp($client_state, 'West Bengal') !== 0);
        $tax_type = $isClientInterstate ? 'igst' : 'cgst_sgst';

        $start_date = !empty($data['start_date']) ? $data['start_date'] : null;
        $end_date = !empty($data['end_date']) ? $data['end_date'] : null;
        $status = $data['status'] ?? 'active';

        $days = 30;
        if (!empty($start_date) && !empty($end_date)) {
            $d1 = new DateTime($start_date);
            $d2 = new DateTime($end_date);
            $days = $d1->diff($d2)->days + 1;
        }

        // 1. Group Sites by Vendor & Collect Printing / Mounting Info
        $vendorSites = [];
        $printingVendorItems = [];
        $overallSubtotal = 0;
        $overallPrinting = 0;
        $overallMounting = 0;
        
        foreach ($data['site_ids'] as $sid) {
            // Fetch vendor_id for each site
            $stmtV = $pdo->prepare("SELECT vendor_id FROM sites WHERE id = ?");
            $stmtV->execute([$sid]);
            $vid = $stmtV->fetchColumn();
            
            $rate = floatval($data['rates'][$sid] ?? 0);
            $proratedRate = $rate * $days / 30;
            $overallSubtotal += $proratedRate;

            if ($vid) {
                if (!isset($vendorSites[$vid])) $vendorSites[$vid] = [];
                $vendorSites[$vid][] = ['site_id' => $sid, 'rate' => $rate, 'prorated_rate' => $proratedRate];
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

            // Handle Mounting Info
            if (!empty($data['mounting_info'][$sid])) {
                $mInfo = $data['mounting_info'][$sid];
                $overallMounting += floatval($mInfo['total'] ?? 0);
            }
        }

        // 3. Create or Update Single Booking (Direct)
        $bookingId = isset($data['booking_id']) ? intval($data['booking_id']) : null;
        $isEdit = ($bookingId > 0);

        $bookingSubtotal = $overallSubtotal + $overallPrinting + $overallMounting;
        $overallTax = $bookingSubtotal * 0.18;
        $overallGrand = $bookingSubtotal + $overallTax;

        $bookingStatus         = 'active';
        $bookingApprovalStatus = 'approved';

        $entityId = $_SESSION['active_entity_id'] ?? null;
        if (!$entityId) {
            $stmtEntity = $pdo->query("SELECT id FROM entities LIMIT 1");
            $entityId = $stmtEntity->fetchColumn() ?: null;
        }

        if ($isEdit) {
            $stmtBooking = $pdo->prepare("
                UPDATE bookings SET 
                    client_id = ?, entity_id = ?, campaign_name = ?, brand_name = ?, external_po = ?, 
                    contact_person = ?, billing_gstin = ?, tax_type = ?, start_date = ?, end_date = ?, 
                    total_amount = ?, tax_amount = ?, grand_total = ?
                WHERE id = ?
            ");
            $stmtBooking->execute([
                $client_id, $entityId, $campaign_name, $brand_name, $external_po, 
                $contact_person, $billing_gstin, $tax_type, $start_date, $end_date, 
                $bookingSubtotal, $overallTax, $overallGrand, $bookingId
            ]);
            
            // Get existing booking number to reuse it for replicated records
            $stmtBNum = $pdo->prepare("SELECT booking_number FROM bookings WHERE id = ?");
            $stmtBNum->execute([$bookingId]);
            $bookingNum = $stmtBNum->fetchColumn();

            // Delete existing booking items
            $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$bookingId]);
            // Delete existing operations tasks
            $pdo->prepare("DELETE FROM operations WHERE booking_id = ?")->execute([$bookingId]);
            // Delete existing replicated printing/mounting rates
            $pdo->prepare("DELETE FROM client_printing_rates WHERE po_number COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci")->execute([$bookingNum]);
            $pdo->prepare("DELETE FROM client_mounting_rates WHERE po_number COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci")->execute([$bookingNum]);
        } else {
            $bookingNum = generateSequenceNumber($pdo, 'booking');
            $stmtBooking = $pdo->prepare("
                INSERT INTO bookings (booking_number, client_id, entity_id, campaign_name, brand_name, external_po, contact_person, billing_gstin, tax_type, start_date, end_date, total_amount, tax_amount, grand_total, status, approval_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtBooking->execute([$bookingNum, $client_id, $entityId, $campaign_name, $brand_name, $external_po, $contact_person, $billing_gstin, $tax_type, $start_date, $end_date, $bookingSubtotal, $overallTax, $overallGrand, $bookingStatus, $bookingApprovalStatus]);
            $bookingId = $pdo->lastInsertId();
        }

        $lastPoId = 0;
        $poCount = 0;
        $approvalStatus = 'approved';

        // 4. Create Operational Tasks & Booking Items
        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");
        $stmtBI = $pdo->prepare("INSERT INTO booking_items (booking_id, site_id, purchase_rate, sale_rate, start_date, end_date, days, purchase_amount, amount, printing_vendor_id, printing_rate, printing_amount, mounting_vendor_id, mounting_rate, mounting_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($data['site_ids'] as $sid) {
            $stmtOps->execute([$bookingId, $sid]);
            
            $rate = floatval($data['rates'][$sid] ?? 0);
            $proratedRate = $rate * $days / 30;
            $pInfo = $data['printing_info'][$sid] ?? null;
            $mInfo = $data['mounting_info'][$sid] ?? null;

            $stmtBI->execute([
                $bookingId,
                $sid,
                $rate, // purchase_rate
                $rate, // sale_rate
                $start_date,
                $end_date,
                $days,
                $proratedRate, // purchase_amount
                $proratedRate, // amount
                ($pInfo && !empty($pInfo['vendor_id'])) ? intval($pInfo['vendor_id']) : null,
                $pInfo ? floatval($pInfo['rate']) : 0,
                $pInfo ? floatval($pInfo['total']) : 0,
                null, // mounting_vendor_id
                $mInfo ? floatval($mInfo['rate']) : 0,
                $mInfo ? floatval($mInfo['total']) : 0
            ]);

            // Replicate to client_printing_rates
            if ($pInfo && floatval($pInfo['rate']) > 0) {
                $stmtSite = $pdo->prepare("SELECT type FROM sites WHERE id = ?");
                $stmtSite->execute([$sid]);
                $siteType = $stmtSite->fetchColumn() ?: 'Flex';

                $stmtCPR = $pdo->prepare("INSERT INTO client_printing_rates 
                    (client_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin, is_final_invoice, approval_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'approved')");
                $stmtCPR->execute([
                    $client_id,
                    $sid,
                    $siteType,
                    floatval($pInfo['rate']),
                    $bookingNum,
                    $campaign_name,
                    $brand_name,
                    $billing_gstin
                ]);
            }

            // Replicate to client_mounting_rates
            if ($mInfo && floatval($mInfo['rate']) > 0) {
                $stmtCMR = $pdo->prepare("INSERT INTO client_mounting_rates 
                    (client_id, site_id, mounting_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin, is_final_invoice, approval_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'approved')");
                $stmtCMR->execute([
                    $client_id,
                    $sid,
                    !empty($mInfo['type']) ? $mInfo['type'] : 'Standard',
                    floatval($mInfo['rate']),
                    $bookingNum,
                    $campaign_name,
                    $brand_name,
                    $billing_gstin
                ]);
            }
        }

        logActivity('generated a direct booking', 'bookings', $bookingId, "Booking ID: $bookingId");

        $pdo->commit();
        
        $message = $isEdit ? "Booking updated successfully." : "Booking created successfully.";
        if ($poCount > 0) {
            $message = $isAdmin
                ? ($isEdit ? "Booking updated successfully." : "Booking created successfully.")
                : ($isEdit ? "Booking updated successfully." : "Booking created successfully. $poCount Purchase Orders submitted for admin approval.");
        }

        echo json_encode([
            'success'         => true, 
            'po_id'           => ($poCount === 1) ? $lastPoId : null,
            'approval_status' => $approvalStatus,
            'message'         => $message
        ]);

    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
