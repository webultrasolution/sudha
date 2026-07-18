<?php
$activePage = 'payments';
$pageTitle = 'Payment Tracking';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

// Filters
$typeFilter = $_GET['type'] ?? '';
$periodFilter = $_GET['period'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$partnerFilter = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;

$whereClause = "WHERE 1=1";
$queryParams = [];

$activeEntityId = $_SESSION['active_entity_id'] ?? null;
if ($activeEntityId) {
    $whereClause .= " AND p.entity_id = ?";
    $queryParams[] = $activeEntityId;
}

if ($typeFilter === 'receivable') {
    $whereClause .= " AND p.type IN ('receivable', 'credit')";
} elseif ($typeFilter === 'payable') {
    $whereClause .= " AND p.type = 'payable'";
}

if ($partnerFilter > 0) {
    $whereClause .= " AND p.partner_id = ?";
    $queryParams[] = $partnerFilter;
}

// Period filtering
if ($periodFilter === 'today') {
    $whereClause .= " AND DATE(p.payment_date) = CURDATE()";
} elseif ($periodFilter === 'this_month') {
    $whereClause .= " AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY)";
} elseif ($periodFilter === 'last_month') {
    $whereClause .= " AND p.payment_date >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY), INTERVAL 1 MONTH) 
                      AND p.payment_date < DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY)";
} elseif ($periodFilter === 'this_quarter') {
    $whereClause .= " AND QUARTER(p.payment_date) = QUARTER(CURDATE()) AND YEAR(p.payment_date) = YEAR(CURDATE())";
} elseif ($periodFilter === 'this_year') {
    // Financial year starts April 1st
    $currentYear = intval(date('Y'));
    $currentMonth = intval(date('n'));
    if ($currentMonth >= 4) {
        $fyStart = "$currentYear-04-01";
        $fyEnd = ($currentYear + 1) . "-03-31";
    } else {
        $fyStart = ($currentYear - 1) . "-04-01";
        $fyEnd = "$currentYear-03-31";
    }
    $whereClause .= " AND p.payment_date >= ? AND p.payment_date <= ?";
    $queryParams[] = $fyStart;
    $queryParams[] = $fyEnd;
} elseif ($periodFilter === 'custom' && !empty($fromDate) && !empty($toDate)) {
    $whereClause .= " AND p.payment_date >= ? AND p.payment_date <= ?";
    $queryParams[] = $fromDate;
    $queryParams[] = $toDate;
}

// Fetch Payments
$stmt = $pdo->prepare("
    SELECT p.*, pr.name as partner_name, 
           i.invoice_number as inv_num_std, 
           cpr.po_number as inv_num_print,
           po.po_number as po_num_std,
           vpr.po_number as po_num_print,
           prop.proposal_number as prop_num,
           COALESCE(p.rejection_reason, ar.remarks) as rejection_reason
    FROM payments p 
    JOIN partners pr ON p.partner_id = pr.id 
    LEFT JOIN invoices i ON (p.type = 'receivable' AND p.invoice_id = i.id)
    LEFT JOIN client_printing_rates cpr ON (p.type = 'receivable' AND p.invoice_id = cpr.id)
    LEFT JOIN purchase_orders po ON (p.type = 'payable' AND p.proposal_id = po.id)
    LEFT JOIN vendor_printing_rates vpr ON (p.type = 'payable' AND p.proposal_id = vpr.id)
    LEFT JOIN proposals prop ON (p.proposal_id = prop.id)
    LEFT JOIN approval_requests ar ON (ar.entity_type = 'payment' AND ar.entity_id = p.id)
    $whereClause
    ORDER BY p.id DESC
");
$stmt->execute($queryParams);
$payments = $stmt->fetchAll();

$partners = $pdo->query("SELECT id, name FROM partners ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <h2 style="font-size: 1.25rem; margin: 0;">Financial Transactions</h2>
            <?php if (canDelete('financials')): ?>
            <button id="bulkDeleteBtn" class="btn" onclick="bulkDeletePayments()" style="display: none; background: #fee2e2; color: #ef4444; border: 1.5px solid #fecaca; border-radius: 8px; padding: 0.5rem 1rem; font-weight: 700; cursor: pointer; align-items: center; gap: 6px; font-size: 0.85rem; height: 38px; box-sizing: border-box;">
                <i class="fas fa-trash-alt"></i> Delete Selected (<span id="selectedCount">0</span>)
            </button>
            <?php endif; ?>
        </div>
        <?php if (canAdd('financials')): ?>
        <button class="btn btn-primary" onclick="openPaymentModal()" style="background: #0d9488; border-color: #0d9488; height: 38px; display: inline-flex; align-items: center; gap: 6px; box-sizing: border-box;">
            <i class="fas fa-plus"></i> Record Payment
        </button>
        <?php endif; ?>
    </div>

    <!-- Filter Form -->
    <form method="get" action="payments.php" style="display:flex; flex-wrap: wrap; gap:1rem; align-items:flex-end; margin-bottom:1.5rem; background: #f8fafc; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; box-sizing: border-box; width: 100%;">
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Type</label>
            <select name="type" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; min-width:140px; background: white; font-family: inherit; font-size: 0.9rem;">
                <option value="">All Types</option>
                <option value="receivable" <?php echo $typeFilter === 'receivable' ? 'selected' : ''; ?>>Receivable (Income)</option>
                <option value="payable" <?php echo $typeFilter === 'payable' ? 'selected' : ''; ?>>Payable (Expense)</option>
            </select>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Partner</label>
            <select name="partner_id" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; min-width:180px; background: white; font-family: inherit; font-size: 0.9rem;">
                <option value="">All Partners</option>
                <?php foreach ($partners as $pr): ?>
                    <option value="<?php echo $pr['id']; ?>" <?php echo $partnerFilter === intval($pr['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pr['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Period</label>
            <select name="period" id="filter_period" onchange="toggleCustomDates(this.value)" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; min-width:160px; background: white; font-family: inherit; font-size: 0.9rem;">
                <option value="">All Time</option>
                <option value="today" <?php echo $periodFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="this_month" <?php echo $periodFilter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                <option value="last_month" <?php echo $periodFilter === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="this_quarter" <?php echo $periodFilter === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                <option value="this_year" <?php echo $periodFilter === 'this_year' ? 'selected' : ''; ?>>This Financial Year</option>
                <option value="custom" <?php echo $periodFilter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
            </select>
        </div>

        <div id="custom_date_range" style="display: <?php echo $periodFilter === 'custom' ? 'flex' : 'none'; ?>; gap:0.5rem; align-items:center;">
            <div style="display:flex; flex-direction:column; gap:0.35rem;">
                <label style="font-size:0.85rem; color:#475569; font-weight:600;">From</label>
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; background: white; font-family: inherit; font-size: 0.9rem;">
            </div>
            <div style="display:flex; flex-direction:column; gap:0.35rem;">
                <label style="font-size:0.85rem; color:#475569; font-weight:600;">To</label>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; background: white; font-family: inherit; font-size: 0.9rem;">
            </div>
        </div>

        <div style="display:flex; gap:0.5rem; margin-bottom: 2px;">
            <button type="submit" class="btn btn-primary" style="padding:0.625rem 1.25rem; height: 38px; display: inline-flex; align-items: center; background: #0d9488; border-color: #0d9488; font-family: inherit; font-size: 0.9rem; font-weight: 600;">
                <i class="fas fa-filter" style="margin-right: 4px; font-size: 0.85rem;"></i> Filter
            </button>
            <a href="payments.php" class="btn" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:0.625rem 1.25rem; height: 38px; display: inline-flex; align-items: center; text-decoration:none; box-sizing: border-box; justify-content: center; font-family: inherit; font-size: 0.9rem; font-weight: 600;">Reset</a>
        </div>
    </form>

    <script>
    function toggleCustomDates(val) {
        document.getElementById('custom_date_range').style.display = val === 'custom' ? 'flex' : 'none';
    }
    </script>

    <table class="table">
        <thead>
            <tr>
                <?php if (canDelete('financials')): ?>
                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllPayments" onchange="toggleSelectAllPayments(this)" style="width: 16px; height: 16px; accent-color: #ef4444; cursor: pointer;"></th>
                <?php endif; ?>
                <th>Date</th>
                <th>Partner</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Mode</th>
                <th>Reference (Invoice/PO)</th>
                <th>Status</th>
                <?php if (canDelete('financials')): ?>
                <th style="text-align: right; width: 80px;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $p): 
                $ref = 'N/A';
                $isLinked = false;
                if ($p['type'] === 'receivable' || $p['type'] === 'credit') {
                    if (!empty($p['inv_num_std'])) {
                        $ref = $p['inv_num_std'];
                        $isLinked = true;
                    } elseif (!empty($p['inv_num_print'])) {
                        $ref = $p['inv_num_print'];
                        $isLinked = true;
                    } elseif (!empty($p['prop_num'])) {
                        $ref = $p['prop_num'];
                        $isLinked = true;
                    }
                } else {
                    if (!empty($p['po_num_std'])) {
                        $ref = $p['po_num_std'];
                        $isLinked = true;
                    } elseif (!empty($p['po_num_print'])) {
                        $ref = $p['po_num_print'];
                        $isLinked = true;
                    } elseif (!empty($p['prop_num'])) {
                        $ref = $p['prop_num'];
                        $isLinked = true;
                    }
                }

                if ($ref === 'N/A' && !empty($p['notes']) && stripos($p['notes'], 'Against ') === 0) {
                    $extracted = trim(substr($p['notes'], 8));
                    if (!empty($extracted)) {
                        $ref = $extracted;
                        $isLinked = false;
                    }
                }
            ?>
            <tr id="payment-row-<?php echo $p['id']; ?>">
                <?php if (canDelete('financials')): ?>
                <td style="text-align: center;"><input type="checkbox" class="payment-select-chk" value="<?php echo $p['id']; ?>" onchange="updateBulkDeleteButton()" style="width: 16px; height: 16px; accent-color: #ef4444; cursor: pointer;"></td>
                <?php endif; ?>
                <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($p['partner_name']); ?></strong>
                    <?php if (!empty($p['notes'])): ?>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 400; margin-top: 2px;">
                            <i class="far fa-comment-alt" style="font-size: 0.7rem; margin-right: 3px;"></i> 
                            <?php echo htmlspecialchars($p['notes']); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge-<?php echo $p['type'] == 'credit' || $p['type'] == 'receivable' ? 'success' : 'warning'; ?>" style="padding: 0.125rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">
                        <?php echo $p['type'] == 'credit' || $p['type'] == 'receivable' ? 'RECEIVABLE' : 'PAYABLE'; ?>
                    </span>
                    <?php if (stripos($p['notes'] ?? '', 'Advance') !== false): ?>
                        <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 0.125rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 800; margin-left: 0.25rem; display: inline-flex; align-items: center; gap: 2px;">
                            <i class="fas fa-star" style="font-size: 0.6rem;"></i> ADVANCE
                        </span>
                    <?php endif; ?>
                </td>
                <td><?php echo formatCurrency($p['amount']); ?></td>
                <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                <td>
                    <?php if ($ref !== 'N/A'): ?>
                        <?php if ($isLinked): ?>
                            <span style="background: #eff6ff; color: #1e40af; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #bfdbfe;">
                                <i class="fas fa-link" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($ref); ?>
                            </span>
                        <?php else: ?>
                            <span style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #cbd5e1;">
                                <i class="fas fa-info-circle" style="font-size: 0.65rem; color: #94a3b8;"></i> <?php echo htmlspecialchars($ref); ?>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: #cbd5e1;">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($p['approval_status'] ?? '') === 'pending_approval'): ?>
                        <span style="background: #fff7ed; color: #c2410c; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; display: inline-block;">Pending</span>
                    <?php elseif (($p['approval_status'] ?? '') === 'approved' || ($p['approval_status'] ?? '') === ''): ?>
                        <span style="background: #ecfdf5; color: #047857; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; display: inline-block;">Processed</span>
                    <?php else: ?>
                        <span style="background: #fef2f2; color: #b91c1c; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; display: inline-block;">Rejected</span>
                        <?php if (!empty($p['rejection_reason'])): ?>
                            <div style="font-size: 0.7rem; color: #ef4444; margin-top: 4px; font-weight: 600;" title="<?php echo htmlspecialchars($p['rejection_reason']); ?>">
                                Reason: <?php echo htmlspecialchars($p['rejection_reason']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <?php if (canDelete('financials')): ?>
                <td style="text-align: right;">
                    <button class="btn-icon btn-delete" onclick="deleteSinglePayment(<?php echo $p['id']; ?>)" style="color: #ef4444; background: none; border: none; cursor: pointer; padding: 4px; font-size: 0.9rem;" title="Delete Payment">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div style="height: 100px;"></div> recessed placeholder

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content" style="width: 500px;">
        <div class="modal-header">
            <h3>Record New Transaction</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="paymentForm">
            <div class="form-group">
                <label>Partner (Client/Vendor)</label>
                <select id="entity_id" class="p-input" required onchange="loadPartnerDocs(this.value)">
                    <option value="">Select Partner</option>
                    <?php foreach ($partners as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>"><?php echo $pr['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" id="doc_selection_group" style="display: none;">
                <label id="doc_label">Link to Document</label>
                <select id="doc_id" class="p-input">
                    <option value="">No Link (General Payment)</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Transaction Type</label>
                    <select id="p_type" class="p-input" onchange="loadPartnerDocs(document.getElementById('entity_id').value)">
                        <option value="receivable">Income (Receipt from Client)</option>
                        <option value="payable">Expense (Payment to Vendor)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Payment Date</label>
                    <input type="date" id="p_date" class="p-input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Amount (₹)</label>
                    <input type="number" id="p_amount" class="p-input" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Payment Mode</label>
                    <select id="p_mode" class="p-input">
                        <option value="NEFT">Bank Transfer (NEFT/IMPS)</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Transaction ID / Reference</label>
                    <input type="text" id="p_ref" class="p-input" placeholder="e.g. UTR Number or Chq No">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Remarks / Notes</label>
                    <input type="text" id="p_remarks" class="p-input" placeholder="e.g. Advance or Invoice payment">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" id="is_advance" style="width: 16px; height: 16px; accent-color: #0d9488;"> 
                    Mark as Advance Payment
                </label>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePayment()">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
.modal-content { background: white; padding: 2rem; border-radius: 12px; width: 450px; }
.modal-header { display: flex; justify-content: space-between; margin-bottom: 1.5rem; }
.close { cursor: pointer; font-size: 1.5rem; }
.p-input { width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; margin-top: 0.25rem; }
.status-pill.status-paid { background: #dcfce7; color: #166534; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; }
</style>

<script>
function openPaymentModal() { document.getElementById('paymentModal').style.display = 'flex'; }
function closeModal() { document.getElementById('paymentModal').style.display = 'none'; }

function loadPartnerDocs(partnerId) {
    const group = document.getElementById('doc_selection_group');
    const select = document.getElementById('doc_id');
    const type = document.getElementById('p_type').value;
    
    if (!partnerId) {
        group.style.display = 'none';
        return;
    }

    fetch(`../../ajax/get_partner_docs.php?id=${partnerId}`)
    .then(r => r.json())
    .then(data => {
        select.innerHTML = '<option value="">No Link (General Payment)</option>';
        group.style.display = 'block';
        
        if (type === 'receivable') {
            document.getElementById('doc_label').innerText = 'Link to Tax Invoice';
            data.invoices.forEach(inv => {
                select.innerHTML += `<option value="${inv.id}">Inv: ${inv.invoice_number} (₹${inv.total_amount})</option>`;
            });
        } else {
            document.getElementById('doc_label').innerText = 'Link to Purchase Order';
            data.pos.forEach(po => {
                select.innerHTML += `<option value="${po.id}">PO: ${po.po_number} (₹${po.grand_total})</option>`;
            });
        }
    });
}

function savePayment() {
    const formData = new URLSearchParams();
    formData.append('client_id', document.getElementById('entity_id').value);
    formData.append('type', document.getElementById('p_type').value);
    formData.append('amount', document.getElementById('p_amount').value);
    formData.append('payment_mode', document.getElementById('p_mode').value);
    formData.append('reference_no', document.getElementById('p_ref').value);
    formData.append('doc_id', document.getElementById('doc_id').value);
    formData.append('payment_date', document.getElementById('p_date').value);
    
    const remarks = document.getElementById('p_remarks').value.trim();
    const isAdvance = document.getElementById('is_advance').checked;
    let notes = '';
    if (isAdvance) {
        notes = 'Advance Payment' + (remarks ? ': ' + remarks : '');
    } else {
        notes = remarks;
    }
    formData.append('notes', notes);

    fetch('../../ajax/save_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (res.approval_status === 'pending_approval') {
                Swal.fire('Sent for Approval!', 'Your payment record has been submitted for admin approval.', 'info').then(() => location.reload());
            } else {
                Swal.fire('Recorded', 'Payment has been recorded successfully.', 'success').then(() => location.reload());
            }
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}

function toggleSelectAllPayments(master) {
    const chks = document.querySelectorAll('.payment-select-chk');
    chks.forEach(chk => chk.checked = master.checked);
    updateBulkDeleteButton();
}

function updateBulkDeleteButton() {
    const checked = document.querySelectorAll('.payment-select-chk:checked');
    const btn = document.getElementById('bulkDeleteBtn');
    const countEl = document.getElementById('selectedCount');
    if (btn) {
        if (checked.length > 0) {
            btn.style.display = 'inline-flex';
            if (countEl) countEl.innerText = checked.length;
        } else {
            btn.style.display = 'none';
        }
    }
}

function deleteSinglePayment(id) {
    Swal.fire({
        title: 'Delete Payment?',
        text: "Are you sure you want to delete this payment transaction? This action will move it to Trash.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            performDeletion([id]);
        }
    });
}

function bulkDeletePayments() {
    const checked = document.querySelectorAll('.payment-select-chk:checked');
    if (checked.length === 0) return;
    const ids = Array.from(checked).map(chk => parseInt(chk.value));
    
    Swal.fire({
        title: 'Delete Selected Payments?',
        text: `Are you sure you want to delete the ${ids.length} selected payment transactions? They will be moved to Trash.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete all'
    }).then((result) => {
        if (result.isConfirmed) {
            performDeletion(ids);
        }
    });
}

function performDeletion(ids) {
    Swal.fire({
        title: 'Deleting...',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const formData = new URLSearchParams();
    formData.append('ids', ids.join(','));

    fetch('../../ajax/delete_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted Successfully!',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Delete Failed', res.message || 'Error occurred.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Network error. Please try again.', 'error');
    });
}

function toggleCustomDates(val) {
    const el = document.getElementById('custom_date_range');
    if (el) el.style.display = val === 'custom' ? 'flex' : 'none';
    if (val !== 'custom') {
        const select = document.getElementById('filter_period');
        if (select && select.form) select.form.submit();
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
