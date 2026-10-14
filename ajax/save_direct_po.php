<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
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

        // 1. Group Sites by Vendor & Collect Printing Info
        $vendorSites = [];
        $printingVendorItems = [];
        $overallSubtotal = 0;
        $overallPrinting = 0;

        $gen_booking = !empty($data['gen_booking']) ? 1 : 0;
        $gen_print = !empty($data['gen_client_print_invoice']) ? 1 : 0;
        $gen_mount = !empty($data['gen_client_mounting_invoice']) ? 1 : 0;

        foreach ($data['site_ids'] as $sid) {
            // Fetch vendor_id, type, width, height for each site
            $stmtV = $pdo->prepare("SELECT vendor_id, type, width, height FROM sites WHERE id = ?");
            $stmtV->execute([$sid]);
            $siteData = $stmtV->fetch();
            $vid = $siteData['vendor_id'] ?? null;

            $rateData = $data['rates'][$sid] ?? [];
            $rate = is_array($rateData) ? floatval($rateData['booking'] ?? 0) : floatval($rateData);
            $unit = is_array($rateData) ? intval($rateData['unit'] ?? 1) : 1;

            if (!$gen_booking) {
                $rate = 0;
            }

            // Do not prorate direct bookings, it's a fixed negotiated amount
            $proratedRate = $rate * $unit;

            $clientPrintRate = is_array($rateData) ? floatval($rateData['print'] ?? 0) : 0;
            $clientMountRate = is_array($rateData) ? floatval($rateData['mount'] ?? 0) : 0;
            $sqft = floatval($siteData['width'] ?? 0) * floatval($siteData['height'] ?? 0) * $unit;

            $printTotal = ($gen_print) ? ($clientPrintRate * $sqft) : 0;
            $mountTotal = ($gen_mount) ? ($clientMountRate * $sqft) : 0;

            $overallSubtotal += $proratedRate + $printTotal + $mountTotal;

            if ($vid && $gen_booking && $rate > 0) {
                if (!isset($vendorSites[$vid]))
                    $vendorSites[$vid] = [];
                $vendorSites[$vid][] = ['site_id' => $sid, 'rate' => $rate, 'prorated_rate' => $proratedRate];
            }

            // Handle Printing Info
            if (!empty($data['printing_info'][$sid])) {
                $pInfo = $data['printing_info'][$sid];
                $pVendorId = intval($pInfo['vendor_id']);
                if ($pVendorId) {
                    if (!isset($printingVendorItems[$pVendorId]))
                        $printingVendorItems[$pVendorId] = [];
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

        // 3. Create Single Booking (Direct)
        $bookingId = null;
        $create_booking_record = ($gen_booking || $gen_print || $gen_mount);

        if ($create_booking_record) {
            $bookingNum = generateSequenceNumber($pdo, 'booking');
            // Do not add overallPrinting to client's bookingSubtotal (it's a vendor cost)
            $bookingSubtotal = $overallSubtotal;
            $overallTax = $bookingSubtotal * 0.18;
            $overallGrand = $bookingSubtotal + $overallTax;

            $bookingStatus = 'active';
            $bookingApprovalStatus = 'approved';

            $entityId = !empty($data['entity_id']) ? intval($data['entity_id']) : ($_SESSION['active_entity_id'] ?? null);
            if (!$entityId) {
                $stmtEntity = $pdo->query("SELECT id FROM entities LIMIT 1");
                $entityId = $stmtEntity->fetchColumn() ?: null;
            }

            $userId = $_SESSION['user_id'] ?? null;

            $stmtBooking = $pdo->prepare("
                INSERT INTO bookings (booking_number, client_id, entity_id, campaign_name, brand_name, external_po, contact_person, billing_gstin, tax_type, start_date, end_date, total_amount, tax_amount, grand_total, status, approval_status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtBooking->execute([$bookingNum, $client_id, $entityId, $campaign_name, $brand_name, $external_po, $contact_person, $billing_gstin, $tax_type, $start_date, $end_date, $bookingSubtotal, $overallTax, $overallGrand, $bookingStatus, $bookingApprovalStatus, $userId]);
            $bookingId = $pdo->lastInsertId();
        }

        $lastPoId = 0;
        $poCount = 0;
        $approvalStatus = 'approved';

        if ($create_booking_record) {
            // 4. Create Operational Tasks & Booking Items
            $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");
            $stmtBI = $pdo->prepare("INSERT INTO booking_items (booking_id, site_id, purchase_rate, sale_rate, client_print_rate, client_mount_rate, start_date, end_date, days, purchase_amount, amount, printing_vendor_id, printing_rate, printing_amount, selected_image, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($data['site_ids'] as $sid) {
                $stmtOps->execute([$bookingId, $sid]);

                $rateData = $data['rates'][$sid] ?? [];
                $rate = is_array($rateData) ? floatval($rateData['booking'] ?? 0) : floatval($rateData);
                if (!$gen_booking) $rate = 0;
                
                $unit = is_array($rateData) ? intval($rateData['unit'] ?? 1) : 1;
                $clientPrintRate = is_array($rateData) ? floatval($rateData['print'] ?? 0) : 0;
                $clientMountRate = is_array($rateData) ? floatval($rateData['mount'] ?? 0) : 0;

                // Do not prorate direct bookings, it's a fixed negotiated amount
                $proratedRate = $rate;
                $pInfo = $data['printing_info'][$sid] ?? null;

                $stmtBI->execute([
                    $bookingId,
                    $sid,
                    $rate, // purchase_rate
                    $rate, // sale_rate
                    $clientPrintRate,
                    $clientMountRate,
                    $start_date,
                    $end_date,
                    $days,
                    $proratedRate * $unit, // purchase_amount
                    $proratedRate * $unit, // amount
                    $pInfo ? $pInfo['vendor_id'] : null,
                    $pInfo ? $pInfo['rate'] : 0,
                    $pInfo ? $pInfo['total'] : 0,
                    $data['selected_images'][$sid] ?? null,
                    $unit
                ]);
            }
            logActivity('generated a direct booking', 'bookings', $bookingId, "Booking ID: $bookingId");
        }

        // --- CLIENT PRINTING INVOICE ---
        if ($gen_print) {
            $print_po_number = generateSequenceNumber($pdo, 'client_printing_draft');
            $stmtPrint = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin, booking_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($data['site_ids'] as $sid) {
                $rateData = $data['rates'][$sid] ?? [];
                $ratePerSqft = is_array($rateData) ? floatval($rateData['print'] ?? 0) : 0;

                if ($ratePerSqft > 0) {
                    $stmtV = $pdo->prepare("SELECT type FROM sites WHERE id = ?");
                    $stmtV->execute([$sid]);
                    $mediaType = $stmtV->fetchColumn() ?: 'Vinyl';

                    $stmtPrint->execute([$client_id, $sid, $mediaType, $ratePerSqft, $print_po_number, $campaign_name, $brand_name, $billing_gstin, $bookingId]);
                }
            }
        }

        // --- CLIENT MOUNTING INVOICE ---
        if ($gen_mount) {
            $mount_po_number = generateSequenceNumber($pdo, 'client_mounting_draft');
            $stmtMount = $pdo->prepare("INSERT INTO client_mounting_rates (client_id, site_id, mounting_type, rate_per_sqft, po_number, invoice_number, is_final_invoice, approval_status, campaign_name, brand_name, billing_gstin, booking_id) VALUES (?, ?, ?, ?, ?, NULL, 0, 'draft', ?, ?, ?, ?)");

            foreach ($data['site_ids'] as $sid) {
                $rateData = $data['rates'][$sid] ?? [];
                $ratePerSqft = is_array($rateData) ? floatval($rateData['mount'] ?? 0) : 0;

                if ($ratePerSqft > 0) {
                    $stmtV = $pdo->prepare("SELECT type FROM sites WHERE id = ?");
                    $stmtV->execute([$sid]);
                    $mediaType = $stmtV->fetchColumn() ?: 'Standard';

                    $stmtMount->execute([$client_id, $sid, $mediaType, $ratePerSqft, $mount_po_number, $campaign_name, $brand_name, $billing_gstin, $bookingId]);
                }
            }
        }

        $pdo->commit();

        $message = "Records created successfully.";
        if ($create_booking_record) {
            $message = "Booking created successfully.";
            if ($poCount > 0) {
                $message = $isAdmin
                    ? "Booking created successfully."
                    : "Booking created successfully. $poCount Purchase Orders submitted for admin approval.";
            }
        }

        $redirectUrl = 'bookings.php';
        if (!$create_booking_record) {
            if ($gen_print)
                $redirectUrl = 'client_printing.php';
            elseif ($gen_mount)
                $redirectUrl = 'mounting.php';
        }

        echo json_encode([
            'success' => true,
            'po_id' => ($poCount === 1) ? $lastPoId : null,
            'approval_status' => $approvalStatus,
            'message' => $message,
            'redirect' => $redirectUrl
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>