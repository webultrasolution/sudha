<?php
$activePage = 'bookings';
$pageTitle = 'Booking Details';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Load Core Libraries (SweetAlert, FontAwesome, CSS) without the sidebar
$hideSidebar = true;
include_once __DIR__ . '/../../includes/header.php';

// Prevent timezone warnings
date_default_timezone_set('Asia/Kolkata');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Booking with advanced stats
$stmt = $pdo->prepare("
    SELECT b.*, 
           c.name as client_name, c.email as client_email, c.phone as client_phone, 
           c.contact_person as client_contact_person, c.address as client_address, 
           c.city as client_city, c.state as client_state, c.district as client_district, 
           c.pincode as client_pincode, c.gstin as client_primary_gstin, c.pan as client_pan, 
           c.business_type, c.additional_gst,
           p.proposal_number
    FROM bookings b 
    JOIN partners c ON b.client_id = c.id 
    LEFT JOIN proposals p ON b.proposal_id = p.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$b = $stmt->fetch();

if (!$b) {
    echo "<div class='card'>Booking not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Pre-fill next sequential invoice number
$nextInvoiceNumber = getPreviewSequenceNumber($pdo, 'invoice');

// Fetch Items from booking_items
$stmtItems = $pdo->prepare("
    SELECT bi.*, s.site_code, COALESCE(bi.custom_site_name, s.name) as site_name, COALESCE(bi.custom_location, s.location) as location, s.city, s.type as media_type, s.owner_type, 
           s.width, s.height, s.light_type, s.vendor_id, v.name as vendor_name, v.contact_person as vendor_contact, v.phone as vendor_phone, v.email as vendor_email,
           o.status as op_status, o.id as op_id,
           (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail,
           (SELECT GROUP_CONCAT(filename) FROM site_images WHERE site_id = s.id) as all_images
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    LEFT JOIN partners v ON s.vendor_id = v.id
    LEFT JOIN operations o ON bi.booking_id = o.booking_id AND bi.site_id = o.site_id
    WHERE bi.booking_id = ?
    ORDER BY bi.sort_order ASC, bi.id ASC
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();
$existingBookingSiteIds = array_column($items, 'site_id');

// Advanced Stats
$totalSQFT = 0;
$taCost = 0;
$taSale = 0;
$haCost = 0;
$haSale = 0;

foreach ($items as $item) {
    $sqft = $item['width'] * $item['height'] * ($item['unit'] ?? 1);
    $totalSQFT += $sqft;
    if ($item['owner_type'] === 'TA') {
        $taCost += ($item['purchase_amount'] ?? 0);
        $taSale += $item['amount'];
    } else {
        $haCost += ($item['purchase_amount'] ?? 0);
        $haSale += $item['amount'];
    }
}

$taMargin = $taSale - $taCost;
$haMargin = $haSale - $haCost;
$totalMargin = $taMargin + $haMargin;

$grandTotal = $b['grand_total'];

// Check if Final Tax Invoice already exists — locks cost editing
$stmtInvCheck = $pdo->prepare("SELECT id, invoice_number, invoice_date, created_at, approval_status FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
$stmtInvCheck->execute([$id]);
$invoiceRow = $stmtInvCheck->fetch(PDO::FETCH_ASSOC);
$invoiceFinalized = (bool) $invoiceRow;
$invoiceApprovalStatus = $invoiceRow['approval_status'] ?? '';
$existingInvoiceNumber = $invoiceRow['invoice_number'] ?? '';
$existingInvoiceDate = !empty($invoiceRow['invoice_date']) ? $invoiceRow['invoice_date'] : (!empty($invoiceRow['created_at']) ? date('Y-m-d', strtotime($invoiceRow['created_at'])) : date('Y-m-d'));

// Check if RO Invoice already exists — locks cost editing
$stmtRoCheck = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
$stmtRoCheck->execute([$id]);
$roFinalized = (bool) $stmtRoCheck->fetchColumn();

// Locked only if invoice is finalized and not rejected
$isLocked = $invoiceFinalized && ($invoiceApprovalStatus !== 'rejected');

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

// Fetch unique values for filter dropdowns
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$districts = $pdo->query("SELECT DISTINCT district FROM sites WHERE district != '' ORDER BY district")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM sites WHERE location != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' AND status = 'active' ORDER BY name")->fetchAll();
$entities = $pdo->query("SELECT id, name FROM entities ORDER BY id ASC")->fetchAll();
$entityOptions = [];
foreach ($entities as $ent) {
    $entityOptions[$ent['id']] = $ent['name'];
}
$entityOptionsJson = json_encode($entityOptions);

// Collect all GST options for this client
$gsts = [];
if (!empty($b['client_primary_gstin'])) {
    $gsts[] = [
        'gstin'   => $b['client_primary_gstin'],
        'state'   => !empty($b['client_state']) ? $b['client_state'] : 'Primary',
        'address' => $b['client_address'] ?? ''
    ];
}
if (!empty($b['additional_gst'])) {
    $add = json_decode($b['additional_gst'], true);
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

// Active/Selected billing GSTIN for this booking
$selectedGstin = !empty($b['billing_gstin']) ? $b['billing_gstin'] : (!empty($b['client_primary_gstin']) ? $b['client_primary_gstin'] : '');

// Find matching GST record for state/address
$matchedGstRecord = null;
foreach ($gsts as $g) {
    if (strcasecmp($g['gstin'], $selectedGstin) === 0) {
        $matchedGstRecord = $g;
        break;
    }
}

// Resolve Client Address for this booking
$displayAddress = '';
if ($matchedGstRecord && !empty($matchedGstRecord['address'])) {
    $displayAddress = $matchedGstRecord['address'];
} else {
    $addrParts = array_filter([
        $b['client_address'] ?? '',
        $b['client_city'] ?? '',
        (($b['client_district'] ?? '') && ($b['client_district'] !== ($b['client_city'] ?? ''))) ? $b['client_district'] : '',
        $b['client_state'] ?? '',
        !empty($b['client_pincode']) ? 'PIN: ' . $b['client_pincode'] : ''
    ]);
    $displayAddress = implode(', ', $addrParts);
}

// Tax Type Display: Intra-state (West Bengal 19) -> CGST+SGST, Interstate (Non-WB) -> IGST
if (!empty($selectedGstin)) {
    $stateCode = substr($selectedGstin, 0, 2);
    $isWestBengal = ($stateCode === '19');
} else {
    $clientState = strtolower(trim($b['client_state'] ?? ''));
    $isWestBengal = empty($clientState) || ($clientState === 'west bengal') || ($clientState === 'wb');
}

$taxTypeLabel = $isWestBengal ? 'CGST (9%) + SGST (9%)' : 'IGST (18%)';
$taxTypeBadgeBg = $isWestBengal ? '#eff6ff' : '#fef3c7';
$taxTypeBadgeBorder = $isWestBengal ? '#bfdbfe' : '#fde68a';
$taxTypeBadgeColor = $isWestBengal ? '#1d4ed8' : '#b45309';
?>

<div style="display: flex; justify-content: space-between; align-items: stretch; margin-bottom: 2rem; gap: 1.25rem; flex-wrap: wrap;">
    <!-- 1. Booking Meta Info -->
    <div style="display: flex; flex-direction: column; justify-content: center; min-width: 260px;">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem;">
            <h1 style="font-size: 1.65rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                <?php echo htmlspecialchars(!empty($b['booking_number']) ? $b['booking_number'] : '#BK-' . str_pad($b['id'], 4, '0', STR_PAD_LEFT)); ?></h1>
            <span
                style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.65rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700;">
                <?php echo $b['proposal_number'] ?: 'N/A'; ?>
            </span>
            <span
                style="background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.65rem; border-radius: 50px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;">
                <?php echo strtoupper($b['status']); ?>
            </span>
        </div>
        <div style="margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem;">
            <span style="font-size: 1.1rem; font-weight: 700; color: #1e293b;" id="display_campaign_name">
                <?php echo htmlspecialchars($b['campaign_name'] ?: 'No Campaign Name'); ?>
            </span>
            <?php if ((!$isLocked || $isAdmin) && canEdit('bookings')): ?>
                <button onclick="editCampaignName()" class="btn"
                    style="padding: 0.15rem 0.45rem; font-size: 0.68rem; border-radius: 4px; background: #e2e8f0; color: #475569; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;">
                    <i class="fas fa-edit"></i> Edit Name
                </button>
            <?php endif; ?>
        </div>
        <div style="color: #64748b; font-size: 0.88rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <i class="far fa-calendar-alt" style="color: #0d9488;"></i>
            Tenure: <strong style="color: #334155;" id="display_tenure_dates"><?php echo date('d M', strtotime($b['start_date'])); ?> to <?php echo date('d M Y', strtotime($b['end_date'])); ?></strong>
        </div>
    </div>

    <!-- 2. Client Profile & Selected GST Card (Red Box Area in screenshot) -->
    <div style="flex: 1; min-width: 380px; max-width: 580px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.75rem 1.15rem; box-shadow: 0 2px 4px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: center;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.3rem;">
            <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <i class="fas fa-building" style="color: #0d9488; font-size: 0.95rem; flex-shrink: 0;"></i>
                <span title="<?php echo htmlspecialchars($b['client_name']); ?>"><?php echo htmlspecialchars($b['client_name']); ?></span>
            </div>
            <?php if (!empty($b['business_type'])): ?>
                <span style="background: #f1f5f9; color: #475569; font-size: 0.68rem; font-weight: 700; padding: 2px 7px; border-radius: 4px; white-space: nowrap; flex-shrink: 0;">
                    <?php echo htmlspecialchars($b['business_type']); ?>
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
            <span style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.2rem 0.55rem; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Selected Billing GSTIN for this booking">
                <i class="fas fa-id-card" style="color: #059669;"></i>
                GSTIN: <span style="font-family: monospace; font-size: 0.85rem; letter-spacing: 0.02em;"><?php echo htmlspecialchars($selectedGstin ?: 'Unregistered / None'); ?></span>
                <?php if ($matchedGstRecord && !empty($matchedGstRecord['state'])): ?>
                    <span style="color: #047857; font-weight: 600; font-size: 0.72rem;">(<?php echo htmlspecialchars($matchedGstRecord['state']); ?>)</span>
                <?php endif; ?>
            </span>

            <?php if (!empty($selectedGstin) || !empty($b['client_state'])): ?>
                <span style="background: <?php echo $taxTypeBadgeBg; ?>; border: 1px solid <?php echo $taxTypeBadgeBorder; ?>; color: <?php echo $taxTypeBadgeColor; ?>; padding: 0.2rem 0.55rem; border-radius: 6px; font-weight: 700; font-size: 0.72rem;" title="<?php echo $isWestBengal ? 'Intra-State Supply (West Bengal)' : 'Inter-State Supply (Outside West Bengal)'; ?>">
                    <?php echo $taxTypeLabel; ?>
                </span>
            <?php endif; ?>

            <?php if (!empty($b['client_pan'])): ?>
                <span style="background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 600; font-size: 0.72rem;" title="Client PAN">
                    PAN: <span style="font-family: monospace;"><?php echo htmlspecialchars($b['client_pan']); ?></span>
                </span>
            <?php endif; ?>

            <?php if ($isAdmin || canEdit('bookings') || canAdd('financials')): ?>
                <button type="button" onclick="editBillingGstin()" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #334155; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f8fafc';" title="Change or select billing GSTIN for this booking">
                    <i class="fas fa-edit" style="color: #0284c7;"></i> Change GST
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Action Buttons -->
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <a href="bookings.php" class="btn"
            style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: pointer; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back
        </a>

        <?php
        // Check if invoice already exists
        $stmtInv = $pdo->prepare("SELECT id, invoice_number, invoice_date, created_at, approval_status FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
        $stmtInv->execute([$id]);
        $existingInvoice = $stmtInv->fetch();

        if ($existingInvoice): ?>
            <?php if (($existingInvoice['approval_status'] ?? '') === 'approved' || $isAdmin): ?>
                <a href="generate_invoice.php?booking_id=<?php echo $id; ?>" target="_blank" class="btn"
                    style="background: #10b981; color: white; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                    <i class="fas fa-eye"></i> View Tax Invoice
                </a>
            <?php elseif (($existingInvoice['approval_status'] ?? '') === 'rejected'): ?>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                    <button class="btn" onclick="openInvoicePopup(<?php echo $b['id']; ?>)"
                        style="background: #ef4444; color: white; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-redo"></i> Resubmit Invoice (Rejected)
                    </button>
                    <?php 
                    // Fetch rejection reason
                    $stmtRej = $pdo->prepare("SELECT remarks FROM approval_requests WHERE entity_type = 'invoice' AND entity_id = ? ORDER BY id DESC LIMIT 1");
                    $stmtRej->execute([$existingInvoice['id']]);
                    $rejReason = $stmtRej->fetchColumn();
                    if ($rejReason): ?>
                        <span style="font-size: 0.75rem; color: #ef4444; font-weight: 600;">Reason: <?php echo htmlspecialchars($rejReason); ?></span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <button class="btn"
                    style="background: #f8fafc; border: 1px solid #e2e8f0; color: #94a3b8; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: not-allowed; display: flex; align-items: center; gap: 0.5rem;"
                    title="Invoice is awaiting admin approval.">
                    <i class="fas fa-lock"></i> Invoice Pending Approval
                </button>
            <?php endif; ?>

            <?php if ($isAdmin || canEdit('bookings') || canAdd('financials')): ?>
                <button type="button" onclick="openInvoicePopup(<?php echo $b['id']; ?>)" class="btn"
                    style="background: #0284c7; color: white; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;"
                    title="Edit Invoice Number, Invoice Date, PO Number, PO Date & Billing GSTIN">
                    <i class="fas fa-edit"></i> Edit Invoice / PO
                </button>
            <?php endif; ?>
        <?php else: ?>
            <button class="btn" onclick="openInvoicePopup(<?php echo $b['id']; ?>)"
                style="background: #0f172a; color: white; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-file-invoice-dollar"></i> Final Tax Invoice
            </button>
        <?php endif; ?>

        <?php if ($existingInvoice && $isAdmin): ?>
            <button type="button" onclick="syncBookingInvoice(<?php echo $id; ?>)" class="btn"
                style="background: #0d9488; color: white; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;"
                title="Recalculate booking totals and update Invoice & Client Ledger">
                <i class="fas fa-sync-alt"></i> Sync Invoice & Ledger
            </button>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <button type="button" onclick="openAttachmentsModal(<?php echo $id; ?>)" class="btn"
                style="background: #4f46e5; color: white; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;"
                title="Manage Booking & Invoice Attachments (Admin Only)">
                <i class="fas fa-paperclip"></i> Attachments
            </button>
        <?php endif; ?>

        <?php
        // Check if RO invoice already exists
        $stmtRoInv = $pdo->prepare("SELECT id, approval_status FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
        $stmtRoInv->execute([$id]);
        $existingRoInvoice = $stmtRoInv->fetch();

        if ($existingRoInvoice): ?>
            <?php if (($existingRoInvoice['approval_status'] ?? '') === 'approved' || $isAdmin): ?>
                <a href="generate_ro_invoice.php?booking_id=<?php echo $id; ?>" target="_blank" class="btn"
                    style="background: #10b981; color: white; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                    <i class="fas fa-eye"></i> View RO Invoice
                </a>
            <?php else: ?>
                <button class="btn"
                    style="background: #f8fafc; border: 1px solid #e2e8f0; color: #94a3b8; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: not-allowed; display: flex; align-items: center; gap: 0.5rem;"
                    title="RO Invoice is awaiting admin approval.">
                    <i class="fas fa-lock"></i> RO Invoice Pending Approval
                </button>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($invoiceFinalized): ?>
                <a href="generate_ro_invoice.php?booking_id=<?php echo $id; ?>" target="_blank" class="btn"
                    style="background: #0f172a; color: white; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                    <i class="fas fa-file-invoice-dollar"></i> Generate RO
                </a>
            <?php else: ?>
                <button class="btn" disabled
                    style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #94a3b8; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; border: none; cursor: not-allowed; display: flex; align-items: center; gap: 0.5rem;"
                    title="Generate RO is only active after raising Final Tax Invoice.">
                    <i class="fas fa-lock"></i> Generate RO
                </button>
            <?php endif; ?>
        <?php endif; ?>

            <button class="btn btn-primary" onclick="window.print()"
                style="padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800;">
                <i class="fas fa-print"></i> Print Booking
            </button>
    </div>
</div>

<!-- Financial Summary & Stats -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-chart-line"></i> Campaign Stats</h4>
        <div class="stat-box">
            <small>Total Sites</small>
            <strong><?php echo count($items); ?> Assets</strong>
        </div>
        <div class="stat-box">
            <small>Total Area</small>
            <strong><?php echo number_format($totalSQFT); ?> <span
                    style="font-size:0.7rem; color:#64748b;">sqft</span></strong>
        </div>
    </div>

    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-clock"></i> Execution Timeline</h4>
        <div class="p-row"><span>Start Date</span><strong id="display_execution_start"><?php echo date('d M Y', strtotime($b['start_date'])); ?></strong></div>
        <div class="p-row"><span>End Date</span><strong id="display_execution_end"><?php echo date('d M Y', strtotime($b['end_date'])); ?></strong></div>
    </div>

    <div class="p-panel" style="background: #f8fafc; border-color: #cbd5e1;">
        <h4 class="p-title"><i class="fas fa-funnel-dollar"></i> Profitability</h4>
        <div class="p-row"><span>Vendor Payout</span><strong><?php echo formatCurrency($taCost); ?></strong></div>
        <div class="p-row"><span>Own Earnings</span><strong><?php echo formatCurrency($haMargin); ?></strong></div>
        <div
            style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total
                Margin</span>
            <span
                style="font-size: 1.1rem; font-weight: 800; color: var(--primary);"><?php echo formatCurrency($totalMargin); ?></span>
        </div>
    </div>

    <div class="p-panel"
        style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border: none;">
        <h4 class="p-title" style="color: #94a3b8; border-bottom-color: rgba(255,255,255,0.1);"><i
                class="fas fa-receipt"></i> Billing Summary</h4>
        <div class="p-row" style="color: #cbd5e1;"><span>Base Amount</span><strong
                style="color: white;"><?php echo formatCurrency($b['total_amount']); ?></strong></div>
        <div class="p-row" style="color: #cbd5e1;"><span>Tax (GST)</span><strong
                style="color: white;"><?php echo formatCurrency($b['tax_amount']); ?></strong></div>
        <div
            style="background: rgba(255,255,255,0.1); border-radius: 12px; padding: 1rem; margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.8rem; font-weight: 800; color: #cbd5e1; text-transform: uppercase;">Grand
                Total</span>
            <span
                style="font-size: 1.5rem; font-weight: 900; color: #34d399;"><?php echo formatCurrency($b['grand_total']); ?></span>
        </div>
    </div>
</div>

<?php
// Group sites by vendor for PO generation and procurement tracking
$vendorSummaryList = [];
$stmtCheckPO = $pdo->prepare("SELECT id, po_number, po_date, approval_status, vendor_invoice_approval_status, total_amount, entity_id FROM purchase_orders WHERE campaign_id = ? AND vendor_id = ?");

foreach ($items as $item) {
    if ($item['owner_type'] === 'TA' && !empty($item['vendor_id'])) {
        $hasPrint = floatval($item['client_print_rate'] ?? 0) > 0;
        $hasMount = floatval($item['client_mount_rate'] ?? 0) > 0;
        $isRental = floatval($item['amount'] ?? 0) > 0 || (!$hasPrint && !$hasMount);

        $vId = (int)$item['vendor_id'];
        if (!isset($vendorSummaryList[$vId])) {
            $stmtCheckPO->execute([$b['id'], $vId]);
            $poRow = $stmtCheckPO->fetch();

            $vendorSummaryList[$vId] = [
                'id' => $vId,
                'name' => $item['vendor_name'] ?? 'Unknown Vendor',
                'contact' => $item['vendor_contact'] ?? '',
                'phone' => $item['vendor_phone'] ?? '',
                'email' => $item['vendor_email'] ?? '',
                'count' => 0,
                'rental_count' => 0,
                'total_purchase' => 0,
                'po_id' => $poRow ? $poRow['id'] : null,
                'po_number' => $poRow ? $poRow['po_number'] : null,
                'po_date' => $poRow ? $poRow['po_date'] : '',
                'approval_status' => $poRow ? $poRow['approval_status'] : null,
                'vendor_invoice_approval_status' => $poRow ? $poRow['vendor_invoice_approval_status'] : null,
                'po_total' => $poRow ? (float)$poRow['total_amount'] : 0,
            ];
        }
        $vendorSummaryList[$vId]['count']++;
        if ($isRental) {
            $vendorSummaryList[$vId]['rental_count']++;
            $vendorSummaryList[$vId]['total_purchase'] += floatval($item['purchase_amount'] ?? 0);
        }
    }
}
?>

<?php if (!empty($vendorSummaryList)): ?>
<!-- Vendor Procurement & POs Summary Panel -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1rem;">
                <i class="fas fa-truck-loading"></i>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: #0f172a;">Vendor Procurement & Purchase Orders</h4>
                <p style="margin: 0; font-size: 0.75rem; color: #64748b;">Manage formal vendor POs, rates, and approvals for third-party media in this campaign.</p>
            </div>
        </div>
        <span style="font-size: 0.7rem; font-weight: 800; background: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 50px;">
            <i class="fas fa-layer-group"></i> <?php echo count($vendorSummaryList); ?> Vendor(s) in Campaign
        </span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem;">
        <?php foreach ($vendorSummaryList as $vSum): 
            $isPoCreated = !empty($vSum['po_id']);
            $isApproved = $vSum['approval_status'] === 'approved';
            $isPending = $vSum['approval_status'] === 'pending_approval';
            $hasRental = ($vSum['rental_count'] ?? 0) > 0;
        ?>
        <div style="border: 1px solid <?php echo $isPoCreated ? ($isApproved ? '#bbf7d0' : '#fed7aa') : '#e2e8f0'; ?>; background: <?php echo $isPoCreated ? ($isApproved ? '#f0fdf4' : '#fffbeb') : '#f8fafc'; ?>; border-radius: 12px; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
                    <div>
                        <strong style="color: #0f172a; font-size: 0.95rem; display: block;"><?php echo htmlspecialchars($vSum['name']); ?></strong>
                        <?php if ($vSum['contact']): ?>
                            <span style="font-size: 0.7rem; color: #64748b; font-weight: 600;"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($vSum['contact']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if (!$isPoCreated): ?>
                            <?php if ($hasRental): ?>
                                <span style="background: #f1f5f9; color: #64748b; border: 1px dashed #cbd5e1; font-size: 0.65rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-hourglass-start"></i> PO Not Generated
                                </span>
                            <?php else: ?>
                                <span style="background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-info-circle"></i> No Rental Selected
                                </span>
                            <?php endif; ?>
                        <?php elseif ($isApproved): ?>
                            <span style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-size: 0.65rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-check-circle"></i> <?php echo (stripos($vSum['po_number'] ?? '', 'PO') === 0 ? '' : 'PO: ') . htmlspecialchars($vSum['po_number'] ?? ''); ?>
                            </span>
                        <?php elseif ($isPending): ?>
                            <span style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-size: 0.65rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-clock"></i> Pending Approval
                            </span>
                        <?php else: ?>
                            <span style="background: #f1f5f9; color: #475569; font-size: 0.65rem; font-weight: 800; padding: 3px 8px; border-radius: 6px;">
                                <?php echo ucfirst($vSum['approval_status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; font-size: 0.75rem; color: #475569; margin-top: 0.5rem; background: rgba(255,255,255,0.7); padding: 0.4rem 0.6rem; border-radius: 6px;">
                    <div><span style="color: #94a3b8; font-weight: 700;">Sites:</span> <strong><?php echo $vSum['count']; ?> Asset(s)</strong></div>
                    <div><span style="color: #94a3b8; font-weight: 700;">Purchase Est:</span> <strong style="color: #0f172a;"><?php echo formatCurrency($vSum['total_purchase']); ?></strong></div>
                    <?php if ($isPoCreated && $vSum['po_total'] > 0): ?>
                        <div><span style="color: #94a3b8; font-weight: 700;">PO Total:</span> <strong style="color: #047857;"><?php echo formatCurrency($vSum['po_total']); ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions Bar -->
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 0.5rem; flex-wrap: wrap;">
                <?php if (!$isPoCreated): ?>
                    <?php if ($hasRental): ?>
                        <?php if (canEdit('bookings')): ?>
                            <button onclick="saveAndGeneratePO(<?php echo $b['id']; ?>, <?php echo $vSum['id']; ?>)"
                                style="background: #0d9488; color: white; border: none; padding: 6px 14px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(13,148,136,0.2);">
                                <i class="fas fa-file-invoice-dollar"></i> Generate Vendor PO
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; padding: 5px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 5px;" title="Vendor PO is only applicable for site rental, not for printing/mounting">
                            <i class="fas fa-ban"></i> PO N/A (No Rental)
                        </span>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($isApproved || $isAdmin): ?>
                        <a href="generate_po.php?po_id=<?php echo $vSum['po_id']; ?>" target="_blank"
                            style="background: #10b981; color: white; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-file-pdf"></i> View Official PO
                        </a>
                        <?php if (canEdit('bookings')): ?>
                            <button onclick="sendPoEmail(<?php echo $vSum['po_id']; ?>, '<?php echo addslashes($vSum['po_number']); ?>', '<?php echo number_format($vSum['po_total'] > 0 ? $vSum['po_total'] : $vSum['total_purchase'], 2); ?>', '<?php echo $vSum['po_date']; ?>', '<?php echo $b['start_date']; ?>', '<?php echo $b['end_date']; ?>', '<?php echo addslashes($vSum['email']); ?>', '<?php echo addslashes($vSum['name']); ?>')"
                                title="Send PO via SMTP Email"
                                style="background: #3b82f6; color: white; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; font-weight: 800;">
                                <i class="fas fa-envelope"></i> Email (SMTP)
                            </button>
                            <button onclick="sendPoWhatsApp(<?php echo $vSum['po_id']; ?>, '<?php echo addslashes($vSum['po_number']); ?>', '<?php echo number_format($vSum['po_total'] > 0 ? $vSum['po_total'] : $vSum['total_purchase'], 2); ?>', '<?php echo $vSum['po_date']; ?>', '<?php echo $b['start_date']; ?>', '<?php echo $b['end_date']; ?>', '<?php echo addslashes($vSum['phone']); ?>', '<?php echo addslashes($vSum['name']); ?>')"
                                title="Share PO via WhatsApp"
                                style="background: #22c55e; color: white; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; font-weight: 800;">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </button>
                            <?php if ($hasRental): ?>
                                <button onclick="saveAndGeneratePO(<?php echo $b['id']; ?>, <?php echo $vSum['id']; ?>)"
                                    title="Sync / Update PO Rates"
                                    style="background: #f59e0b; color: white; width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-lock"></i> Pending Admin Approval
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Detailed Site Table -->
<div class="card" style="padding: 0; border-radius: 16px; overflow: hidden;">
    <div
        style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9;">
        <div>
            <h3 style="font-size: 1.1rem; margin: 0; color: #0f172a; font-weight: 800;"><i class="fas fa-list"></i>
                Booked Assets & Operations</h3>
            <p style="margin: 0; font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">Review and adjust individual
                assets for this booking.</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div style="position: relative;">
                <i class="fas fa-search"
                    style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem;"></i>
                <input type="text" id="tableSearch" placeholder="Search in booking..." class="p-input"
                    style="width: 250px; padding-left: 2.5rem; height: 38px; border-radius: 8px;">
            </div>
            <?php if ((!$isLocked || $isAdmin) && canEdit('bookings')): ?>
                <button class="btn btn-secondary" onclick="openAddSiteModal()"
                    style="height: 38px; border-radius: 8px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;"><i
                        class="fas fa-plus-circle text-primary"></i> Add Sites</button>
            <?php endif; ?>
        </div>
    </div>
    <table class="table">
        <thead style="background: #f8fafc;">
            <tr>
                <th style="width: 30px;"></th>
                <th>Asset Details</th>
                <th>City / Code</th>
                <th>Dimensions</th>
                <th>Unit</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Cost / Margin</th>
                <th style="text-align: right;">Selling Cost</th>
                <th style="text-align: right; width: 50px;">Action</th>
            </tr>
        </thead>
        <tbody id="sortable-table">
            <?php foreach ($items as $item): ?>
                <?php
                $itemMargin = $item['amount'] - ($item['purchase_amount'] ?? 0);
                $marginPct = ($item['purchase_amount'] > 0) ? ($itemMargin / $item['purchase_amount'] * 100) : 0;
                $poData = (!empty($item['vendor_id']) && isset($vendorSummaryList[$item['vendor_id']])) ? $vendorSummaryList[$item['vendor_id']] : null;
                $existingPoId = $poData ? $poData['po_id'] : false;
                $poNumber = $poData ? $poData['po_number'] : '';
                $poLocked = !empty($existingPoId);
                $poApprovalStatus = $poData ? $poData['approval_status'] : '';
                $hasPrint = floatval($item['client_print_rate'] ?? 0) > 0;
                $hasMount = floatval($item['client_mount_rate'] ?? 0) > 0;
                $hasRental = floatval($item['amount'] ?? 0) > 0 || (!$hasPrint && !$hasMount);
                ?>
                <tr class="draggable-row" draggable="true" data-item-id="<?php echo $item['id']; ?>">
                    <td style="vertical-align: middle; text-align: center; cursor: move; color: #cbd5e1;"><i class="fas fa-grip-vertical"></i></td>
                    <td>
                        <div style="display: flex; align-items: flex-start;">
                            <!-- Image Thumbnail -->
                            <div style="position: relative; width: 160px; height: 100px; margin-right: 16px; flex-shrink: 0;">
                                <?php if (!empty($item['thumbnail'])): ?>
                                    <img src="../../uploads/sites/<?php echo htmlspecialchars($item['thumbnail']); ?>" 
                                         onclick="openLightboxSlider('<?php echo htmlspecialchars($item['all_images'] ?? ''); ?>', '<?php echo $item['site_id']; ?>')" 
                                         style="width: 100%; height: 100%; border-radius: 10px; object-fit: cover; border: 1px solid #cbd5e1; cursor: pointer; transition: transform 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);" 
                                         onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'"
                                         alt="Site Photo">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; border-radius: 10px; background: #f8fafc; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: #94a3b8; font-weight: 700;">No Img</div>
                                <?php endif; ?>
                            </div>

                            <!-- Text details -->
                            <div style="flex: 1;">
                                <div style="font-weight: 800; color: #1e293b; margin-bottom: 2px; display: flex; align-items: center;">
                                    <span id="site-name-<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['site_name'] ?? ''); ?></span>
                                    <?php if (canEdit('bookings')): ?>
                                        <button onclick="editSiteName(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['site_name'] ?? '')); ?>')" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 0; margin-left: 6px; font-size: 0.75rem;" title="Edit Site Name"><i class="fas fa-edit"></i></button>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                                    <span id="site-loc-<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['location'] ?? ''); ?></span>
                                    <?php if (canEdit('bookings')): ?>
                                        <button onclick="editSiteLocation(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['location'] ?? '')); ?>')" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 0; font-size: 0.75rem;" title="Edit Location"><i class="fas fa-edit"></i></button>
                                    <?php endif; ?>
                                </div>
                                <div
                                    style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php echo $item['media_type']; ?> • <?php echo $item['light_type']; ?>
                                    <?php if ($item['owner_type'] === 'HA'): ?>
                                        • <span class="badge-type"><?php echo $item['owner_type']; ?></span>
                                    <?php endif; ?>

                                    <?php if ($item['owner_type'] === 'TA' && $item['vendor_name']): ?>
                                        <div style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.4rem; width: 100%; flex-wrap: wrap;">
                                            <span class="badge-type"
                                                style="margin-right: 0.1rem;"><?php echo $item['owner_type']; ?></span>
                                            <span
                                                style="color: var(--primary); font-weight: 800; background: #f0fdfa; padding: 0.15rem 0.5rem; border-radius: 4px; border: 1px solid #ccfbf1; display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem;">
                                                <i class="fas fa-truck-loading" style="font-size: 0.65rem;"></i>
                                                <?php echo htmlspecialchars($item['vendor_name']); ?>
                                                <?php if ($item['vendor_contact']): ?>
                                                    <span style="color: #64748b; font-weight: 500; font-size: 0.65rem;">(<?php echo htmlspecialchars($item['vendor_contact']); ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if ($hasRental): ?>
                                                <?php if ($poLocked): ?>
                                                    <?php if ($poApprovalStatus === 'approved' || $isAdmin): ?>
                                                        <a href="generate_po.php?po_id=<?php echo $existingPoId; ?>" target="_blank"
                                                            title="View Official Vendor PO"
                                                            style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                            <i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($poNumber ?: 'PO'); ?>
                                                        </a>
                                                        <?php if (canEdit('bookings')): ?>
                                                            <button onclick="sendPoEmail(<?php echo $existingPoId; ?>, '<?php echo addslashes($poNumber); ?>', '<?php echo number_format($poData['po_total'] > 0 ? $poData['po_total'] : $item['purchase_amount'], 2); ?>', '<?php echo $poData['po_date'] ?? ''; ?>', '<?php echo $item['start_date'] ?? $b['start_date']; ?>', '<?php echo $item['end_date'] ?? $b['end_date']; ?>', '<?php echo addslashes($item['vendor_email'] ?? ''); ?>', '<?php echo addslashes($item['vendor_name'] ?? ''); ?>')"
                                                                title="Send PO via SMTP Email"
                                                                style="background: #3b82f6; color: white; width: 22px; height: 22px; border-radius: 4px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem;">
                                                                <i class="fas fa-envelope"></i>
                                                            </button>
                                                            <button onclick="sendPoWhatsApp(<?php echo $existingPoId; ?>, '<?php echo addslashes($poNumber); ?>', '<?php echo number_format($poData['po_total'] > 0 ? $poData['po_total'] : $item['purchase_amount'], 2); ?>', '<?php echo $poData['po_date'] ?? ''; ?>', '<?php echo $item['start_date'] ?? $b['start_date']; ?>', '<?php echo $item['end_date'] ?? $b['end_date']; ?>', '<?php echo addslashes($item['vendor_phone'] ?? ''); ?>', '<?php echo addslashes($item['vendor_name'] ?? ''); ?>')"
                                                                title="Share via WhatsApp"
                                                                style="background: #22c55e; color: white; width: 22px; height: 22px; border-radius: 4px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem;">
                                                                <i class="fab fa-whatsapp"></i>
                                                            </button>
                                                            <button onclick="saveAndGeneratePO(<?php echo $b['id']; ?>, <?php echo $item['vendor_id']; ?>)"
                                                                title="Update / Sync PO Details"
                                                                style="background: #f59e0b; color: white; width: 22px; height: 22px; border-radius: 4px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem;">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span title="PO Pending Admin Approval"
                                                            style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 3px;">
                                                            <i class="fas fa-clock"></i> Pending Approval
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="background: #f1f5f9; color: #64748b; border: 1px dashed #cbd5e1; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 3px;" title="Vendor PO not generated yet - purchase cost is tentative">
                                                        <i class="fas fa-hourglass-start"></i> PO Not Generated
                                                    </span>
                                                    <?php if (canEdit('bookings')): ?>
                                                        <button onclick="saveAndGeneratePO(<?php echo $b['id']; ?>, <?php echo $item['vendor_id']; ?>)"
                                                            title="Generate Official Vendor PO"
                                                            style="background: #0d9488; color: white; border: none; padding: 2px 7px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                            <i class="fas fa-file-invoice-dollar"></i> Generate PO
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo $item['city']; ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo $item['site_code']; ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #475569;">
                            <?php echo $item['width'] . "' x " . $item['height'] . "'"; ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">
                            <?php echo number_format($item['width'] * $item['height'] * ($item['unit'] ?? 1)); ?> SQFT</div>
                    </td>
                    <td>
                        <input type="number" min="1" value="<?php echo intval($item['unit'] ?? 1); ?>"
                            onchange="updateBookingItemUnit(<?php echo $item['id']; ?>, this.value)"
                            class="t-input"
                            style="height: 32px; width: 70px; text-align: center; border-radius: 6px; padding: 2px 5px; font-weight: 600;"
                            <?php echo (($invoiceFinalized && !$isAdmin) || !canEdit('bookings')) ? 'disabled' : ''; ?>>
                    </td>
                    <td><input type="date" value="<?php echo htmlspecialchars($item['start_date'] ?? ''); ?>"
                            onchange="updateBookingItemPeriod(<?php echo $item['id']; ?>, 'start_date', this.value)"
                            class="t-date"
                            style="height: 32px; width: 130px; border-radius: 6px; padding: 2px 5px; font-weight: 600;"
                            <?php echo (($invoiceFinalized && !$isAdmin) || !canEdit('bookings')) ? 'disabled' : ''; ?>></td>
                    <td><input type="date" value="<?php echo htmlspecialchars($item['end_date'] ?? ''); ?>"
                            onchange="updateBookingItemPeriod(<?php echo $item['id']; ?>, 'end_date', this.value)"
                            class="t-date"
                            style="height: 32px; width: 130px; border-radius: 6px; padding: 2px 5px; font-weight: 600;"
                            <?php echo (($invoiceFinalized && !$isAdmin) || !canEdit('bookings')) ? 'disabled' : ''; ?>></td>
                    <td>
                        <?php if ($item['owner_type'] === 'TA'): ?>
                            <?php if ($hasRental): ?>
                                <?php if (($item['purchase_amount'] ?? 0) > 0): ?>
                                    <div style="margin-bottom: 0.5rem;">
                                        <span
                                            style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">Purchase
                                            Cost</span>
                                        <?php if (($isLocked && !$isAdmin) || !canEdit('bookings')): ?>
                                            <!-- Locked after Final Invoice or without edit permission -->
                                            <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 3px 8px;"
                                                title="Locked">
                                                <i class="fas fa-lock" style="font-size: 0.6rem; color: #94a3b8;"></i>
                                                <span
                                                    style="font-weight: 800; color: #334155; font-size: 0.9rem;"><?php echo number_format(floatval($item['purchase_amount']), 2); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <!-- Always editable before invoice -->
                                            <input type="number" step="0.01" value="<?php echo floatval($item['purchase_amount'] ?? 0); ?>"
                                                onchange="updatePurchaseCost(<?php echo $item['id']; ?>, this.value)"
                                                style="width: 100px; font-weight: 700; color: #475569; font-size: 0.9rem; border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 5px; outline: none;"
                                                onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#e2e8f0'">
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span
                                            style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">Net
                                            Profit</span>
                                        <div
                                            style="display: inline-flex; align-items: center; gap: 0.4rem; background: #f0fdf4; color: #166534; padding: 0.25rem 0.6rem; border-radius: 6px; border: 1px solid #bbf7d0;">
                                            <i class="fas fa-arrow-up" style="font-size: 0.6rem;"></i>
                                            <span
                                                style="font-weight: 800; font-size: 0.8rem;"><?php echo formatCurrency($itemMargin); ?></span>
                                            <span
                                                style="font-size: 0.65rem; font-weight: 600; opacity: 0.8;">(<?php echo number_format($marginPct, 1); ?>%)</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php if (canEdit('bookings')): ?>
                                        <button onclick="promptSetCost(<?php echo $item['id']; ?>)"
                                            style="background: #f1f5f9; border: 1px dashed #cbd5e1; color: #64748b; padding: 0.5rem; border-radius: 8px; font-size: 0.7rem; font-weight: 700; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                                            <i class="fas fa-plus-circle"></i> SET PURCHASE COST
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 600;">Cost Pending</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="color: #94a3b8; font-size: 0.7rem; font-weight: 600; text-align: center; border: 1px solid #f1f5f9; background: #f8fafc; border-radius: 6px; padding: 0.4rem; text-transform: uppercase;">
                                    No Rental Selected
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div
                                style="color: #94a3b8; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="fas fa-house-user"></i> Internal Asset
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right; vertical-align: middle;">
                        <?php 
                        $itemSqft = floatval($item['width']) * floatval($item['height']) * intval($item['unit'] ?? 1);
                        $hasPrint = floatval($item['client_print_rate'] ?? 0) > 0;
                        $hasMount = floatval($item['client_mount_rate'] ?? 0) > 0;
                        ?>
                        <?php 
                        $hasRental = floatval($item['amount'] ?? 0) > 0 || (!$hasPrint && !$hasMount);
                        if ($hasRental): 
                        ?>
                        <!-- Booking / Rental Cost -->
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px; margin-bottom: <?php echo ($hasPrint || $hasMount) ? '8px' : '0'; ?>;">
                            <?php if ($hasPrint || $hasMount): ?>
                                <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Rental:</span>
                            <?php endif; ?>
                            <?php if (($isLocked && !$isAdmin) || !canEdit('bookings')): ?>
                                <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 3px 8px; justify-content: flex-end;"
                                    title="Locked">
                                    <i class="fas fa-lock" style="font-size: 0.6rem; color: #94a3b8;"></i>
                                    <span
                                        style="font-weight: 800; color: #334155; font-size: 0.9rem;"><?php echo number_format(floatval($item['amount']), 2); ?></span>
                                </div>
                            <?php else: ?>
                                <input type="number" step="0.01" value="<?php echo floatval($item['amount'] ?? 0); ?>"
                                    onchange="updateSellingCost(<?php echo $item['id']; ?>, this.value)"
                                    style="width: 120px; text-align: right; font-weight: 800; color: #0f172a; font-size: 0.95rem; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px; outline: none; background: #fff; transition: border-color 0.2s;"
                                    onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#e2e8f0'">
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Printing Cost -->
                        <?php if ($hasPrint): ?>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px; margin-bottom: <?php echo $hasMount ? '8px' : '0'; ?>; margin-top: 4px;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Print / Sqft:</span>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <?php if (($isLocked && !$isAdmin) || !canEdit('bookings')): ?>
                                    <span style="font-weight: 800; color: #334155; font-size: 0.9rem;"><?php echo number_format(floatval($item['client_print_rate']), 2); ?></span>
                                <?php else: ?>
                                    <input type="number" step="0.01" value="<?php echo floatval($item['client_print_rate'] ?? 0); ?>"
                                        onchange="updateClientPrintRate(<?php echo $item['id']; ?>, this.value)"
                                        style="width: 120px; text-align: right; font-weight: 800; color: #0f172a; font-size: 0.95rem; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px; outline: none; background: #fff; transition: border-color 0.2s;"
                                        onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#e2e8f0'">
                                <?php endif; ?>
                                <span style="font-size: 0.8rem; font-weight: 800; color: #059669; min-width: 60px; text-align: right;">₹<?php echo number_format($item['client_print_rate'] * $itemSqft, 2); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Mounting Cost -->
                        <?php if ($hasMount): ?>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px; margin-top: 4px;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Mount / Sqft:</span>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <?php if (($isLocked && !$isAdmin) || !canEdit('bookings')): ?>
                                    <span style="font-weight: 800; color: #334155; font-size: 0.9rem;"><?php echo number_format(floatval($item['client_mount_rate']), 2); ?></span>
                                <?php else: ?>
                                    <input type="number" step="0.01" value="<?php echo floatval($item['client_mount_rate'] ?? 0); ?>"
                                        onchange="updateClientMountRate(<?php echo $item['id']; ?>, this.value)"
                                        style="width: 120px; text-align: right; font-weight: 800; color: #0f172a; font-size: 0.95rem; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px; outline: none; background: #fff; transition: border-color 0.2s;"
                                        onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#e2e8f0'">
                                <?php endif; ?>
                                <span style="font-size: 0.8rem; font-weight: 800; color: #059669; min-width: 60px; text-align: right;">₹<?php echo number_format($item['client_mount_rate'] * $itemSqft, 2); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if (canDelete('bookings')): ?>
                            <button onclick="deleteItem(<?php echo $item['id']; ?>)"
                                style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0.5rem;"
                                title="Remove Site from Booking">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot style="background: #f8fafc; font-weight: 800; border-top: 2px solid #e2e8f0;">
            <tr>
                <td colspan="7" style="text-align: right; padding: 1rem; color: #475569; font-size: 0.9rem; vertical-align: middle;">Total / Summary:</td>
                <td style="padding: 1rem; vertical-align: top; text-align: left;">
                    <div style="margin-bottom: 0.5rem;">
                        <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">Total Purchase Cost</span>
                        <span style="color: #334155; font-size: 0.9rem; font-weight: 800;"><?php echo number_format($taCost, 2); ?></span>
                    </div>
                    <div>
                        <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">Total Margin</span>
                        <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.6rem; border-radius: 6px; border: 1px solid #bae6fd; font-size: 0.8rem; font-weight: 800;">
                            <i class="fas fa-chart-line"></i>
                            <span><?php echo number_format($totalMargin, 2); ?></span>
                        </div>
                    </div>
                </td>
                <td style="text-align: right; padding: 1rem; vertical-align: middle;">
                    <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">Total Selling Cost</span>
                    <span style="color: #0f172a; font-size: 1rem; font-weight: 900;"><?php echo number_format($b['total_amount'], 2); ?></span>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<style>
    .p-panel {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .p-title {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--secondary);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 0.5rem;
    }

    .p-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .stat-box {
        margin-bottom: 1rem;
    }

    .stat-box small {
        display: block;
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
    }

    .stat-box strong {
        font-size: 1.1rem;
        color: #0f172a;
        font-weight: 800;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        text-align: left;
        padding: 1rem;
        border-bottom: 2px solid #f1f5f9;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #64748b;
    }

    .table td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .badge-type {
        background: #f1f5f9;
        padding: 0.2rem 0.4rem;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 800;
        color: #475569;
    }

    .exec-status {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-pending {
        background: #fef9c3;
        color: #854d0e;
    }

    .status-in_progress {
        background: #dcfce7;
        color: #166534;
    }

    .status-completed {
        background: #e0f2fe;
        color: #0369a1;
    }

    /* Modal CSS */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 5000;
        align-items: center;
        justify-content: center;
    }

    .p-input {
        width: 100%;
        padding: 0.5rem 0.6rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.82rem;
        color: #1e293b !important;
        background-color: #ffffff !important;
        font-family: inherit;
        -webkit-text-fill-color: #1e293b !important;
        opacity: 1 !important;
        -webkit-appearance: auto;
        appearance: auto;
        transition: border-color 0.15s;
    }
    .p-input:focus {
        outline: none;
        border-color: #0d9488;
        box-shadow: 0 0 0 2px rgba(13,148,136,0.1);
    }
    select.p-input,
    select.p-input option,
    select.p-input option:first-child {
        color: #1e293b !important;
        background: #ffffff !important;
        -webkit-text-fill-color: #1e293b !important;
    }

    .search-group {
        margin-bottom: 0;
    }

    #modal-site-body tr {
        cursor: pointer;
        transition: background 0.15s, border-left 0.15s;
        border-left: 3px solid transparent;
    }
    #modal-site-body tr:hover {
        background: #f8fafc !important;
    }

    .crs-table th {
        font-size: 0.65rem;
        color: #64748b;
        text-transform: uppercase;
        padding: 1rem;
        text-align: left;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .crs-table td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
        vertical-align: middle;
    }
</style>

<!-- Add Site Modal -->
<div id="addSiteModal" class="modal-overlay">
    <div class="card"
        style="width: 98vw; height: 98vh; max-width: none; max-height: none; padding: 1.5rem 2rem; border-radius: 16px; display: flex; flex-direction: column; margin: 0; box-sizing: border-box;">
        <div
            style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h3 style="margin: 0; font-weight: 800;"><i class="fas fa-plus-circle"
                        style="color: var(--primary);"></i> Add Sites to Booking</h3>
                <div id="modal-bucket-btn" onclick="toggleModalBucket()"
                    style="background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: all 0.2s;">
                    <i class="fas fa-shopping-basket"></i>
                    Selected: <span id="modal-selected-count"
                        style="background: #059669; color: white; padding: 0.1rem 0.5rem; border-radius: 4px; font-size: 0.65rem;">0</span>
                </div>
            </div>
            <button onclick="closeAddSiteModal()"
                style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>

        <div style="display: flex; gap: 2rem; margin-bottom: 1rem;">
            <div class="search-group">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Ownership</label>
                <div style="display: flex; gap: 1rem;">
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_ownership" value="all" checked
                            onchange="modalFetchSites(1)"> All</label>
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_ownership" value="HA"
                            onchange="modalFetchSites(1)"> Self</label>
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_ownership" value="TA"
                            onchange="modalFetchSites(1)"> Vendor</label>
                </div>
            </div>
            <div class="search-group">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Availability</label>
                <div style="display: flex; gap: 1rem;">
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_availability" value="available"
                            checked onchange="modalFetchSites(1)"> Available</label>
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_availability" value="all"
                            onchange="modalFetchSites(1)"> All</label>
                </div>
            </div>
        </div>

        <div
            style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; align-items: flex-end; width: 100%;">
            <div class="search-group" style="flex: 2 1 200px; min-width: 150px; margin-bottom: 0;">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Search
                    Site / Code / Area</label>
                <input type="text" id="modal-search" class="p-input" placeholder="Search..."
                    oninput="modalFetchSites(1)" style="height: 32px; font-size: 0.78rem; color: #1e293b !important; background: #fff; -webkit-text-fill-color: #1e293b; padding: 0 10px; box-sizing: border-box;">
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Media</label>
                <select id="modal-media" class="p-input" onchange="modalFetchSites(1)"
                    style="height: 32px; font-size: 0.78rem; color: #1e293b !important; background: #fff; -webkit-text-fill-color: #1e293b; padding: 0 8px; box-sizing: border-box;">
                    <option value="" style="color:#1e293b;">All</option>
                    <?php foreach ($mediaTypes as $mt): ?>
                        <option value="<?php echo htmlspecialchars($mt); ?>"><?php echo htmlspecialchars($mt); ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">District</label>
                <select id="modal-district" class="p-input" onchange="modalFetchSites(1)"
                    style="height: 32px; font-size: 0.78rem; color: #1e293b !important; background: #fff; -webkit-text-fill-color: #1e293b; padding: 0 8px; box-sizing: border-box;">
                    <option value="" style="color:#1e293b;">All</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">State</label>
                <select id="modal-state" class="p-input" onchange="modalFetchSites(1)"
                    style="height: 32px; font-size: 0.78rem; color: #1e293b !important; background: #fff; -webkit-text-fill-color: #1e293b; padding: 0 8px; box-sizing: border-box;">
                    <option value="" style="color:#1e293b;">All</option>
                    <?php foreach ($states as $s): ?>
                        <option value="<?php echo $s; ?>"><?php echo $s; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">City</label>
                <select id="modal-city" class="p-input" onchange="modalFetchSites(1)"
                    style="height: 32px; font-size: 0.78rem; color: #1e293b !important; background: #fff; -webkit-text-fill-color: #1e293b; padding: 0 8px; box-sizing: border-box;">
                    <option value="" style="color:#1e293b;">All</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?php echo $c; ?>"><?php echo $c; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Location</label>
                <select id="modal-location" class="p-input" onchange="modalFetchSites(1)"
                    style="height: 32px; font-size: 0.78rem; color: #1e293b !important; background: #fff; -webkit-text-fill-color: #1e293b; padding: 0 8px; box-sizing: border-box;">
                    <option value="" style="color:#1e293b;">All</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Light</label>
                <select id="modal-light" class="p-input" onchange="modalFetchSites(1)"
                    style="height: 32px; font-size: 0.78rem; color: #1e293b !important; background: #fff; -webkit-text-fill-color: #1e293b; padding: 0 8px; box-sizing: border-box;">
                    <option value="" style="color:#1e293b;">All</option>
                    <?php foreach ($illuminations as $il): ?>
                        <option value="<?php echo $il; ?>"><?php echo $il; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label
                    style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Vendor</label>
                <select id="modal-vendor" class="p-input" onchange="modalFetchSites(1)"
                    style="height: 32px; font-size: 0.78rem; color: #1e293b !important; background: #fff; -webkit-text-fill-color: #1e293b; padding: 0 8px; box-sizing: border-box;">
                    <option value="" style="color:#1e293b;">All</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 0 0 auto; margin-bottom: 0; display: flex; align-items: flex-end;">
                <button class="btn btn-secondary" onclick="clearModalFilters()"
                    style="height: 30px; font-size: 0.75rem; padding: 0 0.75rem; border-radius: 8px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; box-sizing: border-box;"><i
                        class="fas fa-times-circle"></i> Clear</button>
            </div>
        </div>

        <!-- Category Filter Tabs for Modal -->
        <div class="inventory-tabs" id="modal-media-tabs" style="margin-bottom: 1rem;">
            <button type="button" class="tab active" onclick="selectModalMediaTab('all', this)">All</button>
            <?php foreach ($mediaTypes as $mtype): ?>
                <button type="button" class="tab" onclick="selectModalMediaTab('<?php echo htmlspecialchars($mtype); ?>', this)">
                    <?php echo htmlspecialchars($mtype); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div style="flex: 1; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
            <table class="crs-table selection-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="modal-select-all"
                                onclick="toggleAllModalSites(this)"></th>
                        <th style="width: 100px;">Preview</th>
                        <th>City / Code</th>
                        <th>Asset Details</th>
                        <th>Size</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody id="modal-site-body">
                    <!-- Fetched dynamically -->
                </tbody>
            </table>
        </div>

        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
            <div id="modal-pg-info" style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Loading...</div>
            <div id="modal-pg-numbers" style="display: flex; gap: 0.25rem;"></div>
            <button class="btn btn-primary" onclick="addSelectedSitesToBooking()">Add Selected Sites to Booking</button>
        </div>
    </div>
</div>

<!-- Simple Lightbox HTML -->
<div id="simple-lightbox" onclick="closeLightbox()"
    style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
    <div style="position: relative; text-align: center; max-width: 90%; max-height: 90vh;">
        <div style="display: flex; align-items: center; justify-content: center; position: relative;">
            <button id="lightbox-prev" onclick="changeLightboxImage(-1); event.stopPropagation();"
                style="position: absolute; left: -60px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 50%; width: 44px; height: 44px; font-size: 1.2rem; cursor: pointer; display: none; align-items: center; justify-content: center; transition: all 0.2s;"
                onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-chevron-left"></i>
            </button>

            <img id="lightbox-img" src=""
                style="max-width: 100%; max-height: 85vh; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.15);">

            <button id="lightbox-next" onclick="changeLightboxImage(1); event.stopPropagation();"
                style="position: absolute; right: -60px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 50%; width: 44px; height: 44px; font-size: 1.2rem; cursor: pointer; display: none; align-items: center; justify-content: center; transition: all 0.2s;"
                onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-chevron-right"></i>
            </button>

            <div id="lightbox-badge"
                style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 6px 16px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2);">
            </div>
        </div>

        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 1rem;">
            <button id="lightbox-select-btn" onclick="selectLightboxImage(); event.stopPropagation();"
                style="background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 800; font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 15px rgba(13,148,136,0.4); display: none;">
                <i class="fas fa-check-circle"></i> Use This Image
            </button>
            <button id="lightbox-export-btn" onclick="toggleExportImage(); event.stopPropagation();"
                style="background: rgba(0,0,0,0.75); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 800; font-size: 0.9rem; cursor: pointer; display: none;">
                <i class="far fa-square"></i> Select for PPT/PDF
            </button>
        </div>

        <div onclick="closeLightbox()"
            style="position: absolute; top: -60px; right: -60px; color: white; font-size: 2.5rem; cursor: pointer; opacity: 0.6; transition: opacity 0.2s;"
            onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</div>
    </div>
</div>

<script>
    function deleteItem(itemId) {
        Swal.fire({
            title: 'Remove this site?',
            text: "This will remove the site from the booking and delete associated operation tasks. Are you sure?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../../ajax/delete_booking_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${itemId}`
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            location.reload();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    });
            }
        });
    }

    function sendPOEmail(bookingId, vendorId) {
        Swal.fire({
            title: 'Sending PO...',
            text: 'Please wait while we prepare the email for the vendor.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Simulate AJAX call to send email
        setTimeout(() => {
            Swal.fire({
                icon: 'success',
                title: 'PO Sent!',
                text: 'The Purchase Order has been successfully emailed to the vendor.',
                timer: 2000,
                showConfirmButton: false
            });
        }, 1500);
    }

    function updatePurchaseCost(itemId, cost) {
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('purchase_cost', cost);

        fetch('../../ajax/update_purchase_cost.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Purchase cost updated'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to update cost', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Network or server error', 'error');
            });
    }
    function updateSellingCost(itemId, cost) {
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('selling_cost', cost);

        fetch('../../ajax/update_selling_cost.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Selling cost updated'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to update cost', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Network or server error', 'error');
            });
    }

    function updateClientPrintRate(itemId, rate) {
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('print_rate', rate);

        fetch('../../ajax/update_client_print_rate.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Print rate updated'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to update rate', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Network or server error', 'error');
            });
    }

    function updateBookingItemUnit(itemId, unit) {
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('unit', unit);

        fetch('../../ajax/update_booking_item_unit.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Unit updated'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to update unit', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Network or server error', 'error');
            });
    }

    function updateClientMountRate(itemId, rate) {
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('mount_rate', rate);

        fetch('../../ajax/update_client_mount_rate.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Mount rate updated'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to update rate', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Network or server error', 'error');
            });
    }
    function editCampaignName() {
        Swal.fire({
            title: 'Campaign Name',
            input: 'text',
            inputValue: '<?php echo addslashes($b['campaign_name'] ?? ''); ?>',
            showCancelButton: true,
            confirmButtonText: 'Save',
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('booking_id', <?php echo $id; ?>);
                formData.append('campaign_name', result.value);
                fetch('../../ajax/update_booking_campaign.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                });
            }
        });
    }

    const clientGsts = <?php echo json_encode($gsts); ?>;
    const currentBillingGstin = '<?php echo addslashes($selectedGstin); ?>';

    function editBillingGstin() {
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
                title: 'Select Billing GSTIN for Booking',
                html: `
                    <div style="text-align: left; font-size: 0.88rem; margin-bottom: 8px; color: #475569;">
                        Choose registered GSTIN or enter a new one:
                    </div>
                    <select id="edit_billing_gstin_select" class="swal2-input" style="width: 100%; box-sizing: border-box; margin: 0 0 10px 0; font-size: 0.9rem;" onchange="toggleCustomGstinInput(this.value)">
                        ${optionsHtml}
                    </select>
                    <div id="custom_gstin_wrap" style="display: none; text-align: left;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155;">Custom 15-digit GSTIN:</label>
                        <input type="text" id="edit_billing_gstin_custom" class="swal2-input" placeholder="e.g. 19AAFCG1067K2ZD" maxlength="15" style="width: 100%; box-sizing: border-box; text-transform: uppercase; margin: 4px 0 0 0;">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Save GSTIN',
                confirmButtonColor: '#0d9488',
                cancelButtonColor: '#64748b',
                didOpen: () => {
                    window.toggleCustomGstinInput = function(val) {
                        const wrap = document.getElementById('custom_gstin_wrap');
                        if (val === '__custom__') {
                            wrap.style.display = 'block';
                            document.getElementById('edit_billing_gstin_custom').focus();
                        } else {
                            wrap.style.display = 'none';
                        }
                    };
                },
                preConfirm: () => {
                    const selVal = document.getElementById('edit_billing_gstin_select').value;
                    let finalGstin = selVal;
                    if (selVal === '__custom__') {
                        finalGstin = document.getElementById('edit_billing_gstin_custom').value.trim().toUpperCase();
                        if (!finalGstin) {
                            Swal.showValidationMessage('Please enter a valid GSTIN');
                            return false;
                        }
                    }
                    return finalGstin;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    saveGstinAjax(result.value);
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
                    saveGstinAjax(result.value.trim().toUpperCase());
                }
            });
        }
    }

    function saveGstinAjax(gstin) {
        const formData = new FormData();
        formData.append('booking_id', <?php echo $id; ?>);
        formData.append('billing_gstin', gstin);

        fetch('../../ajax/update_booking_gstin.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'GSTIN Updated!',
                    text: 'Billing GSTIN has been saved for this booking.',
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
            Swal.fire('Error', 'Server communication failed.', 'error');
        });
    }
    function updateBookingItemPeriod(itemId, field, value) {
        if (field === 'start_date' || field === 'end_date') {
            if (value) {
                const year = parseInt(value.split('-')[0]);
                if (isNaN(year) || year < 2000 || year > 2100) {
                    return; // Silently ignore incomplete years while typing
                }
            }
        }

        const formData = new FormData();
        formData.append('id', itemId);
        formData.append('field', field);
        formData.append('value', value);

        fetch('../../ajax/update_booking_item_period.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Booking item period updated'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to update period', 'error');
                }
            });
    }
    function promptSetCost(itemId) {
        Swal.fire({
            title: 'Set Purchase Cost',
            input: 'number',
            inputLabel: 'Enter the purchase cost for this asset',
            inputPlaceholder: '0.00',
            showCancelButton: true,
            confirmButtonText: 'Save Cost',
            inputValidator: (value) => {
                if (!value) {
                    return 'You need to enter an amount!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                updatePurchaseCost(itemId, result.value);
            }
        });
    }


    function saveAndGeneratePO(booking_id, vendor_id) {
        Swal.fire({
            title: 'Select Company / Entity for PO',
            text: "Please select the company under which you want to generate this Purchase Order:",
            icon: 'question',
            input: 'select',
            inputOptions: <?php echo $entityOptionsJson; ?>,
            inputValue: '<?php echo $b['entity_id'] ?? $_SESSION['active_entity_id'] ?? '2'; ?>',
            showCancelButton: true,
            confirmButtonColor: '#0d9488',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Generate PO',
            inputValidator: (value) => {
                if (!value) {
                    return 'You need to select a company!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const selectedEntityId = result.value;
                Swal.showLoading();
                fetch('../../ajax/save_booking_po.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ booking_id: booking_id, vendor_id: vendor_id, entity_id: selectedEntityId })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.approval_status === 'pending_approval') {
                                Swal.fire('Approval Sent!', data.message, 'success').then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('PO Saved!', `Purchase Order ${data.po_number} has been generated successfully.`, 'success').then(() => {
                                    window.location.reload();
                                });
                            }
                        } else {
                            Swal.fire('Error', data.message || 'Failed to save PO', 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Network or server error', 'error');
                    });
            }
        });
    }

    // --- Add Site Modal JS ---
    let modalCurrentPage = 1;
    const modalPageSize = 50;
    let modalSelectedSites = [];
    let currentModalSites = [];
    let showingBucketOnly = false;

    function updateModalSelectedCount() {
        document.getElementById('modal-selected-count').innerText = modalSelectedSites.length;
    }

    function toggleModalBucket() {
        showingBucketOnly = !showingBucketOnly;
        const btn = document.getElementById('modal-bucket-btn');

        if (showingBucketOnly) {
            btn.style.background = '#047857';
            btn.style.color = 'white';
            document.getElementById('modal-pg-info').style.display = 'none';
            document.getElementById('modal-pg-numbers').style.display = 'none';
            renderModalSites(modalSelectedSites, true); // true = bucket mode
        } else {
            btn.style.background = '#ecfdf5';
            btn.style.color = '#059669';
            document.getElementById('modal-pg-info').style.display = 'block';
            document.getElementById('modal-pg-numbers').style.display = 'flex';
            renderModalSites(currentModalSites);
        }
    }

    const currentBookingId = <?php echo $id; ?>;
    const existingBookingSiteIds = <?php echo json_encode($existingBookingSiteIds); ?>;

    let selectedModalMediaTab = 'all';
    function selectModalMediaTab(mtype, btn) {
        selectedModalMediaTab = mtype;
        
        // Update active class on tabs
        const tabs = document.querySelectorAll('#modal-media-tabs .tab');
        tabs.forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        
        // Sync the modal-media select dropdown
        const select = document.getElementById('modal-media');
        if (select) {
            select.value = mtype === 'all' ? '' : mtype;
        }
        
        modalFetchSites(1);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const select = document.getElementById('modal-media');
        if (select) {
            select.addEventListener('change', function() {
                const val = this.value || 'all';
                const tabs = document.querySelectorAll('#modal-media-tabs .tab');
                tabs.forEach(t => {
                    const onclickAttr = t.getAttribute('onclick');
                    if (onclickAttr && (onclickAttr.includes(`'${val}'`) || (val === 'all' && onclickAttr.includes("'all'")))) {
                        tabs.forEach(x => x.classList.remove('active'));
                        t.classList.add('active');
                    }
                });
            });
        }
    });

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
        document.getElementById('modal-vendor').value = '';
        document.querySelector('input[name="modal_ownership"][value="all"]').checked = true;
        document.querySelector('input[name="modal_availability"][value="available"]').checked = true;

        // Reset category tabs to 'All'
        const tabs = document.querySelectorAll('#modal-media-tabs .tab');
        tabs.forEach(t => t.classList.remove('active'));
        const allTab = Array.from(tabs).find(t => t.getAttribute('onclick') && t.getAttribute('onclick').includes("'all'"));
        if (allTab) allTab.classList.add('active');
        selectedModalMediaTab = 'all';

        if (showingBucketOnly) toggleModalBucket();
        modalFetchSites(1);
    }

    function modalFetchSites(page) {
        modalCurrentPage = page;
        const q = document.getElementById('modal-search').value;
        const media = document.getElementById('modal-media').value;
        const district = document.getElementById('modal-district') ? document.getElementById('modal-district').value : '';
        const state = document.getElementById('modal-state').value;
        const city = document.getElementById('modal-city').value;
        const loc = document.getElementById('modal-location').value;
        const light = document.getElementById('modal-light').value;
        const vendor = document.getElementById('modal-vendor').value;
        const ownership = document.querySelector('input[name="modal_ownership"]:checked').value;
        const availability = document.querySelector('input[name="modal_availability"]:checked').value;

        const url = `../../ajax/fetch_sites.php?page=${page}&limit=${modalPageSize}&booking_id=${currentBookingId}&q=${encodeURIComponent(q)}&media=${encodeURIComponent(media)}&district=${encodeURIComponent(district)}&state=${encodeURIComponent(state)}&city=${encodeURIComponent(city)}&location=${encodeURIComponent(loc)}&light=${encodeURIComponent(light)}&vendor=${encodeURIComponent(vendor)}&ownership=${encodeURIComponent(ownership)}&availability=${encodeURIComponent(availability)}`;

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

    function toggleModalSite(cb, siteId) {
        const siteObj = showingBucketOnly
            ? modalSelectedSites.find(s => s.id == siteId)
            : currentModalSites.find(s => s.id == siteId);

        if (!siteObj) return;

        const row = document.getElementById('modal-row-' + siteId) || cb.closest('tr');

        if (cb.checked) {
            if (!modalSelectedSites.find(s => s.id == siteId)) {
                const sCopy = { ...siteObj, image: siteObj.thumbnail || '', selected_images: siteObj.thumbnail ? [siteObj.thumbnail] : [] };
                modalSelectedSites.push(sCopy);
            }
            if (row) {
                row.style.background = '#f0fdfa';
                row.style.borderLeft = '3px solid #0d9488';
            }
        } else {
            modalSelectedSites = modalSelectedSites.filter(s => s.id != siteId);
            if (showingBucketOnly) {
                if (row) row.remove();
            } else if (row) {
                row.style.background = 'white';
                row.style.borderLeft = '3px solid transparent';
            }
        }
        updateModalSelectedCount();

        const checkboxes = document.querySelectorAll('.modal-site-checkbox:not(:disabled)');
        const checkedCount = document.querySelectorAll('.modal-site-checkbox:not(:disabled):checked').length;
        document.getElementById('modal-select-all').checked = (checkboxes.length > 0 && checkboxes.length === checkedCount);
    }

    function renderModalSites(sites, isBucket = false) {
        const body = document.getElementById('modal-site-body');
        body.innerHTML = '';

        if (sites.length === 0) {
            body.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#94a3b8;">No sites found.</td></tr>';
            return;
        }

        sites.forEach(s => {
            const isAlreadyBooked = existingBookingSiteIds.includes(parseInt(s.id));
            const isChecked = isAlreadyBooked || modalSelectedSites.some(ms => ms.id == s.id);
            const savedSite = modalSelectedSites.find(ms => ms.id == s.id);
            const thumbToUse = savedSite ? savedSite.image : (s.thumbnail ? s.thumbnail : '');
            const thumbUrl = thumbToUse ? '../../uploads/sites/' + thumbToUse : 'https://via.placeholder.com/150x95?text=No+Img';
            const cardRate = parseFloat(s.card_rate || 0);

            let imgHtml = '';
            if (s.thumbnail) {
                const imgList = s.all_images ? s.all_images.split(',') : [s.thumbnail];
                const imgCount = imgList.length;
                imgHtml = `
                <div style="position: relative; width: 100px; height: 60px;">
                    <img id="modal-thumb-${s.id}" src="${thumbUrl}" onclick="openLightboxSlider('${s.all_images || s.thumbnail}', '${s.id}')" style="width: 100%; height: 100%; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    ${imgCount > 1 ? `<div style="position: absolute; bottom: 2px; right: 2px; background: rgba(0,0,0,0.7); color: white; font-size: 0.5rem; padding: 2px 4px; border-radius: 4px; font-weight: 800;"><i class="fas fa-images"></i> ${imgCount}</div>` : ''}
                </div>
            `;
            } else {
                imgHtml = `<div style="width: 100px; height: 60px; border-radius: 8px; background: #f8fafc; border: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #94a3b8; font-weight: 700;">No Img</div>`;
            }

            let badgeHtml = `
                <span style="background:#ecfdf5; color:#059669; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.type}</span>
                <span style="background:#f1f5f9; color:#475569; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.owner_type}</span>
            `;
            if (isAlreadyBooked) {
                badgeHtml += `
                    <span style="background:#e0f2fe; color:#0369a1; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase; margin-left: 0.2rem;">Already Exist</span>
                `;
            }

            const row = document.createElement('tr');
            row.id = 'modal-row-' + s.id;
            row.style.background = isAlreadyBooked ? '#f8fafc' : (isChecked ? '#f0fdfa' : 'white');
            row.style.transition = 'background 0.15s';
            row.style.borderLeft = isAlreadyBooked ? '3px solid #cbd5e1' : (isChecked ? '3px solid #0d9488' : '3px solid transparent');
            row.innerHTML = `
            <td style="text-align:center; padding:1rem;">
                <input type="checkbox" class="modal-site-checkbox" value="${s.id}" ${isChecked ? 'checked' : ''} ${isAlreadyBooked ? 'disabled' : ''} onclick="toggleModalSite(this, ${s.id})" style="width:16px; height:16px; accent-color:var(--primary); cursor: ${isAlreadyBooked ? 'not-allowed' : 'pointer'};">
            </td>
            <td style="padding:1rem;">
                ${imgHtml}
            </td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.city || ''}</div>
                <div style="color:#f97316; font-size:0.65rem; font-weight:800;">${s.site_code}</div>
            </td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.name}</div>
                <div style="font-size:0.65rem; color:#64748b; margin-bottom:4px; line-height:1.1;">${s.location}</div>
                <div style="display: flex; gap: 0.3rem; align-items: center;">
                    ${badgeHtml}
                </div>
            </td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.width}' x ${s.height}'</div>
            </td>
            <td style="padding:1rem; font-weight:800; color:var(--primary);">
                ₹${cardRate.toLocaleString()}
            </td>
        `;
            body.appendChild(row);
        });

        const checkboxes = document.querySelectorAll('.modal-site-checkbox:not(:disabled)');
        const checkedCount = document.querySelectorAll('.modal-site-checkbox:not(:disabled):checked').length;
        document.getElementById('modal-select-all').checked = (checkboxes.length > 0 && checkboxes.length === checkedCount);
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

                if (cb.checked) {
                    if (!modalSelectedSites.find(ms => ms.id === siteId)) {
                        const sCopy = { ...siteObj, image: siteObj.thumbnail || '', selected_images: siteObj.thumbnail ? [siteObj.thumbnail] : [] };
                        modalSelectedSites.push(sCopy);
                    }
                } else {
                    modalSelectedSites = modalSelectedSites.filter(ms => ms.id !== siteId);
                    if (showingBucketOnly) {
                        const row = cb.closest('tr');
                        if (row) row.remove();
                    }
                }
            }
        });
        updateModalSelectedCount();
    }

    function renderModalPagination(total) {
        const totalPages = Math.ceil(total / modalPageSize);
        const container = document.getElementById('modal-pg-numbers');
        const info = document.getElementById('modal-pg-info');
        container.innerHTML = '';

        if (total === 0) {
            info.innerText = '0 sites found';
            return;
        }

        const start = (modalCurrentPage - 1) * modalPageSize + 1;
        const end = Math.min(modalCurrentPage * modalPageSize, total);
        info.innerText = `Showing ${start}-${end} of ${total}`;

        // Previous
        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.className = 'btn btn-secondary';
        prevBtn.style.padding = '0.3rem 0.6rem';
        prevBtn.disabled = modalCurrentPage === 1;
        if (!prevBtn.disabled) {
            prevBtn.onclick = () => modalFetchSites(modalCurrentPage - 1);
        }
        container.appendChild(prevBtn);

        // Page Numbers (max 5)
        let pStart = Math.max(1, modalCurrentPage - 2);
        let pEnd = Math.min(totalPages, pStart + 4);
        if (pEnd - pStart < 4) {
            pStart = Math.max(1, pEnd - 4);
        }

        for (let i = pStart; i <= pEnd; i++) {
            const btn = document.createElement('button');
            btn.innerText = i;
            btn.className = i === modalCurrentPage ? 'btn btn-primary' : 'btn btn-secondary';
            btn.style.padding = '0.3rem 0.6rem';
            if (i !== modalCurrentPage) {
                btn.onclick = () => modalFetchSites(i);
            }
            container.appendChild(btn);
        }

        // Next
        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.className = 'btn btn-secondary';
        nextBtn.style.padding = '0.3rem 0.6rem';
        nextBtn.disabled = modalCurrentPage === totalPages;
        if (!nextBtn.disabled) {
            nextBtn.onclick = () => modalFetchSites(modalCurrentPage + 1);
        }
        container.appendChild(nextBtn);
    }

    function addSelectedSitesToBooking() {
        if (modalSelectedSites.length === 0) {
            Swal.fire('Warning', 'Select at least one site to add.', 'warning');
            return;
        }

        const bookingId = <?php echo $id; ?>;

        Swal.fire({
            title: 'Adding Sites...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const cleanedSites = modalSelectedSites.map(s => {
            let selImages = s.selected_images || [s.image || s.thumbnail || ''];
            const primary = s.image || s.thumbnail || '';
            if (primary) {
                selImages = [primary].concat(selImages.filter(img => img !== primary));
            }
            return {
                ...s,
                image: selImages.join(',')
            };
        });

        fetch('../../ajax/add_booking_items_bulk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: bookingId, sites: cleanedSites })
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

    // Lightbox Logic
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
        const exportBtn = document.getElementById('lightbox-export-btn');
        if (exportBtn) exportBtn.style.display = isSelected ? 'block' : 'none';

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

        if (selectBtn && currentLightboxSiteId) {
            const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
            if (savedSite && savedSite.image === lbImages[lbIndex]) {
                selectBtn.innerHTML = '<i class="fas fa-check-circle"></i> Selected as Primary';
                selectBtn.style.background = '#10b981';
                selectBtn.style.boxShadow = '0 4px 15px rgba(16,185,129,0.4)';
            } else {
                selectBtn.innerHTML = '<i class="far fa-circle"></i> Use as Primary Photo';
                selectBtn.style.background = 'var(--primary)';
                selectBtn.style.boxShadow = '0 4px 15px rgba(13,148,136,0.4)';
            }
        }

        if (exportBtn && currentLightboxSiteId) {
            const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
            if (savedSite) {
                const selImages = savedSite.selected_images || [savedSite.image || savedSite.thumbnail || ''];
                const isSelected = selImages.includes(lbImages[lbIndex]);
                
                if (isSelected) {
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
        if (currentLightboxSiteId) {
            const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
            if (savedSite) {
                savedSite.image = lbImages[lbIndex];
                
                // Also ensure it is selected for export/print
                if(!savedSite.selected_images) {
                    savedSite.selected_images = [lbImages[lbIndex]];
                } else if(!savedSite.selected_images.includes(lbImages[lbIndex])) {
                    savedSite.selected_images.push(lbImages[lbIndex]);
                }

                // Update thumbnail in table row
                const thumbImg = document.getElementById('modal-thumb-' + currentLightboxSiteId);
                if (thumbImg) {
                    thumbImg.src = '../../uploads/sites/' + lbImages[lbIndex];
                }

                updateLightboxImage();

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Primary image set!',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
    }

    function toggleExportImage() {
        if (!currentLightboxSiteId) return;
        
        const id = currentLightboxSiteId;
        const savedSite = modalSelectedSites.find(ms => ms.id == id);
        if (!savedSite) {
            Swal.fire('Note', 'Please select this asset first to manage its export images.', 'info');
            return;
        }
        
        if (!savedSite.selected_images) {
            savedSite.selected_images = [savedSite.image || savedSite.thumbnail || ''];
        }
        
        const currentImg = lbImages[lbIndex];
        const imgIdx = savedSite.selected_images.indexOf(currentImg);
        
        if (imgIdx !== -1) {
            if (savedSite.selected_images.length <= 1) {
                Swal.fire('Warning', 'You must keep at least one image selected for export.', 'warning');
                return;
            }
            savedSite.selected_images.splice(imgIdx, 1);
            if ((savedSite.image || savedSite.thumbnail) === currentImg) {
                savedSite.image = savedSite.selected_images[0];
                const thumbImg = document.getElementById('modal-thumb-' + id);
                if (thumbImg) {
                    thumbImg.src = '../../uploads/sites/' + savedSite.image;
                }
            }
        } else {
            savedSite.selected_images.push(currentImg);
        }
        
        updateLightboxImage();
    }

    function closeLightbox() {
        document.getElementById('simple-lightbox').style.display = 'none';
    }

    document.addEventListener('keydown', function (e) {
        const lb = document.getElementById('simple-lightbox');
        if (lb && lb.style.display === 'flex') {
            if (e.key === 'ArrowLeft') changeLightboxImage(-1);
            if (e.key === 'ArrowRight') changeLightboxImage(1);
            if (e.key === 'Escape') closeLightbox();
        }
    });

    function editSiteLocation(itemId, currentLoc) {
        Swal.fire({
            title: 'Edit Site Location',
            input: 'text',
            inputValue: currentLoc,
            showCancelButton: true,
            confirmButtonText: 'Save',
            showLoaderOnConfirm: true,
            preConfirm: (newLoc) => {
                return fetch('../../ajax/update_booking_item_location.php', {
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
                document.getElementById('site-loc-' + itemId).innerText = result.value;
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
                return fetch('../../ajax/update_booking_item_name.php', {
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
                document.getElementById('site-name-' + itemId).innerText = result.value;
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success', title: 'Site Name updated!', showConfirmButton: false, timer: 1500
                });
            }
        });
    }

// Native HTML5 Drag and Drop row reordering script for bookings
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

        fetch('../../ajax/update_booking_item_order.php', {
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

function syncBookingInvoice(id) {
    Swal.fire({
        title: 'Sync Invoice & Ledger?',
        text: 'This will recalculate all booking item amounts and update the Tax Invoice and Client Ledger.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Sync Now'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Syncing...',
                text: 'Recalculating booking financials, invoice, and ledger...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            const fd = new FormData();
            fd.append('booking_id', id);
            fetch('../../ajax/sync_booking_invoice.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('Synced!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Sync failed', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network or server error during sync', 'error');
            });
        }
    });
}

// Helper function to render official Purchase Order to PDF Blob
async function generatePoPdfBlob(poId) {
    try {
        const poResp = await fetch(`generate_po.php?po_id=${poId}`);
        if (!poResp.ok) return null;
        const poHtml = await poResp.text();

        let iframe = document.getElementById('po-pdf-render-iframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'po-pdf-render-iframe';
            iframe.style.cssText = 'position: fixed; left: -9999px; top: 0; width: 794px; height: 1200px; border: none; opacity: 0; pointer-events: none; z-index: -1;';
            document.body.appendChild(iframe);
        }

        const iframeDoc = iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(poHtml);
        iframeDoc.close();

        // Inject overrides for perfect A4 rendering
        const styleElem = iframeDoc.createElement('style');
        styleElem.innerHTML = `
            * { box-sizing: border-box !important; }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                font-family: Arial, sans-serif !important;
                width: 794px !important;
            }
            .po-wrapper {
                width: 794px !important;
                max-width: 794px !important;
                min-height: auto !important;
                margin: 0 auto !important;
                padding: 6mm 8mm !important;
                box-shadow: none !important;
                border: none !important;
                box-sizing: border-box !important;
                background: #ffffff !important;
            }
            .btn-print, .no-print { display: none !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            img { max-width: 100% !important; }
        `;
        iframeDoc.head.appendChild(styleElem);

        // Wait for all images inside iframe to load (letterhead logo, signature, stamps)
        const images = Array.from(iframeDoc.images);
        if (images.length > 0) {
            await Promise.all(images.map(img => {
                if (img.complete && img.naturalHeight !== 0) return Promise.resolve();
                return new Promise(resolve => {
                    img.onload = resolve;
                    img.onerror = resolve;
                    setTimeout(resolve, 2000);
                });
            }));
        }

        // Stabilization pause
        await new Promise(resolve => setTimeout(resolve, 300));

        const targetElem = iframeDoc.querySelector('.po-wrapper') || iframeDoc.body;
        const canvas = await html2canvas(targetElem, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: 794,
            windowWidth: 794
        });

        const JsPdfConstructor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : ((typeof jsPDF !== 'undefined') ? jsPDF : (window.jsPDF || null));
        if (!JsPdfConstructor) {
            console.error('jsPDF not loaded');
            return null;
        }

        const pdf = new JsPdfConstructor({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4',
            compress: true
        });

        const pageWidth = 210; // mm
        const pageHeight = 297; // mm
        const imgWidth = pageWidth;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const imgData = canvas.toDataURL('image/jpeg', 0.95);

        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
        heightLeft -= pageHeight;

        while (heightLeft > 5) {
            position = heightLeft - imgHeight;
            pdf.addPage('a4', 'portrait');
            pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
            heightLeft -= pageHeight;
        }

        return pdf.output('blob');
    } catch (e) {
        console.error('Error generating PO PDF blob:', e);
        return null;
    }
}

// Helper function to render official Purchase Order to Base64 PDF
async function generatePoPdfBase64(poId) {
    const blob = await generatePoPdfBlob(poId);
    if (!blob) return null;
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            const dataUrl = reader.result;
            resolve(dataUrl.split(',')[1] || null);
        };
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(blob);
    });
}

// Send Single PO via Email (SMTP)
async function sendPoEmail(poId, poNumber, totalAmt, poDate, poStartDate = '', poEndDate = '', vendorEmail = '', vendorName = 'Vendor') {
    const defaultSubject = `Purchase Order: ${poNumber} - Sudha Creative`;
    const periodText = (poStartDate && poEndDate) ? `for the period from **${poStartDate} to ${poEndDate}** ` : '';
    const defaultTemplateText = `Dear Sir/Madam,

Please be informed that the Purchase Order **${poNumber}** has been issued ${periodText}at a rate of **₹${totalAmt}**.

Please find the official Purchase Order PDF attached for your reference and records.

Thank you.

Regards,
Sudha Creative`;

    const { value: formValues } = await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#ea4335;"><i class="fas fa-paper-plane"></i> Send Purchase Order via Email</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Recipient Email Address (Vendor) <span style="color:#ef4444;">*</span></label>
                    <input id="smtp_po_recipient_email" type="text" value="${vendorEmail}" placeholder="vendor@example.com" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Email Subject</label>
                    <input id="smtp_po_subject" type="text" value="${defaultSubject}" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Message Body</label>
                    <textarea id="smtp_po_message" style="width: 100%; box-sizing: border-box; height: 130px; font-size: 0.86rem; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; resize: vertical; line-height: 1.45; font-family: inherit;">${defaultTemplateText}</textarea>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; color: #166534; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.3rem;"></i>
                    <span>Official <b>${poNumber}.pdf</b> document will be rendered and attached directly to the email.</span>
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
            const emailVal = document.getElementById('smtp_po_recipient_email').value.trim();
            const subjectVal = document.getElementById('smtp_po_subject').value.trim();
            const msgVal = document.getElementById('smtp_po_message').value.trim();
            if (!emailVal) {
                Swal.showValidationMessage('Please enter a valid recipient email address.');
                return false;
            }
            return { email: emailVal, subject: subjectVal, message: msgVal };
        }
    });

    if (formValues) {
        Swal.fire({
            title: 'Preparing & Sending PO...',
            html: `
                <div style="padding: 10px 0; text-align: center;">
                    <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #0d9488; margin-bottom: 12px;"></i><br>
                    Rendering official <b>${poNumber}.pdf</b> & dispatching via SMTP to <b>${formValues.email}</b>...
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const poPdfBase64 = await generatePoPdfBase64(poId);

            const formData = new FormData();
            formData.append('po_id', poId);
            formData.append('email', formValues.email);
            formData.append('subject', formValues.subject);
            formData.append('message', formValues.message);
            if (poPdfBase64) {
                const cleanPoNum = poNumber.replace(/[\/\\?%*:|"<>]/g, '_');
                formData.append('po_pdf_base64', poPdfBase64);
                formData.append('po_pdf_name', `Purchase_Order_${cleanPoNum}.pdf`);
            }

            const resp = await fetch('../../ajax/send_po_email.php', {
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
                    title: 'PO Sent Successfully!',
                    html: `<b>${res.message || 'Purchase Order sent successfully!'}</b><br><br><span style="color:#059669;font-size:0.88rem;"><i class="fas fa-paperclip"></i> Official PDF Attached (${poNumber}.pdf)</span><br><span style="color:#64748b;font-size:0.82rem;">Sender: sudhacreativemalda@gmail.com</span>`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Sending Failed',
                    text: res.message || 'Could not send PO email via SMTP server.'
                });
            }
        } catch (err) {
            Swal.fire('SMTP Error', err.message || 'Network error occurred while communicating with SMTP server.', 'error');
        }
    } else if (Swal.getDismissReason() === Swal.DismissReason.cancel) {
        const emailInput = document.getElementById('smtp_po_recipient_email')?.value.trim() || vendorEmail;
        const subjectInput = document.getElementById('smtp_po_subject')?.value.trim() || defaultSubject;
        const msgInput = document.getElementById('smtp_po_message')?.value.trim() || defaultTemplateText;
        const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailInput)}&su=${encodeURIComponent(subjectInput)}&body=${encodeURIComponent(msgInput)}`;
        window.open(gmailUrl, '_blank');
    }
}

// Send Single PO via WhatsApp with Attached Official PDF
async function sendPoWhatsApp(poId, poNumber, totalAmt, poDate, poStartDate = '', poEndDate = '', vendorPhone = '', vendorName = 'Vendor') {
    const cleanPoNum = poNumber.replace(/[\/\\?%*:|"<>]/g, '_');
    const pdfFileName = `Purchase_Order_${cleanPoNum}.pdf`;

    await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#25D366;"><i class="fab fa-whatsapp"></i> Share PO PDF via WhatsApp</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Vendor WhatsApp Number</label>
                    <div style="position: relative;">
                        <i class="fab fa-whatsapp" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #25D366; font-size: 1.1rem;"></i>
                        <input id="wa_po_phone" type="text" value="${vendorPhone}" placeholder="e.g. 9876543210 (or leave blank for chat selection)" style="width: 100%; box-sizing: border-box; font-size: 0.95rem; font-weight: 600; padding: 0.6rem 0.75rem 0.6rem 2.4rem; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none;">
                    </div>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.8rem; flex-shrink: 0;"></i>
                    <div style="flex: 1; overflow: hidden;">
                        <div style="font-weight: 800; color: #0f172a; font-size: 0.9rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${pdfFileName}</div>
                        <div style="font-size: 0.75rem; color: #15803d; margin-top: 2px;">PO #${poNumber} ${totalAmt ? '• ₹' + totalAmt : ''} • Ready for WhatsApp</div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                    <button type="button" id="btnShareWaWeb" style="width: 100%; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.92rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(37,211,102,0.35); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                        <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Open & Share in WhatsApp Web
                    </button>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" id="btnShareWaApp" style="flex: 1; background: #0284c7; color: white; border: none; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fas fa-share-alt"></i> WhatsApp App / Mobile
                        </button>
                        <button type="button" id="btnDownloadPoPdf" style="flex: 1; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fas fa-download"></i> Download PDF Only
                        </button>
                    </div>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times"></i> Close',
        cancelButtonColor: '#94a3b8',
        showCloseButton: true,
        didOpen: () => {
            document.getElementById('btnShareWaWeb')?.addEventListener('click', () => {
                executePoShare('web');
            });
            document.getElementById('btnShareWaApp')?.addEventListener('click', () => {
                executePoShare('app');
            });
            document.getElementById('btnDownloadPoPdf')?.addEventListener('click', () => {
                executePoShare('download');
            });
        }
    });

    async function executePoShare(mode) {
        const phoneInput = document.getElementById('wa_po_phone')?.value.trim().replace(/[^0-9]/g, '') || '';
        let phoneFormatted = phoneInput;
        if (phoneFormatted && phoneFormatted.length === 10) phoneFormatted = '91' + phoneFormatted;

        if (mode === 'download') {
            Swal.fire({
                title: 'Generating PDF...',
                text: 'Please wait...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });
            const pdfBlob = await generatePoPdfBlob(poId);
            if (pdfBlob) {
                const blobUrl = URL.createObjectURL(pdfBlob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = pdfFileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);
                Swal.fire({
                    icon: 'success',
                    title: 'PO PDF Downloaded!',
                    text: `${pdfFileName} has been saved to your downloads.`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire('Error', 'Failed to generate PDF.', 'error');
            }
            return;
        }

        Swal.fire({
            title: 'Preparing PO PDF...',
            html: `
                <div style="padding: 10px 0; text-align: center;">
                    <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #25D366; margin-bottom: 12px;"></i><br>
                    Rendering official <b>${pdfFileName}</b> for WhatsApp...
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const pdfBlob = await generatePoPdfBlob(poId);
            if (!pdfBlob) throw new Error('Could not generate PO PDF document.');

            const pdfFile = new File([pdfBlob], pdfFileName, { type: 'application/pdf' });

            if (mode === 'app') {
                if (navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
                    Swal.close();
                    try {
                        await navigator.share({
                            files: [pdfFile]
                        });
                    } catch(shareErr) {
                        if (shareErr.name !== 'AbortError') {
                            triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                        }
                    }
                } else {
                    triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                }
            } else {
                // WhatsApp Web mode
                triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'web');
            }
        } catch(err) {
            Swal.fire('Error', err.message || 'Failed to prepare PO PDF for WhatsApp.', 'error');
        }
    }
}

function triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, target = 'web') {
    // 1. Download PDF to user's device
    const blobUrl = URL.createObjectURL(pdfBlob);
    const a = document.createElement('a');
    a.href = blobUrl;
    a.download = pdfFileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(blobUrl);

    // 2. Open WhatsApp Web or Desktop App (clean without text)
    let waUrl = '';
    if (target === 'app') {
        waUrl = phoneFormatted ? `whatsapp://send?phone=${phoneFormatted}` : `whatsapp://send`;
    } else {
        waUrl = phoneFormatted 
            ? `https://web.whatsapp.com/send?phone=${phoneFormatted}`
            : `https://web.whatsapp.com/`;
    }
    window.open(waUrl, '_blank');

    Swal.fire({
        icon: 'success',
        title: 'PDF Downloaded & WhatsApp Opened!',
        html: `
            <div style="text-align: left; font-size: 0.9rem;">
                <p>Official <b>${pdfFileName}</b> has been downloaded to your PC.</p>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; margin-top: 8px; color: #475569; font-size: 0.82rem;">
                    <b>Next Step in WhatsApp:</b> Simply attach or drag the downloaded <code>${pdfFileName}</code> directly into the chat!
                </div>
            </div>
        `,
        confirmButtonColor: '#25D366',
        confirmButtonText: '<i class="fab fa-whatsapp"></i> Got It'
    });
}
</script>

<!-- Include html2canvas & jsPDF for PDF rendering & WhatsApp/SMTP sharing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>