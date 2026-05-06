<?php
$activePage = 'proposals';
$pageTitle = 'Plan Detail Workspace';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Load Core Libraries (SweetAlert, FontAwesome, CSS) without the sidebar
$hideSidebar = true;
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
    SELECT pi.*, s.site_code, s.location, s.city, s.type as media_type, s.owner_type, 
           s.width, s.height, s.light_type, s.card_rate as site_card_rate, s.available_from, s.purchase_rate as master_purchase
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

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
            <span style="background: #e0e7ff; color: #4338ca; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                <?php echo strtoupper($p['status']); ?>
            </span>
        </div>
        <p style="color: #64748b; margin: 0; font-size: 0.95rem; font-weight: 500;">
            <strong style="color: #334155;"><?php echo $p['client_name']; ?></strong> • Workspace: <?php echo date('d M', strtotime($p['start_date'])); ?> to <?php echo date('d M Y', strtotime($p['end_date'])); ?>
        </p>
    </div>
    <div style="display: flex; gap: 1rem; align-items: center;">
        <div class="dropdown">
            <button class="btn" style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='#cbd5e1'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.02)';">
                <i class="fas fa-download"></i> Export <i class="fas fa-caret-down" style="margin-left: 0.25rem; opacity: 0.7;"></i>
            </button>
            <div class="dropdown-content">
                <a href="export_pdf.php?id=<?php echo $id; ?>" target="_blank"><i class="fas fa-file-pdf" style="color: #ef4444; width: 20px;"></i> PDF Proposal</a>
                <a href="export_excel.php?id=<?php echo $id; ?>"><i class="fas fa-file-excel" style="color: #10b981; width: 20px;"></i> Excel Rate Sheet</a>
                <a href="export_ppt.php?id=<?php echo $id; ?>"><i class="fas fa-file-powerpoint" style="color: #f97316; width: 20px;"></i> PPT Presentation</a>
                <div style="border-top: 1px solid #f1f5f9; margin: 0.5rem 0;"></div>
                <a href="#"><i class="fas fa-link" style="color: #3b82f6; width: 20px;"></i> Public Link</a>
            </div>
        </div>
        <?php if ($p['status'] != 'confirmed'): ?>
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
            <input type="number" step="0.01" class="p-val-input" value="<?php echo $p['discounting_pct']; ?>" onchange="updatePlan('discounting_pct', this.value)">
        </div>
        <div class="p-row">
            <label style="font-weight: 600;">Markup %</label>
            <input type="number" step="0.01" class="p-val-input" value="<?php echo $p['pricing_pct']; ?>" onchange="updatePlan('pricing_pct', this.value)">
        </div>
        <div style="margin-top: 1rem; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
            <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                <input type="checkbox" checked style="width: 16px; height: 16px; accent-color: var(--primary);">
                <span style="font-size: 0.85rem; font-weight: 600; color: #475569;">Auto-Sync Dates</span>
            </label>
        </div>
    </div>

    <!-- Panel B: Production Cost -->
    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-hammer"></i> Production</h4>
        <div class="p-row">
            <label style="font-weight: 600;">Printing (₹)</label>
            <input type="number" class="p-val-input" value="<?php echo $p['printing_cost']; ?>" onchange="updatePlan('printing_cost', this.value)">
        </div>
        <div class="p-row">
            <label style="font-weight: 600;">Mounting (₹)</label>
            <input type="number" class="p-val-input" value="<?php echo $p['mounting_cost']; ?>" onchange="updatePlan('mounting_cost', this.value)">
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
                <input type="text" placeholder="Search in plan..." class="p-input" style="width: 250px; padding-left: 2.5rem; height: 38px; border-radius: 8px;">
            </div>
            <button class="btn btn-primary" style="height: 38px; border-radius: 8px;"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>
    <table class="table" style="font-size: 0.75rem; white-space: nowrap;">
        <thead style="background: #f8fafc;">
            <tr>
                <th style="width: 30px;"><input type="checkbox" id="selectAll" checked style="cursor: pointer;"></th>
                <th>Asset Details</th>
                <th>City / Code</th>
                <th>Dimensions</th>
                <th>Availability</th>
                <th>Start Date</th>
                <th>Monthly Rate</th>
                <th style="text-align: right;">Total Amount</th>
                <th style="width: 50px; text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $sr=1; foreach ($items as $item): ?>
            <tr>
                <td><input type="checkbox" class="item-checkbox" value="<?php echo $item['id']; ?>" checked style="cursor: pointer;"></td>
                <td>
                    <div style="font-weight: 700; color: #334155; margin-bottom: 2px; white-space: normal; line-height: 1.4;"><?php echo $item['location']; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">
                        <?php echo $item['media_type']; ?> • <?php echo $item['light_type']; ?> • <span class="badge-type"><?php echo $item['owner_type']; ?></span>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 700; color: #1e293b;"><?php echo $item['city']; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo $item['site_code']; ?></div>
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
                <td><input type="date" value="<?php echo htmlspecialchars($item['start_date'] ?? ''); ?>" class="t-date" style="height: 32px;"></td>
                <td>
                    <div style="font-size: 0.7rem; color: #94a3b8; margin-bottom: 2px;">Card: <?php echo formatCurrency($item['site_card_rate']); ?></div>
                    <input type="number" class="t-input" value="<?php echo $item['sale_rate']; ?>" onchange="updateItem(<?php echo $item['id']; ?>, 'sale_rate', this.value)" style="height: 32px; width: 100px;">
                </td>
                <td style="font-weight: 800; color: var(--primary); text-align: right; font-size: 1rem;"><?php echo formatCurrency($item['amount']); ?></td>
                <td style="text-align: right;">
                    <button class="btn-icon" style="color: #ef4444; cursor: pointer; border: none; background: transparent; font-size: 1rem;"><i class="fas fa-trash-alt"></i></button>
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
.dropdown { position: relative; display: inline-block; }
.dropdown-content { display: none; position: absolute; right: 0; background-color: white; min-width: 220px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); border-radius: 12px; z-index: 50; border: 1px solid #f1f5f9; overflow: hidden; top: calc(100% + 0.5rem); padding: 0.5rem; }
.dropdown-content a { color: #334155; padding: 0.75rem 1rem; text-decoration: none; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; font-weight: 600; border-radius: 8px; transition: all 0.2s; }
.dropdown-content a:hover { background: #f8fafc; color: var(--primary); transform: translateX(2px); }
.dropdown:hover .dropdown-content { display: block; animation: slideDown 0.2s ease-out forwards; }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

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
        body: `id=${itemId}&field=${field}&value=${val}`
    }).then(() => location.reload());
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
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
