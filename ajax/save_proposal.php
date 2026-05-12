<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            
            $markupVal = $sRate - $pRate;
            if ($site['owner'] === 'HA') $haMarkup += $markupVal;
            else $taMarkup += $markupVal;
        }

        $subtotal = $displayCost + $printCost + $mountCost;
        $gst = $subtotal * 0.18;
        $grandTotal = $subtotal + $gst;
        
        $pricePerSqft = $totalSQFT > 0 ? $displayCost / $totalSQFT : 0;

        // Generate Proposal Number
        $propNum = 'PR-' . date('Ymd') . '-' . rand(1000, 9999);

        // 2. Insert Proposal
        $stmt = $pdo->prepare("INSERT INTO proposals 
            (proposal_number, campaign_name, media_type, inventory_type, light_type, client_id, billing_gstin, contact_person, start_date, end_date, total_days, remark,
             printing_cost, mounting_cost, 
             ha_markup_amount, ta_markup_amount, total_sqft, price_per_sqft, display_cost, 
             total_amount, tax_amount, grand_total, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', ?)");
            
        $stmt->execute([
            $propNum,
            $data['campaignName'] ?? '',
            $data['mediaType'] ?? '',
            $data['inventoryType'] ?? '',
            $data['lightType'] ?? '',
            $data['clientId'],
            $data['selectedGstin'] ?? null,
            $data['contactPerson'] ?? '',
            $data['startDate'],
            $data['endDate'],
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
            $_SESSION['user_id']
        ]);
        
        $proposalId = $pdo->lastInsertId();
        
        logActivity('generated a new proposal', 'proposals', $proposalId, "Proposal Number: $propNum");

        // 3. Insert Items
        $stmtItem = $pdo->prepare("INSERT INTO proposal_items (proposal_id, site_id, sale_rate, purchase_rate, margin_pct, amount) VALUES (?, ?, ?, ?, ?, ?)");
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
                $sRate
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'proposal_id' => $proposalId]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
