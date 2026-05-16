<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $vendor_id = intval($_POST['vendor_id']);
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);

        if ($_POST['action'] === 'add') {
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [null];
            $individual_rates = $_POST['individual_rates'] ?? [];
            $po_number = "PPO-" . date('ymd') . "-" . rand(100, 999);
            
            foreach ($site_ids as $site_id) {
                $site_id = !empty($site_id) ? intval($site_id) : null;
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                // Insert new rate with PO number to preserve historical PO data
                $insertStmt = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft, po_number) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->execute([$vendor_id, $site_id, $media_type, $this_rate, $po_number]);
            }
            header("Location: printing_rates.php?msg=added"); exit;
        } else {
            $id = intval($_POST['id']);
            $site_id = !empty($_POST['site_id']) ? intval($_POST['site_id']) : null;
            $stmt = $pdo->prepare("UPDATE vendor_printing_rates SET vendor_id=?, site_id=?, media_type=?, rate_per_sqft=? WHERE id=?");
            $stmt->execute([$vendor_id, $site_id, $media_type, $rate, $id]);
            header("Location: printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM vendor_printing_rates WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'printing_rates';
$pageTitle = 'Printing PO';
include_once __DIR__ . '/../../includes/header.php';

$selectedVendorId = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$queryWhere = $selectedVendorId ? "WHERE r.vendor_id = $selectedVendorId" : "";

// Fetch Rates Grouped by PO Number
$rates = $pdo->query("
    SELECT 
        r.po_number,
        r.vendor_id,
        v.name as vendor_name,
        GROUP_CONCAT(r.id SEPARATOR '||') as rate_ids,
        GROUP_CONCAT(COALESCE(s.name, 'Generic') SEPARATOR '||') as site_names,
        GROUP_CONCAT(COALESCE(s.site_code, '-') SEPARATOR '||') as site_codes,
        GROUP_CONCAT(COALESCE(s.width, 0) SEPARATOR '||') as widths,
        GROUP_CONCAT(COALESCE(s.height, 0) SEPARATOR '||') as heights,
        GROUP_CONCAT(r.media_type SEPARATOR '||') as media_types,
        GROUP_CONCAT(r.rate_per_sqft SEPARATOR '||') as rates
    FROM vendor_printing_rates r
    JOIN partners v ON r.vendor_id = v.id
    LEFT JOIN sites s ON r.site_id = s.id
    $queryWhere
    GROUP BY r.po_number, r.vendor_id, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY r.id DESC
")->fetchAll();

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$sites = $pdo->query("SELECT id, name, site_code, width, height, vendor_id FROM sites ORDER BY site_code ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Printing PO</h2>
        <div style="display: flex; gap: 0.75rem;">
            <button class="btn" id="bulkPOBtn" onclick="generateBulkPO()" style="display: none; background: #0f172a; color: white;">
                <i class="fas fa-file-pdf"></i> Generate Group PO (<span id="bulkCount">0</span>)
            </button>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Add New Printing PO 
            </button>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 30px; text-align: center;">
                    <input type="checkbox" id="selectAllList" onchange="toggleAllList(this)" style="width: 16px; height: 16px; cursor: pointer;">
                </th>
                <th style="width: 40px;"></th>
                <th>Vendor</th>
                <th>Site / Dimension</th>
                <th>Media Type</th>
                <th>Rate (per SQFT)</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rates as $r): ?>
            <?php 
                $ids = explode('||', $r['rate_ids']);
                $sNames = explode('||', $r['site_names']);
                $sCodes = explode('||', $r['site_codes']);
                $widths = explode('||', $r['widths']);
                $heights = explode('||', $r['heights']);
                $mediaTypes = explode('||', $r['media_types']);
                $unitRates = explode('||', $r['rates']);
                
                $totalGroupSqft = 0;
                $totalGroupAmount = 0;
                foreach($ids as $i => $id) {
                    $sqft = floatval($widths[$i]) * floatval($heights[$i]);
                    $totalGroupSqft += $sqft;
                    $totalGroupAmount += ($sqft * floatval($unitRates[$i]));
                }
            ?>
            <tr class="rate-row" data-vendor-id="<?php echo $r['vendor_id']; ?>">
                <td style="text-align: center;">
                    <input type="checkbox" class="list-chk" value="<?php echo $r['rate_ids']; ?>" onchange="updateBulkUI()" style="width: 16px; height: 16px; cursor: pointer;">
                </td>
                <td style="text-align: center;">
                    <?php 
                        $pdfUrl = "../operations/generate_printing_po.php?vendor_id=" . $r['vendor_id'] . "&preview=1";
                        foreach($ids as $id) $pdfUrl .= "&rate_ids[]=" . $id;
                    ?>
                    <a href="<?php echo $pdfUrl; ?>" target="_blank" title="Download Group PO" style="color: #ef4444; font-size: 1.1rem;">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($r['vendor_name']); ?></strong>
                    <?php if($r['po_number']): ?>
                        <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 2px;">#<?php echo $r['po_number']; ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php foreach($ids as $i => $id): ?>
                        <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                            <div style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($sNames[$i]); ?></div>
                            <small style="color: #64748b;"><?php echo $sCodes[$i]; ?> (<?php echo $widths[$i]; ?>x<?php echo $heights[$i]; ?> = <strong><?php echo floatval($widths[$i]) * floatval($heights[$i]); ?> SQFT</strong>)</small>
                        </div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach($ids as $i => $id): ?>
                        <div style="height: 38px; display: flex; align-items: center;">
                            <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.7rem;"><?php echo htmlspecialchars($mediaTypes[$i]); ?></span>
                        </div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach($ids as $i => $id): ?>
                        <div style="height: 38px; display: flex; flex-direction: column; justify-content: center;">
                            <strong style="color: var(--primary);">₹<?php echo number_format(floatval($unitRates[$i]), 2); ?></strong>
                            <div style="font-size: 0.65rem; color: #059669; font-weight: 700;">₹<?php echo number_format(floatval($unitRates[$i]) * floatval($widths[$i]) * floatval($heights[$i]), 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(count($ids) > 1): ?>
                        <div style="margin-top: 10px; padding-top: 5px; border-top: 2px solid #e2e8f0;">
                            <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Total Amount</div>
                            <strong style="color: #0f172a; font-size: 0.9rem;">₹<?php echo number_format($totalGroupAmount, 2); ?></strong>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-end;">
                        <?php foreach($ids as $i => $id): ?>
                            <?php 
                                // Create a pseudo-row object for the edit function
                                $rowObj = [
                                    'id' => $id,
                                    'vendor_id' => $r['vendor_id'],
                                    'site_id' => ($sCodes[$i] !== '-' ? 'exists' : null), // simplified for modal
                                    'media_type' => $mediaTypes[$i],
                                    'rate_per_sqft' => $unitRates[$i]
                                ];
                                // Need actual site_id for the modal to work perfectly, but this is a start
                            ?>
                            <div style="height: 38px; display: flex; align-items: center; gap: 5px;">
                                <button class="btn-icon btn-delete" onclick="deleteRate(<?php echo $id; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rates)): ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8;">No Printing POs found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="rateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Create Printing PO</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="rateForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="rateId">
            
            <div class="modal-body-scroll">
                <div style="display: grid; grid-template-columns: 320px 1fr; gap: 2.5rem;">
                    <div class="left-col" style="background: #f8fafc; padding: 2rem; border-right: 1px solid #e2e8f0;">
                        <div class="form-group">
                            <label>1. Select Vendor</label>
                            <select name="vendor_id" id="f_vendor" required style="background: #fff; border-width: 2px;">
                                <option value="">Choose Printing Partner...</option>
                                <?php foreach ($vendors as $v): ?>
                                    <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-top: 2rem;">
                            <label>2. Media Type</label>
                            <div class="media-pills">
                                <input type="hidden" name="media_type" id="f_media" value="Flex">
                                <div class="media-pill active" onclick="setMedia('Flex')">Flex</div>
                                <div class="media-pill" onclick="setMedia('Vinyl')">Vinyl</div>
                                <div class="media-pill" onclick="setMedia('Star Flex')">Star Flex</div>
                                <div class="media-pill" onclick="setMedia('Backlit Flex')">Backlit</div>
                                <div class="media-pill" onclick="setMedia('One Way Vision')">OWV</div>
                                <div class="media-pill" onclick="setMedia('Canvas')">Canvas</div>
                            </div>
                        </div>

                        <div class="form-group" id="master_rate_group" style="margin-top: 2rem;">
                            <label id="rate_label">3. Set Global Rate (₹)</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-weight: 800; color: #94a3b8;">₹</span>
                                <input type="number" step="0.01" name="rate_per_sqft" id="f_rate" min="0" placeholder="0.00" style="padding-left: 35px; font-size: 1.2rem; font-weight: 900; color: #0d9488; border-width: 2px;">
                            </div>
                            <small style="color: #94a3b8; font-size: 0.65rem; margin-top: 6px; display: block;">This rate will be applied to all checked sites unless overridden.</small>
                        </div>

                        <div id="sqft_display" style="display: none;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.75rem;">
                                <span>TOTAL AREA</span>
                                <strong id="sqft_value">0 SQFT</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                                <span>ESTIMATED PAYABLE</span>
                                <strong id="total_price_value">₹0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="right-col">
                        <div class="form-group" id="site_selection_group" style="margin: 0;">
                            <label id="site_label" style="display: flex; justify-content: space-between; align-items: center;">
                                Site Selection
                                <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 500; text-transform: none;">Pick sites to apply rates</span>
                            </label>
                            
                            <!-- Single Select (Used for Edit Mode) -->
                            <select name="site_id" id="f_site" style="display: none;">
                                <option value="">Generic / All Sites</option>
                            </select>

                            <!-- Multi-Site Container (Used for Add Mode) -->
                            <div id="multi_site_container">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                    <label style="display: flex; align-items: center; cursor: pointer; font-size: 0.75rem; font-weight: 800; color: #0d9488; margin: 0; background: #fff; padding: 6px 12px; border-radius: 20px; border: 1.5px solid #0d9488;">
                                        <input type="checkbox" id="selectAllSites" style="width: 16px; height: 16px; margin-right: 8px;"> SELECT ALL
                                    </label>
                                    <div style="position: relative;">
                                        <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 0.85rem; color: #94a3b8;"></i>
                                        <input type="text" id="siteSearch" placeholder="Filter by Site Name or Code..." style="width: 300px; padding: 10px 15px 10px 40px; font-size: 0.85rem; border: 1.5px solid #e2e8f0; border-radius: 30px;">
                                    </div>
                                </div>
                                <div id="site_checkbox_list" class="site-grid">
                                    <div style="grid-column: 1 / -1; color: #94a3b8; font-size: 0.9rem; padding: 4rem; text-align: center; font-style: italic;">Select a vendor to see sites...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal()" style="font-weight: 700; color: #64748b; margin-right: 1.5rem; font-size: 1rem;">Discard Changes</button>
                <button type="submit" class="btn btn-primary">Save Printing PO</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 12px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; color: #475569; }
.form-group select, .form-group input { width: 100%; padding: 0.65rem; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; }
.close { cursor: pointer; float: right; font-size: 1.5rem; }

.media-pills { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.media-pill { 
    padding: 10px 5px; text-align: center; background: #fff; border: 1.5px solid #e2e8f0; 
    border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.2s; color: #64748b;
}
.media-pill:hover { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
.media-pill.active { background: #0d9488; color: #fff; border-color: #0d9488; box-shadow: 0 4px 6px -1px rgba(13, 148, 136, 0.3); }

.modal-content { 
    width: 95vw !important; 
    height: 90vh !important; 
    max-width: 1400px !important; 
    margin: 5vh auto !important;
    border-radius: 20px !important; 
    border: none !important; 
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important; 
    padding: 0 !important; 
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.modal-header { background: #fff; color: #0f172a; padding: 1.5rem 2rem; border-bottom: 1px solid #f1f5f9; flex-shrink: 0; }
.modal-header h2 { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.04em; margin: 0; color: #0f172a; }
.modal-header .close { color: #94a3b8; opacity: 1; transition: color 0.2s; font-size: 2rem; line-height: 1; }
.modal-header .close:hover { color: #ef4444; }

#rateForm { 
    flex: 1; 
    display: flex; 
    flex-direction: column; 
    overflow: hidden; 
    background: #fff; 
}

.modal-body-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.form-group label { font-weight: 800; color: #1e293b; font-size: 0.75rem; margin-bottom: 0.75rem; display: block; text-transform: uppercase; letter-spacing: 0.05em; }
.form-group select, .form-group input[type="number"], .form-group input[type="text"] { 
    border-radius: 10px; border: 2px solid #f1f5f9; padding: 0.85rem 1rem; font-size: 1rem; transition: all 0.2s; background: #fff; width: 100%; font-weight: 600;
}
.form-group select:focus, .form-group input:focus { border-color: #0d9488; background: #fff; box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.1); outline: none; }

#multi_site_container { background: #fff; border: none; border-radius: 0; padding: 0; }
.site-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
    padding: 2rem;
}
.site-item { 
    display: flex; align-items: center; padding: 15px; border-radius: 12px; cursor: pointer; 
    border: 2px solid #f1f5f9; transition: all 0.2s; background: #fff; position: relative;
}
.site-item:hover { border-color: #0d9488; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); transform: translateY(-2px); }
.site-item input[type="checkbox"] { width: 22px !important; height: 22px !important; accent-color: #0d9488; margin: 0; }
.site-item .site-info { flex: 1; min-width: 0; padding: 0 15px; }
.site-item .site-info label { margin: 0; color: #0f172a; font-size: 0.95rem; font-weight: 800; text-transform: none; letter-spacing: normal; cursor: pointer; line-height: 1.3; }
.site-item .site-info small { color: #64748b; font-size: 0.8rem; display: block; margin-top: 5px; font-weight: 500; }
.site-item .rate-input-wrap { width: 110px; }
.site-item .rate-input-wrap input { 
    padding: 10px; font-size: 1rem; border-radius: 8px; border: 2px solid #f1f5f9; 
    text-align: right; font-weight: 900; color: #0d9488; background: #f8fafc;
}
.site-item .rate-input-wrap input:focus { background: #fff; border-color: #0d9488; }

#sqft_display { 
    background: #0f172a; padding: 1.75rem; 
    border-radius: 16px; margin-top: 2.5rem; border: none; color: white; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
}
#sqft_display span { color: #94a3b8; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; }
#sqft_display strong { color: #fff; font-size: 1.4rem; font-weight: 800; }
#sqft_display #total_price_value { color: #2dd4bf; font-size: 2rem; font-weight: 900; }

.modal-footer {
    background: #fff; padding: 1.5rem 2.5rem; border-top: 1px solid #f1f5f9; text-align: right; flex-shrink: 0;
}
.btn-primary { background: #0d9488; color: white; border: none; padding: 1rem 3rem; border-radius: 12px; font-weight: 800; font-size: 1.1rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2); }
.btn-primary:hover { background: #0f766e; transform: translateY(-2px); box-shadow: 0 12px 20px -5px rgba(13, 148, 136, 0.4); }
</style>

<script>
const sitesData = <?php echo json_encode($sites); ?>;
const vendorsData = <?php echo json_encode($vendors); ?>;

document.getElementById('f_vendor').addEventListener('change', filterSitesByVendor);
document.getElementById('f_site').addEventListener('change', calculateTotal);
document.getElementById('f_rate').addEventListener('input', function() {
    const val = this.value;
    document.querySelectorAll('.site-chk-input:checked').forEach(chk => {
        const rateInput = chk.closest('.site-item').querySelector('.site-individual-rate');
        if (rateInput.dataset.touched !== 'true') {
            rateInput.value = val;
        }
    });
    calculateTotal();
});
document.getElementById('selectAllSites').addEventListener('change', function() {
    const masterRate = document.getElementById('f_rate').value;
    document.querySelectorAll('.site-chk-input').forEach(chk => {
        if (chk.parentElement.style.display !== 'none') {
            chk.checked = this.checked;
            const rateInput = chk.closest('.site-item').querySelector('.site-individual-rate');
            if (this.checked && masterRate && rateInput.dataset.touched !== 'true') {
                rateInput.value = masterRate;
            }
        }
    });
    calculateTotal();
});
document.getElementById('siteSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.site-item').forEach(item => {
        const text = item.innerText.toLowerCase();
        item.style.display = text.includes(q) ? 'flex' : 'none';
    });
});

function setMedia(val) {
    document.getElementById('f_media').value = val;
    document.querySelectorAll('.media-pill').forEach(p => {
        p.classList.toggle('active', p.innerText === val || (val === 'Backlit Flex' && p.innerText === 'Backlit') || (val === 'One Way Vision' && p.innerText === 'OWV'));
    });
}

function filterSitesByVendor() {
    const vendorId = document.getElementById('f_vendor').value;
    const siteSelect = document.getElementById('f_site');
    const siteList = document.getElementById('site_checkbox_list');
    const isEdit = document.getElementById('formAction').value === 'edit';
    
    // Clear current UI
    siteSelect.innerHTML = '<option value="">Generic / All Sites</option>';
    siteList.innerHTML = '';
    
    // Always show all sites in the checkbox list for 'Add' mode
    // but filter for the dropdown in 'Edit' mode if needed (or keep it all too)
    const displaySites = isEdit && vendorId ? sitesData.filter(s => s.vendor_id == vendorId) : sitesData;

    if (displaySites.length === 0) {
        siteList.innerHTML = '<div style="color: #94a3b8; font-size: 0.8rem; padding: 10px; text-align: center;">No sites found in system.</div>';
    }

    displaySites.forEach(s => {
        // Find vendor name for this site to show in list
        const siteVendor = vendorsData.find(v => v.id == s.vendor_id);
        const vName = siteVendor ? siteVendor.name : 'Unknown Vendor';

        // Add to dropdown (for Edit)
        const option = document.createElement('option');
        option.value = s.id;
        option.text = `${s.site_code} - ${s.name} (${s.width}x${s.height})`;
        siteSelect.add(option);

        // Add to Checkbox List (for Add)
        const item = document.createElement('div');
        item.className = 'site-item';
        // Highlight sites that belong to the currently selected vendor
        const isSelectedVendor = (vendorId && s.vendor_id == vendorId);
        if (isSelectedVendor) item.style.background = '#f0fdfa';

        item.innerHTML = `
            <input type="checkbox" name="site_ids[]" value="${s.id}" class="site-chk-input" data-sqft="${parseFloat(s.width) * parseFloat(s.height)}">
            <div class="site-info">
                <label>${s.site_code} - ${s.name} ${isSelectedVendor ? '<span style="color:#0d9488; font-size:0.55rem; background:#ccfbf1; padding:2px 6px; border-radius:10px; margin-left:4px;">OWN</span>' : ''}</label>
                <small>${s.width}x${s.height} = <strong>${parseFloat(s.width) * parseFloat(s.height)} SQFT</strong> • ${vName}</small>
            </div>
            <div class="rate-input-wrap">
                <input type="number" step="0.01" name="individual_rates[${s.id}]" class="site-individual-rate" placeholder="₹" data-touched="false">
            </div>
        `;
        
        const chk = item.querySelector('.site-chk-input');
        const rateInput = item.querySelector('.site-individual-rate');
        
        chk.onchange = function() {
            const masterRate = document.getElementById('f_rate').value;
            if (this.checked) {
                item.classList.add('selected');
                if (masterRate && rateInput.dataset.touched !== 'true') {
                    rateInput.value = masterRate;
                }
            } else {
                item.classList.remove('selected');
            }
            calculateTotal();
        };

        rateInput.oninput = function() {
            this.dataset.touched = 'true';
            if (this.value !== '') chk.checked = true;
            calculateTotal();
        };

        siteList.appendChild(item);
    });
    calculateTotal();
}

function calculateTotal() {
    const rate = parseFloat(document.getElementById('f_rate').value) || 0;
    const isEdit = document.getElementById('formAction').value === 'edit';
    let totalSqft = 0;
    let netAmount = 0;
    
    if (isEdit) {
        const siteId = document.getElementById('f_site').value;
        if (siteId) {
            const site = sitesData.find(s => s.id == siteId);
            if (site && site.width && site.height) {
                totalSqft = parseFloat(site.width) * parseFloat(site.height);
                netAmount = totalSqft * rate;
            }
        } else {
            // Generic rate (no site selected)
            totalSqft = 0;
            netAmount = 0; // Or some other logic if needed, but usually 0 for generic without site
        }
    } else {
        document.querySelectorAll('.site-chk-input:checked').forEach(chk => {
            const sqft = parseFloat(chk.dataset.sqft) || 0;
            const indRate = parseFloat(chk.closest('.site-item').querySelector('.site-individual-rate').value) || rate;
            totalSqft += sqft;
            netAmount += (sqft * indRate);
        });
    }
    
    if (totalSqft > 0) {
        document.getElementById('sqft_value').innerText = totalSqft.toLocaleString() + ' SQFT';
        document.getElementById('total_price_value').innerText = '₹' + netAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('sqft_display').style.display = 'block';
    } else {
        document.getElementById('sqft_display').style.display = 'none';
    }
}

function openModal() { 
    document.getElementById('rateForm').reset(); 
    document.getElementById('formAction').value = 'add'; 
    document.getElementById('rateId').value = '';
    
    // Reset individual rates and touches
    document.querySelectorAll('.site-individual-rate').forEach(input => {
        input.dataset.touched = 'false';
        input.value = '';
    });
    
    // UI for Add Mode
    document.getElementById('f_site').style.display = 'none';
    document.getElementById('multi_site_container').style.display = 'block';
    document.getElementById('site_label').innerText = 'Select Sites & Set Rates';
    document.getElementById('rate_label').innerText = 'Global Rate (Auto-fill)';
    document.getElementById('f_rate').placeholder = 'Enter rate to fill all selected sites';
    
    // Auto-select vendor if passed in URL
    const urlParams = new URLSearchParams(window.location.search);
    const vId = urlParams.get('vendor_id');
    if (vId) {
        document.getElementById('f_vendor').value = vId;
        filterSitesByVendor();
    }
    
    calculateTotal();
    document.getElementById('rateModal').style.display = 'block'; 
}

function toggleAllList(master) {
    document.querySelectorAll('.list-chk').forEach(chk => chk.checked = master.checked);
    updateBulkUI();
}

function updateBulkUI() {
    const checked = document.querySelectorAll('.list-chk:checked');
    const btn = document.getElementById('bulkPOBtn');
    const count = document.getElementById('bulkCount');
    
    if (checked.length > 0) {
        btn.style.display = 'inline-flex';
        btn.style.alignItems = 'center';
        count.innerText = checked.length;
    } else {
        btn.style.display = 'none';
    }
}

function generateBulkPO() {
    const checked = document.querySelectorAll('.list-chk:checked');
    if (checked.length === 0) return;

    // Check if multiple vendors are selected
    const vendorIds = new Set();
    checked.forEach(chk => {
        const row = chk.closest('tr');
        vendorIds.add(row.dataset.vendorId);
    });

    if (vendorIds.size > 1) {
        Swal.fire('Error', 'Please select sites from a single vendor to generate one PO.', 'error');
        return;
    }

    const vendorId = Array.from(vendorIds)[0];
    let url = `../operations/generate_printing_po.php?vendor_id=${vendorId}&preview=1`;
    checked.forEach(chk => {
        url += `&rate_ids[]=${chk.value}`;
    });

    window.open(url, '_blank');
}
function closeModal() { document.getElementById('rateModal').style.display = 'none'; }

// Auto-open modal if vendor_id is present
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('vendor_id')) {
        openModal();
    }
    
    // Show success message if present in URL
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        let title = 'Success';
        let text = '';
        if (msg === 'added') text = 'Printing PO created successfully.';
        if (msg === 'updated') text = 'Printing PO updated successfully.';
        
        if (text) {
            Swal.fire({
                title: title,
                text: text,
                icon: 'success',
                confirmButtonColor: '#0d9488',
                timer: 2500,
                showConfirmButton: false
            });
            // Clean URL without reloading
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});

function editRate(r) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('rateId').value = r.id;
    document.getElementById('f_vendor').value = r.vendor_id;
    
    // UI for Edit Mode
    document.getElementById('f_site').style.display = 'block';
    document.getElementById('multi_site_container').style.display = 'none';
    document.getElementById('site_label').innerText = 'Site (Single)';
    document.getElementById('rate_label').innerText = 'Rate (₹ per SQFT)';
    document.getElementById('f_rate').placeholder = '';
    
    // Trigger vendor change to populate sites for this vendor
    filterSitesByVendor();
    
    document.getElementById('f_site').value = r.site_id || '';
    document.getElementById('f_media').value = r.media_type;
    document.getElementById('f_rate').value = r.rate_per_sqft;
    calculateTotal();
    document.getElementById('rateModal').style.display = 'block';
}

function deleteRate(id) {
    Swal.fire({
        title: 'Delete Rate?',
        text: "Are you sure you want to remove this Printing PO?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('printing_rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Rate has been removed.', 'success').then(() => location.reload());
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
