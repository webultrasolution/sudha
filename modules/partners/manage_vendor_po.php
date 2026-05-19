<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
if (!$vendor_id) { header("Location: vendors.php"); exit; }

$vendor = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$vendor->execute([$vendor_id]);
$vendor = $vendor->fetch();
if (!$vendor) { echo "Vendor not found."; exit; }

// Fetch vendor sites
$sites = $pdo->prepare("
    SELECT s.*, 
        (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail
    FROM sites s 
    WHERE s.vendor_id = ? 
    ORDER BY s.site_code ASC
");
$sites->execute([$vendor_id]);
$sites = $sites->fetchAll();

// Parse vendor GSTINs for selection
$vendorGsts = [];
if ($vendor['gstin']) {
    $vendorGsts[] = ['gstin' => $vendor['gstin'], 'label' => $vendor['gstin'] . ' (Primary - ' . ($vendor['state'] ?: 'N/A') . ')'];
}
if ($vendor['additional_gst']) {
    try {
        $extra = json_decode($vendor['additional_gst'], true);
        if (is_array($extra)) {
            foreach ($extra as $g) {
                if (is_array($g) && !empty($g['gstin'])) {
                    $vendorGsts[] = ['gstin' => $g['gstin'], 'label' => $g['gstin'] . ' (' . ($g['state'] ?? $g['city'] ?? '') . ')'];
                }
            }
        }
    } catch (Exception $e) {}
}

$activePage = 'vendors';
$pageTitle = 'Create Purchase Order — ' . $vendor['name'];
include_once __DIR__ . '/../../includes/header.php';
?>

<style>
.po-wizard { max-width: 1200px; margin: 0 auto; }
.po-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.04); margin-bottom: 1.5rem; overflow: hidden; }
.po-card-header { padding: 1rem 1.5rem; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
.po-card-header h3 { margin: 0; font-size: 0.85rem; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.05em; }
.po-card-body { padding: 1.5rem; }
.po-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.po-fg { margin-bottom: 0; }
.po-fg label { display: block; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.4rem; }
.po-fg input, .po-fg select, .po-fg textarea { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; font-weight: 600; font-family: inherit; background: #f8fafc; transition: all 0.2s; }
.po-fg input:focus, .po-fg select:focus, .po-fg textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13,148,136,0.1); background: white; }

/* Vendor Info Banner */
.vendor-banner { display: flex; align-items: center; gap: 1.5rem; padding: 1.25rem 1.5rem; background: linear-gradient(135deg, #f0fdfa, #ccfbf1); border-bottom: 2px solid #99f6e4; }
.vendor-avatar { width: 50px; height: 50px; background: var(--primary); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; box-shadow: 0 4px 10px rgba(13,148,136,0.25); }
.vendor-meta h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--primary-dark); }
.vendor-meta span { font-size: 0.75rem; color: #0f766e; font-weight: 600; }

/* Sites Table */
.site-select-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
.site-select-table thead th { font-size: 0.6rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.6rem 1rem; text-align: left; border-bottom: 2px solid #f1f5f9; }
.site-select-table tbody tr { background: white; transition: all 0.2s; border-radius: 10px; }
.site-select-table tbody tr:hover { background: #f0fdfa; }
.site-select-table tbody tr.row-selected { background: #ccfbf1; box-shadow: inset 3px 0 0 var(--primary); }
.site-select-table td { padding: 0.75rem 1rem; font-size: 0.8rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.site-thumb { width: 70px; height: 45px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; }
.site-code-badge { background: #f1f5f9; color: var(--primary); font-size: 0.6rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; }
.rate-input { width: 90px; text-align: right; font-weight: 800; color: var(--primary); padding: 0.4rem 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; background: #f0fdfa; }
.rate-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(13,148,136,0.15); }
.cb-site { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
.select-all-cb { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }

/* Summary Bar */
.po-summary-bar { position: sticky; bottom: 0; background: white; border-top: 3px solid var(--primary); padding: 0.75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; z-index: 100; border-radius: 16px 16px 0 0; box-shadow: 0 -8px 30px rgba(0,0,0,0.06); }
.summary-stat { text-align: right; }
.summary-stat .label { font-size: 0.55rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; }
.summary-stat .value { font-weight: 800; color: #0f172a; font-size: 0.95rem; }
.summary-stat .value.grand { font-size: 1.3rem; color: var(--primary); }
.btn-generate { height: 46px; padding: 0 2rem; border-radius: 12px; font-weight: 900; font-size: 0.9rem; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 15px rgba(13,148,136,0.3); transition: all 0.2s; }
.btn-generate:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(13,148,136,0.4); }
.btn-generate:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.empty-state { text-align: center; padding: 4rem 2rem; color: #94a3b8; }
.empty-state i { font-size: 3rem; opacity: 0.2; margin-bottom: 1rem; display: block; }
</style>

<div class="po-wizard">
    <!-- Vendor Banner -->
    <div class="po-card" style="margin-bottom: 1.5rem;">
        <div class="vendor-banner">
            <div class="vendor-avatar"><i class="fas fa-truck"></i></div>
            <div class="vendor-meta">
                <h2><?php echo htmlspecialchars($vendor['name']); ?></h2>
                <span>
                    <?php echo $vendor['contact_person'] ?: ''; ?>
                    <?php if ($vendor['city']): ?> • <?php echo $vendor['city']; ?><?php endif; ?>
                    <?php if ($vendor['gstin']): ?> • GSTIN: <?php echo $vendor['gstin']; ?><?php endif; ?>
                </span>
            </div>
            <a href="vendors.php" style="margin-left: auto; text-decoration: none; background: white; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.8rem; color: var(--primary-dark); border: 1px solid #99f6e4;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- PO Details -->
    <div class="po-card">
        <div class="po-card-header">
            <h3><i class="fas fa-clipboard-list" style="color: var(--primary); margin-right: 0.5rem;"></i> PO Details</h3>
        </div>
        <div class="po-card-body">
            <div class="po-form-grid">
                <div class="po-fg">
                    <label>Campaign / Purpose <span style="color:red;">*</span></label>
                    <input type="text" id="po_campaign" placeholder="e.g. Summer 2026 Campaign" value="">
                </div>
                <div class="po-fg">
                    <label>From Date <span style="color:red;">*</span></label>
                    <input type="date" id="po_start" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="po-fg">
                    <label>To Date <span style="color:red;">*</span></label>
                    <input type="date" id="po_end" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>">
                </div>
                <?php if (count($vendorGsts) > 1): ?>
                <div class="po-fg">
                    <label>Vendor GSTIN</label>
                    <select id="po_vendor_gst">
                        <?php foreach ($vendorGsts as $g): ?>
                            <option value="<?php echo htmlspecialchars($g['gstin']); ?>"><?php echo htmlspecialchars($g['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="po-fg">
                    <label>Tax Type</label>
                    <select id="po_tax_type">
                        <?php if (empty($vendor['gstin'])): ?>
                            <option value="none" selected>No GST (0%)</option>
                        <?php endif; ?>
                        <option value="igst" <?php echo !empty($vendor['gstin']) ? 'selected' : ''; ?>>IGST (18%)</option>
                        <option value="cgst_sgst">CGST + SGST (9%+9%)</option>
                        <?php if (!empty($vendor['gstin'])): ?>
                            <option value="none">No GST (0%)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="po-fg" style="grid-column: span 2;">
                    <label>Remarks <span style="color:red;">*</span></label>
                    <textarea id="po_remarks" rows="2" placeholder="Internal notes for this PO..." required></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Site Selection -->
    <div class="po-card">
        <div class="po-card-header">
            <h3><i class="fas fa-map-marker-alt" style="color: var(--primary); margin-right: 0.5rem;"></i> Select Sites (<span id="total-site-count"><?php echo count($sites); ?></span> owned)</h3>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="background: #ccfbf1; color: var(--primary-dark); padding: 0.35rem 0.75rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem;">
                    Selected: <span id="sel-count">0</span>
                </div>
            </div>
        </div>
        <div class="po-card-body" style="padding: 0;">
            <?php if (count($sites) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-map-marked-alt"></i>
                    <div style="font-weight: 700;">No sites found for this vendor.</div>
                    <div style="font-size: 0.8rem; margin-top: 0.5rem;">Add sites in Inventory and assign this vendor.</div>
                </div>
            <?php else: ?>
            <table class="site-select-table">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;"><input type="checkbox" class="select-all-cb" id="selectAll" onclick="toggleSelectAll(this)"></th>
                        <th style="width:40px;">#</th>
                        <th style="width:80px;">Preview</th>
                        <th>Site Code / City</th>
                        <th>Location / Details</th>
                        <th>Size</th>
                        <th style="text-align:right;">Purchase Rate (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $idx => $s): 
                        $thumb = '';
                        if (!empty($s['thumbnail'])) $thumb = '../../uploads/sites/' . $s['thumbnail'];
                    ?>
                    <tr id="srow-<?php echo $s['id']; ?>">
                        <td style="text-align:center;">
                            <input type="checkbox" class="cb-site" data-id="<?php echo $s['id']; ?>" 
                                   data-code="<?php echo htmlspecialchars($s['site_code']); ?>"
                                   data-location="<?php echo htmlspecialchars($s['location']); ?>"
                                   data-city="<?php echo htmlspecialchars($s['city'] ?? ''); ?>"
                                   data-rate="<?php echo floatval($s['purchase_rate']); ?>"
                                   data-width="<?php echo $s['width']; ?>"
                                   data-height="<?php echo $s['height']; ?>"
                                   data-type="<?php echo htmlspecialchars($s['type'] ?? ''); ?>"
                                   data-light="<?php echo htmlspecialchars($s['light_type'] ?? ''); ?>"
                                   data-hsn="<?php echo htmlspecialchars($s['hsn_code'] ?? '998366'); ?>"
                                   onclick="toggleSiteRow(this)">
                        </td>
                        <td style="font-weight:700; color:#94a3b8;"><?php echo $idx + 1; ?></td>
                        <td>
                            <?php if ($thumb): ?>
                                <img src="<?php echo $thumb; ?>" class="site-thumb" alt="">
                            <?php else: ?>
                                <div style="width:70px; height:45px; background:#f1f5f9; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.6rem; color:#94a3b8;">No Img</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="site-code-badge"><?php echo $s['site_code']; ?></span>
                            <div style="font-weight:700; color:#0f172a; font-size:0.8rem; margin-top:3px;"><?php echo $s['city'] ?: 'N/A'; ?></div>
                        </td>
                        <td>
                            <div style="font-weight:700; color:#1e293b; font-size:0.8rem;"><?php echo $s['name']; ?></div>
                            <div style="font-size:0.7rem; color:#64748b; line-height:1.2;"><?php echo $s['location']; ?></div>
                            <div style="display:flex; gap:0.25rem; margin-top:3px;">
                                <span style="background:#ecfdf5; color:#059669; padding:1px 5px; border-radius:3px; font-size:0.55rem; font-weight:800;"><?php echo $s['type'] ?: 'N/A'; ?></span>
                                <span style="background:#f1f5f9; color:#475569; padding:1px 5px; border-radius:3px; font-size:0.55rem; font-weight:800;"><?php echo $s['light_type'] ?: 'NL'; ?></span>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:800; font-size:0.8rem;"><?php echo $s['width']; ?>' × <?php echo $s['height']; ?>'</div>
                            <div style="font-size:0.65rem; color:#94a3b8; font-weight:600;"><?php echo number_format($s['width'] * $s['height']); ?> sqft</div>
                        </td>
                        <td style="text-align:right;">
                            <input type="number" class="rate-input" id="rate-<?php echo $s['id']; ?>" 
                                   value="<?php echo floatval($s['purchase_rate']); ?>" 
                                   oninput="recalcTotals()">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Sticky Summary Bar -->
<div class="po-summary-bar">
    <div style="display:flex; align-items:center; gap:0.75rem;">
        <div style="font-size:0.75rem; font-weight:800; color:#64748b;">
            <i class="fas fa-check-circle" style="color:var(--primary);"></i> <span id="bar-sel">0</span> Sites Selected
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:1.5rem;">
        <div class="summary-stat">
            <div class="label">Subtotal</div>
            <div class="value" id="bar-subtotal">₹0.00</div>
        </div>
        <div class="summary-stat" id="tax-display">
            <div class="label">GST (18%)</div>
            <div class="value" id="bar-tax">₹0.00</div>
        </div>
        <div class="summary-stat">
            <div class="label">Grand Total</div>
            <div class="value grand" id="bar-grand">₹0.00</div>
        </div>
        <button class="btn-generate" id="btnGenerate" onclick="generatePO()" disabled>
            <i class="fas fa-file-invoice-dollar"></i> GENERATE PO
        </button>
    </div>
</div>

<script>
function getSelectedSites() {
    const checked = document.querySelectorAll('.cb-site:checked');
    let sites = [];
    checked.forEach(cb => {
        const id = cb.dataset.id;
        const rate = parseFloat(document.getElementById('rate-' + id).value) || 0;
        sites.push({
            id: parseInt(id),
            code: cb.dataset.code,
            location: cb.dataset.location,
            city: cb.dataset.city,
            rate: rate,
            width: cb.dataset.width,
            height: cb.dataset.height,
            type: cb.dataset.type,
            light: cb.dataset.light,
            hsn: cb.dataset.hsn
        });
    });
    return sites;
}

function toggleSiteRow(cb) {
    const row = document.getElementById('srow-' + cb.dataset.id);
    if (cb.checked) {
        row.classList.add('row-selected');
    } else {
        row.classList.remove('row-selected');
    }
    recalcTotals();
}

function toggleSelectAll(masterCb) {
    const cbs = document.querySelectorAll('.cb-site');
    cbs.forEach(cb => {
        cb.checked = masterCb.checked;
        toggleSiteRow(cb);
    });
    recalcTotals();
}

function recalcTotals() {
    const sites = getSelectedSites();
    const count = sites.length;
    const subtotal = sites.reduce((a, s) => a + s.rate, 0);
    const taxType = document.getElementById('po_tax_type').value;
    
    let taxAmt = 0;
    if (taxType !== 'none') {
        taxAmt = subtotal * 0.18;
    }
    const grand = subtotal + taxAmt;

    document.getElementById('sel-count').innerText = count;
    document.getElementById('bar-sel').innerText = count;
    document.getElementById('bar-subtotal').innerText = '₹' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('bar-tax').innerText = '₹' + taxAmt.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('bar-grand').innerText = '₹' + grand.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // Update tax label
    const taxDisp = document.getElementById('tax-display');
    if (taxType === 'cgst_sgst') {
        taxDisp.querySelector('.label').innerText = 'CGST+SGST (9%+9%)';
    } else if (taxType === 'none') {
        taxDisp.querySelector('.label').innerText = 'GST (0%)';
    } else {
        taxDisp.querySelector('.label').innerText = 'IGST (18%)';
    }

    document.getElementById('btnGenerate').disabled = (count === 0);
}

// Recalc on tax type change
document.getElementById('po_tax_type').addEventListener('change', recalcTotals);

function generatePO() {
    const sites = getSelectedSites();
    if (sites.length === 0) {
        return Swal.fire('Error', 'Please select at least one site.', 'error');
    }

    const campaign = document.getElementById('po_campaign').value.trim();
    const startDate = document.getElementById('po_start').value;
    const endDate = document.getElementById('po_end').value;
    const taxType = document.getElementById('po_tax_type').value;
    const remarks = document.getElementById('po_remarks').value.trim();
    const vendorGst = document.getElementById('po_vendor_gst')?.value || '';

    if (!campaign) {
        return Swal.fire('Required', 'Please enter a Campaign / Purpose name.', 'warning');
    }
    if (!startDate || !endDate) {
        return Swal.fire('Required', 'Please select From and To dates.', 'warning');
    }
    if (!remarks) {
        return Swal.fire('Required', 'Please enter Remarks for this PO.', 'warning');
    }

    const btn = document.getElementById('btnGenerate');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SAVING...';

    const payload = {
        vendor_id: <?php echo $vendor_id; ?>,
        campaign_name: campaign,
        start_date: startDate,
        end_date: endDate,
        tax_type: taxType,
        remarks: remarks,
        vendor_gst: vendorGst,
        sites: sites.map(s => ({
            id: s.id,
            rate: s.rate
        }))
    };

    fetch('../../ajax/save_vendor_po.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Purchase Order Generated!',
                text: res.message || 'PO saved successfully.',
                showCancelButton: true,
                confirmButtonText: 'View PO',
                cancelButtonText: 'Back to Vendors',
                confirmButtonColor: '#d97706'
            }).then((result) => {
                if (result.isConfirmed && res.po_id) {
                    window.open('../operations/generate_po.php?po_id=' + res.po_id, '_blank');
                    window.location.href = 'vendors.php';
                } else {
                    window.location.href = 'vendors.php';
                }
            });
        } else {
            Swal.fire('Error', res.message || 'Failed to generate PO.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-invoice-dollar"></i> GENERATE PO';
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-invoice-dollar"></i> GENERATE PO';
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
