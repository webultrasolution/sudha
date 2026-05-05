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
$sites = $pdo->query("SELECT id, site_code, name, type, card_rate, purchase_rate, owner_type, sqft, city, location FROM sites WHERE status = 'available' ORDER BY site_code ASC")->fetchAll();
?>

<div class="proposal-full-wrapper">
    <!-- Top: Full Width Asset Selection -->
    <div class="p-panel" id="asset-plan-panel" style="margin-bottom: 2rem;">
        <div class="p-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span>Asset Selection & Plan Pricing</span>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <div class="selection-stats">Selected: <span id="selected-count">0</span> sites</div>
                <div class="asset-search-bar">
                    <input type="text" placeholder="Search site, location or city..." id="site-search" class="p-input" onkeyup="filterSites()" style="width: 300px; height: 42px;">
                </div>
            </div>
        </div>

        <div class="site-list-container" style="max-height: 500px;">
            <table class="crs-table selection-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Site Code</th>
                        <th>City / Location</th>
                        <th>Size</th>
                        <th>Type</th>
                        <th>Card Rate</th>
                        <th style="width: 140px;">Sale Rate (₹)</th>
                        <th style="width: 120px;">Markup</th>
                        <th style="width: 140px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $s): ?>
                    <tr class="site-row" 
                        id="row-<?php echo $s['id']; ?>"
                        data-id="<?php echo $s['id']; ?>" 
                        data-name="<?php echo $s['name']; ?>" 
                        data-rate="<?php echo $s['card_rate']; ?>" 
                        data-prate="<?php echo $s['purchase_rate']; ?>" 
                        data-owner="<?php echo $s['owner_type']; ?>"
                        data-sqft="<?php echo $s['sqft']; ?>">
                        <td><input type="checkbox" class="asset-chk" onclick="toggleSite('<?php echo $s['id']; ?>')"></td>
                        <td><strong><?php echo $s['site_code']; ?></strong></td>
                        <td>
                            <div style="font-weight: 600;"><?php echo $s['city']; ?></div>
                            <div style="font-size: 0.7rem; color: var(--secondary);"><?php echo $s['location']; ?></div>
                        </td>
                        <td><?php echo $s['sqft']; ?> SQFT</td>
                        <td><span class="badge-<?php echo strtolower($s['owner_type']); ?>"><?php echo $s['owner_type']; ?></span></td>
                        <td>₹<?php echo number_format($s['card_rate']); ?></td>
                        <td>
                            <input type="number" class="p-input sale-rate-input" 
                                   value="<?php echo $s['card_rate']; ?>" 
                                   oninput="updateSitePrice('<?php echo $s['id']; ?>', this.value)"
                                   disabled>
                        </td>
                        <td class="markup-cell">-</td>
                        <td class="total-cell" style="font-weight: 700;">₹0</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bottom: Configuration Grid -->
    <div class="proposal-bottom-grid">
        <!-- Client & Duration -->
        <div class="p-panel">
            <div class="p-header"> Client & Duration</div>
            <div class="form-group">
                <label style="display: flex; justify-content: space-between; align-items: center;">
                    Select Client
                    <button type="button" class="btn-text" onclick="openClientModal()" style="font-size: 0.75rem; color: var(--primary); background: none; border: none; cursor: pointer; padding: 0;">
                        <i class="fas fa-plus-circle"></i> New Client
                    </button>
                </label>
                <select id="client_id" class="p-input" style="height: 48px;">
                    <option value="">-- Choose Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" id="start_date" class="p-input" style="height: 48px;">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" id="end_date" class="p-input" style="height: 48px;">
                </div>
            </div>
        </div>

        <!-- Pricing Controls -->
        <div class="p-panel">
            <div class="p-header"> Pricing & Costs</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Discount (%)</label>
                    <input type="number" id="global_discount" value="0" class="p-input" oninput="recalcAll()" style="height: 48px;">
                </div>
                <div class="form-group">
                    <label>Markup (%)</label>
                    <input type="number" id="global_markup" value="0" class="p-input" oninput="recalcAll()" style="height: 48px;">
                </div>
            </div>
            <div class="form-grid" style="margin-top: 1rem;">
                <div class="form-group">
                    <label>Printing (₹)</label>
                    <input type="number" id="print_cost" value="0" class="p-input" oninput="recalcAll()" style="height: 48px;">
                </div>
                <div class="form-group">
                    <label>Mounting (₹)</label>
                    <input type="number" id="mount_cost" value="0" class="p-input" oninput="recalcAll()" style="height: 48px;">
                </div>
            </div>
        </div>

        <!-- Final Summary -->
        <div class="p-panel summary-box" style="background: #f8fafc; display: flex; flex-direction: column;">
            <div class="p-header"> Summary</div>
            <div style="flex: 1;">
                <div class="stat-row">
                    <span>Sites Selected:</span>
                    <span id="selected-count-btm" style="font-weight: 800;">0</span>
                </div>
                <div class="stat-row">
                    <span>Display Cost:</span>
                    <span id="sum-display-btm">₹0</span>
                </div>
                <div class="stat-row">
                    <span>Tax (18%):</span>
                    <span id="sum-tax-btm">₹0</span>
                </div>
                <div class="grand-total" style="border-top: 2px solid #e2e8f0; padding-top: 1rem; margin-top: 1rem; color: var(--primary); font-weight: 900;">
                    <div style="font-size: 0.7rem; color: var(--secondary); margin-bottom: 0.2rem;">GRAND TOTAL</div>
                    <div id="sum-grand-btm" style="font-size: 2rem;">₹0</div>
                </div>
            </div>
            <button class="btn btn-primary" onclick="saveProposal()" style="width: 100%; margin-top: 1rem; height: 50px; border-radius: 12px; font-weight: 800; font-size: 1rem;">
                GENERATE PROPOSAL
            </button>
        </div>
    </div>
</div>

<div id="clientModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>New Client</h3>
            <button class="close-modal" onclick="closeClientModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Company Name</label>
                <input type="text" id="new_client_name" class="p-input">
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Contact Person</label>
                <input type="text" id="new_client_contact" class="p-input">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="new_client_phone" class="p-input">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="new_client_email" class="p-input">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeClientModal()">Cancel</button>
            <button class="btn btn-primary" onclick="submitQuickClient()">Save Client</button>
        </div>
    </div>
</div>

<style>
/* Layout Structure */
.proposal-full-wrapper { max-width: 100%; margin: 0 auto; padding: 1.5rem; background: #f8fafc; min-height: 100vh; }

.proposal-bottom-grid { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 1.5rem; 
    margin-top: 2rem; 
}

/* Step Indicators */
.step-indicator { 
    background: var(--primary); 
    color: white; 
    padding: 0.35rem 1rem; 
    border-radius: 50px; 
    font-size: 0.75rem; 
    font-weight: 800; 
    text-transform: uppercase; 
    letter-spacing: 0.05em;
}

/* Panel Styling */
.p-panel { 
    background: white; 
    border-radius: 20px; 
    padding: 2rem; 
    border: 1px solid #e2e8f0; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
}

.p-header { 
    font-weight: 800; 
    font-size: 1.25rem; 
    color: var(--primary); 
    margin-bottom: 2rem; 
    border-bottom: 2px solid #f1f5f9; 
    padding-bottom: 1.25rem; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
}

/* Form Elements */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 800; color: var(--secondary); margin-bottom: 0.6rem; text-transform: uppercase; letter-spacing: 0.025em; }

.p-input { 
    width: 100%; padding: 0.875rem; border: 1px solid #e2e8f0; border-radius: 12px; 
    font-family: inherit; font-size: 1rem; font-weight: 600; transition: all 0.2s ease; 
    background: #fcfcfc;
}
.p-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.1); outline: none; background: white; }

/* Table Styling */
.site-list-container { overflow-x: auto; border: 1px solid #f1f5f9; border-radius: 16px; background: white; }
.crs-table { width: 100%; border-collapse: collapse; }
.crs-table th { background: #f8fafc; padding: 1.25rem 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; font-weight: 800; border-bottom: 2px solid #f1f5f9; text-align: left; }
.crs-table td { padding: 1.25rem 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }

.site-row { transition: background 0.2s; }
.site-row:hover { background: #fafafa; }
.site-row.selected { background: #f0fdfa !important; }

/* Summary Items */
.stat-row { display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 1rem; font-weight: 700; color: #475569; }
.summary-box { background: linear-gradient(to bottom right, #ffffff, #f8fafc); }
.grand-total { border-top: 2px solid #e2e8f0; padding-top: 1.5rem; margin-top: 1rem; }

/* Badges */
.badge-ha { background: #dcfce7; color: #166534; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; }
.badge-ta { background: #fef9c3; color: #854d0e; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; }

/* Responsive */
@media (max-width: 1400px) {
    .proposal-bottom-grid { grid-template-columns: repeat(3, 1fr); gap: 1rem; }
}

@media (max-width: 1200px) {
    .proposal-bottom-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .proposal-bottom-grid { grid-template-columns: 1fr; }
    .p-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
}

/* Modal Styles */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 5000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
.modal-content { background: white; width: 95%; max-width: 550px; border-radius: 30px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
.modal-header { padding: 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 2.5rem; }
.modal-footer { padding: 1.5rem 2.5rem; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 1rem; }
</style>

<script>
let selectedSites = [];

function openClientModal() { document.getElementById('clientModal').style.display = 'flex'; }
function closeClientModal() { document.getElementById('clientModal').style.display = 'none'; }

function submitQuickClient() {
    const name = document.getElementById('new_client_name').value;
    const contact = document.getElementById('new_client_contact').value;
    const phone = document.getElementById('new_client_phone').value;
    const email = document.getElementById('new_client_email').value;

    if (!name) {
        Swal.fire('Error', 'Company Name is required', 'error');
        return;
    }

    const data = { type: 'client', name, contact, phone, email };

    fetch('../../ajax/quick_save_partner.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const select = document.getElementById('client_id');
            const opt = document.createElement('option');
            opt.value = res.id;
            opt.text = name;
            opt.selected = true;
            select.add(opt);
            closeClientModal();
            Swal.fire('Success', 'Client created and selected!', 'success');
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}

function toggleSite(id) {
    const row = document.getElementById('row-' + id);
    const chk = row.querySelector('.asset-chk');
    const input = row.querySelector('.sale-rate-input');
    
    const name = row.dataset.name;
    const rate = parseFloat(row.dataset.rate);
    const prate = parseFloat(row.dataset.prate);
    const owner = row.dataset.owner;
    const sqft = parseFloat(row.dataset.sqft);

    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx === -1) {
        selectedSites.push({ id, name, cardRate: rate, purchaseRate: prate, saleRate: rate, owner, sqft });
        row.classList.add('selected');
        chk.checked = true;
        input.disabled = false;
    } else {
        selectedSites.splice(idx, 1);
        row.classList.remove('selected');
        chk.checked = false;
        input.disabled = true;
        row.querySelector('.total-cell').innerText = '₹0';
        row.querySelector('.markup-cell').innerText = '-';
    }
    
    const count = selectedSites.length;
    if(document.getElementById('selected-count')) document.getElementById('selected-count').innerText = count;
    if(document.getElementById('selected-count-btm')) document.getElementById('selected-count-btm').innerText = count;
    recalcAll();
}

function updateSitePrice(id, val) {
    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx !== -1) {
        selectedSites[idx].saleRate = parseFloat(val) || 0;
        recalcAll();
    }
}

function recalcAll() {
    const print = parseFloat(document.getElementById('print_cost').value) || 0;
    const mount = parseFloat(document.getElementById('mount_cost').value) || 0;

    let totalDisplay = 0;

    selectedSites.forEach((site) => {
        const row = document.getElementById('row-' + site.id);
        const currentTotal = site.saleRate;
        const markupVal = site.saleRate - site.purchaseRate;
        const markupPct = ((markupVal / site.purchaseRate) * 100).toFixed(1);

        totalDisplay += currentTotal;
        
        // Update row visual
        if(row) {
            row.querySelector('.total-cell').innerText = '₹' + currentTotal.toLocaleString();
            row.querySelector('.markup-cell').innerText = markupPct + '%';
        }
    });

    const subtotal = totalDisplay + print + mount;
    const tax = subtotal * 0.18;
    const grand = subtotal + tax;

    // Update Summary Panel (Bottom)
    const displayEl = document.getElementById('sum-display-btm');
    const taxEl = document.getElementById('sum-tax-btm');
    const grandEl = document.getElementById('sum-grand-btm');

    if(displayEl) displayEl.innerText = '₹' + totalDisplay.toLocaleString();
    if(taxEl) taxEl.innerText = '₹' + tax.toLocaleString();
    if(grandEl) grandEl.innerText = '₹' + grand.toLocaleString();
}

function filterSites() {
    const q = document.getElementById('site-search').value.toLowerCase();
    document.querySelectorAll('.site-row').forEach(el => {
        el.style.display = el.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}

function saveProposal() {
    const clientId = document.getElementById('client_id').value;
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    
    if (!clientId || !start || !end || selectedSites.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select a client, set the dates, and select at least one asset.',
            confirmButtonColor: 'var(--primary)'
        });
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
