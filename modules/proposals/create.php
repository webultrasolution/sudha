<?php
$activePage = 'proposals';
$pageTitle = 'Create New Proposal';
include_once __DIR__ . '/../../includes/header.php';

if (!hasRole(['admin', 'sales'])) {
    echo "<div class='card'>Access Denied. You do not have permission to create proposals.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch Data
$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
$sites = $pdo->query("SELECT id, site_code, name, type, card_rate, purchase_rate, owner_type, sqft FROM sites WHERE status = 'available' ORDER BY site_code ASC")->fetchAll();
?>

<div class="crs-header-panels">
    <!-- Panel A: Pricing Controls -->
    <div class="crs-panel">
        <div class="panel-label">A. PRICING CONTROLS</div>
        <div class="form-grid">
            <div class="form-group">
                <label>Discounting (%)</label>
                <input type="number" id="global_discount" value="0" class="p-input" oninput="recalcAll()">
            </div>
            <div class="form-group">
                <label>Markup (%)</label>
                <input type="number" id="global_markup" value="0" class="p-input" oninput="recalcAll()">
            </div>
        </div>
    </div>

    <!-- Panel B: Production Costs -->
    <div class="crs-panel">
        <div class="panel-label">B. PRODUCTION COSTS</div>
        <div class="form-grid">
            <div class="form-group">
                <label>Printing Cost (₹)</label>
                <input type="number" id="print_cost" value="0" class="p-input" oninput="recalcAll()">
            </div>
            <div class="form-group">
                <label>Mounting Cost (₹)</label>
                <input type="number" id="mount_cost" value="0" class="p-input" oninput="recalcAll()">
            </div>
        </div>
    </div>

    <!-- Panel C: Statistics -->
    <div class="crs-panel stats">
        <div class="panel-label">C. STATISTICS</div>
        <div class="stat-row">
            <span>Total SQFT:</span>
            <span id="stat-sqft">0</span>
        </div>
        <div class="stat-row">
            <span>HA Markup:</span>
            <span id="stat-ha-markup">₹0</span>
        </div>
        <div class="stat-row">
            <span>TA Markup:</span>
            <span id="stat-ta-markup">₹0</span>
        </div>
        <div class="stat-row">
            <span>Price / SQFT:</span>
            <span id="stat-price-sqft">₹0</span>
        </div>
    </div>

    <!-- Panel D: Summary -->
    <div class="crs-panel summary">
        <div class="panel-label">D. SUMMARY</div>
        <div class="stat-row">
            <span>Display Cost:</span>
            <span id="sum-display">₹0</span>
        </div>
        <div class="stat-row">
            <span>Tax (GST 18%):</span>
            <span id="sum-tax">₹0</span>
        </div>
        <div class="stat-row grand-total">
            <span>GRAND TOTAL:</span>
            <span id="sum-grand">₹0</span>
        </div>
    </div>
</div>

<div class="proposal-layout">
    <div class="side-col">
        <div class="p-panel">
            <div class="p-header">1. Client & Duration</div>
            <div class="form-group">
                <label>Select Client</label>
                <select id="client_id" class="p-input" onchange="validateSteps()">
                    <option value="">-- Choose Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" id="start_date" class="p-input" onchange="validateSteps()">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" id="end_date" class="p-input" onchange="validateSteps()">
                </div>
            </div>
        </div>

        <div class="p-panel step-disabled" id="step-2-panel" style="margin-top: 1.5rem;">
            <div class="p-header">2. Select Assets</div>
            <div class="step-overlay">Please complete Step 1 first</div>
            <input type="text" placeholder="Filter sites..." id="site-search" class="p-input" onkeyup="filterSites()">
            <div class="site-list">
                <?php foreach ($sites as $s): ?>
                <div class="site-item" 
                     data-id="<?php echo $s['id']; ?>" 
                     data-name="<?php echo $s['name']; ?>" 
                     data-rate="<?php echo $s['card_rate']; ?>" 
                     data-prate="<?php echo $s['purchase_rate']; ?>" 
                     data-owner="<?php echo $s['owner_type']; ?>"
                     data-sqft="<?php echo $s['sqft']; ?>"
                     onclick="toggleSite(this)">
                    <div style="font-weight: 600;"><?php echo $s['site_code']; ?></div>
                    <div style="font-size: 0.7rem; color: var(--secondary);"><?php echo $s['name']; ?></div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-top: 0.25rem;">
                        <span>Rate: ₹<?php echo number_format($s['card_rate']); ?></span>
                        <span class="badge-<?php echo strtolower($s['owner_type']); ?>"><?php echo $s['owner_type']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="main-col step-disabled" id="step-3-panel">
        <div class="p-panel">
            <div class="p-header">3. Plan Detail / Site Breakdown</div>
            <table class="crs-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Owner</th>
                        <th>SQFT</th>
                        <th>Purchase Rate</th>
                        <th>Card Rate</th>
                        <th>Sale Rate</th>
                        <th>Markup</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="selected-sites-body">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
            
            <div style="margin-top: 2rem; text-align: right;">
                <button class="btn btn-primary" onclick="saveProposal()" style="padding: 0.75rem 2rem; font-size: 1rem;">
                    <i class="fas fa-file-invoice"></i> Save & Generate Proposal
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.crs-header-panels { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.crs-panel { background: white; border-radius: 10px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm); }
.panel-label { font-size: 0.7rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.75rem; letter-spacing: 0.05em; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.stat-row { display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.85rem; font-weight: 500; }
.crs-panel.stats { background: #f8fafc; }
.crs-panel.summary { background: #eff6ff; border-color: #bfdbfe; }
.grand-total { border-top: 1px solid #bfdbfe; padding-top: 0.5rem; margin-top: 0.5rem; color: var(--primary); font-weight: 800; font-size: 1rem; }

.proposal-layout { display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; }
.p-panel { background: white; border-radius: 10px; padding: 1.25rem; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm); }
.p-header { font-weight: 700; font-size: 1rem; color: var(--primary); margin-bottom: 1rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; }
.p-input { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 0.875rem; }

.site-list { max-height: 500px; overflow-y: auto; margin-top: 1rem; padding-right: 0.25rem; }
.site-item { padding: 0.75rem; border: 1px solid #f1f5f9; border-radius: 8px; cursor: pointer; transition: all 0.2s; margin-bottom: 0.625rem; }
.site-item:hover { border-color: var(--primary); background: #f8fafc; }
.site-item.selected { border-color: var(--primary); background: #eff6ff; box-shadow: 0 0 0 2px rgba(28, 173, 169, 0.1); }

.crs-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.crs-table th { text-align: left; padding: 0.75rem; background: #f8fafc; color: #64748b; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
.crs-table td { padding: 0.75rem; border-bottom: 1px solid #f1f5f9; }

.badge-ha { background: #dcfce7; color: #166534; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; }
.badge-ta { background: #fef9c3; color: #854d0e; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; }

/* Step Logic Styles */
.step-disabled { position: relative; opacity: 0.5; pointer-events: none; transition: all 0.3s; }
.step-overlay { 
    position: absolute; 
    inset: 0; 
    background: rgba(255,255,255,0.4); 
    z-index: 10; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 700; 
    color: var(--secondary); 
    font-size: 0.9rem; 
    border-radius: 10px;
    pointer-events: all;
}
.step-disabled .step-overlay { display: flex; }
:not(.step-disabled) > .step-overlay { display: none; }
</style>

<script>
let selectedSites = [];

function toggleSite(el) {
    if (el.closest('.step-disabled')) return;
    const id = el.dataset.id;
    const name = el.dataset.name;
    const rate = parseFloat(el.dataset.rate);
    const prate = parseFloat(el.dataset.prate);
    const owner = el.dataset.owner;
    const sqft = parseFloat(el.dataset.sqft);

    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx === -1) {
        selectedSites.push({ id, name, cardRate: rate, purchaseRate: prate, saleRate: rate, owner, sqft });
        el.classList.add('selected');
    } else {
        selectedSites.splice(idx, 1);
        el.classList.remove('selected');
    }
    validateSteps();
    recalcAll();
}

function validateSteps() {
    const clientId = document.getElementById('client_id').value;
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;

    const step2 = document.getElementById('step-2-panel');
    const step3 = document.getElementById('step-3-panel');

    if (clientId && start && end) {
        step2.classList.remove('step-disabled');
    } else {
        step2.classList.add('step-disabled');
        // We don't necessarily want to clear selected sites if they just changed a date, 
        // but for now let's keep it simple.
    }

    if (selectedSites.length > 0 && !step2.classList.contains('step-disabled')) {
        step3.classList.remove('step-disabled');
    } else {
        step3.classList.add('step-disabled');
    }
}

function recalcAll() {
    const disc = parseFloat(document.getElementById('global_discount').value) || 0;
    const mark = parseFloat(document.getElementById('global_markup').value) || 0;
    const print = parseFloat(document.getElementById('print_cost').value) || 0;
    const mount = parseFloat(document.getElementById('mount_cost').value) || 0;

    let totalSQFT = 0;
    let totalDisplay = 0;
    let haMarkup = 0;
    let taMarkup = 0;

    const tbody = document.getElementById('selected-sites-body');
    tbody.innerHTML = '';

    selectedSites.forEach((site, idx) => {
        // Apply global markup if cardRate is used, or let user edit
        // For now, let's just calculate based on current saleRate
        const currentTotal = site.saleRate;
        const markupVal = site.saleRate - site.purchaseRate;
        const markupPct = ((markupVal / site.purchaseRate) * 100).toFixed(1);

        totalSQFT += site.sqft;
        totalDisplay += currentTotal;
        
        if(site.owner === 'HA') haMarkup += markupVal;
        else taMarkup += markupVal;

        tbody.innerHTML += `
            <tr>
                <td><strong>${site.name}</strong></td>
                <td><span class="badge-${site.owner.toLowerCase()}">${site.owner}</span></td>
                <td>${site.sqft}</td>
                <td>₹${site.purchaseRate.toLocaleString()}</td>
                <td>₹${site.cardRate.toLocaleString()}</td>
                <td><input type="number" class="p-input" style="width: 100px" value="${site.saleRate}" oninput="updateSiteRate(${idx}, this.value)"></td>
                <td>${markupPct}%</td>
                <td style="font-weight: 600;">₹${currentTotal.toLocaleString()}</td>
            </tr>
        `;
    });

    const subtotal = totalDisplay + print + mount;
    const tax = subtotal * 0.18;
    const grand = subtotal + tax;

    // Update Stats Panel
    document.getElementById('stat-sqft').innerText = totalSQFT.toLocaleString();
    document.getElementById('stat-ha-markup').innerText = '₹' + haMarkup.toLocaleString();
    document.getElementById('stat-ta-markup').innerText = '₹' + taMarkup.toLocaleString();
    document.getElementById('stat-price-sqft').innerText = totalSQFT > 0 ? '₹' + (totalDisplay / totalSQFT).toFixed(2) : '₹0';

    // Update Summary Panel
    document.getElementById('sum-display').innerText = '₹' + totalDisplay.toLocaleString();
    document.getElementById('sum-tax').innerText = '₹' + tax.toLocaleString();
    document.getElementById('sum-grand').innerText = '₹' + grand.toLocaleString();
}

function updateSiteRate(idx, val) {
    selectedSites[idx].saleRate = parseFloat(val) || 0;
    recalcAll();
}

function filterSites() {
    const q = document.getElementById('site-search').value.toLowerCase();
    document.querySelectorAll('.site-item').forEach(el => {
        el.style.display = el.innerText.toLowerCase().includes(q) ? 'block' : 'none';
    });
}

function saveProposal() {
    const clientId = document.getElementById('client_id').value;
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    
    if (!clientId || !start || !end || selectedSites.length === 0) {
        alert('Missing required fields!');
        return;
    }

    const data = {
        clientId,
        startDate: start,
        endDate: end,
        printCost: parseFloat(document.getElementById('print_cost').value) || 0,
        mountCost: parseFloat(document.getElementById('mount_cost').value) || 0,
        selectedSites
    };

    fetch('../../ajax/save_proposal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('Success', 'Proposal generated successfully!', 'success')
            .then(() => window.location.href = '<?php echo BASE_URL; ?>modules/proposals/proposals.php');
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
