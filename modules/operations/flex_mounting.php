<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/mounting_functions.php';

requirePermission(MOUNTING_MODULE_KEY, 'view');

$activePage = 'flex_mounting';
$pageTitle = 'Flex Mounting Register';
include_once __DIR__ . '/../../includes/header.php';

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$perm = [
    'add' => canAdd(MOUNTING_MODULE_KEY),
    'edit' => canEdit(MOUNTING_MODULE_KEY),
    'delete' => canDelete(MOUNTING_MODULE_KEY),
    'admin' => $isAdmin,
];
?>
<style>
.fm-wrap { padding: 15px; }
.fm-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 14px; }
.fm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
.fm-card .lbl { font-size: .68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
.fm-card .val { font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-top: 4px; }
.fm-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; margin-bottom: 14px; }
.fm-bar input, .fm-bar select, .fm-modal input, .fm-modal select, .fm-modal textarea { padding: .45rem .6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: .82rem; background: #f8fafc; }
.fm-btn { border: none; border-radius: 8px; padding: .5rem .9rem; font-weight: 700; font-size: .8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.fm-btn.primary { background: #0f766e; color: #fff; } .fm-btn.ghost { background: #f1f5f9; color: #334155; }
.fm-btn.green { background: #10b981; color: #fff; } .fm-btn.red { background: #ef4444; color: #fff; } .fm-btn.blue { background: #2563eb; color: #fff; }
.fm-btn.sm { padding: .3rem .55rem; font-size: .72rem; }
.fm-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto; margin-bottom: 14px; }
.fm-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.fm-table th { text-align: left; font-size: .66rem; text-transform: uppercase; color: #64748b; font-weight: 800; padding: .65rem .6rem; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
.fm-table td { padding: .6rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.fm-table td.num, .fm-table th.num { text-align: right; white-space: nowrap; }
.fm-pill { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .66rem; font-weight: 800; }
.fm-pill.pending_approval { background: #fef3c7; color: #92400e; } .fm-pill.approved { background: #dcfce7; color: #166534; }
.fm-pill.rejected { background: #fee2e2; color: #991b1b; } .fm-pill.paid { background: #dbeafe; color: #1e40af; } .fm-pill.unpaid { background: #f1f5f9; color: #475569; }
.fm-h { font-size: .9rem; font-weight: 800; color: #0f172a; padding: .75rem .9rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; }
.fm-modal-bg { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: flex-start; justify-content: center; z-index: 2000; overflow-y: auto; padding: 30px 10px; }
.fm-modal { background: #fff; border-radius: 14px; width: 100%; max-width: 980px; padding: 18px 20px; }
.fm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; }
.fm-grid label, .fm-modal label { display: block; font-size: .68rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px; }
.fm-grid input, .fm-grid select { width: 100%; box-sizing: border-box; }
.fm-sug { position: absolute; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; z-index: 10; max-height: 220px; overflow-y: auto; min-width: 260px; box-shadow: 0 10px 20px rgba(0,0,0,.08); }
.fm-sug div { padding: .45rem .6rem; font-size: .8rem; cursor: pointer; } .fm-sug div:hover { background: #f1f5f9; }
.fm-total { background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 10px; padding: 10px 14px; display: flex; flex-wrap: wrap; gap: 18px; font-size: .82rem; margin-top: 10px; }
.fm-total b { color: #0f766e; }
@media (max-width: 700px) { .fm-modal { padding: 14px; } }
.swal2-container { z-index: 3000 !important; }
.fm-x { background: #f1f5f9; border: none; border-radius: 8px; width: 34px; height: 34px; font-size: 1rem; color: #334155; cursor: pointer; }
.fm-x:hover { background: #fee2e2; color: #b91c1c; }
.fm-pill.partial { background: #ffedd5; color: #9a3412; } .fm-pill.reversed { background: #f1f5f9; color: #64748b; text-decoration: line-through; }
.fm-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end; }
.fm-form label { display: block; font-size: .68rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px; }
.fm-form input, .fm-form select { width: 100%; box-sizing: border-box; }
.fm-box { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-top: 12px; }
.fm-box h4 { margin: 0 0 10px; font-size: .85rem; font-weight: 800; color: #0f172a; }
.fm-kv { display: flex; flex-wrap: wrap; gap: 18px; font-size: .84rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; }
.fm-kv b { font-size: .95rem; }
tr.rev td { color: #94a3b8; }
</style>

<div class="fm-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
        <div>
            <h2 style="margin:0;font-size:1.25rem;font-weight:800;color:#0f172a;">Flex Mounting Register</h2>
            <div style="font-size:.78rem;color:#64748b;">Labour contractor trips, site-wise cost split and contractor payments. Approved trips go into each site's P&amp;L.</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="fm-btn ghost" onclick="openContractors()"><i class="fas fa-users"></i> Contractors</button>
                <?php if ($perm['add']): ?><button class="fm-btn primary" onclick="openTrip()"><i class="fas fa-plus"></i> New Mounting Entry</button><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="fm-cards">
        <div class="fm-card"><div class="lbl">Entries</div><div class="val" id="cTrips">0</div></div>
        <div class="fm-card"><div class="lbl">Total Sqft</div><div class="val" id="cSqft">0</div></div>
        <div class="fm-card"><div class="lbl">Total Expense (approved)</div><div class="val" id="cTotal">₹0</div></div>
        <div class="fm-card"><div class="lbl">Paid to Contractors</div><div class="val" id="cPaid" style="color:#2563eb">₹0</div></div>
        <div class="fm-card"><div class="lbl">Pending Payment</div><div class="val" id="cPending" style="color:#dc2626">₹0</div></div>
    </div>

    <div style="display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap" role="tablist">
        <button class="fm-btn primary sm" id="tabE" role="tab" aria-selected="true" onclick="showTab('entries')"><i class="fas fa-clipboard-list"></i> Entries</button>
        <button class="fm-btn ghost sm" id="tabP" role="tab" aria-selected="false" onclick="showTab('payments')"><i class="fas fa-rupee-sign"></i> Payments</button>
    </div>

    <div class="fm-bar">
        <input type="date" id="fFrom" title="From"> <span style="color:#94a3b8">to</span> <input type="date" id="fTo" title="To">
        <input type="text" id="fContractor" placeholder="Contractor or brand">
        <select id="fApproval"><option value="">All approval</option><option value="pending_approval">Pending approval</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
        <select id="fPayment"><option value="">All payment</option><option value="pending">Unpaid</option><option value="partial">Partly paid</option><option value="paid">Paid</option></select>
        <select id="fPayList" hidden><option value="">All payments</option><option value="active">Active</option><option value="reversed">Reverted</option></select>
        <button class="fm-btn primary sm" onclick="reloadTab()"><i class="fas fa-search"></i> Filter</button>
        <button class="fm-btn ghost sm" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
        <button class="fm-btn ghost sm" onclick="exportCsv()" style="margin-left:auto"><i class="fas fa-file-csv"></i> Export CSV</button>
    </div>

    <div id="entriesPanel">
    <div id="selBar" hidden style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:10px 14px;margin-bottom:10px">
        <span style="font-size:.85rem;color:#1e3a8a" id="selInfo"></span>
        <span style="display:flex;gap:8px"><button class="fm-btn ghost sm" onclick="clearTicks()">Clear</button><button class="fm-btn blue" onclick="openBulkPay()"><i class="fas fa-money-bill-wave"></i> Pay ticked entries</button></span>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr>
                <th style="width:28px"><input type="checkbox" id="tickAll" title="Tick all payable entries" onchange="tickAll(this.checked)"></th>
                <th>Entry No / Date</th><th>Contractor</th><th>Mounting Date</th><th>Sites</th><th>Brand / Campaign</th><th class="num">Sqft</th><th class="num">Rate</th>
                <th class="num">Labour</th><th class="num">Food</th><th class="num">Travel</th><th class="num">Misc</th><th class="num">Total</th>
                <th class="num">Paid</th><th class="num">Balance</th><th>Status</th><th>Action</th>
            </tr></thead>
            <tbody id="tripRows"><tr><td colspan="17" style="text-align:center;padding:2rem;color:#94a3b8">Loading…</td></tr></tbody>
        </table>
    </div>
    </div>

    <div class="fm-table-wrap" id="paymentsPanel" hidden>
        <table class="fm-table">
            <thead><tr><th>Payment No / Date</th><th>Contractor</th><th>Entries paid</th><th>Mode / Ref</th><th>Notes</th><th>File</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody id="payListRows"></tbody>
            <tfoot id="payListFoot"></tfoot>
        </table>
    </div>

</div>

<!-- Entry form -->
<div class="fm-modal-bg" id="tripModal">
    <div class="fm-modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1.05rem;font-weight:800" id="tripModalTitle">New Mounting Entry</h3>
            <button class="fm-x" title="Close" aria-label="Close" onclick="closeModal('tripModal')"><i class="fas fa-times"></i></button>
        </div>
        <input type="hidden" id="tId">
        <div class="fm-grid">
            <div><label>Date *</label><input type="date" id="tDate"></div>
            <div><label for="tConSel">Labour Contractor *</label><select id="tConSel" onchange="pickConOption()"></select><input type="hidden" id="tName"></div>
            <div><label>Mobile</label><input type="text" id="tMobile" maxlength="16" placeholder="e.g. 9876543210"></div>
            <div><label for="tBrand">Brand / Campaign</label><input type="text" id="tBrand" maxlength="150" list="brandList" autocomplete="off" oninput="suggestBrand()" placeholder="e.g. Kisna Jewellers"><datalist id="brandList"></datalist></div>
            <div><label>Rate per Sqft (₹)</label><input type="number" step="0.01" min="0" id="tRate" oninput="recalc()"></div>
            <div><label>Fooding (₹)</label><input type="number" step="0.01" min="0" id="tFood" oninput="recalc()"></div>
            <div><label>Travelling (₹)</label><input type="number" step="0.01" min="0" id="tTravel" oninput="recalc()"></div>
            <div><label>Miscellaneous (₹)</label><input type="number" step="0.01" min="0" id="tMisc" oninput="recalc()"></div>
            <div><label for="tMountDate">Mounting Date</label><input type="date" id="tMountDate"></div>
        </div>
        <div style="margin-top:10px"><label>Description / Work details</label><textarea id="tDesc" rows="2" style="width:100%;box-sizing:border-box" placeholder="e.g. Flex mounting at Malda town sites, outstation trip"></textarea></div>

        <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <label style="margin:0">Sites (from Site Master) *</label>
            <div style="position:relative">
                <input type="text" id="siteSearch" placeholder="Search site code / name / location" autocomplete="off" oninput="searchSites()" style="min-width:280px">
                <div class="fm-sug" id="siteSug" style="display:none;right:0"></div>
            </div>
        </div>
        <div class="fm-table-wrap" style="margin-top:8px">
            <table class="fm-table">
                <thead><tr><th>Site Code</th><th>Name / Location</th><th>Type</th><th class="num">Sqft</th><th class="num">Labour</th><th class="num">Food/Travel/Misc share</th><th class="num">Site Cost</th><th>Remarks</th><th></th></tr></thead>
                <tbody id="lineRows"></tbody>
            </table>
        </div>
        <div class="fm-total">
            <span>Total Sqft: <b id="sSqft">0</b></span>
            <span>Labour: <b id="sLabour">₹0</b></span>
            <span>Food + Travel + Misc: <b id="sOver">₹0</b></span>
            <span style="font-size:.95rem">Total Expense: <b id="sTotal">₹0</b></span>
        </div>
        <div style="font-size:.72rem;color:#64748b;margin-top:6px">Food/travel/misc is split across the sites by sqft. Each site's cost goes into that site's P&amp;L after admin approval.</div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
            <button class="fm-btn ghost" onclick="closeModal('tripModal')">Cancel</button>
            <button class="fm-btn primary" id="saveBtn" onclick="saveTrip()"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<!-- View -->
<div class="fm-modal-bg" id="viewModal">
    <div class="fm-modal" style="max-width:820px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 style="margin:0;font-size:1.05rem;font-weight:800" id="viewTitle">Entry</h3>
            <div style="display:flex;gap:6px"><button class="fm-btn ghost sm" onclick="printView()"><i class="fas fa-print"></i> Print</button><button class="fm-x" title="Close" aria-label="Close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button></div>
        </div>
        <div id="viewBody"></div>
    </div>
</div>

<!-- Labour contractor master -->
<div class="fm-modal-bg" id="conModal">
    <div class="fm-modal" style="max-width:980px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1.05rem;font-weight:800">Labour Contractors</h3>
            <button class="fm-x" title="Close" aria-label="Close" onclick="closeModal('conModal')"><i class="fas fa-times"></i></button>
        </div>
        <?php if ($perm['edit']): ?>
        <div class="fm-box" style="margin-top:0">
            <h4 id="cFormTitle"><i class="fas fa-user-plus" style="color:#0f766e"></i> Add contractor</h4>
            <input type="hidden" id="cId">
            <div class="fm-form">
                <div><label for="cName">Name *</label><input type="text" id="cName" maxlength="150"></div>
                <div><label for="cMobile">Mobile</label><input type="text" id="cMobile" maxlength="16" placeholder="e.g. 9876543210"></div>
                <div><label for="cAddress">Address</label><input type="text" id="cAddress" maxlength="255"></div>
                <div><label for="cNotes">Notes</label><input type="text" id="cNotes" maxlength="255" placeholder="e.g. team of 4, works outstation"></div>
                <div><label for="cActive">Status</label><select id="cActive"><option value="1">Active</option><option value="0">Inactive</option></select></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px">
                <button class="fm-btn ghost" onclick="resetConForm()">Clear</button>
                <button class="fm-btn primary" id="cSaveBtn" onclick="saveContractor()"><i class="fas fa-save"></i> Save Contractor</button>
            </div>
        </div>
        <?php endif; ?>
        <div style="display:flex;gap:8px;margin:12px 0 8px"><input type="text" id="cSearch" placeholder="Search name or mobile" oninput="loadContractors()" style="flex:1;max-width:320px"></div>
        <div class="fm-table-wrap" style="margin:0">
            <table class="fm-table">
                <thead><tr><th>Contractor</th><th>Mobile</th><th>Address / Notes</th><th class="num">Entries</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th><th></th></tr></thead>
                <tbody id="cRows"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- One contractor: entries, payments, due -->
<div class="fm-modal-bg" id="ledgerModal">
    <div class="fm-modal" style="max-width:1000px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:8px">
            <h3 style="margin:0;font-size:1.05rem;font-weight:800" id="lTitle">Contractor</h3>
            <div style="display:flex;gap:6px"><button class="fm-btn blue sm" id="lPayBtn" onclick="payContractorDues()"><i class="fas fa-money-bill-wave"></i> Pay dues</button><button class="fm-x" title="Close" aria-label="Close" onclick="closeModal('ledgerModal')"><i class="fas fa-times"></i></button></div>
        </div>
        <div id="lInfo" style="font-size:.82rem;color:#475569;margin-bottom:10px"></div>
        <div class="fm-cards" id="lCards" style="margin-bottom:12px"></div>
        <div class="fm-table-wrap">
            <div class="fm-h"><span><i class="fas fa-clipboard-list" style="color:#0f766e"></i> Entries</span></div>
            <table class="fm-table">
                <thead><tr><th>Entry No / Date</th><th>Mounting Date</th><th>Sites</th><th>Brand / Campaign</th><th class="num">Sqft</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th></tr></thead>
                <tbody id="lEntries"></tbody>
            </table>
        </div>
        <div class="fm-table-wrap" style="margin-bottom:0">
            <div class="fm-h"><span><i class="fas fa-rupee-sign" style="color:#2563eb"></i> Payments</span></div>
            <table class="fm-table">
                <thead><tr><th>Payment No / Date</th><th>Entry</th><th>Mode / Ref</th><th>Notes</th><th>File</th><th class="num">Amount</th><th>Status</th></tr></thead>
                <tbody id="lPays"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- One payment over several ticked entries -->
<div class="fm-modal-bg" id="bulkModal">
    <div class="fm-modal" style="max-width:820px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1.05rem;font-weight:800" id="bTitle">Pay ticked entries</h3>
            <button class="fm-x" title="Close" aria-label="Close" onclick="closeModal('bulkModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="fm-table-wrap" style="margin:0">
            <table class="fm-table">
                <thead><tr><th>Entry No / Date</th><th>Sites</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th class="num">This payment</th></tr></thead>
                <tbody id="bRows"></tbody>
                <tfoot id="bFoot"></tfoot>
            </table>
        </div>
        <div class="fm-box">
            <div class="fm-form">
                <div><label for="bAmount">Amount (₹) *</label><input type="number" step="0.01" min="0.01" id="bAmount" oninput="splitBulk()"></div>
                <div><label for="bDate">Payment date *</label><input type="date" id="bDate"></div>
                <div><label for="bMode">Mode *</label><select id="bMode"></select></div>
                <div><label for="bRef">Reference / UTR</label><input type="text" id="bRef" maxlength="100"></div>
                <div><label for="bFile">Attachment</label><input type="file" id="bFile" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="padding:.32rem"></div>
                <div style="grid-column:1/-1"><label for="bNotes">Notes</label><input type="text" id="bNotes" maxlength="255" placeholder="e.g. Payment for work of 1-10 Nov"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;gap:10px;flex-wrap:wrap">
                <span style="font-size:.75rem;color:#64748b">Full amount pays all ticked entries. A smaller amount fills the oldest entries first.</span>
                <button class="fm-btn blue" id="bSaveBtn" onclick="saveBulk()"><i class="fas fa-check"></i> Save Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- Payments of one entry -->
<div class="fm-modal-bg" id="payModal">
    <div class="fm-modal" style="max-width:860px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1.05rem;font-weight:800" id="payTitle">Payments</h3>
            <button class="fm-x" title="Close" aria-label="Close" onclick="closeModal('payModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="fm-kv" id="payKv"></div>
        <div class="fm-table-wrap" style="margin:12px 0 0">
            <table class="fm-table">
                <thead><tr><th>Date</th><th>Payment No</th><th>Mode / Ref</th><th>Notes</th><th>File</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
                <tbody id="payRows"></tbody>
            </table>
        </div>
        <div class="fm-box" id="payAddBox">
            <h4><i class="fas fa-rupee-sign" style="color:#2563eb"></i> Add payment (full or part)</h4>
            <div class="fm-form">
                <div><label for="pAmount">Amount (₹) *</label><input type="number" step="0.01" min="0.01" id="pAmount"></div>
                <div><label for="pDate">Payment date *</label><input type="date" id="pDate"></div>
                <div><label for="pMode">Mode *</label><select id="pMode"></select></div>
                <div><label for="pRef">Reference / UTR</label><input type="text" id="pRef" maxlength="100"></div>
                <div><label for="pFile">Attachment</label><input type="file" id="pFile" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="padding:.32rem"></div>
                <div style="grid-column:1/-1"><label for="pNotes">Notes</label><input type="text" id="pNotes" maxlength="255" placeholder="Optional"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:10px"><button class="fm-btn blue" id="pSaveBtn" onclick="savePayment()"><i class="fas fa-check"></i> Save Payment</button></div>
        </div>
    </div>
</div>

<script>
const FM_API = '<?php echo BASE_URL; ?>ajax/mounting_trip_api.php';
const FM_APPROVE = '<?php echo BASE_URL; ?>ajax/approve_entity.php';
const PERM = <?php echo json_encode($perm); ?>;
const MODES = <?php echo json_encode(MOUNTING_PAYMENT_MODES); ?>;
let TRIPS = [], LINES = [];

const inr = v => '₹' + Number(v || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const num = v => Number(v || 0).toLocaleString('en-IN', {maximumFractionDigits: 2});
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'}) : '';
const ownerLabel = t => t === 'HA' ? 'Own (HA)' : (t === 'TA' ? 'Traded (TA)' : '-');

async function apiForm(params, fileInput) {
    const fd = new FormData();
    Object.entries(params).forEach(([k, v]) => fd.append(k, v ?? ''));
    if (fileInput && fileInput.files[0]) {
        if (fileInput.files[0].size > 10 * 1024 * 1024) throw new Error('Attachment is larger than 10 MB.');
        fd.append('attachment', fileInput.files[0]);
    }
    const res = await fetch(FM_API, {method: 'POST', body: fd});
    const data = await res.json().catch(() => ({success: false, message: 'Server error'}));
    if (!data.success) throw new Error(data.message || 'Request failed');
    return data;
}
async function api(method, params) {
    let res;
    if (method === 'GET') res = await fetch(FM_API + '?' + new URLSearchParams(params));
    else res = await fetch(FM_API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(params)});
    const data = await res.json().catch(() => ({success: false, message: 'Server error'}));
    if (!data.success) throw new Error(data.message || 'Request failed');
    return data;
}
const SwalX = Swal.mixin({showCloseButton: true});
function toast(icon, title) { SwalX.fire({icon, title, timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success'}); }

async function loadTrips() {
    try {
        const d = await api('GET', {action: 'list', from: fFrom.value, to: fTo.value, contractor: fContractor.value.trim(), approval_status: fApproval.value, payment_status: fPayment.value});
        TRIPS = d.trips; renderTrips();
    } catch (e) { tripRows.innerHTML = `<tr><td colspan="17" style="text-align:center;padding:2rem;color:#dc2626">${esc(e.message)}</td></tr>`; }
}
function resetFilters() { fFrom.value = fTo.value = fContractor.value = fApproval.value = fPayment.value = fPayList.value = ''; reloadTab(); }
let TAB = 'entries', PAYS = [];
function showTab(t) {
    TAB = t;
    tabE.className = 'fm-btn sm ' + (t === 'entries' ? 'primary' : 'ghost'); tabP.className = 'fm-btn sm ' + (t === 'payments' ? 'primary' : 'ghost');
    tabE.setAttribute('aria-selected', t === 'entries'); tabP.setAttribute('aria-selected', t === 'payments');
    entriesPanel.hidden = t !== 'entries'; paymentsPanel.hidden = t !== 'payments';
    fApproval.hidden = fPayment.hidden = t !== 'entries'; fPayList.hidden = t !== 'payments';
    selBar.hidden = t !== 'entries' || !TICKED.size;
    reloadTab();
}
function reloadTab() { loadTrips(); if (TAB === 'payments') loadPayList(); }
async function loadPayList() {
    try {
        const d = await api('GET', {action: 'payment_list', from: fFrom.value, to: fTo.value, contractor: fContractor.value.trim(), status: fPayList.value});
        PAYS = d.payments;
        let tot = 0;
        payListRows.innerHTML = PAYS.length ? PAYS.map(p => {
            const rev = +p.active_rows === 0; if (!rev && p.approval_status === 'approved') tot += +p.amount;
            const entries = (p.entries || '').split('; ').filter(Boolean).map(x => esc(x).replace('Rs.', '₹')).join('<br>');
            let act = '';
            const waitAp = !rev && p.approval_status === 'pending_approval';
            if (waitAp && PERM.admin) act += `<button class="fm-btn green sm" onclick="decidePayment(${p.first_id},'approve')" title="Approve payment"><i class="fas fa-check"></i> Approve</button> <button class="fm-btn red sm" onclick="decidePayment(${p.first_id},'reject')" title="Reject payment"><i class="fas fa-times"></i></button> `;
            if (!rev && !waitAp && PERM.admin && p.payment_number) act += `<button class="fm-btn ghost sm" onclick="revertGroup('${esc(p.payment_number)}')" title="Revert this whole payment"><i class="fas fa-undo"></i> Revert</button>`;
            if (PERM.admin && p.payment_number) act += ` <button class="fm-btn ghost sm" onclick="deleteGroup('${esc(p.payment_number)}')" title="Delete wrong payment" style="color:#dc2626"><i class="fas fa-trash"></i></button>`;
            return `<tr class="${rev ? 'rev' : ''}"><td><b>${esc(p.payment_number || p.ref)}</b><div style="font-size:.7rem;color:#64748b">${fmtDate(p.paid_date)}</div></td>
                <td>${p.contractor_id ? `<a href="#" onclick="openLedger(${p.contractor_id});return false" style="color:#0f766e;font-weight:700">${esc(p.contractor_name)}</a>` : esc(p.contractor_name)}</td>
                <td style="font-size:.74rem;min-width:180px">${+p.rows_count > 1 ? `<span class="fm-pill partial" style="margin-bottom:3px">Bulk · ${+p.rows_count} entries</span><br>` : ''}${entries}</td>
                <td>${esc(p.payment_mode || '')}${p.payment_ref ? '<div style="font-size:.7rem;color:#64748b">' + esc(p.payment_ref) + '</div>' : ''}</td>
                <td style="max-width:200px">${esc(p.notes || '')}${rev && p.reverse_reason ? '<div style="font-size:.7rem">Reverted: ' + esc(p.reverse_reason) + '</div>' : ''}</td>
                <td>${fileLink(p.attachment)}</td>
                <td class="num"><b>${inr(rev ? p.original_amount : p.amount)}</b></td>
                <td>${p.approval_status === 'rejected' ? '<span class="fm-pill rejected">Rejected</span>' : (rev ? '<span class="fm-pill reversed">Reverted</span>' : (p.approval_status === 'pending_approval' ? '<span class="fm-pill pending_approval">Awaiting approval</span>' : `<span class="fm-pill approved">${+p.active_rows < +p.rows_count ? 'Part reverted' : 'Active'}</span>`))}${p.rejection_reason ? '<div style="font-size:.68rem;color:#991b1b">' + esc(p.rejection_reason) + '</div>' : ''}</td>
                <td style="white-space:nowrap">${act}</td></tr>`;
        }).join('') : '<tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8">No payments yet.</td></tr>';
        payListFoot.innerHTML = PAYS.length ? `<tr><td colspan="6">Total paid (${PAYS.filter(p => +p.active_rows > 0).length} payments)</td><td class="num">${inr(tot)}</td><td colspan="2"></td></tr>` : '';
    } catch (e) { payListRows.innerHTML = `<tr><td colspan="9" style="color:#dc2626;padding:1rem">${esc(e.message)}</td></tr>`; }
}
async function revertGroup(no) {
    const r = await SwalX.fire({title: 'Revert ' + no + '?', text: 'Every entry this payment paid goes back to its old balance. The payment stays in the list as reverted.', input: 'text', inputPlaceholder: 'Reason (optional)', icon: 'warning', showCancelButton: true, confirmButtonText: 'Revert', confirmButtonColor: '#f59e0b'});
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'revert_group', payment_number: no, reason: r.value || ''}); toast('success', d.message); reloadTab(); } catch (e) { toast('error', e.message); }
}
async function deleteGroup(no) {
    const r = await SwalX.fire({title: 'Delete ' + no + '?', text: 'Use this only for a wrong payment. It is removed from every entry it paid and cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#ef4444'});
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'delete_group', payment_number: no}); toast('success', d.message); reloadTab(); } catch (e) { toast('error', e.message); }
}

function renderTrips() {
    let sqft = 0, total = 0, paid = 0, pending = 0;
    if (!TRIPS.length) { tripRows.innerHTML = '<tr><td colspan="17" style="text-align:center;padding:2rem;color:#94a3b8">No mounting entries yet.</td></tr>'; }
    else tripRows.innerHTML = TRIPS.map(t => {
        sqft += +t.total_sqft;
        const tPaid = +t.paid_amount || 0, tWait = +t.pending_pay || 0, tBal = Math.round((+t.total_amount - tPaid) * 100) / 100, tAvail = Math.round((tBal - tWait) * 100) / 100;
        if (t.approval_status === 'approved') { total += +t.total_amount; paid += tPaid; pending += tBal; }
        const locked = tPaid > 0 || tWait > 0;
        let act = `<button class="fm-btn ghost sm" onclick="viewTrip(${t.id})" title="View"><i class="fas fa-eye"></i></button>`;
        if (!locked && PERM.edit) act += ` <button class="fm-btn ghost sm" onclick="openTrip(${t.id})" title="${PERM.admin ? 'Edit' : 'Edit (goes to admin for approval)'}"><i class="fas fa-pen"></i></button>`;
        if (PERM.admin && t.approval_status === 'pending_approval') act += ` <button class="fm-btn green sm" onclick="decide(${t.id},'approve')" title="Approve"><i class="fas fa-check"></i></button> <button class="fm-btn red sm" onclick="decide(${t.id},'reject')" title="Reject"><i class="fas fa-times"></i></button>`;
        if (t.approval_status === 'approved') act += ` <button class="fm-btn blue sm" onclick="openPayments(${t.id})" title="Payments"><i class="fas fa-rupee-sign"></i> ${t.payment_status === 'paid' ? 'Payments' : 'Pay'}</button>`;
        if (!locked && PERM.admin) act += ` <button class="fm-btn ghost sm" onclick="deleteTrip(${t.id})" title="Delete" style="color:#dc2626"><i class="fas fa-trash"></i></button>`;
        const apLabel = {pending_approval: 'Pending approval', approved: 'Approved', rejected: 'Rejected'}[t.approval_status];
        const canTick = PERM.edit && t.approval_status === 'approved' && tAvail > 0.009;
        return `<tr>
            <td>${canTick ? `<input type="checkbox" class="tPick" value="${t.id}" ${TICKED.has(+t.id) ? 'checked' : ''} onchange="tickOne(${t.id}, this.checked)">` : ''}</td>
            <td><b>${esc(t.trip_number)}</b><div style="font-size:.7rem;color:#64748b">${fmtDate(t.trip_date)}</div></td>
            <td>${t.contractor_id ? `<a href="#" onclick="openLedger(${t.contractor_id});return false" title="Contractor ledger" style="color:#0f766e;font-weight:700">${esc(t.contractor_name)}</a>` : esc(t.contractor_name)}<div style="font-size:.7rem;color:#64748b">${esc(t.contractor_mobile || '')}</div></td>
            <td style="white-space:nowrap">${t.mounting_date ? fmtDate(t.mounting_date) : '<span style="color:#94a3b8">-</span>'}</td>
            <td style="max-width:200px">${esc(t.site_codes || '')}<div style="font-size:.7rem;color:#64748b">${t.site_count} site(s)</div></td>
            <td style="max-width:180px">${t.brand_campaign ? esc(t.brand_campaign) : '<span style="color:#94a3b8">-</span>'}</td>
            <td class="num">${num(t.total_sqft)}</td><td class="num">${inr(t.rate_per_sqft)}</td><td class="num">${inr(t.labour_amount)}</td>
            <td class="num">${inr(t.food_amount)}</td><td class="num">${inr(t.travel_amount)}</td><td class="num">${inr(t.misc_amount)}</td>
            <td class="num"><b>${inr(t.total_amount)}</b></td>
            <td class="num" style="color:#2563eb">${tPaid ? inr(tPaid) : '-'}${tWait ? `<div style="font-size:.66rem;color:#b45309">+ ${inr(tWait)} awaiting approval</div>` : ''}</td><td class="num" style="color:${tBal > 0 ? '#dc2626' : '#16a34a'};font-weight:700">${inr(tBal)}</td>
            <td><span class="fm-pill ${t.approval_status}">${apLabel}</span><br><span class="fm-pill ${payPill(t.payment_status)}" style="margin-top:3px">${payLabel(t.payment_status)}</span>
                ${t.rejection_reason ? `<div style="font-size:.68rem;color:#991b1b;margin-top:2px">${esc(t.rejection_reason)}</div>` : ''}</td>
            <td style="white-space:nowrap">${act}</td></tr>`;
    }).join('');
    cTrips.textContent = TRIPS.length; cSqft.textContent = num(sqft); cTotal.textContent = inr(total); cPaid.textContent = inr(paid); cPending.textContent = inr(pending);
    [...TICKED].forEach(id => { if (!TRIPS.some(t => +t.id === id && t.approval_status === 'approved' && tripBal(t) > 0.009)) TICKED.delete(id); });
    updateSelBar();
}

/* ---------- labour contractor master and ledger ---------- */
let CONS = [], LEDGER = null;
function openContractors() { if (window.cSearch) cSearch.value = ''; resetConForm(); conModal.style.display = 'flex'; loadContractors(); }
function resetConForm() {
    if (!window.cId) return;
    cId.value = ''; cName.value = cMobile.value = cAddress.value = cNotes.value = ''; cActive.value = '1';
    cFormTitle.innerHTML = '<i class="fas fa-user-plus" style="color:#0f766e"></i> Add contractor';
}
async function loadContractors() {
    try {
        const d = await api('GET', {action: 'contractors', q: cSearch.value.trim()});
        CONS = d.contractors;
        cRows.innerHTML = CONS.length ? CONS.map(c => `<tr style="${+c.is_active ? '' : 'opacity:.55'}">
            <td><a href="#" onclick="openLedger(${c.id});return false" style="color:#0f766e;font-weight:700">${esc(c.name)}</a></td>
            <td>${esc(c.mobile || '-')}</td>
            <td style="max-width:220px;font-size:.75rem">${esc(c.address || '')}${c.notes ? '<div style="color:#64748b">' + esc(c.notes) + '</div>' : ''}</td>
            <td class="num">${+c.entries}</td><td class="num">${inr(c.total)}</td><td class="num" style="color:#2563eb">${inr(c.paid)}</td>
            <td class="num" style="font-weight:800;color:${+c.due > 0 ? '#dc2626' : '#16a34a'}">${inr(c.due)}</td>
            <td><span class="fm-pill ${+c.is_active ? 'approved' : 'unpaid'}">${+c.is_active ? 'Active' : 'Inactive'}</span></td>
            <td style="white-space:nowrap"><button class="fm-btn ghost sm" onclick="openLedger(${c.id})" title="Entries, payments and due"><i class="fas fa-book"></i> Ledger</button>
                ${PERM.edit ? `<button class="fm-btn ghost sm" onclick="editContractor(${c.id})" title="Edit"><i class="fas fa-pen"></i></button>` : ''}
                ${PERM.admin && !+c.entries ? `<button class="fm-btn ghost sm" onclick="deleteContractor(${c.id})" title="Delete" style="color:#dc2626"><i class="fas fa-trash"></i></button>` : ''}</td></tr>`).join('')
            : '<tr><td colspan="9" style="text-align:center;padding:1.5rem;color:#94a3b8">No contractors yet. Add one above, or they are added automatically from mounting entries.</td></tr>';
    } catch (e) { cRows.innerHTML = `<tr><td colspan="9" style="color:#dc2626;padding:1rem">${esc(e.message)}</td></tr>`; }
}
function editContractor(id) {
    const c = CONS.find(x => +x.id === +id); if (!c) return;
    cId.value = c.id; cName.value = c.name; cMobile.value = c.mobile || ''; cAddress.value = c.address || ''; cNotes.value = c.notes || ''; cActive.value = +c.is_active ? '1' : '0';
    cFormTitle.innerHTML = '<i class="fas fa-user-edit" style="color:#0f766e"></i> Edit ' + esc(c.name);
    cName.focus();
}
async function saveContractor() {
    cSaveBtn.disabled = true;
    try {
        const d = await api('POST', {action: 'contractor_save', id: +cId.value || 0, name: cName.value.trim(), mobile: cMobile.value.trim(), address: cAddress.value, notes: cNotes.value, is_active: cActive.value === '1' ? 1 : 0});
        toast('success', d.message); resetConForm(); loadContractors(); loadTrips();
    } catch (e) { toast('error', e.message); }
    cSaveBtn.disabled = false;
}
async function openLedger(id) {
    try {
        const d = await api('GET', {action: 'contractor_ledger', id});
        LEDGER = d;
        const c = d.contractor, t = d.totals;
        lTitle.textContent = c.name + (c.mobile ? ' · ' + c.mobile : '');
        lInfo.innerHTML = [c.address, c.notes].filter(Boolean).map(esc).join(' · ') + (+c.is_active ? '' : ' <span class="fm-pill unpaid">Inactive</span>');
        lCards.innerHTML = `<div class="fm-card"><div class="lbl">Entries</div><div class="val">${d.entries.length}</div></div>
            <div class="fm-card"><div class="lbl">Total (approved)</div><div class="val">${inr(t.total)}</div></div>
            <div class="fm-card"><div class="lbl">Paid</div><div class="val" style="color:#2563eb">${inr(t.paid)}</div></div>
            <div class="fm-card"><div class="lbl">Due</div><div class="val" style="color:${t.due > 0 ? '#dc2626' : '#16a34a'}">${inr(t.due)}</div></div>`;
        lEntries.innerHTML = d.entries.length ? d.entries.map(e => {
            const due = e.approval_status === 'approved' ? +e.total_amount - +e.paid_amount : 0;
            return `<tr><td><a href="#" onclick="closeModal('ledgerModal');viewTrip(${e.id});return false" style="color:#0f766e;font-weight:700">${esc(e.trip_number)}</a><div style="font-size:.7rem;color:#64748b">${fmtDate(e.trip_date)}</div></td>
                <td>${e.mounting_date ? fmtDate(e.mounting_date) : '-'}</td><td>${esc(e.site_codes || '')}</td><td>${esc(e.brand_campaign || '-')}</td>
                <td class="num">${num(e.total_sqft)}</td><td class="num">${inr(e.total_amount)}</td><td class="num" style="color:#2563eb">${+e.paid_amount ? inr(e.paid_amount) : '-'}</td>
                <td class="num" style="font-weight:700;color:${due > 0 ? '#dc2626' : '#16a34a'}">${inr(due)}</td>
                <td><span class="fm-pill ${e.approval_status}">${{pending_approval: 'Pending approval', approved: 'Approved', rejected: 'Rejected'}[e.approval_status]}</span> <span class="fm-pill ${payPill(e.payment_status)}">${payLabel(e.payment_status)}</span></td></tr>`;
        }).join('') : '<tr><td colspan="9" style="text-align:center;padding:1rem;color:#94a3b8">No entries.</td></tr>';
        lPays.innerHTML = d.payments.length ? d.payments.map(p => {
            const rev = p.status === 'reversed';
            return `<tr class="${rev ? 'rev' : ''}"><td><b>${esc(p.payment_number || '-')}</b><div style="font-size:.7rem;color:#64748b">${fmtDate(p.paid_date)}</div></td>
                <td>${esc(p.trip_number)}${p.brand_campaign ? '<div style="font-size:.7rem;color:#64748b">' + esc(p.brand_campaign) + '</div>' : ''}</td>
                <td>${esc(p.payment_mode || '')}${p.payment_ref ? '<div style="font-size:.7rem;color:#64748b">' + esc(p.payment_ref) + '</div>' : ''}</td>
                <td style="max-width:200px">${esc(p.notes || '')}</td><td>${fileLink(p.attachment)}</td><td class="num"><b>${inr(p.amount)}</b></td>
                <td>${payStatusPill(p)}</td></tr>`;
        }).join('') : '<tr><td colspan="7" style="text-align:center;padding:1rem;color:#94a3b8">No payments yet.</td></tr>';
        lPayBtn.hidden = !(PERM.edit && t.due > 0.009);
        ledgerModal.style.display = 'flex';
    } catch (e) { toast('error', e.message); }
}
// Tick every approved entry of this contractor that still has a balance, then open "Pay ticked entries"
async function payContractorDues() {
    if (!LEDGER) return;
    const ids = LEDGER.entries.filter(e => e.approval_status === 'approved' && +e.total_amount - +e.paid_amount > 0.009).map(e => +e.id);
    if (!ids.length) return toast('info', 'Nothing due for this contractor');
    resetFilters(); await loadTrips();
    TICKED.clear(); ids.forEach(id => TICKED.add(id));
    document.querySelectorAll('.tPick').forEach(c => c.checked = TICKED.has(+c.value));
    updateSelBar(); closeModal('ledgerModal'); closeModal('conModal'); openBulkPay();
}
let brandTimer;
function suggestBrand() {
    clearTimeout(brandTimer);
    const q = tBrand.value.trim();
    if (q.length < 1) return;
    brandTimer = setTimeout(async () => {
        try { const {brands} = await api('GET', {action: 'brands', q}); brandList.innerHTML = brands.map(b => `<option value="${esc(b)}">`).join(''); } catch (e) {}
    }, 250);
}

/* ---------- tick entries and pay them together ---------- */
const TICKED = new Set();
const tripBal = t => Math.round((+t.total_amount - (+t.paid_amount || 0) - (+t.pending_pay || 0)) * 100) / 100;
function tickOne(id, on) { on ? TICKED.add(+id) : TICKED.delete(+id); updateSelBar(); }
function tickAll(on) { document.querySelectorAll('.tPick').forEach(c => { c.checked = on; on ? TICKED.add(+c.value) : TICKED.delete(+c.value); }); updateSelBar(); }
function clearTicks() { TICKED.clear(); document.querySelectorAll('.tPick').forEach(c => c.checked = false); tickAllBox(); updateSelBar(); }
function tickAllBox() { const b = document.getElementById('tickAll'); if (b) { const all = document.querySelectorAll('.tPick'); b.checked = all.length > 0 && [...all].every(c => c.checked); b.disabled = !all.length; } }
function tickedTrips() { return TRIPS.filter(t => TICKED.has(+t.id)).sort((a, b) => (a.trip_date < b.trip_date ? -1 : a.trip_date > b.trip_date ? 1 : +a.id - +b.id)); }
function updateSelBar() {
    const list = tickedTrips();
    tickAllBox();
    selBar.hidden = !list.length;
    if (!list.length) return;
    const names = [...new Set(list.map(t => t.contractor_name.trim().toLowerCase()))];
    const bal = list.reduce((a, t) => a + tripBal(t), 0);
    selInfo.innerHTML = `<b>${list.length}</b> entr${list.length === 1 ? 'y' : 'ies'} ticked · balance <b>${inr(bal)}</b>` +
        (names.length > 1 ? ' · <span style="color:#dc2626;font-weight:700">tick entries of one contractor only</span>' : ' · ' + esc(list[0].contractor_name));
}
function openBulkPay() {
    const list = tickedTrips();
    if (!list.length) return toast('warning', 'Tick the entries you are paying for');
    if (new Set(list.map(t => t.contractor_name.trim().toLowerCase())).size > 1) return toast('warning', 'Tick entries of one contractor only');
    const bal = list.reduce((a, t) => a + tripBal(t), 0);
    bTitle.textContent = 'Pay ' + list.length + ' entr' + (list.length === 1 ? 'y' : 'ies') + ' · ' + list[0].contractor_name;
    bRows.innerHTML = list.map(t => `<tr><td><b>${esc(t.trip_number)}</b><div style="font-size:.7rem;color:#64748b">${fmtDate(t.trip_date)}</div></td><td>${esc(t.site_codes || '')}</td>
        <td class="num">${inr(t.total_amount)}</td><td class="num" style="color:#2563eb">${+t.paid_amount ? inr(t.paid_amount) : '-'}</td><td class="num" style="color:#dc2626">${inr(tripBal(t))}</td><td class="num" id="bPart${t.id}"><b>-</b></td></tr>`).join('');
    bFoot.innerHTML = `<tr><td colspan="4">Total</td><td class="num">${inr(bal)}</td><td class="num" id="bPartTotal">-</td></tr>`;
    bMode.innerHTML = MODES.map(m => `<option>${m}</option>`).join(''); bMode.value = 'Cash';
    bAmount.value = bal.toFixed(2); bAmount.max = bal.toFixed(2); bDate.value = todayISO(); bDate.max = todayISO(); bRef.value = bNotes.value = ''; bFile.value = '';
    splitBulk(); bulkModal.style.display = 'flex';
}
// Shows how the amount will fill the ticked entries (oldest first), same rule as the server
function splitBulk() {
    let left = Math.max(0, +bAmount.value || 0), used = 0;
    tickedTrips().forEach(t => {
        const part = Math.min(tripBal(t), left); left = Math.round((left - part) * 100) / 100; used += part;
        const cell = document.getElementById('bPart' + t.id); if (cell) cell.innerHTML = '<b>' + (part > 0 ? inr(part) : '-') + '</b>';
    });
    const tot = document.getElementById('bPartTotal'); if (tot) tot.innerHTML = '<b>' + inr(used) + '</b>' + (left > 0.009 ? ' <span style="color:#dc2626">(₹' + left.toFixed(2) + ' more than balance)</span>' : '');
}
async function saveBulk() {
    bSaveBtn.disabled = true;
    try {
        const ids = tickedTrips().map(t => t.id);
        const d = await apiForm({action: 'bulk_pay', trip_ids: ids.join(','), amount: bAmount.value, paid_date: bDate.value, payment_mode: bMode.value, payment_ref: bRef.value, notes: bNotes.value}, bFile);
        closeModal('bulkModal'); TICKED.clear(); toast('success', d.message); reloadTab();
    } catch (e) { toast('error', e.message); }
    bSaveBtn.disabled = false;
}
const payLabel = s => ({pending: 'Unpaid', partial: 'Partly paid', paid: 'Paid'}[s] || s);
const payPill = s => ({pending: 'unpaid', partial: 'partial', paid: 'paid'}[s] || 'unpaid');
const FM_UPLOADS = '<?php echo BASE_URL; ?>uploads/mounting_payments/';
const fileLink = f => f ? `<a href="${FM_UPLOADS + encodeURIComponent(f)}" target="_blank" rel="noopener" title="Open attachment" style="color:#2563eb"><i class="fas fa-paperclip"></i> View</a>` : '-';
const kindLabel = k => ({payment: 'Payment'}[k] || k);



/* ---------- form ---------- */
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !Swal.isVisible()) ['ledgerModal', 'conModal', 'tripModal', 'viewModal', 'payModal', 'bulkModal'].forEach(closeModal); });
async function openTrip(id) {
    await loadConOptions();
    tId.value = ''; tDate.value = todayISO(); tName.value = tMobile.value = tBrand.value = tRate.value = tFood.value = tTravel.value = tMisc.value = tDesc.value = tMountDate.value = ''; tMountDate.max = todayISO();
    tConSel.value = '';
    LINES = []; tripModalTitle.textContent = 'New Mounting Entry';
    if (id) {
        try {
            const {trip} = await api('GET', {action: 'get', id});
            tId.value = trip.id; tDate.value = trip.trip_date; selectConByName(trip.contractor_name, trip.contractor_mobile); tMobile.value = trip.contractor_mobile || '';
            tRate.value = +trip.rate_per_sqft || ''; tFood.value = +trip.food_amount || ''; tTravel.value = +trip.travel_amount || ''; tMisc.value = +trip.misc_amount || ''; tDesc.value = trip.description || ''; tBrand.value = trip.brand_campaign || ''; tMountDate.value = trip.mounting_date || '';
            LINES = trip.sites.map(s => ({site_id: +s.site_id, site_code: s.site_code, site_name: s.site_name, location: s.location, owner_type: s.owner_type, sqft: +s.sqft, remarks: s.remarks || ''}));
            tripModalTitle.textContent = 'Edit ' + trip.trip_number;
        } catch (e) { return toast('error', e.message); }
    }
    renderLines(); tripModal.style.display = 'flex';
}

// Mirrors calculateMountingTrip() in includes/mounting_functions.php (server recalculates on save)
function calc() {
    const r2 = v => Math.round((v + Number.EPSILON) * 100) / 100;
    const rate = Math.max(0, +tRate.value || 0);
    const over = r2(Math.max(0, +tFood.value || 0) + Math.max(0, +tTravel.value || 0) + Math.max(0, +tMisc.value || 0));
    const totalSqft = r2(LINES.reduce((a, l) => a + Math.max(0, +l.sqft || 0), 0));
    let alloc = 0;
    const rows = LINES.map((l, i) => {
        const sqft = r2(Math.max(0, +l.sqft || 0)), labour = r2(sqft * rate);
        let share = i === LINES.length - 1 ? r2(over - alloc) : (totalSqft > 0 ? r2(over * sqft / totalSqft) : r2(over / LINES.length));
        alloc = r2(alloc + share);
        return {labour, share, total: r2(labour + share)};
    });
    const labour = r2(rows.reduce((a, r) => a + r.labour, 0));
    return {rows, totalSqft, labour, over, total: r2(labour + over)};
}
function renderLines() {
    const c = calc();
    lineRows.innerHTML = LINES.length ? LINES.map((l, i) => `<tr>
        <td><b>${esc(l.site_code)}</b></td><td>${esc(l.site_name || '')}<div style="font-size:.7rem;color:#64748b">${esc(l.location || '')}</div></td>
        <td>${ownerLabel(l.owner_type)}</td>
        <td class="num"><input type="number" step="0.01" min="0" value="${l.sqft === '' ? '' : l.sqft}" placeholder="sqft" title="Edit to the sqft actually mounted" style="width:90px;text-align:right;${+l.sqft > 0 ? '' : 'border-color:#f59e0b;background:#fffbeb'}" oninput="LINES[${i}].sqft=this.value;this.style.borderColor=+this.value>0?'':'#f59e0b';recalc(false)">${+l.master_sqft > 0 ? `<div style="font-size:.66rem;color:#64748b;margin-top:2px">Site: ${num(l.master_sqft)} sqft</div>` : ''}</td>
        <td class="num" id="lb${i}">${inr(c.rows[i].labour)}</td><td class="num" id="ov${i}">${inr(c.rows[i].share)}</td><td class="num" id="tt${i}"><b>${inr(c.rows[i].total)}</b></td>
        <td><input type="text" value="${esc(l.remarks)}" maxlength="255" style="width:130px" oninput="LINES[${i}].remarks=this.value"></td>
        <td><button class="fm-btn ghost sm" onclick="LINES.splice(${i},1);renderLines()" style="color:#dc2626"><i class="fas fa-times"></i></button></td></tr>`).join('')
        : '<tr><td colspan="9" style="text-align:center;padding:1rem;color:#94a3b8">Search and add sites above.</td></tr>';
    paintTotals(c);
}
function recalc(full = true) {
    const c = calc();
    c.rows.forEach((r, i) => { const a = document.getElementById('lb' + i); if (a) { a.textContent = inr(r.labour); document.getElementById('ov' + i).textContent = inr(r.share); document.getElementById('tt' + i).innerHTML = '<b>' + inr(r.total) + '</b>'; } });
    paintTotals(c);
}
function paintTotals(c) { sSqft.textContent = num(c.totalSqft); sLabour.textContent = inr(c.labour); sOver.textContent = inr(c.over); sTotal.textContent = inr(c.total); }

let sugTimer;
function searchSites() {
    clearTimeout(sugTimer);
    const q = siteSearch.value.trim();
    if (q.length < 1) { siteSug.style.display = 'none'; return; }
    sugTimer = setTimeout(async () => {
        try {
            const {sites} = await api('GET', {action: 'sites', q});
            siteSug.innerHTML = sites.length ? sites.map(s => `<div onclick='addSite(${JSON.stringify(s).replace(/'/g, "&#39;")})'><b>${esc(s.site_code)}</b> · ${esc(s.name || '')} <span style="color:#64748b">${esc(s.location || '')} · ${ownerLabel(s.owner_type)} · ${num(siteSqft(s))} sqft</span></div>`).join('') : '<div style="color:#94a3b8">No site found</div>';
            siteSug.style.display = 'block';
        } catch (e) { siteSug.style.display = 'none'; }
    }, 250);
}
// Site size from the master: sqft, else width x height
const siteSqft = s => Math.round((+s.sqft > 0 ? +s.sqft : (+s.width || 0) * (+s.height || 0)) * 100) / 100;
function addSite(s) {
    siteSug.style.display = 'none'; siteSearch.value = '';
    if (LINES.some(l => l.site_id === +s.id)) return toast('info', 'Site already added');
    // Pre-filled with the site's sqft from the master; edit it to the sqft actually mounted
    const def = siteSqft(s);
    LINES.push({site_id: +s.id, site_code: s.site_code, site_name: s.name, location: s.location, owner_type: s.owner_type, sqft: def > 0 ? def : '', master_sqft: def, remarks: ''});
    renderLines();
    const inputs = lineRows.querySelectorAll('input[type=number]'); if (inputs.length) { const el = inputs[inputs.length - 1]; el.focus(); el.select(); }
}
/* contractor dropdown (from the Labour Contractor master) */
let CON_OPTS = [];
async function loadConOptions() {
    try { CON_OPTS = (await api('GET', {action: 'contractor_options'})).contractors; } catch (e) { CON_OPTS = []; }
    tConSel.innerHTML = '<option value="">Select contractor…</option>' + CON_OPTS.map(c => `<option value="${c.id}">${esc(c.name)}${c.mobile ? ' · ' + esc(c.mobile) : ''}</option>`).join('')
        + (PERM.edit ? '<option value="__new">+ Add new contractor…</option>' : '');
}
function selectConByName(name, mobile) {
    const c = CON_OPTS.find(x => x.name.trim().toLowerCase() === String(name || '').trim().toLowerCase());
    if (!c && name) {   // inactive or old name: keep it selectable for this entry
        const o = document.createElement('option'); o.value = 'name:' + name; o.textContent = name + (mobile ? ' · ' + mobile : '') + ' (inactive)';
        tConSel.insertBefore(o, tConSel.options[1] || null);
    }
    tConSel.value = c ? String(c.id) : (name ? 'name:' + name : '');
    tName.value = name || '';
}
async function pickConOption() {
    const v = tConSel.value;
    if (v === '__new') {
        tConSel.value = '';
        const r = await SwalX.fire({title: 'New labour contractor', html: `<div style="text-align:left"><label style="font-size:.75rem;font-weight:700">Name *</label><input id="nqName" class="swal2-input" style="margin:4px 0 10px;width:100%" maxlength="150">
            <label style="font-size:.75rem;font-weight:700">Mobile</label><input id="nqMobile" class="swal2-input" style="margin:4px 0;width:100%" maxlength="16" placeholder="e.g. 9876543210"></div>`,
            showCancelButton: true, confirmButtonText: 'Add', focusConfirm: false,
            preConfirm: async () => {
                const name = document.getElementById('nqName').value.trim(), mobile = document.getElementById('nqMobile').value.trim();
                if (!name) { Swal.showValidationMessage('Enter the contractor name'); return false; }
                try { const d = await api('POST', {action: 'contractor_save', id: 0, name, mobile, is_active: 1}); return {id: d.id, name, mobile}; }
                catch (e) { Swal.showValidationMessage(e.message); return false; }
            }});
        if (!r.isConfirmed || !r.value) return;
        await loadConOptions();
        tConSel.value = String(r.value.id);
    }
    const c = CON_OPTS.find(x => String(x.id) === tConSel.value);
    if (c) { tName.value = c.name; tMobile.value = c.mobile || ''; }
    else if (tConSel.value.startsWith('name:')) { tName.value = tConSel.value.slice(5); }
    else { tName.value = ''; }
}
document.addEventListener('click', e => { if (!e.target.closest('.fm-sug') && e.target !== siteSearch) siteSug.style.display = 'none'; });

async function saveTrip() {
    if (!tName.value.trim()) return toast('warning', 'Select the labour contractor');
    if (!LINES.length) return toast('warning', 'Add at least one site');
    const noSqft = LINES.find(l => !(+l.sqft > 0));
    if (noSqft) return toast('warning', 'Enter the sqft mounted for site ' + noSqft.site_code);
    saveBtn.disabled = true;
    try {
        const d = await api('POST', {action: 'save', id: +tId.value || 0, trip_date: tDate.value, contractor_name: tName.value.trim(), contractor_mobile: tMobile.value.trim(),
            rate_per_sqft: tRate.value || 0, food_amount: tFood.value || 0, travel_amount: tTravel.value || 0, misc_amount: tMisc.value || 0, mounting_date: tMountDate.value, brand_campaign: tBrand.value.trim(), description: tDesc.value,
            sites: LINES.map(l => ({site_id: l.site_id, sqft: l.sqft, remarks: l.remarks}))});
        closeModal('tripModal'); toast('success', d.message + ' (' + d.trip_number + ')'); loadTrips();
    } catch (e) { toast('error', e.message); }
    saveBtn.disabled = false;
}

/* ---------- view / actions ---------- */
async function viewTrip(id) {
    try {
        const {trip: t} = await api('GET', {action: 'get', id});
        viewTitle.textContent = t.trip_number + ' · ' + fmtDate(t.trip_date);
        viewBody.innerHTML = `<div id="printArea">
            <div class="fm-grid" style="margin-bottom:10px;font-size:.84rem">
                <div><label>Contractor</label>${esc(t.contractor_name)}</div><div><label>Mobile</label>${esc(t.contractor_mobile || '-')}</div>
                <div><label>Mounting date</label>${t.mounting_date ? fmtDate(t.mounting_date) : '-'}</div>
                <div><label>Brand / Campaign</label>${esc(t.brand_campaign || '-')}</div>
                <div><label>Rate / sqft</label>${inr(t.rate_per_sqft)}</div><div><label>Status</label>${esc(t.approval_status.replace('_', ' '))} · ${payLabel(t.payment_status)} (paid ${inr(t.paid_amount)}, balance ${inr(+t.total_amount - +t.paid_amount)})</div>
            </div>
            ${t.description ? `<div style="font-size:.82rem;margin-bottom:10px"><label>Description</label>${esc(t.description)}</div>` : ''}
            <table class="fm-table" style="border:1px solid #e2e8f0">
                <thead><tr><th>Site</th><th>Type</th><th class="num">Sqft</th><th class="num">Labour</th><th class="num">Food/Travel/Misc</th><th class="num">Site Cost</th></tr></thead>
                <tbody>${t.sites.map(s => `<tr><td><b>${esc(s.site_code)}</b> ${esc(s.site_name || '')}<div style="font-size:.7rem;color:#64748b">${esc(s.location || '')}${s.remarks ? ' · ' + esc(s.remarks) : ''}</div></td><td>${ownerLabel(s.owner_type)}</td><td class="num">${num(s.sqft)}</td><td class="num">${inr(s.labour_amount)}</td><td class="num">${inr(s.overhead_share)}</td><td class="num"><b>${inr(s.total_cost)}</b></td></tr>`).join('')}</tbody>
                <tfoot><tr style="font-weight:800"><td colspan="2">Total</td><td class="num">${num(t.total_sqft)}</td><td class="num">${inr(t.labour_amount)}</td><td class="num">${inr(+t.food_amount + +t.travel_amount + +t.misc_amount)}</td><td class="num">${inr(t.total_amount)}</td></tr></tfoot>
            </table>
            <div style="font-size:.78rem;color:#475569;margin-top:8px">Food ${inr(t.food_amount)} · Travel ${inr(t.travel_amount)} · Misc ${inr(t.misc_amount)}</div></div>`;
        viewModal.style.display = 'flex';
    } catch (e) { toast('error', e.message); }
}
function printView() {
    const w = window.open('', '_blank');
    w.document.write(`<html><head><title>${esc(viewTitle.textContent)}</title><style>body{font-family:Arial,sans-serif;font-size:13px;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px;text-align:left}.num{text-align:right}label{display:block;font-size:11px;color:#555;text-transform:uppercase}.fm-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}</style></head><body><h2>Flex Mounting · ${esc(viewTitle.textContent)}</h2>${document.getElementById('printArea').innerHTML}</body></html>`);
    w.document.close(); w.focus(); w.print();
}
async function decide(id, action) {
    let reason = '';
    if (action === 'reject') {
        const r = await SwalX.fire({title: 'Reject entry?', input: 'text', inputPlaceholder: 'Reason', showCancelButton: true, confirmButtonText: 'Reject', confirmButtonColor: '#ef4444'});
        if (!r.isConfirmed) return; reason = r.value || '';
    } else {
        const r = await SwalX.fire({title: 'Approve entry?', text: 'Its cost will be added to the sites\' P&L.', icon: 'question', showCancelButton: true, confirmButtonText: 'Approve', confirmButtonColor: '#10b981'});
        if (!r.isConfirmed) return;
    }
    const res = await fetch(FM_APPROVE, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({entity_type: 'mounting_trip', entity_id: id, action, reason})});
    const d = await res.json().catch(() => ({success: false, message: 'Server error'}));
    d.success ? (toast('success', action === 'approve' ? 'Approved' : 'Rejected'), loadTrips()) : toast('error', d.message);
}
/* ---------- payments of an entry ---------- */
let PAYTRIP = null;
const todayISO = () => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
function modeOptions(sel) { sel.innerHTML = MODES.map(m => `<option>${m}</option>`).join(''); }
modeOptions(pMode);

async function openPayments(id) {
    try {
        const d = await api('GET', {action: 'payments', id});
        PAYTRIP = d.trip;
        const t = d.trip, paidAmt = +t.paid_amount;
        const waiting = d.payments.filter(p => p.status === 'active' && p.approval_status === 'pending_approval').reduce((a, p) => a + +p.amount, 0);
        const bal = Math.round((+t.total_amount - paidAmt - waiting) * 100) / 100;
        payTitle.textContent = 'Payments · ' + t.trip_number + ' · ' + t.contractor_name;
        payKv.innerHTML = `<span>Total <b>${inr(t.total_amount)}</b></span><span>Paid <b style="color:#2563eb">${inr(paidAmt)}</b></span>
            ${waiting ? `<span>Awaiting approval <b style="color:#b45309">${inr(waiting)}</b></span>` : ''}<span>Balance <b style="color:${bal > 0 ? '#dc2626' : '#16a34a'}">${inr(bal)}</b></span><span class="fm-pill ${payPill(t.payment_status)}">${payLabel(t.payment_status)}</span>`;
        payRows.innerHTML = d.payments.length ? d.payments.map(p => payRowHtml(p)).join('')
            : '<tr><td colspan="8" style="text-align:center;padding:1rem;color:#94a3b8">No payments yet.</td></tr>';
        const canPay = PERM.edit && bal > 0.009;
        payAddBox.hidden = !canPay;
        if (canPay) {
            pAmount.value = bal.toFixed(2); pAmount.max = bal; pDate.value = todayISO(); pDate.max = todayISO(); pMode.value = 'Cash'; pRef.value = pNotes.value = ''; pFile.value = '';
        }
        payModal.style.display = 'flex';
    } catch (e) { toast('error', e.message); }
}
function payRowHtml(p, withRemaining) {
    const rev = p.status === 'reversed';
    let act = '';
    const waitAp = !rev && p.approval_status === 'pending_approval', rejected = p.approval_status === 'rejected';
    if (waitAp && PERM.admin) act += `<button class="fm-btn green sm" onclick="decidePayment(${p.id},'approve')" title="Approve payment"><i class="fas fa-check"></i></button> <button class="fm-btn red sm" onclick="decidePayment(${p.id},'reject')" title="Reject payment"><i class="fas fa-times"></i></button> `;
    if (!rev && !waitAp && !rejected && PERM.admin) act += `<button class="fm-btn ghost sm" onclick="revertPayment(${p.id})" title="Revert (keeps a record)"><i class="fas fa-undo"></i> Revert</button>`;
    if (PERM.admin) act += ` <button class="fm-btn ghost sm" onclick="deletePayment(${p.id})" title="Delete wrong entry" style="color:#dc2626"><i class="fas fa-trash"></i></button>`;
    const bulk = +p.bulk_count > 1 ? `<div style="font-size:.68rem;color:#7c3aed">Bulk: ${+p.bulk_count} entries, ${inr(p.bulk_total)}<br>${esc(p.bulk_entries || '')}</div>` : '';
    return `<tr class="${rev ? 'rev' : ''}"><td>${fmtDate(p.paid_date)}</td><td><b>${esc(p.payment_number || '-')}</b>${bulk}</td>
        <td>${esc(p.payment_mode || '')}${p.payment_ref ? '<div style="font-size:.7rem;color:#64748b">' + esc(p.payment_ref) + '</div>' : ''}</td>
        <td style="max-width:200px">${esc(p.notes || '')}${rev && p.reverse_reason ? '<div style="font-size:.7rem">Reverted: ' + esc(p.reverse_reason) + '</div>' : ''}</td>
        <td>${fileLink(p.attachment)}</td>
        <td class="num"><b>${inr(p.amount)}</b></td>${withRemaining ? `<td class="num">${rev ? '-' : inr(p.remaining)}</td>` : ''}
        <td>${payStatusPill(p)}</td><td style="white-space:nowrap">${act}</td></tr>`;
}
async function savePayment() {
    pSaveBtn.disabled = true;
    try {
        const d = await apiForm({action: 'pay', id: PAYTRIP.id, amount: pAmount.value, paid_date: pDate.value, payment_mode: pMode.value, payment_ref: pRef.value, notes: pNotes.value}, pFile);
        toast('success', d.message); await openPayments(PAYTRIP.id); loadTrips();
    } catch (e) { toast('error', e.message); }
    pSaveBtn.disabled = false;
}
function payStatusPill(p) {
    if (p.approval_status === 'rejected') return '<span class="fm-pill rejected">Rejected</span>' + (p.rejection_reason ? '<div style="font-size:.68rem;color:#991b1b">' + esc(p.rejection_reason) + '</div>' : '');
    if (p.status === 'reversed') return '<span class="fm-pill reversed">Reverted</span>';
    if (p.approval_status === 'pending_approval') return '<span class="fm-pill pending_approval">Awaiting approval</span>';
    return '<span class="fm-pill approved">Approved</span>';
}
async function decidePayment(pid, decision) {
    let reason = '';
    if (decision === 'reject') {
        const r = await SwalX.fire({title: 'Reject this payment?', text: 'The entries go back to their old balance.', input: 'text', inputPlaceholder: 'Reason', icon: 'warning', showCancelButton: true, confirmButtonText: 'Reject', confirmButtonColor: '#ef4444'});
        if (!r.isConfirmed) return; reason = r.value || '';
    } else {
        const r = await SwalX.fire({title: 'Approve this payment?', text: 'It will count as paid on every entry it covers.', icon: 'question', showCancelButton: true, confirmButtonText: 'Approve', confirmButtonColor: '#10b981'});
        if (!r.isConfirmed) return;
    }
    try {
        const d = await api('POST', {action: 'approve_payment', payment_id: pid, decision, reason});
        toast('success', d.message);
        if (payModal.style.display === 'flex' && PAYTRIP) openPayments(PAYTRIP.id);
        reloadTab();
    } catch (e) { toast('error', e.message); }
}
async function deleteContractor(id) {
    const r = await SwalX.fire({title: 'Delete this contractor?', text: 'Only contractors without entries can be deleted.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#ef4444'});
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'contractor_delete', id}); toast('success', d.message); loadContractors(); } catch (e) { toast('error', e.message); }
}
async function revertPayment(pid) {
    const r = await SwalX.fire({title: 'Revert this payment?', text: 'It stays in the history as reverted and the balance goes back up.', input: 'text', inputPlaceholder: 'Reason (optional)', icon: 'warning', showCancelButton: true, confirmButtonText: 'Revert', confirmButtonColor: '#f59e0b'});
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'revert', payment_id: pid, reason: r.value || ''}); toast('success', d.message); refreshOpenModals(); } catch (e) { toast('error', e.message); }
}
async function deletePayment(pid) {
    const r = await SwalX.fire({title: 'Delete this payment?', text: 'Use this only for a wrong entry. It is removed completely and cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#ef4444'});
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'delete_payment', payment_id: pid}); toast('success', d.message); refreshOpenModals(); } catch (e) { toast('error', e.message); }
}
function refreshOpenModals() {
    if (payModal.style.display === 'flex' && PAYTRIP) openPayments(PAYTRIP.id);
    loadTrips();
}

async function deleteTrip(id) {
    const r = await SwalX.fire({title: 'Delete this entry?', text: 'Its site costs will be removed too. This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#ef4444'});
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'delete', id}); toast('success', d.message); loadTrips(); } catch (e) { toast('error', e.message); }
}
function exportCsv() {
    if (TAB === 'payments') {
        const head = ['Payment No', 'Date', 'Contractor', 'Entries', 'Entry details', 'Mode', 'Reference', 'Notes', 'Amount', 'Status'];
        const rows = PAYS.map(p => [p.payment_number || p.ref, p.paid_date, p.contractor_name, p.rows_count, (p.entries || '').replace(/Rs\./g, 'Rs '), p.payment_mode || '', p.payment_ref || '', p.notes || '', +p.active_rows ? p.amount : p.original_amount, +p.active_rows ? 'Active' : 'Reverted']);
        const csv = [head, ...rows].map(r => r.map(v => '"' + String(v ?? '').replace(/"/g, '""') + '"').join(',')).join('\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], {type: 'text/csv'})); a.download = 'flex_mounting_payments.csv'; a.click();
        return;
    }
    const head = ['Entry No', 'Date', 'Mounting Date', 'Contractor', 'Mobile', 'Brand / Campaign', 'Sites', 'Sqft', 'Rate', 'Labour', 'Food', 'Travel', 'Misc', 'Total', 'Paid', 'Balance', 'Approval', 'Payment', 'Last Paid'];
    const rows = TRIPS.map(t => [t.trip_number, t.trip_date, t.mounting_date || '', t.contractor_name, t.contractor_mobile || '', t.brand_campaign || '', t.site_codes || '', t.total_sqft, t.rate_per_sqft, t.labour_amount, t.food_amount, t.travel_amount, t.misc_amount, t.total_amount, t.paid_amount, (+t.total_amount - +t.paid_amount).toFixed(2), t.approval_status, payLabel(t.payment_status), t.paid_date || '']);
    const csv = [head, ...rows].map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + csv], {type: 'text/csv'})); a.download = 'flex_mounting_register.csv'; a.click();
}

loadTrips();
const viewId = new URLSearchParams(location.search).get('view');
if (viewId) viewTrip(+viewId);
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
