<?php
$activePage = 'ledger';
$pageTitle = 'Partner Statement';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/header.php';

$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : (isset($_GET['client_id']) ? intval($_GET['client_id']) : 0);

if (!$partner_id) {
    echo "<div class='card'>Invalid Partner ID.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch Partner Info
$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();

if (!$partner) {
    echo "<div class='card'>Partner not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$pType = $partner['type']; // 'client' or 'vendor'

// Fetch all Bills (Debits)
$ledgerEntries = [];

if ($pType == 'client') {

    // 2. Fetch Invoices
    $stmtInv = $pdo->prepare("
        SELECT i.id, 'invoice' as type, i.created_at as date, i.invoice_number as ref, 
               i.sub_total as base_amt, (i.cgst + i.sgst + i.igst) as tax_amount, i.total_amount as total_amt, 
               i.total_amount as debit, 0 as credit, 'Billed' as status
        FROM invoices i
        JOIN bookings b ON i.booking_id = b.id
        WHERE b.client_id = ?
    ");
    $stmtInv->execute([$partner_id]);
    $invoices = $stmtInv->fetchAll();
    foreach ($invoices as $inv) {
        $ledgerEntries[] = $inv;
    }
} else {
    // Vendor Logic
    $stmtPO = $pdo->prepare("
        SELECT id, 'po' as type, po_date as date, po_number as ref, 
               po_amount as base_amt, (cgst_amount + sgst_amount + igst_amount) as tax_amount, 
               total_amount as total_amt, total_amount as debit, 0 as credit, status
        FROM purchase_orders 
        WHERE vendor_id = ?
    ");
    $stmtPO->execute([$partner_id]);
    $pos = $stmtPO->fetchAll();
    foreach ($pos as $po) {
        $ledgerEntries[] = $po;
    }
}

// 3. Fetch Payments
$pMode = ($pType == 'client') ? 'receivable' : 'payable';
$dbType = ($pType == 'client') ? 'receivable' : 'payable';
$stmtPay = $pdo->prepare("
    SELECT id, 'payment' as type, payment_date as date, transaction_id as ref, 
           amount as base_amt, 0 as tax_amount, amount as total_amt, 
           0 as debit, amount as credit, payment_mode as status
    FROM payments 
    WHERE partner_id = ? AND type = ?
");
$stmtPay->execute([$partner_id, $dbType]);
$payments = $stmtPay->fetchAll();
foreach ($payments as $pay) {
    $ledgerEntries[] = $pay;
}

// Sort by Date
usort($ledgerEntries, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Summary Stats
$totalContracted = 0;
$totalInvoiced = 0;
$totalReceived = 0;
foreach ($ledgerEntries as $item) {
    if ($item['type'] === 'invoice' || $item['type'] === 'po') $totalInvoiced += $item['total_amt'];
    if ($item['type'] === 'payment') $totalReceived += $item['total_amt'];
}
$outstanding = $totalInvoiced - $totalReceived;

$balance = 0;
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0; text-transform: capitalize;"><?php echo $pType; ?> Statement</h1>
        <p style="color: #64748b; margin: 0; font-weight: 500;">Partner: <strong style="color: #0f172a;"><?php echo $partner['name']; ?></strong></p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <button onclick="window.print()" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; cursor: pointer;">
            <i class="fas fa-print"></i> Print Statement
        </button>
        <a href="ledgers.php?type=<?php echo $pType; ?>" class="btn" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button class="btn btn-primary" onclick="addPayment(<?php echo $partner_id; ?>, '<?php echo $pMode; ?>')" style="padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; background: var(--primary); color: white; border: none; cursor: pointer;">
            <i class="fas fa-plus"></i> <?php echo ($pType == 'client') ? 'Record Receipt' : 'Record Payment Made'; ?>
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.25rem; border-left: 4px solid #f59e0b; border-radius: 12px;">
        <div style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Total Billed</div>
        <div style="font-size: 1.5rem; font-weight: 900; color: #1e293b; margin-top: 0.25rem;"><?php echo formatCurrency($totalInvoiced); ?></div>
    </div>
    <div class="card" style="padding: 1.25rem; border-left: 4px solid #10b981; border-radius: 12px;">
        <div style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase;"><?php echo ($pType == 'client') ? 'Total Received' : 'Total Paid'; ?></div>
        <div style="font-size: 1.5rem; font-weight: 900; color: #1e293b; margin-top: 0.25rem;"><?php echo formatCurrency($totalReceived); ?></div>
    </div>
    <div class="card" style="padding: 1.25rem; border-left: 4px solid #ef4444; background: #fef2f2; border-radius: 12px;">
        <div style="font-size: 0.7rem; font-weight: 800; color: #ef4444; text-transform: uppercase;">Outstanding</div>
        <div style="font-size: 1.5rem; font-weight: 900; color: #b91c1c; margin-top: 0.25rem;"><?php echo formatCurrency($outstanding); ?></div>
    </div>
</div>

<div style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
    <input type="text" id="ledgerSearch" placeholder="Search transactions..." style="padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0; width: 300px; font-size: 0.9rem;" onkeyup="filterLedger()">
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <table class="table" id="ledgerTable" style="margin: 0; width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Date</th>
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Type</th>
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Reference</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Base Amt</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Tax (GST)</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Grand Total</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;"><?php echo ($pType == 'client') ? 'Received' : 'Paid Out'; ?></th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Actual Balance</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800;" class="no-print">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ledgerEntries as $item): 
                // Only Invoices and Payments affect the financial balance
                if ($item['type'] === 'invoice' || $item['type'] === 'po') {
                    $balance += $item['total_amt'];
                } elseif ($item['type'] === 'payment') {
                    $balance -= $item['total_amt'];
                }
                
                $balanceLabel = $balance > 0 ? ($pType == 'client' ? 'DUE' : 'PAYABLE') : 'ADV';
            ?>
            <tr style="background: <?php echo $item['type'] === 'booking' ? '#f8fafc' : '#fff'; ?>; border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 1rem; color: #475569; font-size: 0.85rem;"><?php echo date('d M Y', strtotime($item['date'])); ?></td>
                <td style="padding: 1rem;">
                    <?php if ($item['type'] === 'booking'): ?>
                        <span style="background: #eff6ff; color: #1e40af; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">PROPOSAL</span>
                    <?php elseif ($item['type'] === 'invoice'): ?>
                        <span style="background: #fff7ed; color: #9a3412; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">INVOICE</span>
                    <?php elseif ($item['type'] === 'po'): ?>
                        <span style="background: #fff7ed; color: #9a3412; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">PURCHASE ORDER</span>
                    <?php else: ?>
                        <span style="background: #ecfdf5; color: #065f46; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">PAYMENT</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 1rem;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.85rem;">
                        <?php echo $item['ref'] ?: 'N/A'; ?>
                    </div>
                    <?php if ($item['status']): ?>
                        <div style="font-size: 0.65rem; color: #94a3b8; text-transform: capitalize;"><?php echo $item['status']; ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 600; color: #475569; font-size: 0.85rem;">
                    <?php echo $item['base_amt'] > 0 ? formatCurrency($item['base_amt']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 600; color: #64748b; font-size: 0.85rem;">
                    <?php echo $item['tax_amount'] > 0 ? formatCurrency($item['tax_amount']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 700; color: #1e293b; font-size: 0.85rem;">
                    <?php echo ($item['type'] !== 'payment') ? formatCurrency($item['total_amt']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 700; color: #059669; font-size: 0.85rem;">
                    <?php echo $item['type'] === 'payment' ? formatCurrency($item['total_amt']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 900; color: <?php echo $balance > 0 ? '#e11d48' : '#059669'; ?>; font-size: 0.9rem;">
                    <?php echo formatCurrency(abs($balance)); ?> <span style="font-size: 0.65rem; opacity: 0.8;"><?php echo $balanceLabel; ?></span>
                </td>
                <td style="padding: 1rem; text-align: right;" class="no-print">
                    <?php if ($item['type'] === 'payment'): ?>
                        <button onclick="deletePayment(<?php echo $item['id']; ?>)" class="btn-icon btn-delete" title="Delete Payment" style="color: #ef4444; border: none; background: none; cursor: pointer;">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f1f5f9; border-top: 2px solid #e2e8f0;">
                <td colspan="5" style="padding: 1rem; text-align: right; font-weight: 800; color: #475569; text-transform: uppercase;">Totals & Closing Balance</td>
                <td style="padding: 1rem; text-align: right; font-weight: 800; color: #1e293b;"><?php echo formatCurrency($totalInvoiced); ?></td>
                <td style="padding: 1rem; text-align: right; font-weight: 800; color: #059669;"><?php echo formatCurrency($totalReceived); ?></td>
                <td style="padding: 1rem; text-align: right; font-weight: 900; font-size: 1.1rem; color: <?php echo $balance > 0 ? '#e11d48' : '#059669'; ?>;">
                    <?php echo formatCurrency(abs($balance)); ?> <span style="font-size: 0.8rem; font-weight: 800;"><?php echo $balanceLabel; ?></span>
                </td>
                <td class="no-print"></td>
            </tr>
        </tfoot>
    </table>
</div>

<style>
@media print {
    .no-print, .btn, #ledgerSearch { display: none !important; }
    body { background: white; padding: 0; }
    .card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
}
</style>

<script>
function filterLedger() {
    const input = document.getElementById('ledgerSearch');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('ledgerTable');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length - 1; i++) { // Skip header and footer
        const td = tr[i].getElementsByTagName('td');
        let txtValue = "";
        for (let j = 0; j < td.length; j++) {
            txtValue += td[j].textContent || td[j].innerText;
        }
        if (txtValue.toLowerCase().indexOf(filter) > -1) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

function addPayment(clientId, type) {
    const title = type === 'receivable' ? 'Record Receipt' : 'Record Payment Made';
    
    fetch('../../ajax/get_partner_docs.php?id=' + clientId)
    .then(r => r.json())
    .then(data => {
        let docOptions = '<option value="">No Link (General Payment)</option>';
        if (type === 'receivable') {
            data.invoices.forEach(i => {
                docOptions += '<option value="' + i.id + '">Inv: ' + i.invoice_number + ' (₹' + i.total_amount + ')</option>';
            });
        } else {
            data.pos.forEach(p => {
                docOptions += '<option value="' + p.id + '">PO: ' + p.po_number + ' (₹' + p.grand_total + ')</option>';
            });
        }

        Swal.fire({
            title: title,
            html: 
                '<div style="text-align: left;">' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">LINK TO DOCUMENT</label>' +
                    '<select id="pay_doc_id" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; font-size: 0.9rem;">' + docOptions + '</select>' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">AMOUNT (₹)</label>' +
                    '<input id="pay_amount" type="number" class="swal2-input" placeholder="0.00" style="margin: 0 0 1rem 0; width: 100%;">' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">DATE</label>' +
                    '<input id="pay_date" type="date" class="swal2-input" value="<?php echo date('Y-m-d'); ?>" style="margin: 0 0 1rem 0; width: 100%;">' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">PAYMENT MODE</label>' +
                    '<select id="pay_mode" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%;">' +
                        '<option value="NEFT">Bank Transfer (NEFT/IMPS)</option>' +
                        '<option value="Cheque">Cheque</option>' +
                        '<option value="Cash">Cash</option>' +
                        '<option value="UPI">UPI</option>' +
                    '</select>' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">REFERENCE / TRANS ID</label>' +
                    '<input id="pay_ref" class="swal2-input" placeholder="e.g. Bank Ref No." style="margin: 0 0 1rem 0; width: 100%;">' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: 'Save Transaction',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                Swal.showLoading();
                const amount = document.getElementById('pay_amount').value;
                const date = document.getElementById('pay_date').value;
                const ref = document.getElementById('pay_ref').value;
                const mode = document.getElementById('pay_mode').value;
                const docId = document.getElementById('pay_doc_id').value;

                if (!amount || amount <= 0) {
                    Swal.showValidationMessage('Please enter a valid amount');
                    return false;
                }

                let params = new URLSearchParams();
                params.append('client_id', clientId);
                params.append('amount', amount);
                params.append('payment_date', date);
                params.append('reference_no', ref);
                params.append('payment_mode', mode);
                params.append('doc_id', docId);
                params.append('type', type);

                return fetch('../../ajax/save_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                }).then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        throw new Error('Server Error: ' + text);
                    }
                })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Unknown Error');
                    return true;
                }).catch(error => {
                    Swal.showValidationMessage('Failed: ' + error.message);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Success', 'Transaction recorded', 'success').then(() => {
                    location.reload();
                });
            }
        });
    });
}
function deletePayment(id) {
    Swal.fire({
        title: 'Delete this transaction?',
        text: "This will remove the payment entry from the ledger. Are you sure?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../ajax/delete_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
