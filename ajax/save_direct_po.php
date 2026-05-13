<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

        // 1. Group Sites by Vendor
        $vendorSites = [];
        $overallSubtotal = 0;
        
        foreach ($data['site_ids'] as $sid) {
            // Fetch vendor_id for each site
            $stmtV = $pdo->prepare("SELECT vendor_id FROM sites WHERE id = ?");
            $stmtV->execute([$sid]);
            $vid = $stmtV->fetchColumn();
            
            if (!$vid) continue;
            
            if (!isset($vendorSites[$vid])) {
                $vendorSites[$vid] = [];
            }
            
            $rate = floatval($data['rates'][$sid] ?? 0);
            $vendorSites[$vid][] = [
                'site_id' => $sid,
                'rate' => $rate
            ];
            $overallSubtotal += $rate;
        }

        if (empty($vendorSites)) {
            throw new Exception("No valid sites or vendors found.");
        }

        $lastPoId = 0;

        // 2. Create PO for Each Vendor
        foreach ($vendorSites as $vid => $vItems) {
            $vSubtotal = 0;
            foreach ($vItems as $item) $vSubtotal += $item['rate'];
            
            $cgst = 0; $sgst = 0; $igst = 0;
            if ($tax_type === 'cgst_sgst') {
                $cgst = $vSubtotal * 0.09;
                $sgst = $vSubtotal * 0.09;
            } else {
                $igst = $vSubtotal * 0.18;
            }
            $vGrandTotal = $vSubtotal + $cgst + $sgst + $igst;
            
            $poNum = 'PO-' . date('Ymd') . '-' . rand(100, 999);
            
            $stmtPO = $pdo->prepare("
                INSERT INTO purchase_orders (vendor_id, customer_id, employee_id, campaign_name, brand_name, external_po, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'approved', ?)
            ");
            $stmtPO->execute([
                $vid,
                $client_id,
                $_SESSION['user_id'] ?? 0,
                $campaign_name,
                $brand_name,
                $external_po,
                $poNum,
                $vSubtotal,
                $cgst,
                $sgst,
                $igst,
                $vGrandTotal,
                $remarks
            ]);
            $poId = $pdo->lastInsertId();
            $lastPoId = $poId; // Store for redirection if single vendor

            // Insert PO Items
            $stmtPOItem = $pdo->prepare("
                INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($vItems as $item) {
                $stmtPOItem->execute([
                    $poId,
                    $item['site_id'],
                    $start_date,
                    $end_date,
                    30,
                    $item['rate'],
                    $item['rate']
                ]);
            }
        }

        // 3. Create Single Booking (Direct)
        $overallTax = $overallSubtotal * 0.18;
        $overallGrand = $overallSubtotal + $overallTax;

        $stmtBooking = $pdo->prepare("
            INSERT INTO bookings (client_id, campaign_name, brand_name, external_po, contact_person, billing_gstin, tax_type, start_date, end_date, total_amount, tax_amount, grand_total, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtBooking->execute([
            $client_id,
            $campaign_name,
            $brand_name,
            $external_po,
            $contact_person,
            $billing_gstin,
            $tax_type,
            $start_date,
            $end_date,
            $overallSubtotal,
            $overallTax,
            $overallGrand,
            $status
        ]);
        $bookingId = $pdo->lastInsertId();

        // 4. Create Operational Tasks & Booking Items
        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");
        $stmtBI = $pdo->prepare("INSERT INTO booking_items (booking_id, site_id, start_date, end_date, days, purchase_amount, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($data['site_ids'] as $sid) {
            $stmtOps->execute([$bookingId, $sid]);
            
            $rate = floatval($data['rates'][$sid] ?? 0);
            $stmtBI->execute([
                $bookingId,
                $sid,
                $start_date,
                $end_date,
                30,
                $rate, // purchase_amount (assuming same as rate for direct)
                $rate  // amount (sale rate)
            ]);
        }

        logActivity('generated a direct booking and purchase order(s)', 'bookings', $bookingId, "Booking ID: $bookingId, Multiple POs generated.");

        $pdo->commit();
        
        // If only one PO was generated, return its ID for opening
        echo json_encode([
            'success' => true, 
            'po_id' => (count($vendorSites) === 1) ? $lastPoId : null,
            'message' => count($vendorSites) . " Purchase Orders generated successfully."
        ]);

    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
