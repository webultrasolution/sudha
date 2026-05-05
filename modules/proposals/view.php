<?php
$activePage = 'proposals';
$pageTitle = 'Plan Detail Workspace';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

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
        <h1 style="font-size: 1.5rem; margin: 0;"><?php echo $p['proposal_number']; ?> - <?php echo $p['client_name']; ?></h1>
        <p style="color: var(--secondary); margin-top: 0.25rem;">Workspace: <?php echo date('d M', strtotime($p['start_date'])); ?> to <?php echo date('d M Y', strtotime($p['end_date'])); ?></p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <div class="dropdown">
            <button class="btn btn-primary"><i class="fas fa-download"></i> Export Actions <i class="fas fa-caret-down"></i></button>
            <div class="dropdown-content">
                <a href="#"><i class="fas fa-file-pdf"></i> PDF Proposal</a>
                <a href="#"><i class="fas fa-file-excel"></i> Excel Rate Sheet</a>
                <a href="#"><i class="fas fa-file-powerpoint"></i> PPT Presentation</a>
                <a href="#"><i class="fas fa-link"></i> Public Link</a>
            </div>
        </div>
        <?php if ($p['status'] != 'confirmed'): ?>
            <button class="btn btn-success" onclick="confirmProposal(<?php echo $id; ?>)"><i class="fas fa-check-double"></i> Convert to Campaign</button>
        <?php endif; ?>
    </div>
</div>

<!-- 4-Panel Dynamic Layout -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
    <!-- Panel A: Inventories Control -->
    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-tools"></i> Panel A: Controls</h4>
        <div class="p-row">
            <label>Discount %</label>
            <input type="number" step="0.01" class="p-val-input" value="<?php echo $p['discounting_pct']; ?>" onchange="updatePlan('discounting_pct', this.value)">
        </div>
        <div class="p-row">
            <label>Pricing %</label>
            <input type="number" step="0.01" class="p-val-input" value="<?php echo $p['pricing_pct']; ?>" onchange="updatePlan('pricing_pct', this.value)">
        </div>
        <div style="margin-top: 0.75rem; border-top: 1px solid #f1f5f9; padding-top: 0.5rem;">
            <label class="toggle-switch">
                <input type="checkbox" checked>
                <span class="slider"></span>
                <span style="font-size: 0.7rem; margin-left: 2rem;">Auto Adjust Dates</span>
            </label>
        </div>
    </div>

    <!-- Panel B: Production Cost -->
    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-print"></i> Panel B: Production</h4>
        <div class="p-row">
            <label>Printing (₹)</label>
            <input type="number" class="p-val-input" value="<?php echo $p['printing_cost']; ?>" onchange="updatePlan('printing_cost', this.value)">
        </div>
        <div class="p-row">
            <label>Mounting (₹)</label>
            <input type="number" class="p-val-input" value="<?php echo $p['mounting_cost']; ?>" onchange="updatePlan('mounting_cost', this.value)">
        </div>
    </div>

    <!-- Panel C: Statistics (Calculated) -->
    <div class="p-panel" style="background: #f8fafc;">
        <h4 class="p-title"><i class="fas fa-chart-bar"></i> Panel C: Stats</h4>
        <div class="stat-box">
            <small>HA Markup (Own)</small>
            <strong><?php echo formatCurrency($haMarkup); ?> <span class="badge-tag"><?php echo number_format($haMarkupPct, 1); ?>%</span></strong>
        </div>
        <div class="stat-box">
            <small>Total SQFT</small>
            <strong><?php echo number_format($totalSQFT); ?> sqft</strong>
        </div>
        <div class="stat-box">
            <small>Avg Rate / SQFT</small>
            <strong><?php echo formatCurrency($totalSQFT > 0 ? $displayCost / $totalSQFT : 0); ?></strong>
        </div>
    </div>

    <!-- Panel D: Summary (Auto) -->
    <div class="p-panel" style="background: #1e293b; color: white;">
        <h4 class="p-title" style="color: #94a3b8;"><i class="fas fa-receipt"></i> Panel D: Summary</h4>
        <div class="p-row"><span>Display Cost</span><strong><?php echo formatCurrency($displayCost); ?></strong></div>
        <div class="p-row"><span>Printing</span><strong><?php echo formatCurrency($p['printing_cost']); ?></strong></div>
        <div class="p-row"><span>Installation</span><strong><?php echo formatCurrency($p['mounting_cost']); ?></strong></div>
        <div class="p-row" style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.4rem; margin-top: 0.4rem;">
            <span>Sub Total</span><strong><?php echo formatCurrency($displayCost + $p['printing_cost'] + $p['mounting_cost']); ?></strong>
        </div>
        <div class="p-row"><span>GST (18%)</span><strong><?php echo formatCurrency($p['tax_amount']); ?></strong></div>
        <div class="p-row" style="font-size: 1.1rem; color: #4ade80; margin-top: 0.5rem;">
            <span>Grand Total</span><strong><?php echo formatCurrency($grandTotal); ?></strong>
        </div>
    </div>
</div>

<!-- Detailed Site Table -->
<div class="card" style="padding: 0; overflow-x: auto;">
    <div style="padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9;">
        <h3 style="font-size: 1rem;">Site Selection & Pricing Matrix</h3>
        <div style="display: flex; gap: 0.5rem;">
            <input type="text" placeholder="Search in plan..." class="p-input" style="width: 200px;">
            <button class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Plan</button>
        </div>
    </div>
    <table class="table" style="font-size: 0.75rem; white-space: nowrap;">
        <thead style="background: #f8fafc;">
            <tr>
                <th>Sr</th>
                <th>Type</th>
                <th>Media</th>
                <th>ID</th>
                <th>City</th>
                <th>Location</th>
                <th>Size</th>
                <th>SQFT</th>
                <th>Light</th>
                <th>Status</th>
                <th>Available</th>
                <th>Start Date</th>
                <th>Card Rate</th>
                <th>Monthly Cost</th>
                <th>Total Cost</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $sr=1; foreach ($items as $item): ?>
            <tr>
                <td><?php echo $sr++; ?></td>
                <td><span class="badge-type"><?php echo $item['owner_type']; ?></span></td>
                <td><?php echo $item['media_type']; ?></td>
                <td><code><?php echo $item['site_code']; ?></code></td>
                <td><?php echo $item['city']; ?></td>
                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;"><?php echo $item['location']; ?></td>
                <td><?php echo $item['width']; ?>'x<?php echo $item['height']; ?>'</td>
                <td><?php echo $item['width']*$item['height']; ?></td>
                <td><?php echo $item['light_type']; ?></td>
                <td><span class="status-dot online"></span> Active</td>
                <td><?php echo $item['available_from'] ? date('d M', strtotime($item['available_from'])) : 'N/A'; ?></td>
                <td><input type="date" value="<?php echo htmlspecialchars($item['start_date'] ?? ''); ?>" class="t-date"></td>
                <td><?php echo formatCurrency($item['site_card_rate']); ?></td>
                <td>
                    <input type="number" class="t-input" value="<?php echo $item['sale_rate']; ?>" onchange="updateItem(<?php echo $item['id']; ?>, 'sale_rate', this.value)">
                </td>
                <td style="font-weight: 700; color: var(--primary);"><?php echo formatCurrency($item['amount']); ?></td>
                <td>
                    <button class="btn-icon" style="color: var(--danger);"><i class="fas fa-times"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.p-panel { background: white; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; }
.p-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--secondary); margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.4rem; }
.p-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; font-size: 0.85rem; }
.p-val-input { width: 70px; padding: 0.2rem 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; text-align: right; font-weight: 700; }
.stat-box { margin-bottom: 0.6rem; }
.stat-box small { display: block; font-size: 0.65rem; color: #64748b; }
.stat-box strong { font-size: 0.9rem; }
.badge-tag { background: #dcfce7; color: #166534; font-size: 0.65rem; padding: 0.1rem 0.3rem; border-radius: 4px; margin-left: 0.3rem; }
.badge-type { background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 4px; font-weight: 700; font-size: 0.65rem; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
.status-dot.online { background: #10b981; }
.t-input, .t-date { border: 1px solid #e2e8f0; padding: 0.2rem 0.4rem; border-radius: 4px; width: 90px; font-family: inherit; font-size: 0.75rem; }
.dropdown { position: relative; display: inline-block; }
.dropdown-content { display: none; position: absolute; right: 0; background-color: white; min-width: 180px; box-shadow: 0 8px 16px rgba(0,0,0,0.1); border-radius: 8px; z-index: 1; border: 1px solid #f1f5f9; overflow: hidden; }
.dropdown-content a { color: #475569; padding: 0.75rem 1rem; text-decoration: none; display: block; font-size: 0.85rem; transition: background 0.2s; }
.dropdown-content a:hover { background: #f8fafc; color: var(--primary); }
.dropdown:hover .dropdown-content { display: block; }
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
    Swal.fire({
        title: 'Confirm & Convert?',
        text: "This will finalize the plan and generate active campaign bookings.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Yes, Convert to Campaign'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../ajax/confirm_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proposal_id: id })
            }).then(r => r.json()).then(res => {
                if(res.success) {
                    Swal.fire('Confirmed!', 'Campaign is now live.', 'success').then(() => location.href='../operations/campaigns.php');
                }
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
