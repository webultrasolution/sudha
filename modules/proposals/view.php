<?php
$activePage = 'proposals';
$pageTitle = 'Plan Detail Workspace';
// Load Core Libraries (SweetAlert, FontAwesome, CSS) without the sidebar
$hideSidebar = true;
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('proposals', 'view');

include_once __DIR__ . '/../../includes/header.php';

// Prevent timezone warnings
date_default_timezone_set('Asia/Kolkata');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Proposal with advanced stats
$stmt = $pdo->prepare("
    SELECT p.*, 
           c.name as client_name, c.email as client_email, c.phone as client_phone,
           c.contact_person as client_contact_person, c.address as client_address,
           c.city as client_city, c.state as client_state, c.district as client_district,
           c.pincode as client_pincode, c.gstin as client_primary_gstin, c.pan as client_pan,
           c.business_type, c.additional_gst,
           u.username as creator 
    FROM proposals p 
    JOIN partners c ON p.client_id = c.id 
    LEFT JOIN users u ON p.created_by = u.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) { echo "<div class='card'>Proposal not found.</div>"; include_once __DIR__ . '/../../includes/footer.php'; exit; }

// Fetch Items with full site details
$stmtItems = $pdo->prepare("
    SELECT pi.*, s.site_code, COALESCE(pi.custom_site_name, s.name) as site_name, COALESCE(pi.custom_location, s.location) as location, s.city, s.state as site_state, s.type as media_type, s.owner_type, 
           s.width, s.height, s.light_type, s.card_rate as site_card_rate, s.available_from, s.purchase_rate as master_purchase,
           s.vendor_id, v.name as vendor_name, v.email as vendor_email,
           (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail,
           (SELECT GROUP_CONCAT(filename) FROM site_images WHERE site_id = s.id) as all_images
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    LEFT JOIN partners v ON s.vendor_id = v.id
    WHERE pi.proposal_id = ?
    ORDER BY pi.sort_order ASC, pi.id ASC
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

// Fetch metadata for Add Sites modal
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM sites WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$districts = $pdo->query("SELECT DISTINCT district FROM sites WHERE district IS NOT NULL AND district != '' ORDER BY district")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL AND width != '' AND height != '' ORDER BY width, height")->fetchAll(PDO::FETCH_COLUMN);
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
$clientOptions = [];
foreach ($clients as $c) {
    $clientOptions[$c['id']] = $c['name'];
}
$clientOptionsJson = json_encode($clientOptions);

// Advanced Stats
$totalSQFT = 0;
$haCost = 0; $taCost = 0;
$haSale = 0; $taSale = 0;
$subTotalWoTax = 0;

foreach ($items as $item) {
    $sqft = $item['width'] * $item['height'] * ($item['unit'] ?? 1);
    $totalSQFT += $sqft;
    if ($item['owner_type'] == 'HA') {
        $haCost += $item['purchase_rate'];
        $haSale += $item['sale_rate'];
    } else {
        $taCost += $item['purchase_rate'];
        $taSale += $item['sale_rate'];
    }
}

$discountVal = $p['total_amount'] * ($p['discounting_pct'] / 100);
$displayCost = $p['total_amount'] - $discountVal;
$totalPrintCost = $totalSQFT * $p['printing_cost'];
$totalMountCost = $totalSQFT * $p['mounting_cost'];
$grandTotal = $p['grand_total'];

$haMarkup = $haSale - $haCost;
$taMarkup = $taSale - $taCost;
$haMarkupPct = ($haCost > 0) ? ($haMarkup / $haCost) * 100 : 0;
$taMarkupPct = ($taCost > 0) ? ($taMarkup / $taCost) * 100 : 0;

// Collect all GST options for this client
$gsts = [];
if (!empty($p['client_primary_gstin'])) {
    $gsts[] = [
        'gstin'   => $p['client_primary_gstin'],
        'state'   => !empty($p['client_state']) ? $p['client_state'] : 'Primary',
        'address' => $p['client_address'] ?? ''
    ];
}
if (!empty($p['additional_gst'])) {
    $add = json_decode($p['additional_gst'], true);
    if (is_array($add)) {
        foreach ($add as $g) {
            if (!empty($g['gstin'])) {
                $isDup = false;
                foreach ($gsts as $existing) {
                    if (strcasecmp($existing['gstin'], $g['gstin']) === 0) {
                        $isDup = true;
                        break;
                    }
                }
                if (!$isDup) {
                    $gsts[] = [
                        'gstin'   => $g['gstin'],
                        'state'   => $g['state'] ?? 'Branch',
                        'address' => $g['address'] ?? ''
                    ];
                }
            }
        }
    }
}

// Active/Selected billing GSTIN for this proposal
$selectedGstin = !empty($p['billing_gstin']) ? $p['billing_gstin'] : (!empty($p['client_primary_gstin']) ? $p['client_primary_gstin'] : '');

// Find matching GST record for state/address
$matchedGstRecord = null;
foreach ($gsts as $g) {
    if (strcasecmp($g['gstin'], $selectedGstin) === 0) {
        $matchedGstRecord = $g;
        break;
    }
}

// Resolve Client Address for this proposal
$displayAddress = '';
if ($matchedGstRecord && !empty($matchedGstRecord['address'])) {
    $displayAddress = $matchedGstRecord['address'];
} else {
    $addrParts = array_filter([
        $p['client_address'] ?? '',
        $p['client_city'] ?? '',
        (($p['client_district'] ?? '') && ($p['client_district'] !== ($p['client_city'] ?? ''))) ? $p['client_district'] : '',
        $p['client_state'] ?? '',
        !empty($p['client_pincode']) ? 'PIN: ' . $p['client_pincode'] : ''
    ]);
    $displayAddress = implode(', ', $addrParts);
}

// Tax Type Display: Intra-state (West Bengal 19) -> CGST+SGST, Interstate (Non-WB) -> IGST
if (!empty($selectedGstin)) {
    $stateCode = substr($selectedGstin, 0, 2);
    $isWestBengal = ($stateCode === '19');
} else {
    $clientState = strtolower(trim($p['client_state'] ?? ''));
    $isWestBengal = empty($clientState) || ($clientState === 'west bengal') || ($clientState === 'wb');
}

$taxTypeLabel = $isWestBengal ? 'CGST (9%) + SGST (9%)' : 'IGST (18%)';
$taxTypeBadgeBg = $isWestBengal ? '#eff6ff' : '#fef3c7';
$taxTypeBadgeBorder = $isWestBengal ? '#bfdbfe' : '#fde68a';
$taxTypeBadgeColor = $isWestBengal ? '#1d4ed8' : '#b45309';
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
?>

<div style="display: flex; justify-content: space-between; align-items: stretch; margin-bottom: 2rem; gap: 1.25rem; flex-wrap: wrap;">
    <!-- 1. Proposal Meta Info -->
    <div style="display: flex; flex-direction: column; justify-content: center; min-width: 260px;">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem;">
            <h1 style="font-size: 1.65rem; font-weight: 800; color: #0f172a; margin: 0; display: inline-flex; align-items: center; gap: 0.4rem; letter-spacing: -0.02em;">
                <span><?php echo htmlspecialchars($p['campaign_name']); ?></span>
                <?php if (canEdit('proposals')): ?>
                    <button onclick="editCampaignName()" style="background: none; border: none; color: #10b981; cursor: pointer; font-size: 1rem; padding: 0; display: inline-flex; align-items: center;" title="Edit Campaign Name">
                        <i class="fas fa-edit"></i>
                    </button>
                <?php endif; ?>
            </h1>
            <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.65rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700;">
                <?php echo $p['proposal_number']; ?>
            </span>
            <?php 
                $displayStatus = $p['status'];
                if (($p['approval_status'] ?? '') === 'approved' && $p['status'] === 'sent') {
                    $displayStatus = 'approved';
                }
            ?>
            <span style="background: #e0e7ff; color: #4338ca; padding: 0.2rem 0.65rem; border-radius: 50px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;">
                <?php echo strtoupper($displayStatus); ?>
            </span>
        </div>
        <div style="color: #64748b; font-size: 0.88rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <i class="far fa-calendar-alt" style="color: #0d9488;"></i>
            Tenure / Period: <strong style="color: #334155;"><?php 
            if (!empty($p['start_date']) && $p['start_date'] !== '0000-00-00' && !empty($p['end_date']) && $p['end_date'] !== '0000-00-00') {
                echo date('d M', strtotime($p['start_date'])) . ' to ' . date('d M Y', strtotime($p['end_date']));
            } else {
                echo 'N/A';
            }
            ?></strong>
        </div>
    </div>

    <!-- 2. Client Profile & Selected GST Card (Red Box Area in screenshot) -->
    <div style="flex: 1; min-width: 380px; max-width: 580px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.75rem 1.15rem; box-shadow: 0 2px 4px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: center;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.3rem;">
            <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <i class="fas fa-building" style="color: #0d9488; font-size: 0.95rem; flex-shrink: 0;"></i>
                <span title="<?php echo htmlspecialchars($p['client_name']); ?>"><?php echo htmlspecialchars($p['client_name']); ?></span>
                <?php if (canEdit('proposals')): ?>
                    <button onclick="editClient()" style="background: none; border: none; color: #10b981; cursor: pointer; font-size: 0.82rem; padding: 0; margin-left: 2px;" title="Change Client">
                        <i class="fas fa-edit"></i>
                    </button>
                <?php endif; ?>
            </div>
            <?php if (!empty($p['business_type'])): ?>
                <span style="background: #f1f5f9; color: #475569; font-size: 0.68rem; font-weight: 700; padding: 2px 7px; border-radius: 4px; white-space: nowrap; flex-shrink: 0;">
                    <?php echo htmlspecialchars($p['business_type']); ?>
                </span>
            <?php endif; ?>
        </div>

        <div style="font-size: 0.8rem; color: #475569; line-height: 1.35; margin-bottom: 0.45rem; display: flex; align-items: flex-start; gap: 5px;">
            <i class="fas fa-map-marker-alt" style="color: #ef4444; font-size: 0.8rem; margin-top: 2px; flex-shrink: 0;"></i>
            <span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?php echo htmlspecialchars($displayAddress); ?>">
                <?php echo htmlspecialchars($displayAddress ?: 'Address not provided'); ?>
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; font-size: 0.78rem;">
            <span style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.2rem 0.55rem; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Selected Billing GSTIN for this proposal">
                <i class="fas fa-id-card" style="color: #059669;"></i>
                GSTIN: <span style="font-family: monospace; font-size: 0.85rem; letter-spacing: 0.02em;"><?php echo htmlspecialchars($selectedGstin ?: 'Unregistered / None'); ?></span>
                <?php if ($matchedGstRecord && !empty($matchedGstRecord['state'])): ?>
                    <span style="color: #047857; font-weight: 600; font-size: 0.72rem;">(<?php echo htmlspecialchars($matchedGstRecord['state']); ?>)</span>
                <?php endif; ?>
            </span>

            <?php if (!empty($selectedGstin) || !empty($p['client_state'])): ?>
                <span style="background: <?php echo $taxTypeBadgeBg; ?>; border: 1px solid <?php echo $taxTypeBadgeBorder; ?>; color: <?php echo $taxTypeBadgeColor; ?>; padding: 0.2rem 0.55rem; border-radius: 6px; font-weight: 700; font-size: 0.72rem;" title="<?php echo $isWestBengal ? 'Intra-State Supply (West Bengal)' : 'Inter-State Supply (Outside West Bengal)'; ?>">
                    <?php echo $taxTypeLabel; ?>
                </span>
            <?php endif; ?>

            <?php if (!empty($p['client_pan'])): ?>
                <span style="background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 600; font-size: 0.72rem;" title="Client PAN">
                    PAN: <span style="font-family: monospace;"><?php echo htmlspecialchars($p['client_pan']); ?></span>
                </span>
            <?php endif; ?>

            <?php if ($isAdmin || canEdit('proposals')): ?>
                <button type="button" onclick="editProposalBillingGstin()" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #334155; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f8fafc';" title="Change or select billing GSTIN for this proposal">
                    <i class="fas fa-edit" style="color: #0284c7;"></i> Change GST
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Action Buttons -->
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <?php if (($p['approval_status'] ?? '') === 'approved'): ?>
            <button type="button" class="btn" onclick="sendViaEmail()" style="background: white; border: 1px solid #ea4335; color: #ea4335; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 800; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 2px 4px rgba(234,67,53,0.12);" onmouseover="this.style.background='#ea4335'; this.style.color='white'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='white'; this.style.color='#ea4335'; this.style.transform='none';" title="Send stamped image cards directly via SMTP Email">
                <i class="fas fa-paper-plane" style="color: inherit; font-size: 1rem;"></i> Send via Email (SMTP)
            </button>
            <button type="button" class="btn" onclick="shareWithImagesWebShare()" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; color: white; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 800; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.35);" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 14px rgba(37, 211, 102, 0.45)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 10px rgba(37, 211, 102, 0.35)';" title="Share proposal sites as stamped JPEGs directly to WhatsApp">
                <i class="fab fa-whatsapp" style="font-size: 1.15rem;"></i> Share Stamped JPEGs
            </button>
            <div class="dropdown">
                <button class="btn" style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='#cbd5e1'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.02)';">
                    <i class="fas fa-download"></i> Export <i class="fas fa-caret-down" style="margin-left: 0.25rem; opacity: 0.7;"></i>
                </button>
                <div class="dropdown-content">
                    <div style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; padding: 0.5rem 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Client Documents</div>
                    <a href="export_pdf.php?id=<?php echo $id; ?>" target="_blank"><i class="fas fa-file-pdf" style="color: #ef4444;"></i> Visual Media Plan (PDF)</a>
                    <a href="export_quotation.php?id=<?php echo $id; ?>" target="_blank"><i class="fas fa-file-invoice-dollar" style="color: #0d9488;"></i> Generate Quotation (PDF)</a>
                    <a href="export_excel.php?id=<?php echo $id; ?>"><i class="fas fa-file-excel" style="color: #10b981;"></i> Excel Rate Sheet</a>
                    <a href="javascript:void(0)" onclick="openProposalInGSheet(<?php echo $id; ?>)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: -2px; margin-right: 4px;"><path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#0F9D58"/><path d="M7 7H17V9H7V7ZM7 11H17V13H7V11ZM7 15H14V17H7V15Z" fill="white"/></svg> Open in GSheet</a>
                    <a href="export_ppt.php?id=<?php echo $id; ?>" target="_blank"><i class="fas fa-file-powerpoint" style="color: #f97316;"></i> PPT Deck / Presentation</a>
                    <div style="height: 1px; background: #f1f5f9; margin: 0.25rem 0;"></div>
                    <div style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; padding: 0.5rem 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Visuals</div>
                    <a href="export_ppt.php?id=<?php echo $id; ?>&mode=view" target="_blank"><i class="fas fa-desktop" style="color: #6366f1;"></i> View Presentation</a>
                    <a href="javascript:void(0)" onclick="copyPublicLink('<?php echo BASE_URL; ?>modules/proposals/export_ppt.php?id=<?php echo $id; ?>')"><i class="fas fa-link" style="color: #6366f1;"></i> Copy Public Link</a>
                    <a href="../../ajax/download_sites_zip.php?stamped=1&proposal_id=<?php echo $id; ?>"><i class="fas fa-id-card" style="color: #0284c7;"></i> Download Stamped JPEGs (ZIP)</a>
                    <a href="download_photos.php?id=<?php echo $id; ?>"><i class="fas fa-images" style="color: #8b5cf6;"></i> Download Photos (Raw)</a>
                </div>
            </div>
        <?php else: ?>
            <button class="btn" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #94a3b8; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: not-allowed; display: flex; align-items: center; gap: 0.5rem;" title="Documents are locked until admin approves this proposal.">
                <i class="fas fa-lock"></i> Export Locked
            </button>
        <?php endif; ?>
        <?php if ($p['status'] != 'confirmed' && canAdd('bookings') && ($p['approval_status'] ?? '') === 'approved'): ?>
            <button class="btn" onclick="confirmProposal(<?php echo $id; ?>)" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; color: white; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 10px 15px -3px rgba(16, 185, 129, 0.4)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(16, 185, 129, 0.3)';">
                <i class="fas fa-check-double"></i> Convert to Booking
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- 4-Panel Dynamic Layout -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
    <!-- Panel A: Inventories Control -->
    <div class="p-panel">
        <div>
            <h4 class="p-title"><i class="fas fa-sliders-h"></i> Configuration</h4>
            <div class="p-row">
                <label style="font-weight: 600;">Discount %</label>
                <div style="display: flex; align-items: center; gap: 4px;">
                    <input type="number" step="0.01" class="p-val-input" value="<?php echo $p['discounting_pct']; ?>" onchange="updatePlan('discounting_pct', this.value)" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
                    <span style="font-weight: 700; color: #64748b; font-size: 0.85rem;">%</span>
                </div>
            </div>
            <div class="p-row">
                <label style="font-weight: 600;">Markup %</label>
                <div style="display: flex; align-items: center; gap: 4px;">
                    <input type="number" step="0.01" class="p-val-input" value="<?php echo $p['pricing_pct']; ?>" onchange="updatePlan('pricing_pct', this.value)" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
                    <span style="font-weight: 700; color: #64748b; font-size: 0.85rem;">%</span>
                </div>
            </div>
        </div>
        <div style="margin-top: 1rem; border-top: 1px solid #f1f5f9; padding-top: 0.75rem;">
            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                <input type="checkbox" checked style="width: 16px; height: 16px; accent-color: var(--primary);" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
                <span style="font-size: 0.82rem; font-weight: 600; color: #475569;">Auto-Sync Dates</span>
            </label>
        </div>
    </div>

    <!-- Panel B: Production Cost -->
    <div class="p-panel">
        <div>
            <h4 class="p-title"><i class="fas fa-hammer"></i> Production</h4>
            <div class="p-row">
                <label style="font-weight: 600;">Printing (₹/sqft)</label>
                <input type="number" class="p-val-input" value="<?php echo $p['printing_cost']; ?>" onchange="updatePlan('printing_cost', this.value)" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
            </div>
            <div class="p-row">
                <label style="font-weight: 600;">Mounting (₹/sqft)</label>
                <input type="number" class="p-val-input" value="<?php echo $p['mounting_cost']; ?>" onchange="updatePlan('mounting_cost', this.value)" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
            </div>
        </div>
        <div style="margin-top: 1rem; border-top: 1px solid #f1f5f9; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Prod.</span>
            <strong style="color: #0f172a; font-size: 0.95rem; font-weight: 800;"><?php echo formatCurrency($totalPrintCost + $totalMountCost); ?></strong>
        </div>
    </div>

    <!-- Panel C: Statistics (Calculated) -->
    <div class="p-panel" style="background: #f8fafc; border-color: #e2e8f0;">
        <div>
            <h4 class="p-title"><i class="fas fa-chart-pie"></i> Statistics</h4>
            <div class="stat-box" style="margin-bottom: 0.75rem;">
                <small>HA Margin (Own)</small>
                <strong><?php echo formatCurrency($haMarkup); ?> <span class="badge-tag"><?php echo number_format($haMarkupPct, 1); ?>%</span></strong>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="stat-box" style="margin-bottom: 0;">
                    <small>Total Area</small>
                    <strong><?php echo number_format($totalSQFT); ?> <span style="font-size:0.7rem; color:#64748b;">sqft</span></strong>
                </div>
                <div class="stat-box" style="margin-bottom: 0;">
                    <small>Rate / SQFT</small>
                    <strong><?php echo formatCurrency($totalSQFT > 0 ? $displayCost / $totalSQFT : 0); ?></strong>
                </div>
            </div>
        </div>
        <div style="margin-top: 1rem; border-top: 1px solid #e2e8f0; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Sites</span>
            <strong style="color: var(--primary); font-size: 0.95rem; font-weight: 800;"><?php echo count($items); ?> Assets</strong>
        </div>
    </div>

    <!-- Panel D: Summary (Auto) -->
    <div class="p-panel" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border: none; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.4);">
        <div>
            <h4 class="p-title" style="color: #94a3b8; border-bottom-color: rgba(255,255,255,0.1);"><i class="fas fa-receipt"></i> Financial Summary</h4>
            <div class="p-row" style="color: #cbd5e1; margin-bottom: 0.4rem; font-size: 0.85rem;"><span>Display Cost</span><strong style="color: white;"><?php echo formatCurrency($displayCost); ?></strong></div>
            <div class="p-row" style="color: #cbd5e1; margin-bottom: 0.4rem; font-size: 0.85rem;"><span>Printing & Inst.</span><strong style="color: white;"><?php echo formatCurrency($totalPrintCost + $totalMountCost); ?></strong></div>
            <div class="p-row" style="color: #cbd5e1; margin-bottom: 0.4rem; font-size: 0.85rem;">
                <?php if ($isWestBengal): 
                    $halfTax = floatval($p['tax_amount'] ?? 0) / 2;
                ?>
                    <span>CGST (9%) + SGST (9%)</span><strong style="color: white;"><?php echo formatCurrency($p['tax_amount']); ?></strong>
                <?php elseif (($p['tax_type'] ?? '') === 'no_tax'): ?>
                    <span>GST (0%)</span><strong style="color: white;">₹0.00</strong>
                <?php else: ?>
                    <span>IGST (18%)</span><strong style="color: white;"><?php echo formatCurrency($p['tax_amount']); ?></strong>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="background: rgba(255,255,255,0.1); border-radius: 10px; padding: 0.75rem 1rem; margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.75rem; font-weight: 800; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.05em;">Grand Total</span>
            <span style="font-size: 1.35rem; font-weight: 900; color: #34d399;"><?php echo formatCurrency($grandTotal); ?></span>
        </div>
    </div>
</div>

<!-- Detailed Site Table -->
<div class="card" style="padding: 0; overflow-x: auto; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9;">
        <div>
            <h3 style="font-size: 1.1rem; margin: 0; color: #0f172a; font-weight: 800;">Site Selection & Pricing Matrix</h3>
            <p style="margin: 0; font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">Review and adjust individual asset pricing for this proposal.</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem;"></i>
                <input type="text" id="tableSearch" placeholder="Search in plan..." class="p-input" style="width: 250px; padding-left: 2.5rem; height: 38px; border-radius: 8px;">
            </div>
            <?php if (canEdit('proposals')): ?>
            <button class="btn btn-secondary" onclick="openAddSiteModal()" style="height: 38px; border-radius: 8px; margin-right: 0.5rem; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;"><i class="fas fa-plus-circle text-primary"></i> Add Sites</button>
            <button class="btn btn-primary" onclick="saveAllChanges()" style="height: 38px; border-radius: 8px;"><i class="fas fa-save"></i> Save Changes</button>
            <?php endif; ?>
        </div>
    </div>
    <table class="table" style="font-size: 0.75rem; white-space: nowrap;">
        <thead style="background: #f8fafc;">
            <tr>
                <th style="width: 30px;"></th>
                <th style="width: 30px;"><input type="checkbox" id="selectAll" checked style="cursor: pointer;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></th>
                <th>Asset Details</th>
                <th>City / Code</th>
                <th>Dimensions</th>
                <th>Unit</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th style="text-align: right;">Total Amount</th>
                <th style="width: 50px; text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
             <?php $sr=0; foreach ($items as $item): 
                 $idx = $sr;
                 $sr++;
             ?>
             <tr class="draggable-row" draggable="true" data-item-id="<?php echo $item['id']; ?>">
                 <td style="vertical-align: middle; text-align: center; cursor: move; color: #cbd5e1;"><i class="fas fa-grip-vertical"></i></td>
                 <td><input type="checkbox" class="item-checkbox" value="<?php echo $item['id']; ?>" data-site-id="<?php echo $item['site_id']; ?>" data-idx="<?php echo $idx; ?>" checked style="cursor: pointer;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></td>
                <td>
                    <div style="display: flex; align-items: center;">
                        <!-- Image Thumbnail with 1-Click Stamped Copy Icon -->
                        <div style="position: relative; width: 160px; height: 100px; margin-right: 16px; flex-shrink: 0;">
                            <?php if (!empty($item['thumbnail'])): ?>
                                <img src="../../uploads/sites/<?php echo htmlspecialchars($item['thumbnail']); ?>" 
                                     onclick="openLightboxSlider('<?php echo htmlspecialchars($item['all_images'] ?? ''); ?>', '<?php echo $item['site_id']; ?>')" 
                                     style="width: 100%; height: 100%; border-radius: 10px; object-fit: cover; border: 1px solid #cbd5e1; cursor: pointer; transition: transform 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);" 
                                     onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'"
                                     alt="Site Photo">
                                <button type="button" 
                                        onclick="event.stopPropagation(); copyStampedImageToClipboard(<?php echo $idx; ?>)" 
                                        style="position: absolute; bottom: 4px; right: 4px; background: #0284c7; color: white; border: none; width: 30px; height: 30px; border-radius: 6px; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.35); transition: transform 0.15s, background 0.15s;" 
                                        onmouseover="this.style.background='#0369a1'; this.style.transform='scale(1.1)';" 
                                        onmouseout="this.style.background='#0284c7'; this.style.transform='scale(1)';"
                                        title="Copy Stamped JPEG (Ctrl+V into WhatsApp)">
                                    <i class="fas fa-camera"></i>
                                </button>
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; border-radius: 10px; background: #f8fafc; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: #94a3b8; font-weight: 700;">No Img</div>
                            <?php endif; ?>
                        </div>

                        <!-- Asset details info -->
                        <div>
                            <div style="font-weight: 800; color: #1e293b; margin-bottom: 2px; display: flex; align-items: center;">
                                <span id="site-name-<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['site_name'] ?? ''); ?></span>
                                <?php if (canEdit('proposals')): ?>
                                    <button onclick="editSiteName(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['site_name'] ?? '')); ?>')" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 0; margin-left: 6px; font-size: 0.75rem;" title="Edit Site Name"><i class="fas fa-edit"></i></button>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                                <span id="site-loc-<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['location'] ?? ''); ?></span>
                                <?php if (canEdit('proposals')): ?>
                                    <button onclick="editSiteLocation(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['location'] ?? '')); ?>')" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 0; font-size: 0.75rem;" title="Edit Location"><i class="fas fa-edit"></i></button>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.4rem; align-items: center;">
                                <span style="background: #ecfdf5; color: #059669; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;"><?php echo $item['media_type']; ?></span>
                                <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;"><?php echo $item['light_type']; ?></span>
                                <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;">
                                    <?php echo $item['owner_type']; ?>
                                    <?php if ($item['owner_type'] === 'TA' && $item['vendor_name']) echo " - " . htmlspecialchars($item['vendor_name']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 800; color: #1e293b;"><?php echo $item['city']; ?>, <?php echo $item['site_state']; ?></div>
                    <div style="font-size: 0.75rem; color: #f97316; font-weight: 800;"><?php echo $item['site_code']; ?></div>
                </td>
                <td>
                    <div style="font-weight: 700; color: #475569;"><?php echo $item['width']; ?>' x <?php echo $item['height']; ?>'</div>
                    <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo number_format($item['width'] * $item['height'] * ($item['unit'] ?? 1)); ?> SQFT</div>
                </td>
                <td>
                    <input type="number" min="1" value="<?php echo intval($item['unit'] ?? 1); ?>" oninput="markItemModified(<?php echo $item['id']; ?>, 'unit', this)" class="t-input" style="height: 32px; width: 70px; text-align: center;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
                </td>
                <td><input type="date" value="<?php echo htmlspecialchars($item['start_date'] ?? ''); ?>" onchange="markItemModified(<?php echo $item['id']; ?>, 'start_date', this)" class="t-date" style="height: 32px; width: 130px;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></td>
                <td><input type="date" value="<?php echo htmlspecialchars($item['end_date'] ?? ''); ?>" onchange="markItemModified(<?php echo $item['id']; ?>, 'end_date', this)" class="t-date" style="height: 32px; width: 130px;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></td>
                
                <td style="text-align: right;">
                    <input type="number" class="t-input" value="<?php echo (float)$item['amount']; ?>" oninput="markItemModified(<?php echo $item['id']; ?>, 'amount', this)" style="height: 32px; width: 115px; font-weight: 800; text-align: right;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                    <?php if (!empty($item['thumbnail'])): ?>
                    <button type="button" class="btn-icon" onclick="copyStampedImageToClipboard(<?php echo $idx; ?>)" style="color: #0284c7; cursor: pointer; border: none; background: transparent; font-size: 1rem; margin-right: 6px;" title="Copy Stamped JPEG (Ctrl+V into WhatsApp)"><i class="fas fa-camera"></i></button>
                    <?php endif; ?>
                    <?php if (canEdit('proposals')): ?>
                    <button class="btn-icon" onclick="deleteItem(<?php echo $item['id']; ?>)" style="color: #ef4444; cursor: pointer; border: none; background: transparent; font-size: 1rem;"><i class="fas fa-trash-alt"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f8fafc; font-weight: 800; border-top: 2px solid #e2e8f0;">
                <td></td>
                <td></td>
                <td style="color: #0f172a; font-size: 0.85rem; font-weight: 800;">Total</td>
                <td></td>
                <td style="color: #0f172a; font-size: 0.85rem; font-weight: 800;"><?php echo number_format($totalSQFT); ?> SQFT</td>
                <td></td>
                <td></td>
                <td></td>
                <td id="footerTotalItemsAmount" style="text-align: right; color: var(--primary); font-size: 0.95rem; font-weight: 800; padding-right: 0.5rem;">
                    <?php 
                    $totalItemsAmount = array_sum(array_map(function($it) { return floatval($it['amount']); }, $items));
                    echo formatCurrency($totalItemsAmount); 
                    ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Floating Selection Bar -->
<div id="selectionFloatingBar" style="display: none; position: fixed; bottom: 25px; left: 50%; transform: translateX(-50%); background: #0f172a; color: white; padding: 0.75rem 1.5rem; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.35); z-index: 2000; align-items: center; gap: 1rem; border: 1px solid rgba(255,255,255,0.15); backdrop-filter: blur(10px); animation: fadeInUp 0.3s ease;">
    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem;">
        <span id="selectedCountBadge" style="background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800;">0</span>
        <span>Sites Selected</span>
    </div>
    
    <div style="height: 24px; width: 1px; background: rgba(255,255,255,0.2);"></div>
    
    <button type="button" onclick="sendViaEmail()" style="background: #ea4335; color: white; border: none; padding: 0.55rem 1.15rem; border-radius: 30px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; transition: all 0.2s;" onmouseover="this.style.background='#d93025';" onmouseout="this.style.background='#ea4335';">
        <i class="fas fa-paper-plane"></i> Send via SMTP
    </button>
    
    <button type="button" onclick="shareWithImagesWebShare()" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; padding: 0.55rem 1.25rem; border-radius: 30px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(37,211,102,0.35);" onmouseover="this.style.transform='scale(1.04)';" onmouseout="this.style.transform='scale(1)';">
        <i class="fab fa-whatsapp" style="font-size: 1.05rem;"></i> Share Stamped JPEGs
    </button>

    <button type="button" onclick="downloadSelectedPhotosZip(true)" style="background: #0284c7; color: white; border: none; padding: 0.55rem 1.15rem; border-radius: 30px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(2,132,199,0.35);" onmouseover="this.style.background='#0369a1'; this.style.transform='scale(1.03)';" onmouseout="this.style.background='#0284c7'; this.style.transform='scale(1)';">
        <i class="fas fa-id-card"></i> Download Stamped (ZIP)
    </button>
    
    <button type="button" onclick="quickCopyFormattedDetails()" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 0.55rem 1rem; border-radius: 30px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
        <i class="fas fa-copy"></i> Copy Text
    </button>
    
    <button type="button" onclick="clearSiteSelection()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 0.85rem; font-weight: 600; padding: 0 0.25rem;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#94a3b8'" title="Deselect All">
        <i class="fas fa-times"></i>
    </button>
</div>

<style>
/* Premium Panel Styling */
.p-panel { 
    background: white; 
    padding: 1.25rem 1.4rem; 
    border-radius: 16px; 
    border: 1px solid #e2e8f0; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.3s ease;
}
.p-panel:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08); }
.p-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--secondary); margin-bottom: 0.9rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.6rem; }
.p-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; font-size: 0.88rem; color: #334155; }
.p-val-input { width: 85px; padding: 0.35rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 8px; text-align: right; font-weight: 700; font-family: inherit; transition: all 0.2s; background: #ffffff; }
.p-val-input:focus { border-color: var(--primary); outline: none; background: white; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15); }

/* Stats Boxes */
.stat-box { margin-bottom: 1rem; }
.stat-box small { display: block; font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 0.2rem; }
.stat-box strong { font-size: 1.1rem; color: #0f172a; font-weight: 800; }
.badge-tag { background: #dcfce7; color: #166534; font-size: 0.7rem; padding: 0.2rem 0.4rem; border-radius: 6px; margin-left: 0.4rem; vertical-align: middle; }

/* Table Styling */
.table { width: 100%; border-collapse: separate; border-spacing: 0; }
.table th { background: #f8fafc; padding: 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 800; border-bottom: 2px solid #f1f5f9; text-align: left; }
.table tr { transition: all 0.2s ease; }
.table tr:hover td { background: #f0fdfa; }
.table tr.row-selected td { background: #f0fdfa !important; }
.table tr.row-selected td:first-child { border-left: 4px solid var(--primary) !important; }
.table tbody tr:not(.row-selected) { opacity: 0.55; background: #fafafa; }
.table tbody tr:not(.row-selected):hover { opacity: 0.9; background: #f8fafc; }
.badge-type { background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 800; font-size: 0.7rem; color: #475569; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
.status-dot.online { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }
.t-input, .t-date { border: 1px solid #e2e8f0; padding: 0.4rem 0.6rem; border-radius: 8px; width: 110px; font-family: inherit; font-size: 0.8rem; font-weight: 600; color: var(--primary); transition: all 0.2s; }
.t-input:focus, .t-date:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }
.t-input.is-modified, .t-date.is-modified {
    background-color: #fef08a !important;
    border-color: #eab308 !important;
    color: #854d0e !important;
    font-weight: 800 !important;
    box-shadow: 0 0 0 3px rgba(234, 179, 8, 0.35) !important;
}
.table tr.row-modified td {
    background: #fefce8 !important;
}
.table tr.row-modified td:first-child {
    border-left: 4px solid #eab308 !important;
}

/* Dropdown */
/* Improved Dropdown styling */
.dropdown { position: relative; }
.dropdown-content { 
    display: none; 
    position: absolute; 
    right: 0; 
    top: 100%;
    background-color: white; 
    min-width: 200px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
    border-radius: 12px; 
    z-index: 9999; 
    border: 1px solid #e2e8f0; 
    padding: 0.5rem; 
    text-align: left; 
}
.dropdown-content a { 
    color: #334155; 
    padding: 0.7rem 0.9rem; 
    text-decoration: none !important; 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    font-size: 0.85rem; 
    font-weight: 600; 
    border-radius: 8px; 
    transition: all 0.2s; 
}
.dropdown-content i { font-size: 1rem; width: 20px; text-align: center; }
.dropdown-content a:hover { background: #f0fdfa; color: var(--primary); }
.dropdown:hover .dropdown-content { display: block; animation: slideIn 0.2s ease-out; }
@keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Ensure page containers don't clip the dropdown */
html, body { overflow-x: visible !important; }

/* Add Site Modal Styling */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 5000; align-items: center; justify-content: center; }
.p-input { width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; font-size: 0.85rem; }
.search-group { margin-bottom: 0; }
.crs-table th { font-size: 0.65rem; color: #64748b; text-transform: uppercase; padding: 1rem; text-align: left; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.crs-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; vertical-align: middle; }
.modal-pg-btn { min-width: 32px; height: 32px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 0.75rem; transition: all 0.2s; color: #475569; display: flex; align-items: center; justify-content: center; }
.modal-pg-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); background: #f0fdfa; transform: translateY(-1px); }
.modal-pg-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 10px rgba(13, 148, 136, 0.2); }
.modal-pg-btn:disabled { opacity: 0.4; cursor: not-allowed; background: #f8fafc; }
.modal-pg-dots { display: flex; align-items: center; justify-content: center; width: 24px; color: #94a3b8; font-weight: bold; }
</style>

<!-- Add Site Modal -->
<div id="addSiteModal" class="modal-overlay">
    <div class="card" style="width: 98vw; height: 98vh; max-width: none; max-height: none; padding: 1.5rem 2rem; border-radius: 16px; display: flex; flex-direction: column; margin: 0; box-sizing: border-box;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.8rem; margin-bottom: 0.8rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h3 style="margin: 0; font-weight: 800; font-size: 1.15rem; color: #0f172a;"><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add Sites to Proposal</h3>
            </div>
            <button onclick="closeAddSiteModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>
        
        <!-- Category Filter Tabs -->
        <div class="inventory-tabs" id="proposal-modal-tabs" style="margin-top: 0; margin-bottom: 0.8rem;">
            <button type="button" class="tab active" onclick="selectModalMediaTab('all', this)">All</button>
            <?php foreach ($mediaTypes as $mtype): ?>
                <button type="button" class="tab" onclick="selectModalMediaTab('<?php echo htmlspecialchars($mtype); ?>', this)">
                    <?php echo htmlspecialchars($mtype); ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!-- Search Criteria -->
        <div style="display: flex; gap: 2rem; margin-bottom: 0.8rem; align-items: center; flex-wrap: wrap;">
            <div class="search-group" style="margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.3rem; display: block; text-transform: uppercase;">Ownership</label>
                <div style="display: flex; gap: 1rem;">
                    <label style="font-size: 0.75rem; display: flex; align-items: center; gap: 0.3rem; cursor: pointer;"><input type="radio" name="modal_ownership" value="all" checked onchange="modalFetchSites(1)" style="accent-color: var(--primary);"> All</label>
                    <label style="font-size: 0.75rem; display: flex; align-items: center; gap: 0.3rem; cursor: pointer;"><input type="radio" name="modal_ownership" value="HA" onchange="modalFetchSites(1)" style="accent-color: var(--primary);"> Self</label>
                    <label style="font-size: 0.75rem; display: flex; align-items: center; gap: 0.3rem; cursor: pointer;"><input type="radio" name="modal_ownership" value="TA" onchange="modalFetchSites(1)" style="accent-color: var(--primary);"> Vendor</label>
                </div>
            </div>
            <div class="search-group" style="margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.3rem; display: block; text-transform: uppercase;">Availability</label>
                <div style="display: flex; gap: 1rem;">
                    <label style="font-size: 0.75rem; display: flex; align-items: center; gap: 0.3rem; cursor: pointer;"><input type="radio" name="modal_availability" value="available" checked onchange="modalFetchSites(1)" style="accent-color: var(--primary);"> Available</label>
                    <label style="font-size: 0.75rem; display: flex; align-items: center; gap: 0.3rem; cursor: pointer;"><input type="radio" name="modal_availability" value="all" onchange="modalFetchSites(1)" style="accent-color: var(--primary);"> All</label>
                </div>
            </div>
            <div class="search-group" style="margin-bottom: 0; min-width: 190px;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.3rem; display: block; text-transform: uppercase;">Vendor</label>
                <select id="modal-vendor" class="p-input select2 select2-filter-sm" onchange="if(this.value && document.querySelector('input[name=\'modal_ownership\'][value=\'HA\']').checked){document.querySelector('input[name=\'modal_ownership\'][value=\'TA\']').checked=true;} modalFetchSites(1);" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box; min-width: 190px; background: #ffffff; color: #1e293b; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value="">All Vendors</option>
                    <?php foreach($vendors as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Filter Dropdowns Row -->
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.8rem; align-items: flex-end; width: 100%;">
            <div class="search-group" style="flex: 2 1 200px; min-width: 150px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Search Site / Code / Area</label>
                <input type="text" id="modal-search" class="p-input" placeholder="Search by Site Name, Code, Location, City, State, Media..." oninput="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 10px; box-sizing: border-box;">
            </div>
            <div class="search-group" style="flex: 1 1 100px; min-width: 85px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">District</label>
                <select id="modal-district" class="p-input select2 select2-filter-sm" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($districts as $d): ?> <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option> <?php endforeach; ?>
                </select>
            </div>
            <select id="modal-media" style="display: none;">
                <option value="">All</option>
                <?php foreach($mediaTypes as $mt): ?> <option value="<?php echo $mt; ?>"><?php echo $mt; ?></option> <?php endforeach; ?>
            </select>
            <div class="search-group" style="flex: 1 1 100px; min-width: 85px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">State</label>
                <select id="modal-state" class="p-input select2 select2-filter-sm" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($states as $s): ?> <option value="<?php echo $s; ?>"><?php echo $s; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 100px; min-width: 85px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">City</label>
                <select id="modal-city" class="p-input select2 select2-filter-sm" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($cities as $c): ?> <option value="<?php echo $c; ?>"><?php echo $c; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 100px; min-width: 85px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Location</label>
                <select id="modal-location" class="p-input select2 select2-filter-sm" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($locations as $loc): ?> <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 90px; min-width: 80px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Light</label>
                <select id="modal-light" class="p-input select2 select2-filter-sm" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($illuminations as $il): ?> <option value="<?php echo $il; ?>"><?php echo $il; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 90px; min-width: 80px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Size</label>
                <select id="modal-size" class="p-input select2 select2-filter-sm" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($sizes as $sz): ?> <option value="<?php echo $sz; ?>"><?php echo $sz; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 0 0 auto; margin-bottom: 0; display: flex; align-items: flex-end;">
                <button class="btn btn-secondary" onclick="clearModalFilters()" style="height: 30px; font-size: 0.75rem; padding: 0 0.75rem; border-radius: 8px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; box-sizing: border-box;"><i class="fas fa-times-circle"></i> Clear</button>
            </div>
        </div>

        <!-- Section Header + Selection Counter -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
            <div style="font-weight: 800; font-size: 0.95rem; color: #0d9488; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-layer-group"></i> Select Assets Based on Your Criteria
            </div>
            <div id="modal-bucket-btn" onclick="toggleModalBucket()" style="background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: all 0.2s;">
                <i class="fas fa-shopping-basket"></i>
                Selected: <span id="modal-selected-count" style="background: #059669; color: white; padding: 0.1rem 0.5rem; border-radius: 4px; font-size: 0.65rem;">0</span>
                <span onclick="event.stopPropagation(); clearModalSelectedSites();" title="Clear Selected Sites" style="margin-left: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 900;">&times;</span>
            </div>
        </div>

        <!-- Table Container -->
        <div style="flex: 1; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: white;">
            <table class="crs-table selection-table" style="width: 100%; border-collapse: separate; border-spacing: 0 0.35rem;">
                <thead style="position: sticky; top: 0; z-index: 10; background: white;">
                    <tr style="border-bottom: 2px solid #f1f5f9;">
                        <th style="width: 40px; padding: 0.8rem 0.6rem; text-align: center;">#</th>
                        <th style="width: 40px; padding: 0.8rem 0.6rem; text-align: center;"><input type="checkbox" id="modal-select-all" onclick="toggleAllModalSites(this)" style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary);"></th>
                        <th style="width: 200px; padding: 0.8rem 0.8rem;">PREVIEW</th>
                        <th style="padding: 0.8rem 0.8rem;">CITY / CODE</th>
                        <th style="padding: 0.8rem 0.8rem;">ASSET DETAILS</th>
                        <th style="padding: 0.8rem 0.8rem;">SIZE</th>
                        <th style="padding: 0.8rem 0.8rem;">OFFER RATE</th>
                        <th style="padding: 0.8rem 0.8rem; text-align: right;">TOTAL</th>
                    </tr>
                </thead>
                <tbody id="modal-site-body">
                    <!-- Fetched dynamically -->
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Footer -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid #f1f5f9;">
            <div id="modal-pg-info" style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Loading...</div>
            <div id="modal-pg-numbers" style="display: flex; gap: 0.3rem; align-items: center;"></div>
            <button class="btn btn-primary" onclick="addSelectedSitesToProposal()" style="height: 38px; padding: 0 1.5rem; border-radius: 8px; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-plus-circle"></i> Add Selected Sites</button>
        </div>
    </div>
</div>

<!-- Simple Lightbox HTML -->
<div id="simple-lightbox" onclick="closeLightbox()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
    <div style="position: relative; text-align: center; max-width: 90%; max-height: 90vh;" onclick="event.stopPropagation();">
        <!-- Top Toolbar -->
        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 1rem; margin-bottom: 12px;">
            <button id="lightbox-download-top" onclick="downloadLightboxImage(); event.stopPropagation();" style="background: rgba(255,255,255,0.18); color: white; border: 1px solid rgba(255,255,255,0.35); padding: 8px 18px; border-radius: 8px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; backdrop-filter: blur(8px); transition: all 0.2s;" onmouseover="this.style.background='var(--primary)'; this.style.borderColor='var(--primary)'" onmouseout="this.style.background='rgba(255,255,255,0.18)'; this.style.borderColor='rgba(255,255,255,0.35)'">
                <i class="fas fa-download"></i> Download Full Size (JPEG)
            </button>
            <div onclick="closeLightbox()" style="color: white; font-size: 2.2rem; line-height: 1; cursor: pointer; opacity: 0.8; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.8">&times;</div>
        </div>

        <div style="display: flex; align-items: center; justify-content: center; position: relative;">
            <button id="lightbox-prev" onclick="changeLightboxImage(-1); event.stopPropagation();" style="position: absolute; left: -60px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 50%; width: 44px; height: 44px; font-size: 1.2rem; cursor: pointer; display: none; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <img id="lightbox-img" src="" style="max-width: 100%; max-height: 78vh; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.15);">
            
            <button id="lightbox-next" onclick="changeLightboxImage(1); event.stopPropagation();" style="position: absolute; right: -60px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 50%; width: 44px; height: 44px; font-size: 1.2rem; cursor: pointer; display: none; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <div id="lightbox-badge" style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 6px 16px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2);"></div>
        </div>
        
        <div style="margin-top: 18px; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
            <button id="lightbox-select-btn" onclick="selectLightboxImage(); event.stopPropagation();" style="background: var(--primary); color: white; border: none; padding: 0.75rem 1.4rem; border-radius: 8px; font-weight: 800; font-size: 0.85rem; cursor: pointer; box-shadow: 0 4px 15px rgba(13,148,136,0.4); display: none;">
                <i class="fas fa-check-circle"></i> Use as Primary Photo
            </button>
            <button id="lightbox-export-btn" onclick="toggleExportImage(); event.stopPropagation();" style="background: rgba(0,0,0,0.75); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 0.75rem 1.4rem; border-radius: 8px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: none;">
                <i class="far fa-square"></i> Select for PPT/PDF
            </button>
            <button id="lightbox-download-bottom" onclick="downloadLightboxImage(); event.stopPropagation();" style="background: rgba(15, 23, 42, 0.85); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 0.75rem 1.4rem; border-radius: 8px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='var(--primary)'; this.style.borderColor='var(--primary)'" onmouseout="this.style.background='rgba(15, 23, 42, 0.85)'; this.style.borderColor='rgba(255,255,255,0.25)'">
                <i class="fas fa-download"></i> Download Full Size (JPEG)
            </button>
        </div>
    </div>
</div>

<script>
const pendingChanges = {};

function markItemModified(itemId, field, elem) {
    elem.classList.add('is-modified');
    const row = elem.closest('tr');
    if (row) {
        row.classList.add('row-modified');
    }
    pendingChanges[`${itemId}_${field}`] = {
        itemId: itemId,
        field: field,
        value: elem.value,
        elem: elem,
        row: row
    };
    
    if (field === 'amount') {
        recalculateClientFooterTotal();
    }
}

function recalculateClientFooterTotal() {
    const amountInputs = document.querySelectorAll('input[oninput*="amount"]');
    let sum = 0;
    amountInputs.forEach(inp => {
        sum += parseFloat(inp.value) || 0;
    });
    const totalFooter = document.getElementById('footerTotalItemsAmount');
    if (totalFooter) {
        totalFooter.innerText = '₹' + new Intl.NumberFormat('en-IN').format(sum);
    }
}

function updatePlan(field, val) {
    fetch('../../ajax/update_proposal_global.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=<?php echo $id; ?>&field=${field}&value=${val}`
    }).then(() => location.reload());
}

function updateItem(itemId, field, val) {
    fetch('../../ajax/update_proposal_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${itemId}&field=${field}&value=${encodeURIComponent(val)}`
    }).then(() => location.reload());
}

async function saveAllChanges() {
    const saveBtn = document.querySelector('button[onclick="saveAllChanges()"]');
    const originalHtml = saveBtn ? saveBtn.innerHTML : '';
    
    const changeKeys = Object.keys(pendingChanges);
    if (changeKeys.length === 0) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'No unsaved changes.',
            showConfirmButton: false,
            timer: 1500
        });
        return;
    }

    if (saveBtn) {
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveBtn.disabled = true;
    }

    try {
        const updatePromises = changeKeys.map(key => {
            const item = pendingChanges[key];
            return fetch('../../ajax/update_proposal_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${item.itemId}&field=${item.field}&value=${encodeURIComponent(item.value)}`
            }).then(r => r.json());
        });

        await Promise.all(updatePromises);
        
        document.querySelectorAll('.is-modified').forEach(el => el.classList.remove('is-modified'));
        document.querySelectorAll('.row-modified').forEach(el => el.classList.remove('row-modified'));
        
        for (const key of changeKeys) {
            delete pendingChanges[key];
        }

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'All rate changes saved!',
            showConfirmButton: false,
            timer: 1500
        }).then(() => {
            location.reload();
        });

    } catch (err) {
        console.error("Save error:", err);
        Swal.fire('Error', 'Failed to save changes: ' + err.message, 'error');
    } finally {
        if (saveBtn) {
            saveBtn.innerHTML = originalHtml;
            saveBtn.disabled = false;
        }
    }
}

function deleteItem(itemId) {
    Swal.fire({
        title: 'Remove this site?',
        text: "This asset will be removed from the proposal.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../ajax/delete_proposal_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${itemId}`
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}

function confirmProposal(id) {
    try {
        const checkedItems = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);
        
        if (checkedItems.length === 0) {
            Swal.fire('Warning', 'Please select at least one site to convert to a booking.', 'warning');
            return;
        }

    Swal.fire({
        title: 'Confirm & Convert?',
        text: `This will convert the ${checkedItems.length} selected sites into an active booking.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Yes, Convert to Booking'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../ajax/confirm_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proposal_id: id, item_ids: checkedItems })
            }).then(r => r.json()).then(res => {
                if(res.success) {
                    Swal.fire('Confirmed!', 'Booking is now live.', 'success').then(() => location.href='../operations/bookings.php');
                } else {
                    Swal.fire('Error', res.message || 'Failed to convert proposal.', 'error');
                }
            }).catch((err) => {
                Swal.fire('Error', 'Server error occurred: ' + err, 'error');
            });
        }
    });
    } catch (e) {
        alert("JavaScript Error: " + e);
        console.error(e);
    }
}

const proposalSitesData = <?php 
    $exportItems = [];
    foreach ($items as $idx => $it) {
        $exportItems[] = [
            'item_id' => $it['id'],
            'site_id' => $it['site_id'],
            'site_code' => $it['site_code'],
            'city' => $it['city'],
            'name' => $it['site_name'],
            'location' => $it['location'],
            'width' => $it['width'],
            'height' => $it['height'],
            'sqft' => $it['width'] * $it['height'],
            'type' => $it['media_type'],
            'light_type' => $it['light_type'] ?? '',
            'price' => (float)$it['amount'],
            'start_date' => $it['start_date'] ?? '',
            'end_date' => $it['end_date'] ?? '',
            'vendor_id' => $it['vendor_id'] ?? '',
            'vendor_name' => $it['vendor_name'] ?? '',
            'vendor_email' => $it['vendor_email'] ?? '',
            'photo' => $it['thumbnail'] ?? ''
        ];
    }
    echo json_encode($exportItems);
?>;

function getSelectedSitesData() {
    const checkedCbs = document.querySelectorAll('.item-checkbox:checked');
    const selected = [];
    checkedCbs.forEach(cb => {
        const idx = parseInt(cb.getAttribute('data-idx'));
        if (!isNaN(idx) && proposalSitesData[idx]) {
            selected.push(proposalSitesData[idx]);
        }
    });
    return selected;
}

function updateSelectionFloatingBar() {
    const checkedCbs = document.querySelectorAll('.item-checkbox:checked');
    const count = checkedCbs.length;
    const bar = document.getElementById('selectionFloatingBar');
    const badge = document.getElementById('selectedCountBadge');
    if (badge) badge.innerText = count;
    if (bar) {
        if (count > 0) {
            bar.style.display = 'flex';
        } else {
            bar.style.display = 'none';
        }
    }
}

function clearSiteSelection() {
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = false;
        const row = cb.closest('tr');
        if (row) row.classList.remove('row-selected');
    });
    updateSelectionFloatingBar();
}

function downloadSelectedPhotosZip(isStamped = true) {
    const selected = getSelectedSitesData();
    const sitesToUse = selected.length > 0 ? selected : proposalSitesData;
    if (sitesToUse.length === 0) {
        Swal.fire('No Sites Selected', 'Please select at least one site to download photos.', 'warning');
        return;
    }
    const siteIds = sitesToUse.map(s => s.site_id);
    window.location.href = `../../ajax/download_sites_zip.php?ids=${siteIds.join(',')}&stamped=${isStamped ? 1 : 0}`;
}

async function copyStampedImageToClipboard(idx) {
    const s = proposalSitesData[idx];
    if (!s) return;
    if (!s.photo) {
        Swal.fire('No Photo', 'This site does not have a photo.', 'info');
        return;
    }
    
    try {
        const promiseOfBlob = new Promise(async (resolve, reject) => {
            try {
                const imgUrl = `${window.location.origin}/uploads/sites/${s.photo}`;
                const resp = await fetch(imgUrl);
                if (!resp.ok) throw new Error('Could not load image');
                const blob = await resp.blob();
                
                const img = new Image();
                img.crossOrigin = 'anonymous';
                const objectUrl = URL.createObjectURL(blob);
                img.src = objectUrl;
                
                await new Promise((res, rej) => {
                    img.onload = res;
                    img.onerror = rej;
                });
                
                const srcW = img.naturalWidth || 1200;
                const srcH = img.naturalHeight || 800;
                const targetW = Math.max(1200, srcW);
                const scale = targetW / srcW;
                const targetH = Math.round(srcH * scale);
                const footerH = Math.max(165, Math.round(targetW * 0.145));
                
                const canvas = document.createElement('canvas');
                canvas.width = targetW;
                canvas.height = targetH + footerH;
                const ctx = canvas.getContext('2d');
                
                // Background
                ctx.fillStyle = '#0b1220';
                ctx.fillRect(0, 0, targetW, targetH + footerH);
                
                // Draw photo
                ctx.drawImage(img, 0, 0, targetW, targetH);
                URL.revokeObjectURL(objectUrl);
                
                // Top Accent Divider Bar (Sky blue with Amber corner)
                ctx.fillStyle = '#0ea5e9';
                ctx.fillRect(0, targetH, targetW, 6);
                ctx.fillStyle = '#f59e0b';
                ctx.fillRect(0, targetH, Math.round(targetW * 0.18), 6);
                
                // Banner background
                ctx.fillStyle = '#0b1220';
                ctx.fillRect(0, targetH + 6, targetW, footerH - 6);
                
                const padX = Math.round(targetW * 0.035);
                const startY = targetH + Math.round(targetW * 0.018);
                
                // --- LINE 1: CITY NAME (Very Big, Bold, Crisp White) ---
                const cityText = (s.city || 'CITY').toUpperCase();
                ctx.fillStyle = '#ffffff';
                ctx.font = `bold ${Math.round(targetW * 0.027)}px sans-serif`;
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(cityText, padX, startY + Math.round(targetW * 0.022));
                
                // --- LINE 2: LOCATION / ASSET (Thana More - Big & Clean) ---
                const row2Y = startY + Math.round(targetW * 0.062);
                ctx.font = `bold ${Math.round(targetW * 0.021)}px sans-serif`;
                ctx.fillStyle = '#cbd5e1';
                const nameStr = (s.name || '').trim();
                const locStr = (s.location || '').trim();
                let locVal = nameStr || locStr || 'Main Location';
                if (locVal.length > 65) locVal = locVal.substring(0, 62) + '...';
                ctx.fillText(locVal, padX, row2Y);
                
                // --- LINE 3: SIZE (Size: 20' x 18' - Vibrant Lime Green) ---
                const row3Y = startY + Math.round(targetW * 0.104);
                ctx.font = `bold ${Math.round(targetW * 0.022)}px sans-serif`;
                ctx.fillStyle = '#cbd5e1';
                ctx.fillText('Size: ', padX, row3Y);
                const sizeLabelMetrics = ctx.measureText('Size: ');
                
                const sizeVal = `${s.width}' x ${s.height}'`;
                ctx.fillStyle = '#4ade80';
                ctx.fillText(sizeVal, padX + sizeLabelMetrics.width, row3Y);
                
                canvas.toBlob((pngBlob) => {
                    if (pngBlob) resolve(pngBlob);
                    else reject(new Error('Canvas toBlob failed'));
                }, 'image/png');
            } catch (err) {
                reject(err);
            }
        });

        if (typeof ClipboardItem !== 'undefined' && navigator.clipboard && navigator.clipboard.write) {
            const item = new ClipboardItem({ 'image/png': promiseOfBlob });
            await navigator.clipboard.write([item]);
            
            Swal.fire({
                icon: 'success',
                title: 'Photo Copied!',
                html: `Stamped photo for <strong>${s.site_code} (${s.city})</strong> with all details is copied to clipboard.<br><br>👉 Go to WhatsApp Web chat and press <kbd style="background:#e2e8f0;border:1px solid #cbd5e1;padding:3px 8px;border-radius:6px;font-weight:800;font-size:1rem;color:#0f172a;">Ctrl + V</kbd> to paste!`,
                showCancelButton: true,
                confirmButtonText: '<i class="fab fa-whatsapp"></i> Open WhatsApp Web',
                cancelButtonText: 'Done',
                confirmButtonColor: '#25D366'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('https://web.whatsapp.com', '_blank');
                }
            });
        } else {
            const blob = await promiseOfBlob;
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Stamped_${s.site_code}_${s.city}.png`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            Swal.fire('Downloaded', 'Photo downloaded with info stamped banner.', 'success');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Could not generate stamped photo: ' + e.message, 'error');
    }
}

async function shareWithImagesWebShare() {
    const selected = getSelectedSitesData();
    const sitesToUse = selected.length > 0 ? selected : proposalSitesData;
    
    const validSites = sitesToUse.filter(s => s.photo);
    if (validSites.length === 0) {
        Swal.fire('No Photos', 'Proposal sites do not have photos to share.', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Preparing Stamped Photos...',
        html: `Generating high-resolution stamped cards for <b>${validSites.length}</b> site(s)...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    try {
        const filesArray = [];
        const batchSize = 6;
        for (let i = 0; i < validSites.length; i += batchSize) {
            const batch = validSites.slice(i, i + batchSize);
            const promises = batch.map(async (s) => {
                try {
                    const cardUrl = `${window.location.origin}/ajax/get_site_card_jpeg.php?id=${s.site_id}`;
                    const resp = await fetch(cardUrl);
                    if (resp.ok) {
                        const blob = await resp.blob();
                        const safeName = `${s.site_code || 'SITE'}_${(s.city || '').replace(/[^a-zA-Z0-9]/g, '')}_Card.jpg`;
                        return new File([blob], safeName, { type: 'image/jpeg' });
                    }
                } catch(e) {}
                return null;
            });
            const results = await Promise.all(promises);
            results.forEach(f => { if (f) filesArray.push(f); });
        }
        
        if (filesArray.length === 0) {
            throw new Error('Could not generate any photo cards.');
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Photos Ready to Share!',
            html: `Prepared <strong>${filesArray.length}</strong> stamped photo cards for WhatsApp.<br><br>Click the button below to send:`,
            confirmButtonText: '<i class="fab fa-whatsapp"></i> Share on WhatsApp Now',
            confirmButtonColor: '#25D366',
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-id-card"></i> Download ZIP',
            cancelButtonColor: '#0284c7',
            showCloseButton: true
        }).then(async (result) => {
            if (result.isConfirmed) {
                if (navigator.canShare && navigator.canShare({ files: filesArray })) {
                    try {
                        await navigator.share({
                            files: filesArray
                        });
                    } catch(shareErr) {
                        if (shareErr.name !== 'AbortError') {
                            downloadSelectedPhotosZip(true);
                        }
                    }
                } else {
                    downloadSelectedPhotosZip(true);
                }
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                downloadSelectedPhotosZip(true);
            }
        });
    } catch (err) {
        if (err.name !== 'AbortError') {
            Swal.fire('Sharing Failed', err.message || 'Could not complete sharing.', 'error');
        }
    }
}

function quickCopyFormattedDetails() {
    const selected = getSelectedSitesData();
    const sitesToUse = selected.length > 0 ? selected : proposalSitesData;
    if (sitesToUse.length === 0) return;
    
    let text = `🚀 *MEDIA INVENTORY PROPOSAL / SITE DETAILS*\n━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
    sitesToUse.forEach((s, i) => {
        text += `📍 *${i+1}. ${s.city ? s.city.toUpperCase() : 'LOCATION'}* (${s.site_code})\n`;
        text += `🏢 *Asset Details:* ${s.name || s.location || 'N/A'}\n`;
        text += `📐 *Size:* ${s.width}' x ${s.height}' (${s.sqft} SQFT)\n`;
        text += `💰 *Rate:* ₹${Number(s.price).toLocaleString('en-IN')}\n\n`;
    });
    text += `━━━━━━━━━━━━━━━━━━━━━━━\n*Sudha Creative & Advertising*`;
    
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Proposal details copied to clipboard.',
            timer: 1500,
            showConfirmButton: false
        });
    });
}

async function sendViaEmail() {
    const selected = getSelectedSitesData();
    const sitesToUse = selected.length > 0 ? selected : proposalSitesData;
    
    const validSites = sitesToUse.filter(s => s.photo);
    if (validSites.length === 0) {
        Swal.fire('No Photos', 'Proposal sites do not have photos to send.', 'warning');
        return;
    }
    
    const clientEmail = <?php echo json_encode($p['client_email'] ?? ''); ?>;
    const campaignName = <?php echo json_encode($p['campaign_name'] ?? 'Media Campaign'); ?>;
    const defaultSubject = `Site Photos - ${campaignName}`;
    const siteIds = validSites.map(s => s.site_id).join(',');

    function formatDateNice(dStr) {
        if (!dStr || dStr === '0000-00-00') return '';
        const d = new Date(dStr);
        if (isNaN(d.getTime())) return dStr;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    let pStart = <?php echo json_encode((!empty($p['start_date']) && $p['start_date'] !== '0000-00-00') ? date('d M Y', strtotime($p['start_date'])) : ''); ?>;
    let pEnd = <?php echo json_encode((!empty($p['end_date']) && $p['end_date'] !== '0000-00-00') ? date('d M Y', strtotime($p['end_date'])) : ''); ?>;

    if (!pStart && validSites[0]?.start_date) {
        pStart = formatDateNice(validSites[0].start_date);
    }
    if (!pEnd && validSites[0]?.end_date) {
        pEnd = formatDateNice(validSites[0].end_date);
    }

    const startDateText = pStart || '{{Start Date}}';
    const endDateText = pEnd || '{{End Date}}';

    const totalAmtVal = validSites.reduce((sum, s) => sum + (Number(s.price) || 0), 0);
    const amountText = totalAmtVal > 0 ? Number(totalAmtVal).toLocaleString('en-IN') : '{{Amount}}';

    const defaultTemplateText = `Dear Sir/Madam,

Please be informed that the attached site has been booked for the period from ${startDateText} to ${endDateText} at a rate of ₹${amountText}.

Please find the booking details and stamped photos attached for your reference.

Thank you.

Regards,

Sudha Creative`;

    // Fetch Vendor Emails from selected sites
    const vendorEmails = [...new Set(validSites.map(s => (s.vendor_email || '').trim()).filter(Boolean))];
    const initialEmail = vendorEmails.join(', ') || clientEmail || '';

    const { value: formValues } = await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#ea4335;"><i class="fas fa-paper-plane"></i> Send Stamped Photos via Email</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Recipient Email Address (Vendor) <span style="color:#ef4444;">*</span></label>
                    <input id="smtp_recipient_email" type="text" value="${initialEmail}" placeholder="vendor@example.com" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Email Subject</label>
                    <input id="smtp_subject" type="text" value="${defaultSubject}" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Message Body</label>
                    <textarea id="smtp_message" style="width: 100%; box-sizing: border-box; height: 145px; font-size: 0.86rem; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; resize: vertical; line-height: 1.45; font-family: inherit;">${defaultTemplateText}</textarea>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; color: #166534; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-paperclip" style="color: #16a34a; font-size: 1rem;"></i>
                    <span><b>${validSites.length} Stamped Photo Card(s)</b> will be attached directly.</span>
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Email (SMTP)',
        confirmButtonColor: '#0d9488',
        cancelButtonText: '<i class="fab fa-google"></i> Open in Gmail Web',
        cancelButtonColor: '#ea4335',
        showCloseButton: true,
        preConfirm: () => {
            const emailVal = document.getElementById('smtp_recipient_email').value.trim();
            const subjectVal = document.getElementById('smtp_subject').value.trim();
            const msgVal = document.getElementById('smtp_message').value.trim();
            if (!emailVal) {
                Swal.showValidationMessage('Please enter a valid recipient email address.');
                return false;
            }
            return { email: emailVal, subject: subjectVal, message: msgVal };
        }
    });

    if (formValues) {
        Swal.fire({
            title: 'Sending Stamped Photos...',
            html: `Generating stamped JPEG cards and sending via SMTP to <b>${formValues.email}</b>...`,
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const formData = new FormData();
            formData.append('proposal_id', '<?php echo $id; ?>');
            formData.append('site_ids', siteIds);
            formData.append('email', formValues.email);
            formData.append('subject', formValues.subject);
            formData.append('message', formValues.message);

            const resp = await fetch('ajax_send_stamped_email.php', {
                method: 'POST',
                body: formData
            });
            const rawText = await resp.text();
            let res;
            try {
                res = JSON.parse(rawText);
            } catch(jsonErr) {
                const cleanError = rawText.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(cleanError || 'Invalid response from server.');
            }

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Email Sent Successfully!',
                    html: `<b>${res.message || 'Email sent successfully!'}</b><br><br><span style="color:#64748b;font-size:0.85rem;">Sender: sudhacreativemalda@gmail.com</span>`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Sending Failed',
                    text: res.message || 'Could not send email via SMTP server.'
                });
            }
        } catch (err) {
            Swal.fire('SMTP Error', err.message || 'Network error occurred while communicating with SMTP server.', 'error');
        }
    } else if (Swal.getDismissReason() === Swal.DismissReason.cancel) {
        // Compose in Gmail Web fallback
        const emailInput = document.getElementById('smtp_recipient_email')?.value.trim() || clientEmail;
        const subjectInput = document.getElementById('smtp_subject')?.value.trim() || defaultSubject;
        const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailInput)}&su=${encodeURIComponent(subjectInput)}`;
        window.open(gmailUrl, '_blank');
    }
}

// Keep alias for backwards compatibility
const sendViaGmail = sendViaEmail;

document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('selectAll');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    
    function updateRowHighlight(cb) {
        const row = cb.closest('tr');
        if (row) {
            if (cb.checked) {
                row.classList.add('row-selected');
            } else {
                row.classList.remove('row-selected');
            }
        }
    }

    if(selectAll) {
        selectAll.addEventListener('change', (e) => {
            itemCheckboxes.forEach(cb => {
                cb.checked = e.target.checked;
                updateRowHighlight(cb);
            });
            updateSelectionFloatingBar();
        });
    }
    
    itemCheckboxes.forEach(cb => {
        // Initial highlight on load
        updateRowHighlight(cb);
        
        cb.addEventListener('change', () => {
            updateRowHighlight(cb);
            if(document.querySelectorAll('.item-checkbox:checked').length === itemCheckboxes.length) {
                selectAll.checked = true;
            } else {
                selectAll.checked = false;
            }
            updateSelectionFloatingBar();
        });
    });

    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('table.table tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});

// Modal Logic for Adding Sites
const existingProposalSiteIds = <?php echo json_encode(array_column($items, 'site_id')); ?>;
let modalCurrentPage = 1;
const modalPageSize = 10;
let modalSelectedSites = [];
let currentModalSites = [];
let showingBucketOnly = false;

function updateModalSelectedCount() {
    const el = document.getElementById('modal-selected-count');
    if (el) el.innerText = modalSelectedSites.length;
}

function clearModalSelectedSites() {
    modalSelectedSites = [];
    updateModalSelectedCount();
    if (showingBucketOnly) {
        toggleModalBucket();
    } else {
        renderModalSites(currentModalSites);
    }
}

function toggleModalBucket() {
    showingBucketOnly = !showingBucketOnly;
    const btn = document.getElementById('modal-bucket-btn');
    
    if (showingBucketOnly) {
        if (btn) {
            btn.style.background = '#047857';
            btn.style.color = 'white';
        }
        const infoEl = document.getElementById('modal-pg-info');
        const numEl = document.getElementById('modal-pg-numbers');
        if (infoEl) infoEl.style.display = 'none';
        if (numEl) numEl.style.display = 'none';
        renderModalSites(modalSelectedSites, true); // true = bucket mode
    } else {
        if (btn) {
            btn.style.background = '#ecfdf5';
            btn.style.color = '#059669';
        }
        const infoEl = document.getElementById('modal-pg-info');
        const numEl = document.getElementById('modal-pg-numbers');
        if (infoEl) infoEl.style.display = 'block';
        if (numEl) numEl.style.display = 'flex';
        renderModalSites(currentModalSites);
    }
}

function openAddSiteModal() {
    document.getElementById('addSiteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    modalFetchSites(1);
}

function closeAddSiteModal() {
    document.getElementById('addSiteModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function clearModalFilters() {
    document.getElementById('modal-search').value = '';
    document.getElementById('modal-media').value = '';
    if (document.getElementById('modal-district')) document.getElementById('modal-district').value = '';
    document.getElementById('modal-state').value = '';
    document.getElementById('modal-city').value = '';
    document.getElementById('modal-location').value = '';
    document.getElementById('modal-light').value = '';
    if (document.getElementById('modal-size')) document.getElementById('modal-size').value = '';
    if (document.getElementById('modal-vendor')) document.getElementById('modal-vendor').value = '';
    
    // Reset active tab
    const tabs = document.querySelectorAll('#proposal-modal-tabs .tab');
    tabs.forEach(t => t.classList.remove('active'));
    const allTab = document.querySelector('#proposal-modal-tabs .tab[onclick*="\'all\'"]');
    if (allTab) allTab.classList.add('active');
    
    if (window.jQuery && $.fn.select2) {
        $('#modal-district, #modal-state, #modal-city, #modal-location, #modal-light, #modal-size, #modal-vendor').val('').trigger('change');
    }
    
    const ownAll = document.querySelector('input[name="modal_ownership"][value="all"]');
    if (ownAll) ownAll.checked = true;
    const availAvail = document.querySelector('input[name="modal_availability"][value="available"]');
    if (availAvail) availAvail.checked = true;
    
    if (showingBucketOnly) toggleModalBucket(); // Exit bucket view when clearing filters
    modalFetchSites(1);
}

function selectModalMediaTab(mtype, btn) {
    const tabs = document.querySelectorAll('#proposal-modal-tabs .tab');
    tabs.forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    
    const select = document.getElementById('modal-media');
    if (select) {
        select.value = mtype === 'all' ? '' : mtype;
    }
    modalFetchSites(1);
}

function modalFetchSites(page) {
    modalCurrentPage = page;
    const q = document.getElementById('modal-search') ? document.getElementById('modal-search').value : '';
    const media = document.getElementById('modal-media') ? document.getElementById('modal-media').value : '';
    const district = document.getElementById('modal-district') ? document.getElementById('modal-district').value : '';
    const state = document.getElementById('modal-state') ? document.getElementById('modal-state').value : '';
    const city = document.getElementById('modal-city') ? document.getElementById('modal-city').value : '';
    const loc = document.getElementById('modal-location') ? document.getElementById('modal-location').value : '';
    const light = document.getElementById('modal-light') ? document.getElementById('modal-light').value : '';
    const size = document.getElementById('modal-size') ? document.getElementById('modal-size').value : '';
    const vendor = document.getElementById('modal-vendor') ? document.getElementById('modal-vendor').value : '';
    const ownRadio = document.querySelector('input[name="modal_ownership"]:checked');
    const ownership = ownRadio ? ownRadio.value : 'all';
    const availRadio = document.querySelector('input[name="modal_availability"]:checked');
    const availability = availRadio ? availRadio.value : 'available';

    const url = `../../ajax/fetch_sites.php?page=${page}&limit=${modalPageSize}&q=${encodeURIComponent(q)}&media=${encodeURIComponent(media)}&district=${encodeURIComponent(district)}&state=${encodeURIComponent(state)}&city=${encodeURIComponent(city)}&location=${encodeURIComponent(loc)}&light=${encodeURIComponent(light)}&size=${encodeURIComponent(size)}&vendor=${encodeURIComponent(vendor)}&ownership=${encodeURIComponent(ownership)}&availability=${encodeURIComponent(availability)}`;

    fetch(url)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            currentModalSites = res.sites;
            if (!showingBucketOnly) {
                renderModalSites(currentModalSites);
                renderModalPagination(res.total);
            } else {
                renderModalPagination(res.total);
            }
        }
    });
}

function updateModalSitePrice(siteId, newRate) {
    const rate = parseFloat(newRate) || 0;
    const siteInSelected = modalSelectedSites.find(s => s.id == siteId);
    if (siteInSelected) {
        siteInSelected.card_rate = rate;
        siteInSelected.offer_rate = rate;
    }
    const siteInCurrent = currentModalSites.find(s => s.id == siteId);
    if (siteInCurrent) {
        siteInCurrent.card_rate = rate;
        siteInCurrent.offer_rate = rate;
    }
    const isChecked = modalSelectedSites.some(s => s.id == siteId);
    const totalEl = document.getElementById('modal-total-' + siteId);
    if (totalEl) {
        totalEl.innerText = '₹' + (isChecked ? rate : 0).toLocaleString();
    }
}

function toggleModalSite(cb, siteId) {
    const siteObj = showingBucketOnly 
        ? modalSelectedSites.find(s => s.id == siteId) 
        : currentModalSites.find(s => s.id == siteId);
        
    if (!siteObj) return;

    const rateInput = document.querySelector(`#modal-row-${siteId} .modal-sale-rate-input`);
    const rateVal = rateInput ? (parseFloat(rateInput.value) || 0) : parseFloat(siteObj.offer_rate !== undefined ? siteObj.offer_rate : (siteObj.card_rate || 0));

    if (cb.checked) {
        if (!modalSelectedSites.find(s => s.id == siteId)) {
            const sCopy = {
                ...siteObj, 
                card_rate: rateVal,
                offer_rate: rateVal,
                image: siteObj.thumbnail || '', 
                selected_images: (siteObj.thumbnail ? [siteObj.thumbnail] : [])
            };
            modalSelectedSites.push(sCopy);
        }
        const row = document.getElementById('modal-row-' + siteId);
        if (row) {
            row.style.background = '#f0fdfa';
            row.style.borderLeft = '4px solid #0d9488';
        }
        const totalEl = document.getElementById('modal-total-' + siteId);
        if (totalEl) {
            totalEl.innerText = '₹' + rateVal.toLocaleString();
        }
    } else {
        modalSelectedSites = modalSelectedSites.filter(s => s.id != siteId);
        const row = document.getElementById('modal-row-' + siteId);
        if (row) {
            row.style.background = 'white';
            row.style.borderLeft = '4px solid transparent';
        }
        const totalEl = document.getElementById('modal-total-' + siteId);
        if (totalEl) {
            totalEl.innerText = '₹0';
        }
        if (showingBucketOnly && row) {
            row.remove();
        }
    }
    updateModalSelectedCount();
    
    // Update select all checkbox state
    const checkboxes = document.querySelectorAll('.modal-site-checkbox:not(:disabled)');
    const checkedCount = document.querySelectorAll('.modal-site-checkbox:not(:disabled):checked').length;
    const selectAllEl = document.getElementById('modal-select-all');
    if (selectAllEl) {
        selectAllEl.checked = (checkboxes.length > 0 && checkboxes.length === checkedCount);
    }
}

function toggleAllModalSites(source) {
    const checkboxes = document.querySelectorAll('.modal-site-checkbox:not(:disabled)');
    checkboxes.forEach(cb => {
        if (cb.checked !== source.checked) {
            cb.checked = source.checked;
            const siteId = parseInt(cb.value);
            
            const siteObj = showingBucketOnly 
                ? modalSelectedSites.find(s => s.id == siteId) 
                : currentModalSites.find(s => s.id == siteId);
                
            if (!siteObj) return;

            const rateInput = document.querySelector(`#modal-row-${siteId} .modal-sale-rate-input`);
            const rateVal = rateInput ? (parseFloat(rateInput.value) || 0) : parseFloat(siteObj.offer_rate !== undefined ? siteObj.offer_rate : (siteObj.card_rate || 0));

            if (cb.checked) {
                if (!modalSelectedSites.find(ms => ms.id === siteId)) {
                    const sCopy = {
                        ...siteObj, 
                        card_rate: rateVal,
                        offer_rate: rateVal,
                        image: siteObj.thumbnail || '', 
                        selected_images: (siteObj.thumbnail ? [siteObj.thumbnail] : [])
                    };
                    modalSelectedSites.push(sCopy);
                }
                const row = document.getElementById('modal-row-' + siteId);
                if (row) {
                    row.style.background = '#f0fdfa';
                    row.style.borderLeft = '4px solid #0d9488';
                }
                const totalEl = document.getElementById('modal-total-' + siteId);
                if (totalEl) {
                    totalEl.innerText = '₹' + rateVal.toLocaleString();
                }
            } else {
                modalSelectedSites = modalSelectedSites.filter(ms => ms.id !== siteId);
                const row = document.getElementById('modal-row-' + siteId);
                if (row) {
                    row.style.background = 'white';
                    row.style.borderLeft = '4px solid transparent';
                }
                const totalEl = document.getElementById('modal-total-' + siteId);
                if (totalEl) {
                    totalEl.innerText = '₹0';
                }
                if (showingBucketOnly && row) {
                    row.remove();
                }
            }
        }
    });
    updateModalSelectedCount();
}

function renderModalSites(sites, isBucket = false) {
    const body = document.getElementById('modal-site-body');
    body.innerHTML = '';
    
    if (sites.length === 0) {
        body.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:3rem; color:#94a3b8; font-weight:600;"><i class="fas fa-search" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.5;"></i>No sites found matching your criteria.</td></tr>';
        return;
    }

    const startNo = isBucket ? 1 : (modalCurrentPage - 1) * modalPageSize + 1;

    sites.forEach((s, idx) => {
        const isAlreadyBooked = existingProposalSiteIds.includes(parseInt(s.id));
        const savedSite = modalSelectedSites.find(ms => ms.id == s.id);
        const isChecked = isAlreadyBooked || !!savedSite;
        const thumbToUse = savedSite ? savedSite.image : (s.thumbnail ? s.thumbnail : '');
        const thumbUrl = thumbToUse ? '../../uploads/sites/' + thumbToUse : 'https://via.placeholder.com/150x95?text=No+Img';
        const cardRate = parseFloat(s.card_rate || 0);
        const offerRate = savedSite && savedSite.offer_rate !== undefined ? savedSite.offer_rate : (s.offer_rate !== undefined ? s.offer_rate : cardRate);
        const sqft = (parseInt(s.width) || 0) * (parseInt(s.height) || 0);
        const itemTotal = isChecked ? offerRate : 0;

        let imgHtml = '';
        if (s.thumbnail) {
            const imgList = s.all_images ? s.all_images.split(',') : [s.thumbnail];
            const imgCount = imgList.length;
            imgHtml = `
                <div style="position: relative; width: 190px; height: 120px;">
                    <img id="modal-thumb-${s.id}" src="${thumbUrl}" onclick="openLightboxSlider('${s.all_images || s.thumbnail}', '${s.id}')" style="width: 100%; height: 100%; border-radius: 12px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; box-shadow: 0 4px 8px rgba(0,0,0,0.08); transition: transform 0.2s;">
                    ${imgCount > 1 ? `<div style="position: absolute; bottom: 6px; right: 6px; background: rgba(0,0,0,0.75); color: white; font-size: 0.65rem; padding: 3px 6px; border-radius: 5px; font-weight: 800; backdrop-filter: blur(2px);"><i class="fas fa-images"></i> ${imgCount}</div>` : ''}
                </div>
            `;
        } else {
            imgHtml = `<div style="width: 190px; height: 120px; border-radius: 12px; background: #f8fafc; border: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: #94a3b8; font-weight: 700;">No Img</div>`;
        }

        let badgeHtml = `
            <span style="background:#ecfdf5; color:#059669; padding:0.15rem 0.45rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.type}</span>
            <span style="background:#f1f5f9; color:#475569; padding:0.15rem 0.45rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.light_type || 'NL'}</span>
            <span style="background:#f1f5f9; color:#475569; padding:0.15rem 0.45rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">
                ${s.owner_type || ''}${s.owner_type === 'TA' && s.vendor_name ? ' - ' + s.vendor_name : ''}
            </span>
        `;
        if (isAlreadyBooked) {
            badgeHtml += `
                <span style="background:#e0f2fe; color:#0369a1; padding:0.15rem 0.45rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase; margin-left: 0.2rem;">Already In Plan</span>
            `;
        }

        const row = document.createElement('tr');
        row.id = 'modal-row-' + s.id;
        row.style.background = isAlreadyBooked ? '#f8fafc' : (isChecked ? '#f0fdfa' : 'white');
        row.style.transition = 'all 0.15s';
        row.style.borderLeft = isAlreadyBooked ? '4px solid #cbd5e1' : (isChecked ? '4px solid #0d9488' : '4px solid transparent');
        row.innerHTML = `
            <td style="text-align:center; padding:0.6rem 0.8rem; font-weight:700; color:#64748b; font-size:0.75rem;">
                ${startNo + idx}
            </td>
            <td style="text-align:center; padding:0.6rem 0.8rem;">
                <input type="checkbox" class="modal-site-checkbox" value="${s.id}" ${isChecked ? 'checked' : ''} ${isAlreadyBooked ? 'disabled' : ''} onclick="toggleModalSite(this, ${s.id})" style="width:18px; height:18px; accent-color:var(--primary); cursor: ${isAlreadyBooked ? 'not-allowed' : 'pointer'};">
            </td>
            <td style="padding:0.6rem 0.8rem;">
                ${imgHtml}
            </td>
            <td style="padding:0.6rem 0.8rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.city ? s.city + ',' : ''}</div>
                <div style="color:#f97316; font-size:0.65rem; font-weight:800;">${s.site_code}</div>
            </td>
            <td style="padding:0.6rem 0.8rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.name}</div>
                <div style="font-size:0.65rem; color:#64748b; margin-bottom:3px; line-height:1.2;">${s.location || ''}</div>
                <div style="display: flex; gap: 0.3rem; align-items: center; flex-wrap: wrap;">
                    ${badgeHtml}
                </div>
            </td>
            <td style="padding:0.6rem 0.8rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.width}' x ${s.height}'</div>
                <div style="font-size:0.65rem; color:#94a3b8; font-weight:700;">${sqft.toLocaleString()} SQFT</div>
            </td>
            <td style="padding:0.6rem 0.8rem;">
                <div style="font-size:0.5rem; color:var(--primary); font-weight:900; text-transform:uppercase; margin-bottom:2px;">Offer Rate</div>
                <input type="number" class="p-input modal-sale-rate-input" 
                       value="${offerRate}" 
                       oninput="updateModalSitePrice('${s.id}', this.value)"
                       style="width:85px; height:26px; font-size:0.75rem; font-weight:800; border-radius:5px; padding:0 0.4rem; border:1px solid #e2e8f0; background:white;">
            </td>
            <td style="padding:0.6rem 0.8rem; text-align:right;">
                <div style="font-size:0.55rem; color:#64748b; font-weight:800; text-transform:uppercase;">Total</div>
                <div id="modal-total-${s.id}" style="font-weight:900; color:var(--primary); font-size:0.85rem;">
                    ₹${Math.round(itemTotal).toLocaleString()}
                </div>
            </td>
        `;
        body.appendChild(row);
    });
    
    // Update select all state
    const checkboxes = document.querySelectorAll('.modal-site-checkbox:not(:disabled)');
    const checkedCount = document.querySelectorAll('.modal-site-checkbox:not(:disabled):checked').length;
    const selectAllEl = document.getElementById('modal-select-all');
    if (selectAllEl) {
        selectAllEl.checked = (checkboxes.length > 0 && checkboxes.length === checkedCount);
    }
}

function renderModalPagination(total) {
    const totalPages = Math.ceil(total / modalPageSize);
    const start = total === 0 ? 0 : (modalCurrentPage - 1) * modalPageSize + 1;
    const end = Math.min(modalCurrentPage * modalPageSize, total);

    const infoEl = document.getElementById('modal-pg-info');
    if (infoEl) {
        infoEl.innerHTML = `Showing <span style="color:#0f172a; font-weight:800;">${start}</span> to <span style="color:#0f172a; font-weight:800;">${end}</span> of <span style="color:#0f172a; font-weight:800;">${total}</span> assets matching criteria`;
    }

    const container = document.getElementById('modal-pg-numbers');
    if (!container) return;
    container.innerHTML = '';

    if (totalPages <= 1) return;

    const createBtn = (content, disabled, active, onClick) => {
        const btn = document.createElement('button');
        btn.className = 'modal-pg-btn' + (active ? ' active' : '');
        btn.innerHTML = content;
        btn.disabled = disabled;
        if (!disabled) btn.onclick = onClick;
        return btn;
    };

    // First & Prev
    container.appendChild(createBtn('<i class="fas fa-angle-double-left"></i>', modalCurrentPage === 1, false, () => { modalFetchSites(1); }));
    container.appendChild(createBtn('<i class="fas fa-angle-left"></i>', modalCurrentPage === 1, false, () => { modalFetchSites(modalCurrentPage - 1); }));

    // Page Numbers
    let startPage = Math.max(1, modalCurrentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    if (startPage > 1) {
        container.appendChild(createBtn('1', false, false, () => { modalFetchSites(1); }));
        if (startPage > 2) {
            const dots = document.createElement('span');
            dots.className = 'modal-pg-dots';
            dots.innerText = '...';
            container.appendChild(dots);
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createBtn(i, false, i === modalCurrentPage, () => { modalFetchSites(i); }));
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const dots = document.createElement('span');
            dots.className = 'modal-pg-dots';
            dots.innerText = '...';
            container.appendChild(dots);
        }
        container.appendChild(createBtn(totalPages, false, false, () => { modalFetchSites(totalPages); }));
    }

    // Next & Last
    container.appendChild(createBtn('<i class="fas fa-angle-right"></i>', modalCurrentPage === totalPages, false, () => { modalFetchSites(modalCurrentPage + 1); }));
    container.appendChild(createBtn('<i class="fas fa-angle-double-right"></i>', modalCurrentPage === totalPages, false, () => { modalFetchSites(totalPages); }));
}

function addSelectedSitesToProposal() {
    if (modalSelectedSites.length === 0) {
        Swal.fire('Warning', 'Select at least one site to add.', 'warning');
        return;
    }

    const propId = <?php echo $id; ?>;

    Swal.fire({
        title: 'Adding Sites...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const cleanedSites = modalSelectedSites.map(s => {
        let selImages = s.selected_images || [s.image || s.thumbnail || ''];
        // Ensure primary image is first
        const primary = s.image || s.thumbnail || '';
        if (primary) {
            selImages = [primary].concat(selImages.filter(img => img !== primary));
        }
        return {
            ...s,
            offer_rate: s.offer_rate !== undefined ? s.offer_rate : (s.card_rate || 0),
            image: selImages.join(',')
        };
    });

    fetch('../../ajax/add_proposal_items_bulk.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ proposal_id: propId, sites: cleanedSites })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            Swal.fire('Error', res.message || 'Failed to add sites', 'error');
        }
    });
}

// Lightbox & Slider Logic
let lbImages = [];
let lbIndex = 0;
let currentLightboxSiteId = null;

function openLightboxSlider(imageString, siteId) {
    currentLightboxSiteId = siteId;
    lbImages = imageString ? imageString.split(',') : [];
    if (lbImages.length === 0) return;
    
    lbIndex = 0;
    const lb = document.getElementById('simple-lightbox');
    const lbPrev = document.getElementById('lightbox-prev');
    const lbNext = document.getElementById('lightbox-next');
    const selectBtn = document.getElementById('lightbox-select-btn');
    const exportBtn = document.getElementById('lightbox-export-btn');
    
    lb.style.display = 'flex';
    
    if (lbImages.length > 1) {
        lbPrev.style.display = 'flex';
        lbNext.style.display = 'flex';
    } else {
        lbPrev.style.display = 'none';
        lbNext.style.display = 'none';
    }
    
    const isSelected = modalSelectedSites.some(ms => ms.id == siteId);
    selectBtn.style.display = isSelected ? 'block' : 'none';
    if(exportBtn) {
        exportBtn.style.display = isSelected ? 'block' : 'none';
    }
    
    updateLightboxImage();
}

function updateLightboxImage() {
    const lbImg = document.getElementById('lightbox-img');
    const lbBadge = document.getElementById('lightbox-badge');
    const selectBtn = document.getElementById('lightbox-select-btn');
    const exportBtn = document.getElementById('lightbox-export-btn');
    
    lbImg.src = '../../uploads/sites/' + lbImages[lbIndex];
    let selCount = 0;
    if(currentLightboxSiteId) {
        const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
        if(savedSite) {
            const selImages = savedSite.selected_images || [savedSite.image || savedSite.thumbnail || ''];
            selCount = selImages.length;
        }
    }
    lbBadge.innerText = (lbIndex + 1) + " / " + lbImages.length + " (" + selCount + " Selected)";
    
    if(selectBtn && currentLightboxSiteId) {
        const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
        if(savedSite && savedSite.image === lbImages[lbIndex]) {
            selectBtn.innerHTML = '<i class="fas fa-check-circle"></i> Selected as Primary';
            selectBtn.style.background = '#10b981';
            selectBtn.style.boxShadow = '0 4px 15px rgba(16,185,129,0.4)';
        } else {
            selectBtn.innerHTML = '<i class="far fa-circle"></i> Use as Primary Photo';
            selectBtn.style.background = 'var(--primary)';
            selectBtn.style.boxShadow = '0 4px 15px rgba(13,148,136,0.4)';
        }
    }
    
    if(exportBtn && currentLightboxSiteId) {
        const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
        if(savedSite) {
            const selImages = savedSite.selected_images || [savedSite.image || savedSite.thumbnail || ''];
            const isSelected = selImages.includes(lbImages[lbIndex]);
            
            if(isSelected) {
                exportBtn.innerHTML = '<i class="fas fa-check-square"></i> Selected for PPT/PDF';
                exportBtn.style.background = '#059669';
                exportBtn.style.borderColor = '#059669';
                exportBtn.style.boxShadow = '0 4px 15px rgba(5,150,105,0.4)';
            } else {
                exportBtn.innerHTML = '<i class="far fa-square"></i> Select for PPT/PDF';
                exportBtn.style.background = 'rgba(0,0,0,0.75)';
                exportBtn.style.borderColor = 'rgba(255,255,255,0.25)';
                exportBtn.style.boxShadow = 'none';
            }
        }
    }
}

function changeLightboxImage(dir) {
    lbIndex += dir;
    if (lbIndex < 0) lbIndex = lbImages.length - 1;
    if (lbIndex >= lbImages.length) lbIndex = 0;
    updateLightboxImage();
}

function selectLightboxImage() {
    if(currentLightboxSiteId) {
        const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
        if(savedSite) {
            savedSite.image = lbImages[lbIndex];
            
            // Also ensure it is selected for export/print
            if(!savedSite.selected_images) {
                savedSite.selected_images = [lbImages[lbIndex]];
            } else if(!savedSite.selected_images.includes(lbImages[lbIndex])) {
                savedSite.selected_images.push(lbImages[lbIndex]);
            }
            
            // Update thumbnail in table row
            const thumbImg = document.getElementById('modal-thumb-' + currentLightboxSiteId);
            if(thumbImg) {
                thumbImg.src = '../../uploads/sites/' + lbImages[lbIndex];
            }
            
            updateLightboxImage();
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Image selected!',
                showConfirmButton: false,
                timer: 1500
            });
        }
    }
}

function toggleExportImage() {
    if(!currentLightboxSiteId) return;
    
    const id = currentLightboxSiteId;
    const savedSite = modalSelectedSites.find(ms => ms.id == id);
    if(!savedSite) {
        Swal.fire('Note', 'Please select this asset first to manage its export images.', 'info');
        return;
    }
    
    if(!savedSite.selected_images) {
        savedSite.selected_images = [savedSite.image || savedSite.thumbnail || ''];
    }
    
    const currentImg = lbImages[lbIndex];
    const imgIdx = savedSite.selected_images.indexOf(currentImg);
    
    if(imgIdx !== -1) {
        if(savedSite.selected_images.length <= 1) {
            Swal.fire('Warning', 'You must keep at least one image selected for export.', 'warning');
            return;
        }
        savedSite.selected_images.splice(imgIdx, 1);
        if((savedSite.image || savedSite.thumbnail) === currentImg) {
            savedSite.image = savedSite.selected_images[0];
            const thumbImg = document.getElementById('modal-thumb-' + id);
            if(thumbImg) {
                thumbImg.src = '../../uploads/sites/' + savedSite.image;
            }
        }
    } else {
        savedSite.selected_images.push(currentImg);
    }
    updateLightboxImage();
}

async function downloadLightboxImage() {
    if (!lbImages || lbImages.length === 0) return;
    const currentImg = lbImages[lbIndex];
    if (!currentImg) return;
    
    const imgUrl = '../../uploads/sites/' + currentImg;
    
    try {
        const topBtn = document.getElementById('lightbox-download-top');
        const botBtn = document.getElementById('lightbox-download-bottom');
        const origTop = topBtn ? topBtn.innerHTML : '';
        const origBot = botBtn ? botBtn.innerHTML : '';
        if (topBtn) topBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
        if (botBtn) botBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';

        const response = await fetch(imgUrl);
        if (!response.ok) throw new Error('Network error');
        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        
        let rawName = currentImg.split('/').pop().split('?')[0];
        let baseName = rawName.replace(/\.[^/.]+$/, "");
        a.download = baseName + '.jpg';
        
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(blobUrl);

        if (topBtn) topBtn.innerHTML = origTop;
        if (botBtn) botBtn.innerHTML = origBot;
    } catch (err) {
        const a = document.createElement('a');
        a.href = imgUrl;
        a.download = currentImg.split('/').pop();
        a.target = '_blank';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}

function closeLightbox() {
    document.getElementById('simple-lightbox').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('simple-lightbox');
    if (lb && lb.style.display === 'flex') {
        if (e.key === 'ArrowLeft') changeLightboxImage(-1);
        if (e.key === 'ArrowRight') changeLightboxImage(1);
        if (e.key === 'Escape') closeLightbox();
    }
});

function copyPublicLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Public link copied to clipboard!',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}

function editSiteLocation(itemId, currentLoc) {
    Swal.fire({
        title: 'Edit Site Location',
        input: 'text',
        inputValue: currentLoc,
        showCancelButton: true,
        confirmButtonText: 'Save',
        showLoaderOnConfirm: true,
        preConfirm: (newLoc) => {
            return fetch('../../ajax/update_proposal_item_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `item_id=${itemId}&location=${encodeURIComponent(newLoc)}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                return newLoc;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            const el = document.getElementById('site-loc-' + itemId);
            if (el) el.innerText = result.value;
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success', title: 'Location updated!', showConfirmButton: false, timer: 1500
            });
        }
    });
}

function editSiteName(itemId, currentName) {
    Swal.fire({
        title: 'Edit Site Name',
        input: 'text',
        inputValue: currentName,
        showCancelButton: true,
        confirmButtonText: 'Save',
        showLoaderOnConfirm: true,
        preConfirm: (newName) => {
            return fetch('../../ajax/update_proposal_item_name.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `item_id=${itemId}&site_name=${encodeURIComponent(newName)}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                return newName;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            const el = document.getElementById('site-name-' + itemId);
            if (el) el.innerText = result.value;
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success', title: 'Site Name updated!', showConfirmButton: false, timer: 1500
            });
        }
    });
}

// Native HTML5 Drag and Drop row reordering script
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.querySelector('.table tbody');
    if (!tableBody) return;

    let dragRow = null;

    tableBody.addEventListener('dragstart', (e) => {
        const row = e.target.closest('.draggable-row');
        if (!row) return;
        dragRow = row;
        row.style.opacity = '0.4';
        row.style.background = '#f1f5f9';
    });

    tableBody.addEventListener('dragend', (e) => {
        const row = e.target.closest('.draggable-row');
        if (!row) return;
        row.style.opacity = '';
        row.style.background = '';
        dragRow = null;
        
        // Save the new order
        saveNewRowOrder();
    });

    tableBody.addEventListener('dragover', (e) => {
        e.preventDefault();
        const row = e.target.closest('.draggable-row');
        if (!row || row === dragRow) return;

        const bounding = row.getBoundingClientRect();
        const offset = bounding.y + (bounding.height / 2);
        if (e.clientY - offset > 0) {
            row.after(dragRow);
        } else {
            row.before(dragRow);
        }
    });

    function saveNewRowOrder() {
        const rows = tableBody.querySelectorAll('.draggable-row');
        const itemIds = [];
        rows.forEach(r => {
            if (r.dataset.itemId) {
                itemIds.push(r.dataset.itemId);
            }
        });

        if (itemIds.length === 0) return;

        fetch('../../ajax/update_proposal_item_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_ids: itemIds })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1000
                });
                Toast.fire({
                    icon: 'success',
                    title: 'Sequence updated'
                });
            } else {
                Swal.fire('Error', res.message || 'Failed to update order', 'error');
            }
        })
        .catch(err => {
            console.error('Order reorder failed:', err);
        });
    }
});

function editCampaignName() {
    Swal.fire({
        title: 'Edit Campaign Name',
        input: 'text',
        inputValue: '<?php echo addslashes($p['campaign_name']); ?>',
        showCancelButton: true,
        confirmButtonText: 'Save',
        showLoaderOnConfirm: true,
        preConfirm: (newName) => {
            if (!newName.trim()) {
                Swal.showValidationMessage('Campaign name cannot be empty');
                return false;
            }
            return fetch('../../ajax/update_proposal_meta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=<?php echo $id; ?>&field=campaign_name&value=${encodeURIComponent(newName.trim())}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                return newName;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Campaign name has been updated.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

function editClient() {
    const clientOptions = <?php echo $clientOptionsJson; ?>;
    Swal.fire({
        title: 'Select Client',
        input: 'select',
        inputOptions: clientOptions,
        inputValue: '<?php echo $p['client_id']; ?>',
        showCancelButton: true,
        confirmButtonText: 'Save',
        showLoaderOnConfirm: true,
        preConfirm: (clientId) => {
            return fetch('../../ajax/update_proposal_meta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=<?php echo $id; ?>&field=client_id&value=${clientId}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                return clientId;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Client has been updated.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

const clientGsts = <?php echo json_encode($gsts); ?>;
const currentBillingGstin = '<?php echo addslashes($selectedGstin); ?>';

function editProposalBillingGstin() {
    let optionsHtml = '';
    if (clientGsts && clientGsts.length > 0) {
        optionsHtml = clientGsts.map(g => {
            const isSel = (g.gstin.toUpperCase() === currentBillingGstin.toUpperCase()) ? 'selected' : '';
            return `<option value="${g.gstin}" ${isSel}>${g.gstin} - ${g.state || 'Primary'}</option>`;
        }).join('');
        optionsHtml += `<option value="__custom__">+ Enter Custom / New GSTIN</option>`;
    }

    if (clientGsts && clientGsts.length > 0) {
        Swal.fire({
            title: 'Select Billing GSTIN for Proposal',
            html: `
                <div style="text-align: left; font-size: 0.88rem; margin-bottom: 8px; color: #475569;">
                    Choose registered GSTIN or enter a new one:
                </div>
                <select id="edit_prop_billing_gstin_select" class="swal2-input" style="width: 100%; box-sizing: border-box; margin: 0 0 10px 0; font-size: 0.9rem;" onchange="toggleCustomPropGstinInput(this.value)">
                    ${optionsHtml}
                </select>
                <div id="custom_prop_gstin_wrap" style="display: none; text-align: left;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155;">Custom 15-digit GSTIN:</label>
                    <input type="text" id="edit_prop_billing_gstin_custom" class="swal2-input" placeholder="e.g. 19AAFCG1067K2ZD" maxlength="15" style="width: 100%; box-sizing: border-box; text-transform: uppercase; margin: 4px 0 0 0;">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Save GSTIN',
            confirmButtonColor: '#0d9488',
            cancelButtonColor: '#64748b',
            didOpen: () => {
                window.toggleCustomPropGstinInput = function(val) {
                    const wrap = document.getElementById('custom_prop_gstin_wrap');
                    if (val === '__custom__') {
                        wrap.style.display = 'block';
                        document.getElementById('edit_prop_billing_gstin_custom').focus();
                    } else {
                        wrap.style.display = 'none';
                    }
                };
            },
            preConfirm: () => {
                const selVal = document.getElementById('edit_prop_billing_gstin_select').value;
                let finalGstin = selVal;
                if (selVal === '__custom__') {
                    finalGstin = document.getElementById('edit_prop_billing_gstin_custom').value.trim().toUpperCase();
                    if (!finalGstin) {
                        Swal.showValidationMessage('Please enter a valid GSTIN');
                        return false;
                    }
                }
                return finalGstin;
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                saveProposalGstinAjax(result.value);
            }
        });
    } else {
        Swal.fire({
            title: 'Enter Billing GSTIN',
            input: 'text',
            inputLabel: 'Client 15-digit GSTIN',
            inputValue: currentBillingGstin,
            inputPlaceholder: 'e.g. 19AAFCG1067K2ZD',
            inputAttributes: {
                maxlength: 15,
                autocapitalize: 'characters',
                style: 'text-transform: uppercase;'
            },
            showCancelButton: true,
            confirmButtonText: 'Save GSTIN',
            confirmButtonColor: '#0d9488',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                saveProposalGstinAjax(result.value.trim().toUpperCase());
            }
        });
    }
}

function saveProposalGstinAjax(gstin) {
    const formData = new FormData();
    formData.append('id', <?php echo $id; ?>);
    formData.append('field', 'billing_gstin');
    formData.append('value', gstin);

    fetch('../../ajax/update_proposal_meta.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'GSTIN Updated!',
                text: 'Billing GSTIN has been saved for this proposal.',
                timer: 1200,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', res.message || 'Failed to update GSTIN', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'An error occurred while saving GSTIN', 'error');
    });
}

async function openProposalInGSheet(proposalId) {
    Swal.fire({
        title: 'Preparing Google Sheet...',
        text: 'Generating formatted Media Plan & Rate Sheet...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('export_sheet.php?id=' + proposalId);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to generate Google Sheet data');
        }

        // Copy formatted HTML table & TSV to clipboard
        let copied = false;
        try {
            if (navigator.clipboard && window.ClipboardItem) {
                const blobHtml = new Blob([data.html], { type: 'text/html' });
                const blobText = new Blob([data.tsv], { type: 'text/plain' });
                await navigator.clipboard.write([
                    new ClipboardItem({
                        'text/html': blobHtml,
                        'text/plain': blobText
                    })
                ]);
                copied = true;
            } else if (navigator.clipboard) {
                await navigator.clipboard.writeText(data.tsv);
                copied = true;
            }
        } catch (clipErr) {
            const ta = document.createElement('textarea');
            ta.value = data.tsv;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            copied = true;
        }

        // Open Google Sheets in new tab
        const gsheetWin = window.open('https://sheets.new', '_blank');

        Swal.fire({
            icon: 'success',
            title: '<div style="font-size: 1.25rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; justify-content: center; gap: 8px;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#0F9D58"/><path d="M7 7H17V9H7V7ZM7 11H17V13H7V11ZM7 15H14V17H7V15Z" fill="white"/></svg> Google Sheets Ready!</div>',
            html: `
                <div style="text-align: left; font-size: 0.9rem; color: #334155; line-height: 1.5; padding: 5px 10px;">
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px; margin-bottom: 12px; color: #065f46;">
                        <strong style="font-size: 0.95rem; display: block; margin-bottom: 4px;"><i class="fas fa-check-circle"></i> Media Rate Sheet Copied to Clipboard!</strong>
                        New Google Sheet has opened in a new tab.
                    </div>
                    <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px;">Next Step in Google Sheets:</div>
                    <ol style="margin-left: 20px; color: #475569; font-size: 0.88rem;">
                        <li style="margin-bottom: 4px;">Click inside <strong>Cell A1</strong> in the new Google Sheet tab.</li>
                        <li>Press <kbd style="background: #e2e8f0; border: 1px solid #cbd5e1; padding: 2px 6px; border-radius: 4px; font-weight: 800; color: #0f172a;">Ctrl + V</kbd> (or <kbd style="background: #e2e8f0; border: 1px solid #cbd5e1; padding: 2px 6px; border-radius: 4px; font-weight: 800; color: #0f172a;">Cmd + V</kbd> on Mac).</li>
                    </ol>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 10px; font-style: italic;">
                        All colors, headers, sites, and amounts will paste instantly into Google Sheets.
                    </div>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: '<i class="fas fa-external-link-alt"></i> Go to Google Sheet',
            confirmButtonColor: '#0f9d58',
            showCancelButton: true,
            cancelButtonText: 'Done',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                if (gsheetWin) {
                    gsheetWin.focus();
                }
            }
        });

    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Export Failed',
            text: err.message || 'Could not copy data for Google Sheets'
        });
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
