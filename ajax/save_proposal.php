<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $printCost = floatval($data['printCost'] ?? 0);
        $mountCost = floatval($data['mountCost'] ?? 0);

        // 1. Calculate Totals
        $displayCost = 0;
        $totalSQFT = 0;
        $haMarkup = 0;
        $taMarkup = 0;

        foreach ($data['selectedSites'] as $site) {
            $sRate = floatval($site['saleRate']);
            $pRate = floatval($site['purchaseRate']);
            $displayCost += $sRate;
            $totalSQFT += floatval($site['sqft']);
            
            // Add per-site printing cost to overall printCost if not already summed
            $pTotal = floatval($site['printing_total'] ?? 0);
            $printCost += $pTotal;

            $markupVal = $sRate - $pRate;
            if ($site['owner'] === 'HA') $haMarkup += $markupVal;
            else $taMarkup += $markupVal;
        }

        $subtotal = $displayCost + $printCost + $mountCost;
        $gst = $subtotal * 0.18;
        $grandTotal = $subtotal + $gst;
        
        $pricePerSqft = $totalSQFT > 0 ? $displayCost / $totalSQFT : 0;

        // Generate Proposal Number
        $propNum = generateSequenceNumber($pdo, 'proposal');

        // 2. Insert Proposal
        // Admin sends directly; non-admin goes to pending_approval queue
        $proposalStatus   = 'sent';
        $approvalStatus   = 'approved';

        $entityId = $_SESSION['active_entity_id'] ?? null;
        if (!$entityId) {
            $stmtEntity = $pdo->query("SELECT id FROM entities LIMIT 1");
            $entityId = $stmtEntity->fetchColumn() ?: null;
        }

        $stmt = $pdo->prepare("INSERT INTO proposals 
            (proposal_number, campaign_name, media_type, inventory_type, light_type, client_id, entity_id, billing_gstin, tax_type, contact_person, start_date, end_date, total_days, remark,
             printing_cost, mounting_cost, 
             ha_markup_amount, ta_markup_amount, total_sqft, price_per_sqft, display_cost, 
             total_amount, tax_amount, grand_total, status, approval_status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        $stmt->execute([
            $propNum,
            $data['campaignName'] ?? '',
            !empty($data['mediaType']) ? intval($data['mediaType']) : null,
            $data['inventoryType'] ?? '',
            $data['lightType'] ?? '',
            $data['clientId'],
            $entityId,
            $data['selectedGstin'] ?? null,
            $data['taxType'] ?? 'igst',
            $data['contactPerson'] ?? '',
            !empty($data['startDate']) ? $data['startDate'] : null,
            !empty($data['endDate']) ? $data['endDate'] : null,
            $data['totalDays'] ?: null,
            $data['remark'] ?? '',
            $printCost,
            $mountCost,
            $haMarkup,
            $taMarkup,
            $totalSQFT,
            $pricePerSqft,
            $displayCost,
            $subtotal,
            $gst,
            $grandTotal,
            $proposalStatus,
            $approvalStatus,
            $_SESSION['user_id']
        ]);
        
        $proposalId = $pdo->lastInsertId();
        
        logActivity('generated a new proposal', 'proposals', $proposalId, "Proposal Number: $propNum");

        // 3. Insert Items
        $stmtItem = $pdo->prepare("INSERT INTO proposal_items (proposal_id, site_id, sale_rate, purchase_rate, margin_pct, amount, selected_image, printing_vendor_id, printing_rate, printing_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['selectedSites'] as $site) {
            $pRate = floatval($site['purchaseRate']);
            $sRate = floatval($site['saleRate']);
            $margin = $pRate > 0 ? (($sRate - $pRate) / $pRate * 100) : 0;
            
            $stmtItem->execute([
                $proposalId,
                $site['id'],
                $sRate,
                $pRate,
                $margin,
                $sRate,
                $site['thumbnail'] ?? null,
                $site['printing_vendor_id'] ?? null,
                $site['printing_rate'] ?? 0,
                $site['printing_total'] ?? 0
            ]);
        }

        $pdo->commit();
        echo json_encode([
            'success'         => true,
            'proposal_id'     => $proposalId,
            'approval_status' => $approvalStatus,
            'message'         => "Proposal $propNum created and sent."
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
