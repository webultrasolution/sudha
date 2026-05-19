<?php
$activePage = 'payments';
$pageTitle = 'Payment Tracking';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

// Fetch Payments
$payments = $pdo->query("
    SELECT p.*, pr.name as partner_name, i.invoice_number, prop.proposal_number as po_number 
    FROM payments p 
    JOIN partners pr ON p.partner_id = pr.id 
    LEFT JOIN invoices i ON p.invoice_id = i.id 
    LEFT JOIN proposals prop ON p.proposal_id = prop.id 
    ORDER BY p.id DESC
")->fetchAll();

$partners = $pdo->query("SELECT id, name FROM partners ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Financial Transactions</h2>
        <?php if (canAdd('financials')): ?>
        <button class="btn btn-primary" onclick="openPaymentModal()">
            <i class="fas fa-plus"></i> Record Payment
        </button>
        <?php endif; ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Partner</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Mode</th>
                <th>Reference (Invoice/PO)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                <td><strong><?php echo $p['partner_name']; ?></strong></td>
                <td>
                    <span class="badge-<?php echo $p['type'] == 'credit' ? 'success' : 'warning'; ?>" style="padding: 0.125rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">
                        <?php echo $p['type'] == 'credit' ? 'RECEIVABLE' : 'PAYABLE'; ?>
                    </span>
                </td>
                <td><?php echo formatCurrency($p['amount']); ?></td>
                <td><?php echo $p['payment_mode']; ?></td>
                <td><?php echo $p['invoice_number'] ?: ($p['po_number'] ?: 'N/A'); ?></td>
                <td><span class="status-pill status-paid">Processed</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
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

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Transaction Type</label>
                    <select id="p_type" class="p-input">
                        <option value="receivable">Income (Receipt from Client)</option>
                        <option value="payable">Expense (Payment to Vendor)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (₹)</label>
                    <input type="number" id="p_amount" class="p-input" required>
                </div>
            </div>
            <div class="form-group">
                <label>Payment Mode</label>
                <select id="p_mode" class="p-input">
                    <option value="NEFT">Bank Transfer (NEFT/IMPS)</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI</option>
                </select>
            </div>
            <div class="form-group">
                <label>Transaction ID / Notes</label>
                <input type="text" id="p_ref" class="p-input" placeholder="e.g. UTR Number or Chq No">
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

    fetch('../../ajax/save_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('Recorded', 'Payment has been recorded successfully.', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
