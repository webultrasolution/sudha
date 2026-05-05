<?php
$activePage = 'proposals';
$pageTitle = 'Create New Proposal';
$hideSidebar = true;
include_once __DIR__ . '/../../includes/header.php';

if (!hasRole(['admin', 'sales'])) {
    echo "<div class='card'>Access Denied. You do not have permission to create proposals.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch Data
$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
$sitesQuery = "
    SELECT 
        s.*, 
        (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail 
    FROM sites s 
    WHERE s.status = 'available' 
    ORDER BY s.site_code ASC";
$sites = $pdo->query($sitesQuery)->fetchAll();
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

        <div class="site-list-container" style="max-height: 600px; overflow-y: auto;">
            <table class="crs-table selection-table" id="asset-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">S.NO</th>
                            <th style="width: 40px;">SELECT</th>
                            <th>MEDIA</th>
                            <th>CITY</th>
                            <th>PHOTOS</th>
                            <th>LOCATION</th>
                            <th>SIZE</th>
                            <th>SQFT</th>
                            <th>MONTHLY / DAILY</th>
                            <th>TYPE</th>
                            <th style="width: 140px;">SALE RATE (₹)</th>
                            <th>MARKUP (%)</th>
                            <th>STATUS</th>
                            <th style="width: 140px;">TOTAL</th>
                        </tr>
                    </thead>
                <tbody id="asset-body">
                    <?php $sno = 1; foreach ($sites as $s): 
                        $sqft = $s['width'] * $s['height'];
                        $dailyRate = $s['card_rate'] / 30;
                    ?>
                    <tr class="site-row" 
                        id="row-<?php echo $s['id']; ?>"
                        data-id="<?php echo $s['id']; ?>" 
                        data-name="<?php echo $s['name']; ?>" 
                        data-rate="<?php echo $s['card_rate']; ?>" 
                        data-prate="<?php echo $s['purchase_rate']; ?>" 
                        data-owner="<?php echo $s['owner_type']; ?>"
                        data-sqft="<?php echo $sqft; ?>">
                        <td class="sno-cell"><?php echo $sno++; ?></td>
                        <td><input type="checkbox" class="asset-chk" onclick="toggleSite('<?php echo $s['id']; ?>')"></td>
                        <td><span class="badge-media"><?php echo strtoupper($s['type']); ?></span></td>
                        <td><strong><?php echo $s['city']; ?></strong></td>
                        <td>
                            <?php if ($s['thumbnail']): ?>
                                <img src="../../uploads/sites/<?php echo $s['thumbnail']; ?>" class="site-thumb" onclick="window.open(this.src)">
                            <?php else: ?>
                                <span class="no-img">No Image</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.8rem; max-width: 200px;"><?php echo $s['location']; ?></td>
                        <td><?php echo $s['width']; ?>' x <?php echo $s['height']; ?>'</td>
                        <td style="font-weight: 700;"><?php echo number_format($sqft); ?></td>
                        <td>
                            <div style="font-size: 0.85rem; font-weight: 700;">₹<?php echo number_format($s['card_rate']); ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8;">₹<?php echo number_format($dailyRate, 2); ?> / day</div>
                        </td>
                        <td><span class="badge-<?php echo strtolower($s['owner_type']); ?>"><?php echo $s['owner_type']; ?></span></td>
                        <td>
                            <input type="number" class="p-input sale-rate-input" 
                                   value="<?php echo $s['card_rate']; ?>" 
                                   oninput="updateSitePrice('<?php echo $s['id']; ?>', this.value)"
                                   disabled>
                        </td>
                        <td class="markup-cell" style="font-weight: 800; color: #64748b;">-</td>
                        <td><span class="status-available">Available</span></td>
                        <td class="total-cell" style="font-weight: 700; color: var(--primary);">₹0</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-wrap">
            <div class="pg-info">
                Showing <span id="pg-start">1</span> to <span id="pg-end">10</span> of <span id="pg-total"><?php echo count($sites); ?></span> sites
            </div>
            <div class="pg-controls" id="pg-numbers">
                <!-- JS will populate -->
            </div>
            <div class="pg-size">
                <select id="pg-limit" onchange="changePageSize()" class="p-input" style="width: 100px; height: 38px; font-size: 0.8rem;">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
            </div>
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
                
                <div style="border-top: 1px dashed #e2e8f0; padding-top: 1rem; margin-top: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--secondary);">TAX TYPE</span>
                        <select id="tax-type" class="p-input" onchange="recalcAll()" style="width: 140px; height: 32px; font-size: 0.75rem; padding: 0 0.5rem; border-radius: 8px;">
                            <option value="igst">IGST (18%)</option>
                            <option value="cgst_sgst">CGST/SGST (9%+9%)</option>
                        </select>
                    </div>
                    <div id="tax-breakdown">
                        <div class="stat-row">
                            <span>GST (18%):</span>
                            <span id="sum-tax-btm">₹0</span>
                        </div>
                    </div>
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
.badge-media { background: #eff6ff; color: #1e40af; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800; border: 1px solid #dbeafe; }
.badge-ha { background: #dcfce7; color: #166534; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; }
.badge-ta { background: #fef9c3; color: #854d0e; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; }

.status-available { background: #ecfdf5; color: #059669; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; border: 1px solid #d1fae5; }
.site-thumb { width: 60px; height: 40px; border-radius: 6px; object-fit: cover; cursor: zoom-in; border: 1px solid #e2e8f0; }
.no-img { font-size: 0.7rem; color: #94a3b8; font-style: italic; }

.sno-cell { font-weight: 800; color: #94a3b8; text-align: center; font-size: 0.85rem; }

/* Pagination Styles */
.pagination-wrap { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2rem; background: #fafafa; border-top: 1px solid #f1f5f9; border-radius: 0 0 20px 20px; }
.pg-info { font-size: 0.85rem; color: #64748b; font-weight: 600; }
.pg-controls { display: flex; gap: 0.4rem; align-items: center; }
.pg-btn { min-width: 38px; height: 38px; border: 1px solid #e2e8f0; background: white; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 0.85rem; transition: all 0.2s; color: #475569; display: flex; align-items: center; justify-content: center; }
.pg-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); background: #f0fdfa; transform: translateY(-1px); }
.pg-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2); }
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; background: #f8fafc; }
.pg-dots { color: #94a3b8; font-weight: 800; padding: 0 0.5rem; }

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
let currentPage = 1;
let pageSize = 10;

function renderPagination() {
    const allRows = Array.from(document.querySelectorAll('#asset-body tr.site-row'));
    const activeRows = allRows.filter(row => !row.classList.contains('search-hidden'));
    
    const total = activeRows.length;
    const start = (currentPage - 1) * pageSize;
    const end = Math.min(start + pageSize, total);
    
    // Hide everything first
    allRows.forEach(row => row.style.display = 'none');

    // Update S.No for all active rows (sequential 1 to N)
    activeRows.forEach((row, index) => {
        const snoCell = row.querySelector('.sno-cell');
        if(snoCell) snoCell.innerText = index + 1;
    });

    // Show only current page slice
    const visibleRows = activeRows.slice(start, end);
    visibleRows.forEach(row => row.style.display = '');

    // Update info
    document.getElementById('pg-start').innerText = total === 0 ? 0 : start + 1;
    document.getElementById('pg-end').innerText = end;
    document.getElementById('pg-total').innerText = total;

    updatePgControls(total);
}

function updatePgControls(total) {
    const totalPages = Math.ceil(total / pageSize);
    const container = document.getElementById('pg-numbers');
    container.innerHTML = '';

    if (totalPages <= 1) return;

    const createBtn = (content, disabled, active, onClick) => {
        const btn = document.createElement('button');
        btn.className = 'pg-btn' + (active ? ' active' : '');
        btn.innerHTML = content;
        btn.disabled = disabled;
        if (!disabled) btn.onclick = onClick;
        return btn;
    };

    // First & Prev
    container.appendChild(createBtn('<i class="fas fa-angle-double-left"></i>', currentPage === 1, false, () => { currentPage = 1; renderPagination(); }));
    container.appendChild(createBtn('<i class="fas fa-angle-left"></i>', currentPage === 1, false, () => { currentPage--; renderPagination(); }));

    // Page Numbers
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    if (startPage > 1) {
        container.appendChild(createBtn('1', false, false, () => { currentPage = 1; renderPagination(); }));
        if (startPage > 2) {
            const dots = document.createElement('span');
            dots.className = 'pg-dots';
            dots.innerText = '...';
            container.appendChild(dots);
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createBtn(i, false, i === currentPage, () => { currentPage = i; renderPagination(); }));
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const dots = document.createElement('span');
            dots.className = 'pg-dots';
            dots.innerText = '...';
            container.appendChild(dots);
        }
        container.appendChild(createBtn(totalPages, false, false, () => { currentPage = totalPages; renderPagination(); }));
    }

    // Next & Last
    container.appendChild(createBtn('<i class="fas fa-angle-right"></i>', currentPage === totalPages, false, () => { currentPage++; renderPagination(); }));
    container.appendChild(createBtn('<i class="fas fa-angle-double-right"></i>', currentPage === totalPages, false, () => { currentPage = totalPages; renderPagination(); }));
}

function changePageSize() {
    pageSize = parseInt(document.getElementById('pg-limit').value);
    currentPage = 1;
    renderPagination();
}

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
    const globalDisc = parseFloat(document.getElementById('global_discount').value) || 0;
    const globalMark = parseFloat(document.getElementById('global_markup').value) || 0;
    const print = parseFloat(document.getElementById('print_cost').value) || 0;
    const mount = parseFloat(document.getElementById('mount_cost').value) || 0;
    const taxType = document.getElementById('tax-type').value;

    let totalDisplay = 0;

    selectedSites.forEach((site) => {
        const row = document.getElementById('row-' + site.id);
        
        // If global pricing is used, we could auto-adjust saleRate
        // For now, we'll treat them as adjustments to the total or allow them to drive individual rates
        // Let's make them drive the saleRate if the user inputs a global value
        // But to keep it "proper", we'll apply them to the base cardRate if not manually overridden
        
        const currentTotal = site.saleRate;
        
        // Margin Analysis: (Sale - Purchase) / Purchase * 100
        const markupVal = site.saleRate - site.purchaseRate;
        const markupPct = site.purchaseRate > 0 ? ((markupVal / site.purchaseRate) * 100).toFixed(1) : '0';

        totalDisplay += currentTotal;
        
        // Update row visual
        if(row) {
            row.querySelector('.total-cell').innerText = '₹' + currentTotal.toLocaleString();
            row.querySelector('.markup-cell').innerText = markupPct + '%';
            
            // Color markup based on performance
            const markupEl = row.querySelector('.markup-cell');
            if (markupPct > 20) markupEl.style.color = '#059669';
            else if (markupPct > 0) markupEl.style.color = '#1d4ed8';
            else markupEl.style.color = '#dc2626';
        }
    });

    // Apply global discount/markup to the total display cost
    let adjustedDisplay = totalDisplay;
    if (globalMark > 0) adjustedDisplay += (totalDisplay * (globalMark / 100));
    if (globalDisc > 0) adjustedDisplay -= (totalDisplay * (globalDisc / 100));

    const subtotal = adjustedDisplay + print + mount;
    const totalTax = subtotal * 0.18;
    const grand = subtotal + totalTax;

    // Update Summary Panel
    document.getElementById('sum-display-btm').innerText = '₹' + adjustedDisplay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Tax Breakdown Logic
    const taxContainer = document.getElementById('tax-breakdown');
    if (taxType === 'cgst_sgst') {
        const halfTax = totalTax / 2;
        taxContainer.innerHTML = `
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span style="color:#64748b; font-weight:600;">CGST (9%):</span>
                <span style="font-weight:800;">₹${halfTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span style="color:#64748b; font-weight:600;">SGST (9%):</span>
                <span style="font-weight:800;">₹${halfTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
        `;
    } else {
        taxContainer.innerHTML = `
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span style="color:#64748b; font-weight:600;">IGST (18%):</span>
                <span style="font-weight:800;">₹${totalTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
        `;
    }

    document.getElementById('sum-grand-btm').innerText = '₹' + grand.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function filterSites() {
    const q = document.getElementById('site-search').value.toLowerCase();
    const rows = document.querySelectorAll('#asset-body tr.site-row');
    
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(q)) {
            row.classList.remove('search-hidden');
            row.style.display = ''; // Reset for pagination to handle
        } else {
            row.classList.add('search-hidden');
            row.style.display = 'none';
        }
    });

    currentPage = 1;
    renderPagination();
}

document.addEventListener('DOMContentLoaded', () => {
    renderPagination();
});

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
