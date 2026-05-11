<?php
$activePage = 'ledger';
$pageTitle = 'Partner Statement';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/header.php';

$partner_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

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
if ($pType == 'client') {
    $stmtInv = $pdo->prepare("
        SELECT 'invoice' as type, i.created_at as date, invoice_number as ref, i.total_amount as debit, 0 as credit 
        FROM invoices i
        JOIN bookings b ON i.booking_id = b.id
        WHERE b.client_id = ?
    ");
} else {
    $stmtInv = $pdo->prepare("
        SELECT 'po' as type, po_date as date, po_number as ref, total_amount as debit, 0 as credit 
        FROM purchase_orders 
        WHERE vendor_id = ?
    ");
}
$stmtInv->execute([$partner_id]);
$bills = $stmtInv->fetchAll();

// Fetch all Payments (Credits for Client, Debits for Vendor)
$pMode = ($pType == 'client') ? 'receivable' : 'payable';
$dbType = ($pType == 'client') ? 'credit' : 'debit';
$stmtPay = $pdo->prepare("
    SELECT 'payment' as type, payment_date as date, transaction_id as ref, 0 as debit, amount as credit 
    FROM payments 
    WHERE partner_id = ? AND type = ?
");
$stmtPay->execute([$partner_id, $dbType]);
$payments = $stmtPay->fetchAll();

// Combine and Sort by Date
$ledger = array_merge($bills, $payments);
usort($ledger, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

$balance = 0;
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0; text-transform: capitalize;"><?php echo $pType; ?> Statement</h1>
        <p style="color: #64748b; margin: 0; font-weight: 500;">Name: <strong style="color: #0f172a;"><?php echo $partner['name']; ?></strong></p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <a href="ledgers.php?type=<?php echo $pType; ?>" class="btn" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <button class="btn btn-primary" onclick="addPayment(<?php echo $partner_id; ?>, '<?php echo $pMode; ?>')" style="padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800;">
            <i class="fas fa-plus"></i> <?php echo ($pType == 'client') ? 'Record Receipt' : 'Record Payment Made'; ?>
        </button>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Date</th>
                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Description</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;"><?php echo ($pType == 'client') ? 'Debit (Billed)' : 'Credit (Bill Recd)'; ?></th>
                <th style="padding: 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;"><?php echo ($pType == 'client') ? 'Credit (Paid)' : 'Debit (Paid Out)'; ?></th>
                <th style="padding: 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ledger as $item): 
                if ($item['type'] === 'invoice' || $item['type'] === 'po') {
                    $balance += $item['debit'];
                } else {
                    $balance -= $item['credit'];
                }
            ?>
            <tr style="background: #fff; border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 1rem; color: #475569;"><?php echo date('d M Y', strtotime($item['date'])); ?></td>
                <td style="padding: 1rem;">
                    <?php if ($item['type'] === 'invoice'): ?>
                        <span style="font-weight: 700; color: #0f172a;">Tax Invoice #<?php echo $item['ref']; ?></span>
                    <?php elseif ($item['type'] === 'po'): ?>
                        <span style="font-weight: 700; color: #0f172a;">Purchase Order #<?php echo $item['ref']; ?></span>
                    <?php else: ?>
                        <span style="font-weight: 700; color: #10b981;">
                            <?php echo ($pType == 'client') ? 'Payment Received' : 'Payment Made'; ?> 
                            (Ref: <?php echo $item['ref']; ?>)
                        </span>
                    <?php endif; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 600; color: #e11d48;">
                    <?php echo $item['debit'] > 0 ? formatCurrency($item['debit']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 600; color: #059669;">
                    <?php echo $item['credit'] > 0 ? formatCurrency($item['credit']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 800; color: <?php echo $balance > 0 ? '#e11d48' : '#059669'; ?>;">
                    <?php echo formatCurrency(abs($balance)); ?> <?php echo $balance > 0 ? 'DUE' : 'ADV'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f1f5f9; border-top: 2px solid #e2e8f0;">
                <td colspan="4" style="padding: 1rem; text-align: right; font-weight: 800; color: #475569; text-transform: uppercase;">Closing Balance</td>
                <td style="padding: 1rem; text-align: right; font-weight: 900; font-size: 1.1rem; color: <?php echo $balance > 0 ? '#e11d48' : '#059669'; ?>;">
                    <?php echo formatCurrency(abs($balance)); ?> <?php echo $balance > 0 ? 'OUTSTANDING' : 'ADVANCE'; ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
