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
</style>

<div class="fm-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
        <div>
            <h2 style="margin:0;font-size:1.25rem;font-weight:800;color:#0f172a;">Flex Mounting Register</h2>
            <div style="font-size:.78rem;color:#64748b;">Labour contractor trips, site-wise cost split and contractor payments. Approved trips go into each site's P&amp;L.</div>
        </div>
        <?php if ($perm['add']): ?><button class="fm-btn primary" onclick="openTrip()"><i class="fas fa-plus"></i> New Mounting Entry</button><?php endif; ?>
    </div>

    <div class="fm-cards">
        <div class="fm-card"><div class="lbl">Entries</div><div class="val" id="cTrips">0</div></div>
        <div class="fm-card"><div class="lbl">Total Sqft</div><div class="val" id="cSqft">0</div></div>
        <div class="fm-card"><div class="lbl">Total Expense (approved)</div><div class="val" id="cTotal">₹0</div></div>
        <div class="fm-card"><div class="lbl">Paid to Contractors</div><div class="val" id="cPaid" style="color:#2563eb">₹0</div></div>
        <div class="fm-card"><div class="lbl">Pending Payment</div><div class="val" id="cPending" style="color:#dc2626">₹0</div></div>
    </div>

    <div class="fm-bar">
        <input type="date" id="fFrom" title="From"> <span style="color:#94a3b8">to</span> <input type="date" id="fTo" title="To">
        <input type="text" id="fContractor" placeholder="Contractor name">
        <select id="fApproval"><option value="">All approval</option><option value="pending_approval">Pending approval</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
        <select id="fPayment"><option value="">All payment</option><option value="pending">Unpaid</option><option value="paid">Paid</option></select>
        <button class="fm-btn primary sm" onclick="loadTrips()"><i class="fas fa-search"></i> Filter</button>
        <button class="fm-btn ghost sm" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
        <button class="fm-btn ghost sm" onclick="exportCsv()" style="margin-left:auto"><i class="fas fa-file-csv"></i> Export CSV</button>
    </div>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead><tr>
                <th>Entry No / Date</th><th>Contractor</th><th>Sites</th><th class="num">Sqft</th><th class="num">Rate</th>
                <th class="num">Labour</th><th class="num">Food</th><th class="num">Travel</th><th class="num">Misc</th><th class="num">Total</th>
                <th>Status</th><th>Action</th>
            </tr></thead>
            <tbody id="tripRows"><tr><td colspan="12" style="text-align:center;padding:2rem;color:#94a3b8">Loading…</td></tr></tbody>
        </table>
    </div>

    <div class="fm-table-wrap">
        <div class="fm-h"><span><i class="fas fa-user-hard-hat" style="color:#0f766e"></i> Contractor-wise Payment Summary (approved entries)</span></div>
        <table class="fm-table">
            <thead><tr><th>Contractor</th><th>Mobile</th><th class="num">Entries</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Pending</th></tr></thead>
            <tbody id="summaryRows"></tbody>
        </table>
    </div>
</div>

<!-- Entry form -->
<div class="fm-modal-bg" id="tripModal">
    <div class="fm-modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1.05rem;font-weight:800" id="tripModalTitle">New Mounting Entry</h3>
            <button class="fm-btn ghost sm" onclick="closeModal('tripModal')"><i class="fas fa-times"></i></button>
        </div>
        <input type="hidden" id="tId">
        <div class="fm-grid">
            <div><label>Date *</label><input type="date" id="tDate"></div>
            <div style="position:relative"><label>Labour Contractor Name *</label><input type="text" id="tName" autocomplete="off" oninput="suggestContractor()"><div class="fm-sug" id="nameSug" style="display:none"></div></div>
            <div><label>Mobile</label><input type="text" id="tMobile" maxlength="13" placeholder="10-digit"></div>
            <div><label>Rate per Sqft (₹)</label><input type="number" step="0.01" min="0" id="tRate" oninput="recalc()"></div>
            <div><label>Fooding (₹)</label><input type="number" step="0.01" min="0" id="tFood" oninput="recalc()"></div>
            <div><label>Travelling (₹)</label><input type="number" step="0.01" min="0" id="tTravel" oninput="recalc()"></div>
            <div><label>Miscellaneous (₹)</label><input type="number" step="0.01" min="0" id="tMisc" oninput="recalc()"></div>
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
            <div style="display:flex;gap:6px"><button class="fm-btn ghost sm" onclick="printView()"><i class="fas fa-print"></i> Print</button><button class="fm-btn ghost sm" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button></div>
        </div>
        <div id="viewBody"></div>
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

async function api(method, params) {
    let res;
    if (method === 'GET') res = await fetch(FM_API + '?' + new URLSearchParams(params));
    else res = await fetch(FM_API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(params)});
    const data = await res.json().catch(() => ({success: false, message: 'Server error'}));
    if (!data.success) throw new Error(data.message || 'Request failed');
    return data;
}
function toast(icon, title) { Swal.fire({icon, title, timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success'}); }

async function loadTrips() {
    try {
        const d = await api('GET', {action: 'list', from: fFrom.value, to: fTo.value, contractor: fContractor.value.trim(), approval_status: fApproval.value, payment_status: fPayment.value});
        TRIPS = d.trips; renderTrips(); renderSummary(d.summary);
    } catch (e) { tripRows.innerHTML = `<tr><td colspan="12" style="text-align:center;padding:2rem;color:#dc2626">${esc(e.message)}</td></tr>`; }
}
function resetFilters() { fFrom.value = fTo.value = fContractor.value = fApproval.value = fPayment.value = ''; loadTrips(); }

function renderTrips() {
    let sqft = 0, total = 0, paid = 0, pending = 0;
    if (!TRIPS.length) { tripRows.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:2rem;color:#94a3b8">No mounting entries yet.</td></tr>'; }
    else tripRows.innerHTML = TRIPS.map(t => {
        sqft += +t.total_sqft;
        if (t.approval_status === 'approved') { total += +t.total_amount; if (t.payment_status === 'paid') paid += +t.total_amount; else pending += +t.total_amount; }
        const locked = t.payment_status === 'paid';
        let act = `<button class="fm-btn ghost sm" onclick="viewTrip(${t.id})" title="View"><i class="fas fa-eye"></i></button>`;
        if (!locked && PERM.edit && (t.approval_status !== 'approved' || PERM.admin)) act += ` <button class="fm-btn ghost sm" onclick="openTrip(${t.id})" title="Edit"><i class="fas fa-pen"></i></button>`;
        if (PERM.admin && t.approval_status === 'pending_approval') act += ` <button class="fm-btn green sm" onclick="decide(${t.id},'approve')" title="Approve"><i class="fas fa-check"></i></button> <button class="fm-btn red sm" onclick="decide(${t.id},'reject')" title="Reject"><i class="fas fa-times"></i></button>`;
        if (PERM.edit && t.approval_status === 'approved' && !locked) act += ` <button class="fm-btn blue sm" onclick="payTrip(${t.id})" title="Mark paid"><i class="fas fa-rupee-sign"></i> Pay</button>`;
        if (PERM.admin && locked) act += ` <button class="fm-btn ghost sm" onclick="unpayTrip(${t.id})" title="Reverse payment"><i class="fas fa-undo"></i></button>`;
        if (!locked && PERM.delete) act += ` <button class="fm-btn ghost sm" onclick="deleteTrip(${t.id})" title="Delete" style="color:#dc2626"><i class="fas fa-trash"></i></button>`;
        const apLabel = {pending_approval: 'Pending approval', approved: 'Approved', rejected: 'Rejected'}[t.approval_status];
        return `<tr>
            <td><b>${esc(t.trip_number)}</b><div style="font-size:.7rem;color:#64748b">${fmtDate(t.trip_date)}</div></td>
            <td>${esc(t.contractor_name)}<div style="font-size:.7rem;color:#64748b">${esc(t.contractor_mobile || '')}</div></td>
            <td style="max-width:220px">${esc(t.site_codes || '')}<div style="font-size:.7rem;color:#64748b">${t.site_count} site(s)</div></td>
            <td class="num">${num(t.total_sqft)}</td><td class="num">${inr(t.rate_per_sqft)}</td><td class="num">${inr(t.labour_amount)}</td>
            <td class="num">${inr(t.food_amount)}</td><td class="num">${inr(t.travel_amount)}</td><td class="num">${inr(t.misc_amount)}</td>
            <td class="num"><b>${inr(t.total_amount)}</b></td>
            <td><span class="fm-pill ${t.approval_status}">${apLabel}</span><br><span class="fm-pill ${locked ? 'paid' : 'unpaid'}" style="margin-top:3px">${locked ? 'Paid ' + fmtDate(t.paid_date) : 'Unpaid'}</span>
                ${t.rejection_reason ? `<div style="font-size:.68rem;color:#991b1b;margin-top:2px">${esc(t.rejection_reason)}</div>` : ''}</td>
            <td style="white-space:nowrap">${act}</td></tr>`;
    }).join('');
    cTrips.textContent = TRIPS.length; cSqft.textContent = num(sqft); cTotal.textContent = inr(total); cPaid.textContent = inr(paid); cPending.textContent = inr(pending);
}
function renderSummary(rows) {
    summaryRows.innerHTML = rows.length ? rows.map(r => `<tr><td>${esc(r.contractor_name)}</td><td>${esc(r.contractor_mobile || '')}</td><td class="num">${r.trips}</td><td class="num">${inr(r.total)}</td><td class="num" style="color:#2563eb">${inr(r.paid)}</td><td class="num" style="color:#dc2626;font-weight:700">${inr(r.pending)}</td></tr>`).join('')
        : '<tr><td colspan="6" style="text-align:center;padding:1.2rem;color:#94a3b8">No approved entries.</td></tr>';
}

/* ---------- form ---------- */
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
async function openTrip(id) {
    tId.value = ''; tDate.value = new Date().toISOString().slice(0, 10); tName.value = tMobile.value = tRate.value = tFood.value = tTravel.value = tMisc.value = tDesc.value = '';
    LINES = []; tripModalTitle.textContent = 'New Mounting Entry';
    if (id) {
        try {
            const {trip} = await api('GET', {action: 'get', id});
            tId.value = trip.id; tDate.value = trip.trip_date; tName.value = trip.contractor_name; tMobile.value = trip.contractor_mobile || '';
            tRate.value = +trip.rate_per_sqft || ''; tFood.value = +trip.food_amount || ''; tTravel.value = +trip.travel_amount || ''; tMisc.value = +trip.misc_amount || ''; tDesc.value = trip.description || '';
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
        <td class="num"><input type="number" step="0.01" min="0" value="${l.sqft}" style="width:90px;text-align:right" oninput="LINES[${i}].sqft=this.value;recalc(false)"></td>
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
            siteSug.innerHTML = sites.length ? sites.map(s => `<div onclick='addSite(${JSON.stringify(s).replace(/'/g, "&#39;")})'><b>${esc(s.site_code)}</b> · ${esc(s.name || '')} <span style="color:#64748b">${esc(s.location || '')} · ${ownerLabel(s.owner_type)} · ${num(+s.sqft || (+s.width * +s.height))} sqft</span></div>`).join('') : '<div style="color:#94a3b8">No site found</div>';
            siteSug.style.display = 'block';
        } catch (e) { siteSug.style.display = 'none'; }
    }, 250);
}
function addSite(s) {
    siteSug.style.display = 'none'; siteSearch.value = '';
    if (LINES.some(l => l.site_id === +s.id)) return toast('info', 'Site already added');
    LINES.push({site_id: +s.id, site_code: s.site_code, site_name: s.name, location: s.location, owner_type: s.owner_type, sqft: +s.sqft || Math.round(+s.width * +s.height * 100) / 100 || 0, remarks: ''});
    renderLines();
}
function suggestContractor() {
    clearTimeout(sugTimer);
    const q = tName.value.trim();
    if (q.length < 1) { nameSug.style.display = 'none'; return; }
    sugTimer = setTimeout(async () => {
        try {
            const {contractors} = await api('GET', {action: 'suggest', q});
            if (!contractors.length) { nameSug.style.display = 'none'; return; }
            nameSug.innerHTML = contractors.map(c => `<div onclick='pickContractor(${JSON.stringify(c).replace(/'/g, "&#39;")})'>${esc(c.contractor_name)} <span style="color:#64748b">${esc(c.contractor_mobile || '')}</span></div>`).join('');
            nameSug.style.display = 'block';
        } catch (e) { nameSug.style.display = 'none'; }
    }, 250);
}
function pickContractor(c) { tName.value = c.contractor_name; if (c.contractor_mobile) tMobile.value = c.contractor_mobile; nameSug.style.display = 'none'; }
document.addEventListener('click', e => { if (!e.target.closest('.fm-sug') && e.target !== siteSearch && e.target !== tName) { siteSug.style.display = 'none'; nameSug.style.display = 'none'; } });

async function saveTrip() {
    if (!tName.value.trim()) return toast('warning', 'Enter labour contractor name');
    if (!LINES.length) return toast('warning', 'Add at least one site');
    saveBtn.disabled = true;
    try {
        const d = await api('POST', {action: 'save', id: +tId.value || 0, trip_date: tDate.value, contractor_name: tName.value.trim(), contractor_mobile: tMobile.value.trim(),
            rate_per_sqft: tRate.value || 0, food_amount: tFood.value || 0, travel_amount: tTravel.value || 0, misc_amount: tMisc.value || 0, description: tDesc.value,
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
                <div><label>Rate / sqft</label>${inr(t.rate_per_sqft)}</div><div><label>Status</label>${esc(t.approval_status.replace('_', ' '))} · ${t.payment_status === 'paid' ? 'Paid ' + fmtDate(t.paid_date) + ' (' + esc(t.payment_mode) + (t.payment_ref ? ', ' + esc(t.payment_ref) : '') + ')' : 'Unpaid'}</div>
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
        const r = await Swal.fire({title: 'Reject entry?', input: 'text', inputPlaceholder: 'Reason', showCancelButton: true, confirmButtonText: 'Reject', confirmButtonColor: '#ef4444'});
        if (!r.isConfirmed) return; reason = r.value || '';
    } else {
        const r = await Swal.fire({title: 'Approve entry?', text: 'Its cost will be added to the sites\' P&L.', icon: 'question', showCancelButton: true, confirmButtonText: 'Approve', confirmButtonColor: '#10b981'});
        if (!r.isConfirmed) return;
    }
    const res = await fetch(FM_APPROVE, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({entity_type: 'mounting_trip', entity_id: id, action, reason})});
    const d = await res.json().catch(() => ({success: false, message: 'Server error'}));
    d.success ? (toast('success', action === 'approve' ? 'Approved' : 'Rejected'), loadTrips()) : toast('error', d.message);
}
async function payTrip(id) {
    const t = TRIPS.find(x => +x.id === id);
    const r = await Swal.fire({
        title: 'Mark paid: ' + inr(t.total_amount),
        html: `<div style="text-align:left;font-size:.85rem">To <b>${esc(t.contractor_name)}</b><br><br>
            <label>Payment date</label><input type="date" id="pDate" class="swal2-input" style="margin:4px 0 10px" value="${new Date().toISOString().slice(0, 10)}">
            <label>Mode</label><select id="pMode" class="swal2-input" style="margin:4px 0 10px">${MODES.map(m => `<option>${m}</option>`).join('')}</select>
            <label>Reference / UTR (optional)</label><input id="pRef" class="swal2-input" style="margin:4px 0" maxlength="100"></div>`,
        showCancelButton: true, confirmButtonText: 'Mark Paid', confirmButtonColor: '#2563eb',
        preConfirm: () => ({paid_date: document.getElementById('pDate').value, payment_mode: document.getElementById('pMode').value, payment_ref: document.getElementById('pRef').value})
    });
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'pay', id, ...r.value}); toast('success', d.message); loadTrips(); } catch (e) { toast('error', e.message); }
}
async function unpayTrip(id) {
    const r = await Swal.fire({title: 'Reverse payment?', text: 'Entry will go back to unpaid.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Reverse'});
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'unpay', id}); toast('success', d.message); loadTrips(); } catch (e) { toast('error', e.message); }
}
async function deleteTrip(id) {
    const r = await Swal.fire({title: 'Delete this entry?', text: 'Its site costs will be removed too. This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#ef4444'});
    if (!r.isConfirmed) return;
    try { const d = await api('POST', {action: 'delete', id}); toast('success', d.message); loadTrips(); } catch (e) { toast('error', e.message); }
}
function exportCsv() {
    const head = ['Entry No', 'Date', 'Contractor', 'Mobile', 'Sites', 'Sqft', 'Rate', 'Labour', 'Food', 'Travel', 'Misc', 'Total', 'Approval', 'Payment', 'Paid Date'];
    const rows = TRIPS.map(t => [t.trip_number, t.trip_date, t.contractor_name, t.contractor_mobile || '', t.site_codes || '', t.total_sqft, t.rate_per_sqft, t.labour_amount, t.food_amount, t.travel_amount, t.misc_amount, t.total_amount, t.approval_status, t.payment_status, t.paid_date || '']);
    const csv = [head, ...rows].map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + csv], {type: 'text/csv'})); a.download = 'flex_mounting_register.csv'; a.click();
}

loadTrips();
const viewId = new URLSearchParams(location.search).get('view');
if (viewId) viewTrip(+viewId);
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
