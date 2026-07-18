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
    SELECT b.*, c.name as client_name, c.email as client_email, p.proposal_number
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
           s.width, s.height, s.light_type, s.vendor_id, v.name as vendor_name, v.contact_person as vendor_contact,
           o.status as op_status, o.id as op_id
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    LEFT JOIN partners v ON s.vendor_id = v.id
    LEFT JOIN operations o ON bi.booking_id = o.booking_id AND bi.site_id = o.site_id
    WHERE bi.booking_id = ?
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
    $sqft = $item['width'] * $item['height'];
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
$stmtInvCheck = $pdo->prepare("SELECT id, approval_status FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
$stmtInvCheck->execute([$id]);
$invoiceRow = $stmtInvCheck->fetch(PDO::FETCH_ASSOC);
$invoiceFinalized = (bool) $invoiceRow;
$invoiceApprovalStatus = $invoiceRow['approval_status'] ?? '';

// Check if RO Invoice already exists — locks cost editing
$stmtRoCheck = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
$stmtRoCheck->execute([$id]);
$roFinalized = (bool) $stmtRoCheck->fetchColumn();

// Locked only if invoice is finalized and not rejected (Disabled: Always unlock as requested)
$isLocked = false;

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

// Fetch unique values for filter dropdowns
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM sites WHERE location != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' AND status = 'active' ORDER BY name")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">
                <?php echo htmlspecialchars(!empty($b['booking_number']) ? $b['booking_number'] : '#BK-' . str_pad($b['id'], 4, '0', STR_PAD_LEFT)); ?></h1>
            <span
                style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700;">
                <?php echo $b['proposal_number'] ?: 'N/A'; ?>
            </span>
            <span
                style="background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                <?php echo strtoupper($b['status']); ?>
            </span>
        </div>
        <div style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1.1rem; font-weight: 700; color: #1e293b;" id="display_campaign_name">
                <?php echo htmlspecialchars($b['campaign_name'] ?: 'No Campaign Name'); ?>
            </span>
            <?php if ((!$isLocked || $isAdmin) && canEdit('bookings')): ?>
                <button onclick="editCampaignName()" class="btn"
                    style="padding: 0.2rem 0.5rem; font-size: 0.7rem; border-radius: 4px; background: #e2e8f0; color: #475569; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.3rem; margin-left: 0.5rem;">
                    <i class="fas fa-edit"></i> Edit Name
                </button>
            <?php endif; ?>
        </div>
        <p style="color: #64748b; margin: 0 0 0.5rem 0; font-size: 0.95rem; font-weight: 500;">
            <strong style="color: #334155;"><?php echo htmlspecialchars($b['client_name']); ?></strong> • Tenure:
            <span id="display_tenure_dates"><?php echo date('d M', strtotime($b['start_date'])); ?> to <?php echo date('d M Y', strtotime($b['end_date'])); ?></span>
        </p>
        
        <?php
        // Parse GSTs for Group Companies
        $gsts = [];
        if (($b['business_type'] ?? '') === 'Group of Companies') {
            if (!empty($b['primary_gstin'])) {
                $gsts[] = ['gstin' => $b['primary_gstin'], 'state' => 'Primary'];
            }
            if (!empty($b['additional_gst'])) {
                $add = json_decode($b['additional_gst'], true);
                if (is_array($add)) {
                    foreach ($add as $g) {
                        $gsts[] = ['gstin' => $g['gstin'], 'state' => $g['state']];
                    }
                }
            }
        }
        if (!empty($gsts)): ?>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 0.85rem; color: #475569; background: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 4px; border: 1px solid #e2e8f0;">
                <i class="fas fa-building" style="color: #64748b; margin-right: 4px;"></i> 
                Billing GSTIN: <strong><?php echo htmlspecialchars($b['billing_gstin'] ?: $b['primary_gstin']); ?></strong>
            </span>
            <?php if ((!$isLocked || $isAdmin) && canEdit('bookings')): ?>
                <button onclick="editBillingGstin()" class="btn"
                    style="padding: 0.2rem 0.5rem; font-size: 0.7rem; border-radius: 4px; background: #e2e8f0; color: #475569; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.3rem;">
                    <i class="fas fa-edit"></i> Edit GSTIN
                </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 1rem; align-items: center;">
        <a href="bookings.php" class="btn"
            style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: pointer; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back
        </a>

        <?php
        // Check if invoice already exists
        $stmtInv = $pdo->prepare("SELECT id, approval_status FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
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
        <?php else: ?>
            <button class="btn" onclick="openInvoicePopup(<?php echo $b['id']; ?>)"
                style="background: #0f172a; color: white; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-file-invoice-dollar"></i> Final Tax Invoice
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
// Group sites by vendor & branch GST for PO generation
$vendorSites = [];
foreach ($items as $item) {
    if ($item['owner_type'] === 'TA' && isset($item['vendor_id'])) {
        $vId = $item['vendor_id'];
        $vgst = $item['vendor_gst'] ?? '';
        $key = $vId . '_' . $vgst;

        if (!isset($vendorSites[$key])) {
            $vendorSites[$key] = [
                'id' => $vId,
                'name' => $item['vendor_name'] ?? 'Unknown Vendor',
                'vendor_gst' => $vgst,
                'count' => 0
            ];
        }
        $vendorSites[$key]['count']++;
    }
}

// Prepare statement to check if PO exists
$stmtCheckPO = $pdo->prepare("SELECT id, approval_status FROM purchase_orders WHERE campaign_id = ? AND vendor_id = ?");
?>


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
                <th>Asset Details</th>
                <th>City / Code</th>
                <th>Dimensions</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days</th>
                <th>Cost / Margin</th>
                <th style="text-align: right;">Selling Cost</th>
                <th style="text-align: right; width: 50px;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                $itemMargin = $item['amount'] - ($item['purchase_amount'] ?? 0);
                $marginPct = ($item['purchase_amount'] > 0) ? ($itemMargin / $item['purchase_amount'] * 100) : 0;
                // Check PO existence for this vendor on this booking
                $stmtCheckPO->execute([$b['id'], $item['vendor_id']]);
                $poData = $stmtCheckPO->fetch();
                $existingPoId = $poData ? $poData['id'] : false;
                $poLocked = !empty($existingPoId);
                $poApprovalStatus = $poData ? $poData['approval_status'] : '';
                ?>
                <tr>
                    <td>
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
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; width: 100%;">
                                    <span class="badge-type"
                                        style="margin-right: 0.2rem;"><?php echo $item['owner_type']; ?></span>
                                    <span
                                        style="color: var(--primary); font-weight: 800; background: #f0fdfa; padding: 0.1rem 0.4rem; border-radius: 4px; border: 1px solid #ccfbf1; display: flex; align-items: center; gap: 0.3rem;">
                                        <i class="fas fa-truck-loading" style="font-size: 0.6rem;"></i>
                                        <?php echo $item['vendor_name']; ?>
                                        <?php if ($item['vendor_contact']): ?>
                                            <span
                                                style="color: #64748b; font-weight: 500; font-size: 0.65rem;">(<?php echo $item['vendor_contact']; ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if (floatval($item['amount']) <= 0): ?>
                                        <button
                                            title="PO Blocked: Space Rental is ₹0"
                                            style="background: #dc2626; color: white; width: 26px; height: 26px; border-radius: 6px; border: none; cursor: not-allowed; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;" disabled>
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php elseif ($poLocked): ?>
                                        <?php if ($poApprovalStatus === 'approved' || $isAdmin): ?>
                                            <a href="generate_po.php?po_id=<?php echo $existingPoId; ?>" target="_blank"
                                                title="View Saved PO"
                                                style="background: #10b981; color: white; width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; text-decoration: none;">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                            <?php if (canEdit('bookings')): ?>
                                                <button onclick="sendPOEmail(<?php echo $b['id']; ?>, <?php echo $item['vendor_id']; ?>)"
                                                    title="Send PO via Email"
                                                    style="background: #3b82f6; color: white; width: 26px; height: 26px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                                <button onclick="saveAndGeneratePO(<?php echo $b['id']; ?>, <?php echo $item['vendor_id']; ?>)"
                                                    title="Update PO Details"
                                                    style="background: #f59e0b; color: white; width: 26px; height: 26px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $item['vendor_phone'] ?? ''); ?>?text=Dear <?php echo urlencode($item['vendor_name'] ?? 'Vendor'); ?>, Please find the Purchase Order for Campaign: <?php echo urlencode($b['campaign_name'] ?? 'General'); ?>. Booking Ref: <?php echo urlencode(!empty($b['booking_number']) ? $b['booking_number'] : '#BK-' . str_pad($b['id'], 4, '0', STR_PAD_LEFT)); ?>. Thank you."
                                                target="_blank" title="Send via WhatsApp"
                                                style="background: #22c55e; color: white; width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; text-decoration: none;">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php elseif (in_array($poApprovalStatus, ['draft', 'rejected'])): ?>
                                            <?php if (canEdit('bookings')): ?>
                                                <button onclick="saveAndGeneratePO(<?php echo $b['id']; ?>, <?php echo $item['vendor_id']; ?>)"
                                                    title="Generate & Save PO to Database"
                                                    style="background: #0f172a; color: white; width: 26px; height: 26px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button title="PO Pending Admin Approval"
                                                style="background: #f8fafc; color: #94a3b8; border: 1px solid #cbd5e1; width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; cursor: not-allowed; border: none;">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (canEdit('bookings')): ?>
                                            <button
                                                onclick="saveAndGeneratePO(<?php echo $b['id']; ?>, <?php echo $item['vendor_id']; ?>)"
                                                title="Generate & Save PO to Database"
                                                style="background: #0f172a; color: white; width: 26px; height: 26px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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
                            <?php echo number_format($item['width'] * $item['height']); ?> SQFT</div>
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
                    <td><input type="number" min="1" value="<?php echo intval($item['days'] ?? 30); ?>"
                            onchange="updateBookingItemPeriod(<?php echo $item['id']; ?>, 'days', this.value)"
                            class="t-input"
                            style="height: 32px; width: 70px; text-align: center; border-radius: 6px; padding: 2px 5px; font-weight: 600;"
                            <?php echo (($invoiceFinalized && !$isAdmin) || !canEdit('bookings')) ? 'disabled' : ''; ?>></td>
                    <td>
                        <?php if ($item['owner_type'] === 'TA'): ?>
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
                                    <?php elseif (floatval($item['amount']) <= 0): ?>
                                        <!-- Disabled because selling cost is 0 -->
                                        <input type="number" step="0.01" value="<?php echo floatval($item['purchase_amount'] ?? 0); ?>"
                                            style="width: 100px; font-weight: 700; color: #94a3b8; font-size: 0.9rem; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 5px; outline: none; background: #f8fafc; cursor: not-allowed;" disabled
                                            title="Purchase Cost is not required when Space Rental is 0">
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
                                <?php if (floatval($item['amount']) <= 0): ?>
                                    <!-- Blocked because selling cost is 0 -->
                                    <button title="Purchase Cost is not required when Space Rental is 0"
                                        style="background: #f8fafc; border: 1px dashed #cbd5e1; color: #cbd5e1; padding: 0.5rem; border-radius: 8px; font-size: 0.7rem; font-weight: 700; cursor: not-allowed; width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.4rem;" disabled>
                                        <i class="fas fa-ban"></i> SET PURCHASE COST
                                    </button>
                                <?php elseif (canEdit('bookings')): ?>
                                    <button onclick="promptSetCost(<?php echo $item['id']; ?>)"
                                        style="background: #f1f5f9; border: 1px dashed #cbd5e1; color: #64748b; padding: 0.5rem; border-radius: 8px; font-size: 0.7rem; font-weight: 700; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                                        <i class="fas fa-plus-circle"></i> SET PURCHASE COST
                                    </button>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 600;">Cost Pending</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <div
                                style="color: #94a3b8; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="fas fa-house-user"></i> Internal Asset
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right; vertical-align: middle;">
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
                <td colspan="6" style="text-align: right; padding: 1rem; color: #475569; font-size: 0.9rem; vertical-align: middle;">Total / Summary:</td>
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

    <?php if (!empty($gsts)): ?>
    function editBillingGstin() {
        Swal.fire({
            title: 'Select Billing GSTIN / State',
            html: `
                <select id="edit_billing_gstin" class="swal2-input" style="width: 100%; box-sizing: border-box;">
                    <?php foreach($gsts as $g): ?>
                        <option value="<?php echo htmlspecialchars($g['gstin']); ?>" <?php echo (($b['billing_gstin'] ?? '') === $g['gstin']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['gstin'] . ' - ' . $g['state']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Save',
            preConfirm: () => {
                return document.getElementById('edit_billing_gstin').value;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('booking_id', <?php echo $id; ?>);
                formData.append('billing_gstin', result.value);
                fetch('../../ajax/update_booking_gstin.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        Swal.fire('Error', res.message || 'Failed to update GSTIN', 'error');
                    }
                });
            }
        });
    }
    <?php endif; ?>
    function updateBookingItemPeriod(itemId, field, value) {
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
    function toggleConfFields() {
        const type = document.getElementById('confirmation_type').value;
        const poFields = document.getElementById('po_fields');
        const emailFields = document.getElementById('email_fields');
        if (poFields) poFields.style.display = type === 'po' ? 'block' : 'none';
        if (emailFields) emailFields.style.display = type === 'email' ? 'block' : 'none';
    }

    function openInvoicePopup(bookingId) {
        Swal.fire({
            title: 'Campaign Confirmation',
            html: `
            <div style="text-align: left;">
                <?php
                // Parse GSTs for Group Companies
                $gsts = [];
                if (($b['business_type'] ?? '') === 'Group of Companies') {
                    if (!empty($b['primary_gstin'])) {
                        $gsts[] = ['gstin' => $b['primary_gstin'], 'state' => 'Primary'];
                    }
                    if (!empty($b['additional_gst'])) {
                        $add = json_decode($b['additional_gst'], true);
                        if (is_array($add)) {
                            foreach ($add as $g) {
                                $gsts[] = ['gstin' => $g['gstin'], 'state' => $g['state']];
                            }
                        }
                    }
                }
                if (!empty($gsts)): ?>
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">BILLING GSTIN / STATE</label>
                    <select id="invoice_billing_gstin" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                        <?php foreach($gsts as $g): ?>
                            <option value="<?php echo htmlspecialchars($g['gstin']); ?>" <?php echo (($b['billing_gstin'] ?? '') === $g['gstin']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['gstin'] . ' - ' . $g['state']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">INVOICE NUMBER (Last Used: <?php echo getLastSequenceNumber($pdo, 'invoice'); ?>)</label>
                <input id="custom_invoice_number" class="swal2-input" value="" placeholder="Type Invoice Number manually..." style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">

                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">INVOICE DATE</label>
                <input id="custom_invoice_date" type="date" class="swal2-input" value="<?php echo date('Y-m-d'); ?>" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">

                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CONFIRMATION TYPE</label>
                <select id="confirmation_type" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;" onchange="toggleConfFields()">
                    <option value="po">Customer Purchase Order (PO)</option>
                    <option value="email">Email Confirmation</option>
                </select>

                <div id="po_fields">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CUSTOMER PO NUMBER</label>
                    <input id="customer_po_no" class="swal2-input" placeholder="Enter PO ID" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                    
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">PO DATE</label>
                    <input id="customer_po_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>

                <div id="email_fields" style="display:none;">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">EMAIL CONFIRMATION DATE</label>
                    <input id="email_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>
                
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">UPLOAD ATTACHMENT (PDF/IMAGE)</label>
                <input id="customer_po_file" type="file" accept=".pdf,image/*" class="swal2-file" style="margin: 0; width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px;">
            </div>
        `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Save & Generate Invoice',
            preConfirm: () => {
                const type = document.getElementById('confirmation_type').value;
                const po_no = document.getElementById('customer_po_no').value;
                const po_date = document.getElementById('customer_po_date').value;
                const email_date = document.getElementById('email_date').value;
                const po_file = document.getElementById('customer_po_file').files[0];
                const customInvoiceNo = document.getElementById('custom_invoice_number').value;
                const customInvoiceDate = document.getElementById('custom_invoice_date').value;

                if (!customInvoiceNo) { Swal.showValidationMessage(`Invoice Number is mandatory`); return false; }

                if (type === 'po') {
                    if (!po_no) { Swal.showValidationMessage(`Customer PO Number is mandatory`); return false; }
                    if (!po_date) { Swal.showValidationMessage(`PO Date is mandatory`); return false; }
                }
                if (type === 'email') {
                    if (!email_date) { Swal.showValidationMessage(`Email Confirmation Date is mandatory`); return false; }
                }
                if (!po_file) {
                    Swal.showValidationMessage(`Please upload the PO/Email attachment (PDF/Image)`);
                    return false;
                }

                let formData = new FormData();
                formData.append('booking_id', bookingId);
                formData.append('confirmation_type', type);
                formData.append('customer_po_no', po_no);
                formData.append('customer_po_date', po_date);
                formData.append('email_date', email_date);
                formData.append('custom_invoice_number', customInvoiceNo);
                formData.append('custom_invoice_date', customInvoiceDate);
                formData.append('customer_po_file', po_file);
                
                const billingGstinSelect = document.getElementById('invoice_billing_gstin');
                if (billingGstinSelect) {
                    formData.append('billing_gstin', billingGstinSelect.value);
                }

                return fetch('../../ajax/upload_customer_po.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Upload failed');
                        }
                        return data;
                    }).catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`);
                    });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const data = result.value;
                if (data.approval_status === 'pending_approval' && !data.is_admin) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Approval Sent to Admin!',
                        text: 'The invoice generation request has been sent to the Admin for approval.',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    window.open(`generate_invoice.php?booking_id=${bookingId}`, '_blank');
                    location.reload();
                }
            }
        });
    }

    function saveAndGeneratePO(booking_id, vendor_id) {
        Swal.fire({
            title: 'Save PO to Database?',
            text: "This will officially save the Purchase Order for this vendor.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d9488',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Save it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                fetch('../../ajax/save_booking_po.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ booking_id: booking_id, vendor_id: vendor_id })
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
        const state = document.getElementById('modal-state').value;
        const city = document.getElementById('modal-city').value;
        const loc = document.getElementById('modal-location').value;
        const light = document.getElementById('modal-light').value;
        const vendor = document.getElementById('modal-vendor').value;
        const ownership = document.querySelector('input[name="modal_ownership"]:checked').value;
        const availability = document.querySelector('input[name="modal_availability"]:checked').value;

        const url = `../../ajax/fetch_sites.php?page=${page}&limit=${modalPageSize}&booking_id=${currentBookingId}&q=${encodeURIComponent(q)}&media=${encodeURIComponent(media)}&state=${encodeURIComponent(state)}&city=${encodeURIComponent(city)}&location=${encodeURIComponent(loc)}&light=${encodeURIComponent(light)}&vendor=${encodeURIComponent(vendor)}&ownership=${encodeURIComponent(ownership)}&availability=${encodeURIComponent(availability)}`;

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
                const sCopy = { ...siteObj, image: siteObj.thumbnail || '' };
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
                    <span style="background:#e0f2fe; color:#0369a1; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase; margin-left: 0.2rem;">Already Booked</span>
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
                        const sCopy = { ...siteObj, image: siteObj.thumbnail || '' };
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

        fetch('../../ajax/add_booking_items_bulk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: bookingId, sites: modalSelectedSites })
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

        updateLightboxImage();
    }

    function updateLightboxImage() {
        const lbImg = document.getElementById('lightbox-img');
        const lbBadge = document.getElementById('lightbox-badge');
        const selectBtn = document.getElementById('lightbox-select-btn');

        lbImg.src = '../../uploads/sites/' + lbImages[lbIndex];
        lbBadge.innerText = (lbIndex + 1) + " / " + lbImages.length;

        if (selectBtn && currentLightboxSiteId) {
            const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
            if (savedSite && savedSite.image === lbImages[lbIndex]) {
                selectBtn.innerHTML = '<i class="fas fa-check-circle"></i> Image Selected';
                selectBtn.style.background = '#10b981';
                selectBtn.style.boxShadow = '0 4px 15px rgba(16,185,129,0.4)';
            } else {
                selectBtn.innerHTML = '<i class="far fa-circle"></i> Select This Image';
                selectBtn.style.background = 'var(--primary)';
                selectBtn.style.boxShadow = '0 4px 15px rgba(13,148,136,0.4)';
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
                    title: 'Image selected!',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>