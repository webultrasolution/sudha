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
    SELECT p.*, c.name as client_name, c.email as client_email, u.username as creator 
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
    SELECT pi.*, s.site_code, s.name as site_name, s.location, s.city, s.state as site_state, s.type as media_type, s.owner_type, 
           s.width, s.height, s.light_type, s.card_rate as site_card_rate, s.available_from, s.purchase_rate as master_purchase,
           v.name as vendor_name
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    LEFT JOIN partners v ON s.vendor_id = v.id
    WHERE pi.proposal_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

// Fetch metadata for Add Sites modal
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM sites WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();

// Advanced Stats
$totalSQFT = 0;
$haCost = 0; $taCost = 0;
$haSale = 0; $taSale = 0;
$subTotalWoTax = 0;

foreach ($items as $item) {
    $sqft = $item['width'] * $item['height'];
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
$grandTotal = $displayCost + $p['tax_amount'] + $p['printing_cost'] + $p['mounting_cost'];

$haMarkup = $haSale - $haCost;
$taMarkup = $taSale - $taCost;
$haMarkupPct = ($haCost > 0) ? ($haMarkup / $haCost) * 100 : 0;
$taMarkupPct = ($taCost > 0) ? ($taMarkup / $taCost) * 100 : 0;
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;"><?php echo htmlspecialchars($p['campaign_name']); ?></h1>
            <span style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700;">
                <?php echo $p['proposal_number']; ?>
            </span>
            <?php 
                $displayStatus = $p['status'];
                if (($p['approval_status'] ?? '') === 'approved' && $p['status'] === 'sent') {
                    $displayStatus = 'approved';
                }
            ?>
            <span style="background: #e0e7ff; color: #4338ca; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                <?php echo strtoupper($displayStatus); ?>
            </span>
        </div>
        <p style="color: #64748b; margin: 0; font-size: 0.95rem; font-weight: 500;">
            <strong style="color: #334155;"><?php echo $p['client_name']; ?></strong> • Workspace: <?php echo date('d M', strtotime($p['start_date'])); ?> to <?php echo date('d M Y', strtotime($p['end_date'])); ?>
        </p>
    </div>
    <div style="display: flex; gap: 1rem; align-items: center;">
        <?php if (($p['approval_status'] ?? '') === 'approved'): ?>
            <div class="dropdown">
                <button class="btn" style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='#cbd5e1'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.02)';">
                    <i class="fas fa-download"></i> Export <i class="fas fa-caret-down" style="margin-left: 0.25rem; opacity: 0.7;"></i>
                </button>
                <div class="dropdown-content">
                    <div style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; padding: 0.5rem 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Client Documents</div>
                    <a href="export_pdf.php?id=<?php echo $id; ?>" target="_blank"><i class="fas fa-file-pdf" style="color: #ef4444;"></i> Visual Media Plan (PDF)</a>
                    <a href="export_excel.php?id=<?php echo $id; ?>"><i class="fas fa-file-excel" style="color: #10b981;"></i> Excel Rate Sheet</a>
                    <a href="export_ppt.php?id=<?php echo $id; ?>" target="_blank"><i class="fas fa-file-powerpoint" style="color: #f97316;"></i> PPT Deck / Presentation</a>
                    <div style="height: 1px; background: #f1f5f9; margin: 0.25rem 0;"></div>
                    <div style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; padding: 0.5rem 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Visuals</div>
                    <a href="export_ppt.php?id=<?php echo $id; ?>&mode=view" target="_blank"><i class="fas fa-desktop" style="color: #6366f1;"></i> View Presentation</a>
                    <a href="javascript:void(0)" onclick="copyPublicLink('<?php echo BASE_URL; ?>modules/proposals/export_ppt.php?id=<?php echo $id; ?>')"><i class="fas fa-link" style="color: #6366f1;"></i> Copy Public Link</a>
                    <a href="download_photos.php?id=<?php echo $id; ?>"><i class="fas fa-images" style="color: #8b5cf6;"></i> Download Photos</a>
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
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Panel A: Inventories Control -->
    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-sliders-h"></i> Configuration</h4>
        <div class="p-row">
            <label style="font-weight: 600;">Discount %</label>
            <input type="number" step="0.01" class="p-val-input" value="<?php echo $p['discounting_pct']; ?>" onchange="updatePlan('discounting_pct', this.value)" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
        </div>
        <div class="p-row">
            <label style="font-weight: 600;">Markup %</label>
            <input type="number" step="0.01" class="p-val-input" value="<?php echo $p['pricing_pct']; ?>" onchange="updatePlan('pricing_pct', this.value)" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
        </div>
        <div style="margin-top: 1rem; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
            <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                <input type="checkbox" checked style="width: 16px; height: 16px; accent-color: var(--primary);" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
                <span style="font-size: 0.85rem; font-weight: 600; color: #475569;">Auto-Sync Dates</span>
            </label>
        </div>
    </div>

    <!-- Panel B: Production Cost -->
    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-hammer"></i> Production</h4>
        <div class="p-row">
            <label style="font-weight: 600;">Printing (₹)</label>
            <input type="number" class="p-val-input" value="<?php echo $p['printing_cost']; ?>" onchange="updatePlan('printing_cost', this.value)" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
        </div>
        <div class="p-row">
            <label style="font-weight: 600;">Mounting (₹)</label>
            <input type="number" class="p-val-input" value="<?php echo $p['mounting_cost']; ?>" onchange="updatePlan('mounting_cost', this.value)" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
        </div>
    </div>

    <!-- Panel C: Statistics (Calculated) -->
    <div class="p-panel" style="background: #f8fafc; border-color: #e2e8f0;">
        <h4 class="p-title"><i class="fas fa-chart-pie"></i> Statistics</h4>
        <div class="stat-box">
            <small>HA Margin (Own)</small>
            <strong><?php echo formatCurrency($haMarkup); ?> <span class="badge-tag"><?php echo number_format($haMarkupPct, 1); ?>%</span></strong>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="stat-box">
                <small>Total Area</small>
                <strong><?php echo number_format($totalSQFT); ?> <span style="font-size:0.7rem; color:#64748b;">sqft</span></strong>
            </div>
            <div class="stat-box">
                <small>Rate / SQFT</small>
                <strong><?php echo formatCurrency($totalSQFT > 0 ? $displayCost / $totalSQFT : 0); ?></strong>
            </div>
        </div>
    </div>

    <!-- Panel D: Summary (Auto) -->
    <div class="p-panel" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border: none; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.4);">
        <h4 class="p-title" style="color: #94a3b8; border-bottom-color: rgba(255,255,255,0.1);"><i class="fas fa-receipt"></i> Financial Summary</h4>
        <div class="p-row" style="color: #cbd5e1;"><span>Display Cost</span><strong style="color: white;"><?php echo formatCurrency($displayCost); ?></strong></div>
        <div class="p-row" style="color: #cbd5e1;"><span>Printing</span><strong style="color: white;"><?php echo formatCurrency($p['printing_cost']); ?></strong></div>
        <div class="p-row" style="color: #cbd5e1;"><span>Installation</span><strong style="color: white;"><?php echo formatCurrency($p['mounting_cost']); ?></strong></div>
        
        <div class="p-row" style="border-top: 1px dashed rgba(255,255,255,0.2); padding-top: 0.75rem; margin-top: 0.75rem; color: #94a3b8;">
            <span>Sub Total</span><strong style="color: white;"><?php echo formatCurrency($displayCost + $p['printing_cost'] + $p['mounting_cost']); ?></strong>
        </div>
        <div class="p-row" style="color: #94a3b8;"><span>GST (18%)</span><strong style="color: white;"><?php echo formatCurrency($p['tax_amount']); ?></strong></div>
        
        <div style="background: rgba(255,255,255,0.1); border-radius: 12px; padding: 1rem; margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.8rem; font-weight: 800; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.05em;">Grand Total</span>
            <span style="font-size: 1.5rem; font-weight: 900; color: #34d399;"><?php echo formatCurrency($grandTotal); ?></span>
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
                <th style="width: 30px;"><input type="checkbox" id="selectAll" checked style="cursor: pointer;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></th>
                <th>Asset Details</th>
                <th>City / Code</th>
                <th>Dimensions</th>
                <th>Availability</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days</th>
                <th>Monthly Rate</th>
                <th style="text-align: right;">Total Amount</th>
                <th style="width: 50px; text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $sr=1; foreach ($items as $item): ?>
            <tr>
                <td><input type="checkbox" class="item-checkbox" value="<?php echo $item['id']; ?>" checked style="cursor: pointer;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></td>
                <td>
                    <div style="font-weight: 800; color: #1e293b; margin-bottom: 2px;"><?php echo $item['site_name']; ?></div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 6px;"><?php echo $item['location']; ?></div>
                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                        <span style="background: #ecfdf5; color: #059669; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;"><?php echo $item['media_type']; ?></span>
                        <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;"><?php echo $item['light_type']; ?></span>
                        <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;">
                            <?php echo $item['owner_type']; ?>
                            <?php if ($item['owner_type'] === 'TA' && $item['vendor_name']) echo " - " . htmlspecialchars($item['vendor_name']); ?>
                        </span>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 800; color: #1e293b;"><?php echo $item['city']; ?>, <?php echo $item['site_state']; ?></div>
                    <div style="font-size: 0.75rem; color: #f97316; font-weight: 800;"><?php echo $item['site_code']; ?></div>
                </td>
                <td>
                    <div style="font-weight: 700; color: #475569;"><?php echo $item['width']; ?>' x <?php echo $item['height']; ?>'</div>
                    <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo number_format($item['width']*$item['height']); ?> SQFT</div>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="status-dot online"></span>
                        <span style="font-weight: 700; color: #10b981;"><?php echo $item['available_from'] ? date('d M', strtotime($item['available_from'])) : 'Live'; ?></span>
                    </div>
                </td>
                <td><input type="date" value="<?php echo htmlspecialchars($item['start_date'] ?? ''); ?>" onchange="updateItem(<?php echo $item['id']; ?>, 'start_date', this.value)" class="t-date" style="height: 32px; width: 130px;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></td>
                <td><input type="date" value="<?php echo htmlspecialchars($item['end_date'] ?? ''); ?>" onchange="updateItem(<?php echo $item['id']; ?>, 'end_date', this.value)" class="t-date" style="height: 32px; width: 130px;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></td>
                <td><input type="number" min="1" value="<?php echo intval($item['days'] ?? 30); ?>" onchange="updateItem(<?php echo $item['id']; ?>, 'days', this.value)" class="t-input" style="height: 32px; width: 70px; text-align: center;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>></td>
                <td>
                    <div style="font-size: 0.7rem; color: #94a3b8; margin-bottom: 2px;">Card: <?php echo formatCurrency($item['site_card_rate']); ?></div>
                    <input type="number" class="t-input" value="<?php echo (float)$item['sale_rate']; ?>" onchange="updateItem(<?php echo $item['id']; ?>, 'sale_rate', this.value)" style="height: 32px; width: 100px;" <?php echo !canEdit('proposals') ? 'disabled' : ''; ?>>
                </td>
                <td style="font-weight: 800; color: var(--primary); text-align: right; font-size: 1rem;"><?php echo formatCurrency($item['amount']); ?></td>
                <td style="text-align: right;">
                    <?php if (canEdit('proposals')): ?>
                    <button class="btn-icon" onclick="deleteItem(<?php echo $item['id']; ?>)" style="color: #ef4444; cursor: pointer; border: none; background: transparent; font-size: 1rem;"><i class="fas fa-trash-alt"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
/* Premium Panel Styling */
.p-panel { 
    background: white; 
    padding: 1.5rem; 
    border-radius: 16px; 
    border: 1px solid #f1f5f9; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    transition: all 0.3s ease;
}
.p-panel:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08); }
.p-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--secondary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; }
.p-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; font-size: 0.9rem; color: #334155; }
.p-val-input { width: 80px; padding: 0.4rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px; text-align: right; font-weight: 700; font-family: inherit; transition: all 0.2s; background: #fcfcfc; }
.p-val-input:focus { border-color: var(--primary); outline: none; background: white; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }

/* Stats Boxes */
.stat-box { margin-bottom: 1rem; }
.stat-box small { display: block; font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 0.2rem; }
.stat-box strong { font-size: 1.1rem; color: #0f172a; font-weight: 800; }
.badge-tag { background: #dcfce7; color: #166534; font-size: 0.7rem; padding: 0.2rem 0.4rem; border-radius: 6px; margin-left: 0.4rem; vertical-align: middle; }

/* Table Styling */
.table { width: 100%; border-collapse: separate; border-spacing: 0; }
.table th { background: #f8fafc; padding: 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 800; border-bottom: 2px solid #f1f5f9; text-align: left; }
.table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; color: #334155; }
.table tr:hover td { background: #fcfcfc; }
.badge-type { background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 800; font-size: 0.7rem; color: #475569; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
.status-dot.online { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }
.t-input, .t-date { border: 1px solid #e2e8f0; padding: 0.4rem 0.6rem; border-radius: 8px; width: 110px; font-family: inherit; font-size: 0.8rem; font-weight: 600; color: var(--primary); transition: all 0.2s; }
.t-input:focus, .t-date:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }

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
</style>

<!-- Add Site Modal -->
<div id="addSiteModal" class="modal-overlay">
    <div class="card" style="width: 98vw; height: 98vh; max-width: none; max-height: none; padding: 1.5rem 2rem; border-radius: 16px; display: flex; flex-direction: column; margin: 0; box-sizing: border-box;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h3 style="margin: 0; font-weight: 800;"><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add Sites to Proposal</h3>
                <div id="modal-bucket-btn" onclick="toggleModalBucket()" style="background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: all 0.2s;">
                    <i class="fas fa-shopping-basket"></i>
                    Selected: <span id="modal-selected-count" style="background: #059669; color: white; padding: 0.1rem 0.5rem; border-radius: 4px; font-size: 0.65rem;">0</span>
                </div>
            </div>
            <button onclick="closeAddSiteModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>
        
        <div style="display: flex; gap: 2rem; margin-bottom: 1rem;">
            <div class="search-group">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Ownership</label>
                <div style="display: flex; gap: 1rem;">
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_ownership" value="all" checked onchange="modalFetchSites(1)"> All</label>
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_ownership" value="HA" onchange="modalFetchSites(1)"> Self</label>
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_ownership" value="TA" onchange="modalFetchSites(1)"> Vendor</label>
                </div>
            </div>
            <div class="search-group">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Availability</label>
                <div style="display: flex; gap: 1rem;">
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_availability" value="available" checked onchange="modalFetchSites(1)"> Available</label>
                    <label style="font-size: 0.75rem;"><input type="radio" name="modal_availability" value="all" onchange="modalFetchSites(1)"> All</label>
                </div>
            </div>
        </div>
        
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; align-items: flex-end; width: 100%;">
            <div class="search-group" style="flex: 2 1 200px; min-width: 150px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Search Site / Code / Area</label>
                <input type="text" id="modal-search" class="p-input" placeholder="Search..." oninput="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 10px; box-sizing: border-box;">
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Media</label>
                <select id="modal-media" class="p-input" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($mediaTypes as $mt): ?> <option value="<?php echo $mt; ?>"><?php echo $mt; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">State</label>
                <select id="modal-state" class="p-input" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($states as $s): ?> <option value="<?php echo $s; ?>"><?php echo $s; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">City</label>
                <select id="modal-city" class="p-input" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($cities as $c): ?> <option value="<?php echo $c; ?>"><?php echo $c; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Location</label>
                <select id="modal-location" class="p-input" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($locations as $loc): ?> <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Light</label>
                <select id="modal-light" class="p-input" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($illuminations as $il): ?> <option value="<?php echo $il; ?>"><?php echo $il; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; margin-bottom: 0.2rem; text-transform: uppercase;">Vendor</label>
                <select id="modal-vendor" class="p-input" onchange="modalFetchSites(1)" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                    <option value="">All</option>
                    <?php foreach($vendors as $v): ?> <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group" style="flex: 0 0 auto; margin-bottom: 0; display: flex; align-items: flex-end;">
                <button class="btn btn-secondary" onclick="clearModalFilters()" style="height: 30px; font-size: 0.75rem; padding: 0 0.75rem; border-radius: 8px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; box-sizing: border-box;"><i class="fas fa-times-circle"></i> Clear</button>
            </div>
        </div>

        <div style="flex: 1; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
            <table class="crs-table selection-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="modal-select-all" onclick="toggleAllModalSites(this)"></th>
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
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
            <div id="modal-pg-info" style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Loading...</div>
            <div id="modal-pg-numbers" style="display: flex; gap: 0.25rem;"></div>
            <button class="btn btn-primary" onclick="addSelectedSitesToProposal()">Add Selected Sites</button>
        </div>
    </div>
</div>

<!-- Simple Lightbox HTML -->
<div id="simple-lightbox" onclick="closeLightbox()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
    <div style="position: relative; text-align: center; max-width: 90%; max-height: 90vh;">
        <div style="display: flex; align-items: center; justify-content: center; position: relative;">
            <button id="lightbox-prev" onclick="changeLightboxImage(-1); event.stopPropagation();" style="position: absolute; left: -60px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 50%; width: 44px; height: 44px; font-size: 1.2rem; cursor: pointer; display: none; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <img id="lightbox-img" src="" style="max-width: 100%; max-height: 85vh; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.15);">
            
            <button id="lightbox-next" onclick="changeLightboxImage(1); event.stopPropagation();" style="position: absolute; right: -60px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 50%; width: 44px; height: 44px; font-size: 1.2rem; cursor: pointer; display: none; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <div id="lightbox-badge" style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 6px 16px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2);"></div>
        </div>
        
        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 1rem;">
            <button id="lightbox-select-btn" onclick="selectLightboxImage(); event.stopPropagation();" style="background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 800; font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 15px rgba(13,148,136,0.4); display: none;">
                <i class="fas fa-check-circle"></i> Use This Image
            </button>
        </div>
        
        <div onclick="closeLightbox()" style="position: absolute; top: -60px; right: -60px; color: white; font-size: 2.5rem; cursor: pointer; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</div>
    </div>
</div>

<script>
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

function saveAllChanges() {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'All changes saved successfully!',
        showConfirmButton: false,
        timer: 1500
    });
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

document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('selectAll');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    
    if(selectAll) {
        selectAll.addEventListener('change', (e) => {
            itemCheckboxes.forEach(cb => cb.checked = e.target.checked);
        });
    }
    
    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            if(document.querySelectorAll('.item-checkbox:checked').length === itemCheckboxes.length) {
                selectAll.checked = true;
            } else {
                selectAll.checked = false;
            }
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
    
    if (showingBucketOnly) toggleModalBucket(); // Exit bucket view when clearing filters
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

    const url = `../../ajax/fetch_sites.php?page=${page}&limit=${modalPageSize}&q=${encodeURIComponent(q)}&media=${encodeURIComponent(media)}&state=${encodeURIComponent(state)}&city=${encodeURIComponent(city)}&location=${encodeURIComponent(loc)}&light=${encodeURIComponent(light)}&vendor=${encodeURIComponent(vendor)}&ownership=${encodeURIComponent(ownership)}&availability=${encodeURIComponent(availability)}`;

    fetch(url)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            currentModalSites = res.sites;
            if (!showingBucketOnly) {
                renderModalSites(currentModalSites);
                renderModalPagination(res.total);
            } else {
                // If in bucket mode, just update pagination in background
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

    if (cb.checked) {
        if (!modalSelectedSites.find(s => s.id == siteId)) {
            const sCopy = {...siteObj, image: siteObj.thumbnail || ''};
            modalSelectedSites.push(sCopy);
        }
    } else {
        modalSelectedSites = modalSelectedSites.filter(s => s.id != siteId);
        if (showingBucketOnly) {
            // Remove row visually immediately when unchecked in bucket view
            const row = cb.closest('tr');
            if (row) row.remove();
        }
    }
    updateModalSelectedCount();
    
    // Update select all checkbox state
    const checkboxes = document.querySelectorAll('.modal-site-checkbox');
    const checkedCount = document.querySelectorAll('.modal-site-checkbox:checked').length;
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
        const isChecked = modalSelectedSites.some(ms => ms.id == s.id);
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

        const row = document.createElement('tr');
        row.style.background = 'white';
        row.innerHTML = `
            <td style="text-align:center; padding:1rem;">
                <input type="checkbox" class="modal-site-checkbox" value="${s.id}" ${isChecked ? 'checked' : ''} onclick="toggleModalSite(this, ${s.id})" style="width:16px; height:16px; accent-color:var(--primary);">
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
                    <span style="background:#ecfdf5; color:#059669; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.type}</span>
                    <span style="background:#f1f5f9; color:#475569; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.owner_type}</span>
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
    
    // Update select all state
    const checkboxes = document.querySelectorAll('.modal-site-checkbox');
    const checkedCount = document.querySelectorAll('.modal-site-checkbox:checked').length;
    document.getElementById('modal-select-all').checked = (checkboxes.length > 0 && checkboxes.length === checkedCount);
}

function toggleAllModalSites(source) {
    const checkboxes = document.querySelectorAll('.modal-site-checkbox');
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
                    const sCopy = {...siteObj, image: siteObj.thumbnail || ''};
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

    fetch('../../ajax/add_proposal_items_bulk.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ proposal_id: propId, sites: modalSelectedSites })
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
    
    if(selectBtn && currentLightboxSiteId) {
        const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
        if(savedSite && savedSite.image === lbImages[lbIndex]) {
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
    if(currentLightboxSiteId) {
        const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
        if(savedSite) {
            savedSite.image = lbImages[lbIndex];
            
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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
