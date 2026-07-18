<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Fetch Client Info if ID provided
$c = null;
if ($client_id > 0) {
    $stmtC = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'client'");
    $stmtC->execute([$client_id]);
    $c = $stmtC->fetch();
}

// Fetch all clients for client dropdown
$clientsList = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();

// Fetch all vendors for filtering rates
$vendorsList = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();

// Fetch Printing Rates (optionally filtered by vendor)
$selectedFilterVendorId = isset($_GET['filter_vendor_id']) ? intval($_GET['filter_vendor_id']) : 0;
$rateParams = [];
$rateWhere = "";
if ($client_id > 0) {
    $rateWhere = "WHERE r.client_id = ?";
    $rateParams[] = $client_id;
    if ($selectedFilterVendorId > 0) {
        $rateWhere .= " AND s.vendor_id = ?";
        $rateParams[] = $selectedFilterVendorId;
    }
}

$rates = [];
if ($client_id > 0) {
    $stmtR = $pdo->prepare("
        SELECT r.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.type as media_type_site, s.hsn_code, s.vendor_gst, v.name as vendor_name
        FROM client_printing_rates r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN partners v ON s.vendor_id = v.id
        $rateWhere
        ORDER BY s.site_code ASC
    ");
    $stmtR->execute($rateParams);
    $rates = $stmtR->fetchAll();
}

// Check if we are in preview mode (form submitted)
$preview = isset($_GET['preview']) && $_GET['preview'] === '1' && $client_id > 0;
$selected_rate_ids = isset($_GET['rate_ids']) ? array_map('intval', $_GET['rate_ids']) : [];
$po_remark = $_GET['remark'] ?? '';

// If preview mode, filter only selected rates
if ($preview && !empty($selected_rate_ids)) {
    $rates = array_filter($rates, function($r) use ($selected_rate_ids) {
        return in_array($r['id'], $selected_rate_ids);
    });
    $rates = array_values($rates);
}

// Company Settings — uses active session entity
$co                 = resolveCompanyDetails();
$company_name       = $co['name'];
$company_gstin      = $co['gstin'];
$company_pan        = $co['pan'];
$company_address    = $co['address'];
$company_phone      = $co['phone'];
$company_email      = $co['email'];
$company_letterhead = $co['letterhead'];
$company_signature  = $co['signature'];
$company_msme       = $co['msme_number'];
$company_cin        = $co['cin'] ?? '';
$company_tan        = $co['tan'] ?? '';

$is_final = isset($_GET['is_final']) && $_GET['is_final'] === '1';
$company_terms = $is_final ? $co['invoice_terms'] : $co['terms_conditions'];

$po_number = getPreviewSequenceNumber($pdo, 'client_printing_po');
$po_date = date('d-m-Y');

if ($preview && !empty($rates)) {
    if ($is_final) {
        if (!empty($rates[0]['custom_invoice_number'])) {
            $po_number = $rates[0]['custom_invoice_number'];
        }
        if (!empty($rates[0]['custom_invoice_date']) && $rates[0]['custom_invoice_date'] !== '0000-00-00') {
            $po_date = date('d-m-Y', strtotime($rates[0]['custom_invoice_date']));
        }
    } else {
        if (!empty($rates[0]['po_number'])) {
            $po_number = $rates[0]['po_number'];
        }
        if (!empty($rates[0]['created_at'])) {
            $po_date = date('d-m-Y', strtotime($rates[0]['created_at']));
        }
    }
}

// ============================
// SELECTION FORM (Not Preview)
// ============================
if (!$preview):

$activePage = 'client_printing';
$pageTitle = 'Client Printing Invoice - Generate Print';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">
                <i class="fas fa-file-invoice" style="color: var(--primary); margin-right: 0.5rem;"></i>
                Generate Client Printing Invoice
            </h2>
            <div style="font-size: 0.8rem; color: #64748b;">
                Select Client and choose printing rates to include.
            </div>
        </div>
        <a href="../partners/client_printing_rates.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Printing PO
        </a>
    </div>

    <!-- Wizard Progress Tracker -->
    <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 1.5rem; background: white; padding: 0.6rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); max-width: 400px; margin-left: auto; margin-right: auto;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div id="step-tab-1" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem;">
                <div class="step-circle" style="width: 24px; height: 24px; background: #0d9488; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid white; box-shadow: 0 0 0 2px #0d9488;">1</div>
                <span class="step-label" style="font-size: 0.55rem; font-weight: 800; color: #0d9488; text-transform: uppercase;">Details</span>
            </div>
            <div style="width: 30px; height: 2px; background: #e2e8f0; position: relative; margin-top: -12px;">
                <div id="wizard-progress-line" style="position: absolute; left: 0; top: 0; height: 100%; width: 0%; background: #0d9488; transition: width 0.4s;"></div>
            </div>
            <div id="step-tab-2" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem;">
                <div class="step-circle" id="step-circle-2" style="width: 24px; height: 24px; background: #fff; color: #94a3b8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; border: 2px solid white; box-shadow: 0 0 0 2px #e2e8f0;">2</div>
                <span class="step-label" id="step-label-2" style="font-size: 0.55rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Assets</span>
            </div>
        </div>
    </div>

    <form method="GET" action="client_printing.php" id="poForm" enctype="multipart/form-data">
        <input type="hidden" name="preview" value="1">

        <!-- STEP 1: Details -->
        <div id="step-1">

            <!-- Client & Vendor Selection Row -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: start;">
                <div style="flex: 1.5; min-width: 250px;">
                    <label style="display: block; font-size: 0.65rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">1. Select Client (Required)</label>
                    <select name="client_id" id="selected_client_id" required onchange="handleClientSelectionChange()" style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; font-weight: 600; background: white; outline: none; height: 38px;">
                        <option value="">Choose Client...</option>
                        <?php foreach ($clientsList as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>" <?php echo $client_id == $cl['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cl['name'], ENT_QUOTES, 'UTF-8', false); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-size: 0.65rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Filter Rates by Vendor</label>
                    <select name="filter_vendor_id" id="filter_vendor_id" onchange="handleClientSelectionChange()" style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; font-weight: 600; background: white; outline: none; height: 38px;">
                        <option value="">All Printing Vendors</option>
                        <?php foreach ($vendorsList as $vl): ?>
                            <option value="<?php echo $vl['id']; ?>" <?php echo $selectedFilterVendorId == $vl['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vl['name'], ENT_QUOTES, 'UTF-8', false); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- GST Selection for Group Companies / Branches -->
            <div id="gst_selection_container" style="display: none; margin-bottom: 1.5rem; background: #f0fdfa; padding: 1.25rem; border-radius: 12px; border: 1px solid #ccfbf1; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <label style="color: var(--primary); font-weight: 800; font-size: 0.75rem; margin-bottom: 0; display: block; text-transform: uppercase; letter-spacing: 0.05em;">
                        <i class="fas fa-id-card"></i> Billing GSTIN / State Selection
                    </label>
                    <span id="gst_count_badge" style="background: var(--primary); color: white; font-size: 0.65rem; padding: 2px 8px; border-radius: 50px; font-weight: 700;"></span>
                </div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <select id="selected_gstin" name="billing_gstin" style="flex: 1; min-width: 250px; height: 38px; border: 1.5px solid #5eead4; border-radius: 8px; padding: 0.5rem; background: white; font-weight: 600; font-size: 0.85rem;" onchange="handleGstSelectionChange()">
                        <!-- Dynamic Options -->
                    </select>
                    <div id="gst_details_preview" style="flex: 2; min-width: 300px; background: white; border: 1px solid #ccfbf1; border-radius: 8px; padding: 0.5rem 1rem; font-size: 0.8rem; color: #0f766e; display: flex; align-items: center; gap: 0.5rem; min-height: 38px; box-sizing: border-box;">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="gst_preview_text">Select a GSTIN to see location details</span>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Remark / Reference (Optional)</label>
                        <input type="text" name="remark" placeholder="e.g. Campaign Name, Brand name..." style="width: 100%; padding: 0.65rem; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                    </div>
                    
                    <div style="flex: 1; min-width: 200px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Ownership Type</label>
                        <div style="display: flex; gap: 1rem; padding-top: 0.4rem;">
                            <label style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.9rem; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="ownership" value="Self" checked style="accent-color: var(--primary); width: 16px; height: 16px;"> Self
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.9rem; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="ownership" value="TA" style="accent-color: var(--primary); width: 16px; height: 16px;"> TA (Agency)
                            </label>
                        </div>
                    </div>
                    
                    <div style="flex: 1; min-width: 250px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Attach POS / Tax Invoice</label>
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" style="width: 100%; padding: 0.5rem 0; font-size: 0.85rem;">
                        <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.2rem;">Optional. JPG, PNG, or PDF allowed.</div>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin: 2rem 0;">
                <button type="button" class="btn btn-primary" onclick="goToStep2()" style="width: 250px; height: 48px; border-radius: 12px; font-weight: 800; font-size: 0.95rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: #0d9488; border-color: #0d9488; color: white; cursor: pointer;">
                    Next Step: Select Assets <i class="fas fa-arrow-right"></i>
                </button>
            </div>
            </div> <!-- /#step-1 -->

            <!-- STEP 2: Assets -->
            <div id="step-2" style="display: none;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                    <button type="button" onclick="goToStep1()" class="btn btn-secondary" style="height: 38px; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 800; padding: 0 1.2rem; background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569; cursor: pointer;">
                        <i class="fas fa-arrow-left"></i> Back to Details
                    </button>
                    <span style="font-size: 0.8rem; font-weight: 700; color: #0d9488; text-transform: uppercase; letter-spacing: 0.05em;">Select Assets to Include</span>
                </div>

            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                        </th>
                        <th>Vendor</th>
                        <th>Site / Code</th>
                        <th>Location</th>
                        <th>Size</th>
                        <th>Media</th>
                        <th>Rate/SQFT</th>
                        <th>Total SQFT</th>
                        <th style="text-align: right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody id="rates_table_body">
                    <?php if ($client_id <= 0): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 3rem; color: #94a3b8;">
                                Please select a client in Step 1 to load printing rates.
                            </td>
                        </tr>
                    <?php elseif (empty($rates)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 3rem; color: #94a3b8;">
                                No Printing rates found in the system for this client. Add rates first in Printing PO module.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rates as $r): ?>
                        <?php
                            $sqft = ($r['width'] && $r['height']) ? floatval($r['width']) * floatval($r['height']) : 0;
                            $total = $sqft * floatval($r['rate_per_sqft']);
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="rate_ids[]" value="<?php echo $r['id']; ?>" class="rate-chk" data-total="<?php echo $total; ?>"
                                       onchange="updateSummary()" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                            </td>
                            <td><strong><?php echo htmlspecialchars($r['vendor_name'], ENT_QUOTES, 'UTF-8', false); ?></strong></td>
                            <td>
                                <?php if ($r['site_id']): ?>
                                    <strong><?php echo htmlspecialchars($r['site_name'], ENT_QUOTES, 'UTF-8', false); ?></strong>
                                    <div style="font-size: 0.7rem; color: #f97316; font-weight: 700;"><?php echo $r['site_code']; ?></div>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Generic Rate</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['site_id']): ?>
                                    <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($r['location'] ?? $r['city'] ?? '-', ENT_QUOTES, 'UTF-8', false); ?></div>
                                    <div style="font-size: 0.7rem; color: #64748b;"><?php echo $r['city']; ?></div>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['width'] && $r['height']): ?>
                                    <strong><?php echo $r['width']; ?>'x<?php echo $r['height']; ?>'</strong>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge" style="background: #f1f5f9; color: #475569;"><?php echo $r['media_type']; ?></span></td>
                            <td><strong style="color: var(--primary);">₹<?php echo number_format($r['rate_per_sqft'], 2); ?></strong></td>
                            <td><?php echo $sqft > 0 ? number_format($sqft) . ' SQFT' : '-'; ?></td>
                            <td style="text-align: right;">
                                <?php if ($total > 0): ?>
                                    <strong style="color: #059669;">₹<?php echo number_format($total, 2); ?></strong>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Summary Bar -->
            <div id="summary-bar" style="display: none; margin-top: 1.5rem; padding: 1rem 1.5rem; background: linear-gradient(135deg, #0d9488, #0f766e); border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(13, 148, 136, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">Selected Sites</span>
                        <div style="font-size: 1.25rem; font-weight: 900;" id="summary-count">0</div>
                    </div>
                    <div style="text-align: center;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">Net Amount</span>
                        <div style="font-size: 1.25rem; font-weight: 900;" id="summary-net">₹0.00</div>
                    </div>
                    <div style="text-align: center;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">GST (18%)</span>
                        <div style="font-size: 1.25rem; font-weight: 900;" id="summary-gst">₹0.00</div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">Grand Total</span>
                        <div style="font-size: 1.5rem; font-weight: 900;" id="summary-grand">₹0.00</div>
                    </div>
                    <button type="submit" class="btn" style="background: white; color: #0d9488; font-weight: 800; padding: 0.75rem 2rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 0.9rem;">
                        <i class="fas fa-file-alt"></i> Generate Client Print
                    </button>
                </div>
            </div>
            </div> <!-- /#step-2 -->
        </form>

</div>

<script>
function toggleAll(el) {
    document.querySelectorAll('.rate-chk').forEach(chk => chk.checked = el.checked);
    updateSummary();
}

function updateSummary() {
    const checked = document.querySelectorAll('.rate-chk:checked');
    const bar = document.getElementById('summary-bar');

    if (checked.length === 0) {
        bar.style.display = 'none';
        return;
    }

    bar.style.display = 'block';
    let net = 0;
    checked.forEach(chk => net += parseFloat(chk.dataset.total) || 0);
    const gst = net * 0.18;
    const grand = net + gst;

    document.getElementById('summary-count').innerText = checked.length;
    document.getElementById('summary-net').innerText = '₹' + net.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summary-gst').innerText = '₹' + gst.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summary-grand').innerText = '₹' + grand.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

document.getElementById('poForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const checked = document.querySelectorAll('.rate-chk:checked');
    if (checked.length === 0) {
        Swal.fire('Error', 'Please select at least one site/rate to generate an Invoice.', 'error');
        return;
    }

    const formData = new FormData(this);
    
    // FormData handles checked inputs naturally if they have name="rate_ids[]"
    // Just ensure they are included based on current selection
    formData.delete('rate_ids[]');
    Array.from(checked).forEach(chk => {
        formData.append('rate_ids[]', chk.value);
    });

    fetch('../../ajax/save_client_printing_invoice.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'print_client_printing.php?invoice_id=' + data.invoice_id;
        } else {
            Swal.fire('Error', data.message || 'Failed to save Invoice', 'error');
        }
    })
    .catch(err => Swal.fire('Error', 'Network error', 'error'));
});

function initGstSelection(clientId) {
    const gstContainer = document.getElementById('gst_selection_container');
    const gstSelect = document.getElementById('selected_gstin');
    const gstBadge = document.getElementById('gst_count_badge');
    const gstPreview = document.getElementById('gst_preview_text');

    if (!clientId) {
        gstContainer.style.display = 'none';
        return;
    }

    fetch(`../../ajax/get_partner_details.php?id=${clientId}`)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const p = res.data;
            gstSelect.innerHTML = '';
            
            let gsts = [];
            if (p.gstin) {
                gsts.push({ gstin: p.gstin, state: 'Primary', city: '', district: '', address: 'Main Address' });
            }

            if (p.additional_gst) {
                try {
                    const extra = JSON.parse(p.additional_gst);
                    if (Array.isArray(extra)) {
                        gsts = gsts.concat(extra);
                    } else if (typeof extra === 'object') {
                        gsts = gsts.concat(Object.values(extra));
                    }
                } catch(e) { console.error("GST Parse Error", e); }
            }

            if (gsts.length > 0) {
                gstContainer.style.display = 'block';
                gstBadge.innerText = `${gsts.length} GST Records Found`;
                
                gsts.forEach((g, idx) => {
                    const opt = document.createElement('option');
                    opt.value = g.gstin;
                    opt.text = `${g.gstin} - ${g.state || ''} ${g.city ? '(' + g.city + ')' : ''}`;
                    opt.dataset.details = JSON.stringify(g);
                    gstSelect.add(opt);
                });
                
                handleGstSelectionChange();
            } else {
                gstContainer.style.display = 'none';
            }
        } else {
            gstContainer.style.display = 'none';
        }
    });
}

function handleGstSelectionChange() {
    const select = document.getElementById('selected_gstin');
    const preview = document.getElementById('gst_preview_text');
    
    if (select.selectedIndex === -1) return;
    
    const data = JSON.parse(select.options[select.selectedIndex].dataset.details);
    let text = "";
    if (data.address) text += data.address;
    if (data.city) text += (text ? ", " : "") + data.city;
    if (data.state) text += (text ? ", " : "") + data.state;
    
    preview.innerText = text || "No specific location details";
}

function goToStep1() {
    document.getElementById('step-2').style.display = 'none';
    document.getElementById('step-1').style.display = 'block';
    document.getElementById('wizard-progress-line').style.width = '0%';
    
    const stepCircle2 = document.getElementById('step-circle-2');
    const stepLabel2 = document.getElementById('step-label-2');
    if (stepCircle2) {
        stepCircle2.style.background = '#fff';
        stepCircle2.style.color = '#94a3b8';
        stepCircle2.style.boxShadow = '0 0 0 2px #e2e8f0';
    }
    if (stepLabel2) {
        stepLabel2.style.color = '#94a3b8';
    }
}

function goToStep2() {
    const clientId = document.getElementById('selected_client_id').value;
    if (!clientId || clientId === '0' || clientId === '') {
        Swal.fire('Error', 'Please select a Client first.', 'error');
        return;
    }
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'block';
    document.getElementById('wizard-progress-line').style.width = '100%';
    
    const stepCircle2 = document.getElementById('step-circle-2');
    const stepLabel2 = document.getElementById('step-label-2');
    if (stepCircle2) {
        stepCircle2.style.background = '#0d9488';
        stepCircle2.style.color = 'white';
        stepCircle2.style.boxShadow = '0 0 0 2px #0d9488';
    }
    if (stepLabel2) {
        stepLabel2.style.color = '#0d9488';
    }
}

function handleClientSelectionChange() {
    const clientId = document.getElementById('selected_client_id').value;
    const filterVendorId = document.getElementById('filter_vendor_id')?.value || '';
    
    initGstSelection(clientId);
    fetchPrintingRates(clientId, filterVendorId);
}

function fetchPrintingRates(clientId, filterVendorId) {
    const tbody = document.getElementById('rates_table_body');
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    
    if (!clientId) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 3rem; color: #94a3b8;">
                    Please select a client in Step 1 to load printing rates.
                </td>
            </tr>
        `;
        updateSummary();
        return;
    }
    
    tbody.innerHTML = `
        <tr>
            <td colspan="9" style="text-align: center; padding: 3rem; color: #94a3b8;">
                <i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i> Loading printing rates...
            </td>
        </tr>
    `;
    
    fetch(`../../ajax/fetch_client_printing_rates.php?client_id=${clientId}&filter_vendor_id=${filterVendorId}`)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            renderRatesTable(res.rates);
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 3rem; color: #ef4444;">
                        Error: ${res.message || 'Failed to load rates'}
                    </td>
                </tr>
            `;
        }
        updateSummary();
    })
    .catch(err => {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 3rem; color: #ef4444;">
                    Error loading rates from server.
                </td>
            </tr>
        `;
        updateSummary();
    });
}

function renderRatesTable(rates) {
    const tbody = document.getElementById('rates_table_body');
    if (!rates || rates.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 3rem; color: #94a3b8;">
                    No Printing rates found in the system for this client. Add rates first in Printing PO module.
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    rates.forEach(r => {
        const width = parseFloat(r.width) || 0;
        const height = parseFloat(r.height) || 0;
        const sqft = (width && height) ? width * height : 0;
        const rate = parseFloat(r.rate_per_sqft) || 0;
        const total = sqft * rate;
        
        const vendorName = r.vendor_name || '';
        const siteName = r.site_name || '';
        const siteCode = r.site_code || '';
        const location = r.location || r.city || '-';
        const city = r.city || '';
        const mediaType = r.media_type || '';
        
        const siteCell = r.site_id ? `
            <strong>${escapeHtml(siteName)}</strong>
            <div style="font-size: 0.7rem; color: #f97316; font-weight: 700;">${escapeHtml(siteCode)}</div>
        ` : `
            <span style="color: #94a3b8; font-style: italic;">Generic Rate</span>
        `;
        
        const locCell = r.site_id ? `
            <div style="font-size: 0.85rem;">${escapeHtml(location)}</div>
            <div style="font-size: 0.7rem; color: #64748b;">${escapeHtml(city)}</div>
        ` : `
            <span style="color: #94a3b8;">-</span>
        `;
        
        const sizeCell = (width && height) ? `
            <strong>${width}'x${height}'</strong>
        ` : `
            <span style="color: #94a3b8;">-</span>
        `;
        
        const sqftCell = sqft > 0 ? `${sqft.toLocaleString(undefined, {maximumFractionDigits: 2})} SQFT` : '-';
        const totalCell = total > 0 ? `
            <strong style="color: #059669;">₹${total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
        ` : `
            <span style="color: #94a3b8;">-</span>
        `;
        
        html += `
            <tr>
                <td>
                    <input type="checkbox" name="rate_ids[]" value="${r.id}" class="rate-chk" data-total="${total}"
                           onchange="updateSummary()" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                </td>
                <td><strong>${escapeHtml(vendorName)}</strong></td>
                <td>${siteCell}</td>
                <td>${locCell}</td>
                <td>${sizeCell}</td>
                <td><span class="badge" style="background: #f1f5f9; color: #475569;">${escapeHtml(mediaType)}</span></td>
                <td><strong style="color: var(--primary);">₹${rate.toFixed(2)}</strong></td>
                <td>${sqftCell}</td>
                <td style="text-align: right;">${totalCell}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', function() {
    const clientId = <?php echo json_encode($client_id); ?>;
    if (clientId > 0) {
        initGstSelection(clientId);
        fetchPrintingRates(clientId, <?php echo json_encode($selectedFilterVendorId); ?>);
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
// ============================
// PREVIEW / PRINT MODE
// ============================
else:

if (empty($rates)) die("No rates selected for this PO.");

// Resolve Billing details if selected
$billing_gst = $_GET['billing_gstin'] ?? '';
if (empty($billing_gst) && !empty($rates)) {
    $billing_gst = $rates[0]['billing_gstin'] ?? '';
}

$display_gstin = $c['gstin'] ?: 'N/A';
$display_address = $c['address'] ?? '';
$display_state = $c['state'] ?? '';

if (!empty($billing_gst)) {
    if ($billing_gst === $c['gstin']) {
        $display_gstin = $c['gstin'];
        $display_address = $c['address'];
        $display_state = $c['state'];
    } else {
        if (!empty($c['additional_gst'])) {
            $extra = json_decode($c['additional_gst'], true);
            if (is_array($extra)) {
                foreach ($extra as $item) {
                    if (isset($item['gstin']) && $item['gstin'] === $billing_gst) {
                        $display_gstin = $billing_gst;
                        $display_address = $item['address'] ?? '';
                        if (!empty($item['city'])) {
                            $display_address .= (!empty($display_address) ? ", " : "") . $item['city'];
                        }
                        if (!empty($item['district'])) {
                            $display_address .= (!empty($display_address) ? ", " : "") . $item['district'];
                        }
                        $display_state = $item['state'] ?? '';
                        break;
                    }
                }
            }
        }
    }
}

if ($is_final) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    if (!$isAdmin) {
        $pendingCount = 0;
        foreach ($rates as $r) {
            if (in_array($r['approval_status'] ?? '', ['pending_approval', 'rejected'])) {
                $pendingCount++;
            }
        }
        if ($pendingCount > 0) {
            die("<div style='padding: 50px; text-align: center; font-family: Arial, sans-serif; color: #c2410c; background: #fff; min-height: 100vh; box-sizing: border-box;'>
                    <h2>Awaiting Admin Approval</h2>
                    <p>This Client Printing Invoice requires Admin approval before it can be printed or viewed.</p>
                    <a href='../../modules/partners/client_printing_rates.php' style='color: #0d9488; font-weight: bold; text-decoration: none;'>Back</a>
                 </div>");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $is_final ? 'Tax Invoice' : 'Client Printing Invoice'; ?> - <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8', false); ?></title>
    <style>
        @page {
            size: A4;
            margin: 12mm 14mm;
            @bottom-right {
                content: counter(page) "/" counter(pages);
                font-family: Arial, sans-serif;
                font-size: 9px;
                color: #555;
            }
        }
        .avoid-break {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-top: auto;
        }
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; color: #000; font-size: 11px; line-height: 1.3; background: #f1f5f9; }
        .po-wrapper {
            border: 1px solid #d1d5db;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            position: relative;
            background: #fff;
            padding: 12mm 14mm;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .header-top { border-bottom: 1px solid #000; padding: 5px 10px; }
        .header-top p { margin: 0; }

        .main-info { display: flex; border-bottom: 1px solid #000; }
        .info-col { flex: 1; padding: 10px; }
        .info-col:first-child { border-right: 1px solid #000; }

        .info-row { display: flex; margin-bottom: 3px; }
        .info-label { width: 90px; font-weight: normal; }
        .info-sep { width: 15px; }
        .info-value { flex: 1; font-weight: normal; }

        .section-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; font-style: italic; }
        .table-title { background: #f0f0f0; border-bottom: 1px solid #000; text-align: center; font-weight: bold; padding: 4px; letter-spacing: 2px; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 6px; text-align: center; font-weight: bold; background: #fafafa; }
        th:last-child { border-right: none; }
        td { border-bottom: 1px solid #d0d0d0; border-right: 1px solid #000; padding: 8px 5px; vertical-align: top; text-align: center; }
        td:last-child { border-right: none; }

        .totals-row td { border-bottom: none; border-top: 1px solid #000; font-weight: bold; }
        .footer { display: flex; border-top: 1px solid #000; }
        .footer-left { flex: 2; padding: 10px; border-right: 1px solid #000; min-height: 120px; }
        .footer-right { flex: 1; padding: 10px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; }

        .btn-print { position: fixed; bottom: 30px; right: 30px; background: #000; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .btn-back { position: fixed; bottom: 30px; right: 180px; background: #6366f1; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; text-decoration: none; font-size: 12px; }
        @media print {
            .btn-print, .btn-back { display: none; }
            body { padding: 0; background: #fff; }
            .po-wrapper {
                border: none;
                width: 100%;
                min-height: 273mm;
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()"><?php echo $is_final ? 'PRINT TAX INVOICE' : 'PRINT CLIENT PO'; ?></button>
<a class="btn-back" href="client_printing.php?client_id=<?php echo $client_id; ?>&filter_vendor_id=<?php echo $selectedFilterVendorId; ?>">← BACK TO SELECTION</a>

<div class="po-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 10px 2px;">
        <div style="flex: 1.4; text-align: left;">
            <?php if ($company_letterhead): ?>
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_letterhead; ?>" style="max-height: 80px; width: auto; display: block; margin-bottom: 5px;">
            <?php else: ?>
                <h2 style="margin: 0; text-transform: uppercase; font-size: 18px; color: #8B1A1A;"><?php echo htmlspecialchars($company_name); ?></h2>
            <?php endif; ?>
        </div>
        <div style="flex: 1.2; text-align: center; padding-top: 15px;">
            <div style="font-size: 15px; font-weight: bold; text-decoration: underline; letter-spacing: 1.5px; text-transform: uppercase;">
                <?php echo $is_final ? 'TAX INVOICE' : 'CLIENT PRINTING ORDER'; ?>
            </div>
        </div>
        <div style="flex: 0.8; text-align: right; font-style: italic; font-size: 10px; padding-top: 15px; color: #555;">
            <?php echo $is_final ? 'Original Copy' : ''; ?>
        </div>
    </div>
    <div style="padding: 0 10px 10px; font-size: 10px; line-height: 1.4; color: #000; border-bottom: 1px solid #000; margin-bottom: 10px;">
        <?php echo nl2br(htmlspecialchars($company_address)); ?><br>
        Ph : <?php echo htmlspecialchars($company_phone); ?> &nbsp;|&nbsp; Email : <?php echo htmlspecialchars($company_email); ?>
    </div>



    <!-- PO Info -->
    <div class="main-info">
        <div class="info-col">
            <div style="margin-bottom: 15px;">
                 <!-- Removed PO Ref as per user request -->
                <?php if (!empty($rates[0]['customer_po_no'])): ?>
                <div class="info-row">
                    <span class="info-label">Client PO</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($rates[0]['customer_po_no']); ?></span>
                </div>
                <?php if (!empty($rates[0]['customer_po_date']) && $rates[0]['customer_po_date'] !== '0000-00-00'): ?>
                <div class="info-row">
                    <span class="info-label">PO Date</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo date('d-m-Y', strtotime($rates[0]['customer_po_date'])); ?></span>
                </div>
                <?php endif; ?>
                <?php elseif (!empty($rates[0]['email_date']) && $rates[0]['email_date'] !== '0000-00-00'): ?>
                <div class="info-row">
                    <span class="info-label">Email Date</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo date('d-m-Y', strtotime($rates[0]['email_date'])); ?></span>
                </div>
                <?php endif; ?>

                <?php
                $camp_brand = [];
                if (!empty($rates[0]['campaign_name'])) $camp_brand[] = trim($rates[0]['campaign_name']);
                if (!empty($rates[0]['brand_name'])) $camp_brand[] = trim($rates[0]['brand_name']);
                $display_camp_brand = implode(' / ', $camp_brand);
                if (!empty($display_camp_brand)): ?>
                <div class="info-row">
                    <span class="info-label">Campaign / Brand</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo htmlspecialchars($display_camp_brand); ?></strong></span>
                </div>
                <?php endif; ?>

                <div class="section-title" style="margin-top: 10px;">Client / Customer:</div>
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 2px;"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8', false); ?></div>
                <div style="width: 250px;"><?php echo htmlspecialchars($display_address, ENT_QUOTES, 'UTF-8', false); ?></div>
                
                <div class="info-row" style="margin-top: 5px;">
                    <span class="info-label">GSTIN / UIN</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo htmlspecialchars($display_gstin); ?></strong></span>
                </div>
                <?php if (!empty($display_state)): ?>
                <div class="info-row">
                    <span class="info-label">State / Code</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($display_state); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Contact Person</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($c['contact_person'], ENT_QUOTES, 'UTF-8', false); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($c['phone'], ENT_QUOTES, 'UTF-8', false); ?></span>
                </div>
            </div>
        </div>

        <div class="info-col">
            <div class="info-row">
                <span class="info-label"><?php echo $is_final ? 'Invoice Number' : 'PO Number'; ?></span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo $po_number; ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label"><?php echo $is_final ? 'Invoice Date' : 'PO Date'; ?></span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $po_date; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Type</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo $is_final ? 'TAX INVOICE (FINAL)' : 'CLIENT PRINTING ORDER'; ?></strong></span>
            </div>
            <?php if ($po_remark): ?>
            <div class="info-row">
                <span class="info-label">Remark</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($po_remark); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row" style="margin-top: 5px;">
                <span class="info-label">PAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_pan; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">GSTIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_gstin; ?></span>
            </div>
            <?php if (!empty($company_cin)): ?>
            <div class="info-row">
                <span class="info-label">CIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_cin); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($company_tan)): ?>
            <div class="info-row">
                <span class="info-label">TAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_tan); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-title"><?php echo $is_final ? 'Tax Invoice Details' : 'Printing Order Details'; ?>:</div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">S.N.</th>
                <th>SITE / LOCATION</th>
                <th style="width: 70px;">HSN/SAC<br>Code</th>
                <th style="width: 70px;">SIZE</th>
                <th style="width: 70px;">SQFT</th>
                <th style="width: 70px;">MEDIA</th>
                <th style="width: 70px;">Rate/SQFT</th>
                <th style="width: 90px;">Total Cost(₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $net_total = 0;
            foreach ($rates as $idx => $item):
                $sqft = ($item['width'] && $item['height']) ? floatval($item['width']) * floatval($item['height']) : 0;
                $item_total = $sqft * floatval($item['rate_per_sqft']);
                $net_total += $item_total;
            ?>
            <tr>
                <td><?php echo $idx + 1; ?></td>
                <td style="text-align: left; padding-left: 10px;">
                    <?php if ($item['site_id']): ?>
                        <div style="font-weight: bold;"><?php echo $item['site_name']; ?></div>
                        <div style="font-size: 9px; color: #555;"><?php echo $item['site_code']; ?> • <?php echo $item['city'] ?? ''; ?></div>
                    <?php else: ?>
                        <div style="font-weight: bold;">Generic Printing</div>
                        <div style="font-size: 9px; color: #555;"><?php echo $item['media_type']; ?></div>
                    <?php endif; ?>
                </td>
                <td><?php echo $item['hsn_code'] ?: ''; ?></td>
                <td><?php echo ($item['width'] && $item['height']) ? $item['width'] . "'x" . $item['height'] . "'" : '-'; ?></td>
                <td><?php echo $sqft > 0 ? number_format($sqft) : '-'; ?></td>
                <td style="font-size: 9px;"><?php echo $item['media_type']; ?></td>
                <td>₹<?php echo number_format($item['rate_per_sqft'], 2); ?></td>
                <td style="text-align: right; padding-right: 10px; font-weight: bold;"><?php echo number_format($item_total, 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <?php
            $is_interstate = (strcasecmp($display_state, 'West Bengal') !== 0 && substr($display_gstin, 0, 2) !== '19');
            $cgst_pct = $is_interstate ? 0 : 9;
            $sgst_pct = $is_interstate ? 0 : 9;
            $igst_pct = $is_interstate ? 18 : 0;

            $cgst_amount = $is_interstate ? 0 : $net_total * 0.09;
            $sgst_amount = $is_interstate ? 0 : $net_total * 0.09;
            $igst_amount = $is_interstate ? $net_total * 0.18 : 0;
            $grand_total = $net_total + $cgst_amount + $sgst_amount + $igst_amount;
            ?>

            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">Taxable Amount (Total Cost)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($net_total, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">CGST (<?php echo $cgst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($cgst_amount, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">SGST (<?php echo $sgst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($sgst_amount, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">IGST (<?php echo $igst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($igst_amount, 2); ?></td>
            </tr>
            <tr class="totals-row" style="background: #f9f9f9; border-top: 2px solid #000;">
                <td colspan="7" style="text-align: right; padding-right: 10px; font-size: 12px; height: 30px; vertical-align: middle;">Gross Payable Amount</td>
                <td style="text-align: right; padding-right: 10px; font-size: 12px; vertical-align: middle;"><?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- HSN/SAC Breakdown Table -->
    <?php
    $hsnGroups = [];
    foreach ($rates as $item) {
        $hsn = $item['hsn_code'] ?: '998366';
        $sqft = ($item['width'] && $item['height']) ? floatval($item['width']) * floatval($item['height']) : 0;
        $amount = $sqft * floatval($item['rate_per_sqft']);
        $hsnGroups[$hsn] = ($hsnGroups[$hsn] ?? 0) + $amount;
    }
    ?>
    <table style="border-top: 1.5px solid #000; border-bottom: 1px solid #000; margin-top: 6px; margin-bottom: 10px;">
        <thead>
            <tr style="background: #f2f2f2;">
                <th style="text-align: left; padding-left: 8px; width: 90px; border-top: none;">HSN/SAC</th>
                <th style="width: 70px; border-top: none;">Tax Rate</th>
                <th style="text-align: right; padding-right: 8px; border-top: none;">Taxable Amt.</th>
                <th style="text-align: right; padding-right: 8px; border-top: none;">CGST Amt.</th>
                <th style="text-align: right; padding-right: 8px; border-top: none;">SGST Amt.</th>
                <th style="text-align: right; padding-right: 8px; border-top: none; border-right: none;">IGST Amt.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($hsnGroups as $hsn => $taxableAmt): 
                $hsnCgst = $is_interstate ? 0 : $taxableAmt * 0.09;
                $hsnSgst = $is_interstate ? 0 : $taxableAmt * 0.09;
                $hsnIgst = $is_interstate ? $taxableAmt * 0.18 : 0;
            ?>
            <tr>
                <td style="text-align: left; padding-left: 8px; border-left: none;"><?php echo htmlspecialchars($hsn); ?></td>
                <td style="text-align: center;">18%</td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($taxableAmt, 2); ?></td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($hsnCgst, 2); ?></td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($hsnSgst, 2); ?></td>
                <td style="text-align: right; padding-right: 8px; border-right: none;"><?php echo number_format($hsnIgst, 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="padding: 10px; border-top: 1px solid #000;">
        <strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($grand_total); ?> Only</span>
    </div>

    <div style="padding: 10px; border-top: 1px solid #000; font-size: 9px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
            <div style="font-weight: bold; text-decoration: underline; font-size: 10px;">Terms & Conditions</div>
            <div style="font-weight: bold; color: #cc0000; font-size: 11px;"><?php echo getSetting('po_important_note', 'Filing of GSTR-1 within time is mandatory for acceptance of Invoice.'); ?></div>
        </div>
        <div style="margin: 0; line-height: 1.2; white-space: pre-wrap;">
            <?php echo nl2br(htmlspecialchars($company_terms)); ?>
        </div>
    </div>

    <div class="footer">
        <div class="footer-left">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 5px;">Payment Terms:</div>
            <p style="margin: 2px 0;">- 100% after printing delivery with proofs</p>
            <p style="margin: 2px 0;">- Cheque/NEFT in favor of: <strong><?php echo $company_name; ?></strong></p>
        </div>
        <div class="footer-right">
            <div>For <strong><?php echo $company_name; ?></strong></div>
            <div style="margin-top: 30px;">
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_signature; ?>" style="height: 40px; display: block; margin: 0 auto;" onerror="this.style.display='none'">
                <div style="border-top: 1px solid #000; width: 150px; margin: 5px auto 0;"></div>
                <div style="font-weight: bold; margin-top: 2px;">Authorised Signatory</div>
            </div>
        </div>
    </div>
</div>

<div style="max-width: 800px; margin: 10px auto; text-align: center; font-size: 9px; color: #888;">
    This is a computer generated Client Printing Invoice and does not require physical signature.
</div>

</body>
</html>
<?php endif; ?>
