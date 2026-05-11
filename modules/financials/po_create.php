<?php
$activePage = 'pos';
$pageTitle = 'Create Purchase Order (Wizard)';
include_once __DIR__ . '/../../includes/header.php';

if (!hasRole(['admin', 'accounts'])) {
    echo "<div class='card'>Access Denied.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch Campaigns & Vendors
$campaigns = $pdo->query("SELECT id, display_name, project_id FROM campaigns WHERE status = 'approved' OR status = 'running'")->fetchAll();
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
?>

<div class="wizard-container">
    <!-- Wizard Progress -->
    <div class="wizard-progress">
        <div class="step active" id="step1-tab">1. Source Selection</div>
        <div class="step" id="step2-tab">2. Configure Items</div>
        <div class="step" id="step3-tab">3. Review & Confirm</div>
    </div>

    <!-- Step 1: Selection -->
    <div class="p-panel wizard-step" id="step1">
        <div class="p-header">Step 1: Select Campaign & Supplier</div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label>Target Campaign</label>
                <select id="campaign_id" class="p-input" onchange="loadCampaignDetails()">
                    <option value="">-- Choose Campaign --</option>
                    <?php foreach ($campaigns as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo $c['project_id'] . ' - ' . $c['display_name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Supplier / Vendor</label>
                <select id="vendor_id" class="p-input">
                    <option value="">-- Choose Vendor --</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top: 2rem; text-align: right;">
            <button class="btn btn-primary" onclick="goToStep(2)">Next: Configure Items <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 2: Items -->
    <div class="p-panel wizard-step" id="step2" style="display: none;">
        <div class="p-header">Step 2: Configure PO Line Items</div>
        <div id="campaign-sites-container">
            <!-- Loaded via AJAX/JS -->
            <p style="color: var(--secondary);">Please select a campaign in Step 1 first.</p>
        </div>
        <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
            <button class="btn" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
            <button class="btn btn-primary" onclick="goToStep(3)">Next: Final Review <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 3: Confirm -->
    <div class="p-panel wizard-step" id="step3" style="display: none;">
        <div class="p-header">Step 3: Review & Finalize PO</div>
        <div id="po-review-container" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Summary Columns -->
        </div>
        <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
            <button class="btn" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
            <button class="btn btn-primary" id="confirm-po-btn" onclick="finalizePO()">
                <i class="fas fa-check-circle"></i> Confirm & Generate PO
            </button>
        </div>
    </div>
</div>

<style>
.wizard-progress { display: flex; gap: 0.5rem; margin-bottom: 2rem; background: #f8fafc; padding: 0.5rem; border-radius: 8px; }
.step { flex: 1; text-align: center; padding: 0.75rem; border-radius: 6px; font-weight: 600; color: #94a3b8; }
.step.active { background: var(--primary); color: white; }
.p-panel { background: white; border-radius: 10px; padding: 1.5rem; border: 1px solid #e2e8f0; }
.p-header { color: var(--primary); font-weight: 700; margin-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; }
.p-input { width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; margin-top: 0.25rem; }
</style>

<script>
let currentStep = 1;
let campaignSites = [];

function goToStep(step) {
    if (step === 2 && !document.getElementById('campaign_id').value) {
        Swal.fire('Selection Required', 'Please select a campaign before proceeding.', 'warning');
        return;
    }
    
    document.querySelectorAll('.wizard-step').forEach(s => s.style.display = 'none');
    document.getElementById('step' + step).style.display = 'block';
    
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById('step' + step + '-tab').classList.add('active');
    
    if(step === 3) prepareReview();
    currentStep = step;
}

function loadCampaignDetails() {
    const cid = document.getElementById('campaign_id').value;
    if (!cid) return;
    
    // Simulate fetching campaign sites
    // In a real app, this would be an AJAX call to get sites linked to campaign
    fetch(`../../ajax/get_campaign_sites.php?id=${cid}`)
    .then(r => r.json())
    .then(data => {
        campaignSites = data.sites;
        renderSitesTable();
    });
}

function renderSitesTable() {
    let html = `
        <table class="table">
            <thead>
                <tr>
                    <th>Site</th>
                    <th>Dates</th>
                    <th>Monthly Rate (Purchase)</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    campaignSites.forEach((s, i) => {
        html += `
            <tr>
                <td><strong>${s.site_code}</strong><br><small>${s.name}</small></td>
                <td>${s.start_date} to ${s.end_date}</td>
                <td><input type="number" class="p-input" value="${s.purchase_rate}" onchange="updateSiteRate(${i}, this.value)"></td>
                <td>₹${parseFloat(s.purchase_rate).toLocaleString()}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table>';
    document.getElementById('campaign-sites-container').innerHTML = html;
}

function updateSiteRate(i, val) {
    campaignSites[i].purchase_rate = parseFloat(val);
    renderSitesTable();
}

function prepareReview() {
    const vendorEl = document.getElementById('vendor_id');
    const vendorName = vendorEl.options[vendorEl.selectedIndex].text;
    
    let total = 0;
    campaignSites.forEach(s => total += s.purchase_rate);
    const tax = total * 0.18;
    const grand = total + tax;

    const html = `
        <div>
            <h4 style="margin-bottom: 1rem;">Order Details</h4>
            <div class="card" style="background: #f8fafc;">
                <p><strong>Supplier:</strong> ${vendorName}</p>
                <p><strong>PO Number:</strong> PO-${Date.now().toString().slice(-6)}</p>
                <p><strong>Terms:</strong> Standard 30 Days</p>
            </div>
            <table class="table" style="margin-top: 1rem; font-size: 0.85rem;">
                <thead><tr><th>Description</th><th>Rate</th></tr></thead>
                <tbody>
                    ${campaignSites.map(s => `<tr><td>${s.site_code} Rental</td><td>₹${s.purchase_rate}</td></tr>`).join('')}
                </tbody>
            </table>
        </div>
        <div class="card" style="background: #eff6ff;">
            <h4 style="margin-bottom: 1rem;">PO Summary</h4>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Subtotal:</span><span>₹${total.toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>GST (18%):</span><span>₹${tax.toLocaleString()}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 1.1rem; border-top: 1px solid #bfdbfe; padding-top: 1rem;">
                <span>TOTAL:</span><span>₹${grand.toLocaleString()}</span>
            </div>
        </div>
    `;
    document.getElementById('po-review-container').innerHTML = html;
}

function finalizePO() {
    const btn = document.getElementById('confirm-po-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PO...';

    const data = {
        campaignId: document.getElementById('campaign_id').value,
        vendorId: document.getElementById('vendor_id').value,
        sites: campaignSites
    };

    fetch('../../ajax/save_po.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('PO Generated', 'Purchase Order #' + res.po_number + ' has been created successfully.', 'success')
            .then(() => window.location.href = 'purchase_orders.php');
        } else {
            Swal.fire('Error', res.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm & Generate PO';
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
