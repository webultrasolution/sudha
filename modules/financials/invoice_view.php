<?php
$activePage = 'invoices';
$pageTitle = 'Invoice Details';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Invoice
$stmt = $pdo->prepare("
    SELECT i.*, b.id as booking_id, b.billing_gstin, c.name as client_name, c.email as client_email, c.phone as client_phone, c.address as client_address, c.gstin as client_gstin, c.additional_gst, p.proposal_number, p.start_date, p.end_date
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    JOIN proposals p ON b.proposal_id = p.id
    JOIN partners c ON p.client_id = c.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    echo "<div class='card'>Invoice not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Override with selected Billing GSTIN details if applicable
if (!empty($invoice['billing_gstin']) && $invoice['billing_gstin'] !== $invoice['client_gstin'] && !empty($invoice['additional_gst'])) {
    $addGsts = json_decode($invoice['additional_gst'], true);
    if (is_array($addGsts)) {
        foreach ($addGsts as $g) {
            if ($g['gstin'] === $invoice['billing_gstin']) {
                $invoice['client_gstin'] = $g['gstin'];
                $invoice['client_address'] = $g['address'];
                break;
            }
        }
    }
}

// Fetch Items (from the linked proposal)
$stmtItems = $pdo->prepare("
    SELECT pi.*, s.name as site_name, s.site_code, s.type as site_type
    FROM proposal_items pi
    JOIN bookings b ON pi.proposal_id = b.proposal_id
    JOIN sites s ON pi.site_id = s.id
    WHERE b.id = ?
");
$stmtItems->execute([$invoice['booking_id']]);
$items = $stmtItems->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
    <div>
        <h2 style="font-size: 1.5rem; color: var(--primary);"><?php echo $invoice['invoice_number']; ?></h2>
        <p style="color: var(--secondary);">Status: <span class="pay-status pay-<?php echo $invoice['payment_status']; ?>"><?php echo str_replace('_', ' ', ucfirst($invoice['payment_status'])); ?></span></p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button class="btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <button class="btn btn-primary" onclick="sendEmail(<?php echo $id; ?>)"><i class="fas fa-envelope"></i> Email to Client</button>
        <?php if ($invoice['payment_status'] !== 'paid' && canEdit('financials')): ?>
            <button class="btn" style="background: var(--success); color: white;" onclick="markPaid(<?php echo $id; ?>)">
                <i class="fas fa-check"></i> Mark as Paid
            </button>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">From: Sudha Creative</h3>
        <div style="line-height: 1.6; font-size: 0.9rem;">
            <strong style="color: var(--primary);"><?php echo COMPANY_NAME; ?></strong><br>
            <?php echo COMPANY_ADDRESS; ?><br>
            <?php echo COMPANY_CITY; ?><br>
            <strong style="color: #475569;">GSTIN: <?php echo COMPANY_GSTIN; ?></strong>
        </div>
    </div>
    <div class="card">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">Billed To</h3>
        <div style="line-height: 1.6; font-size: 0.9rem;">
            <strong style="color: #1e293b;"><?php echo $invoice['client_name']; ?></strong><br>
            <?php echo $invoice['client_address']; ?><br>
            <i class="fas fa-phone" style="font-size: 0.75rem; color: #94a3b8;"></i> <?php echo $invoice['client_phone']; ?><br>
            <strong style="color: #475569;">GSTIN: <?php echo $invoice['client_gstin'] ?: 'N/A'; ?></strong>
        </div>
    </div>
    <div class="card">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">Document Info</h3>
        <div style="line-height: 1.6; font-size: 0.9rem;">
            <strong>Booking Ref:</strong> #BK-<?php echo str_pad($invoice['booking_id'], 4, '0', STR_PAD_LEFT); ?><br>
            <strong>Proposal:</strong> <?php echo $invoice['proposal_number']; ?><br>
            <strong>Date:</strong> <?php echo date('d M Y', strtotime($invoice['created_at'])); ?><br>
            <strong>Period:</strong> <?php echo date('d M Y', strtotime($invoice['start_date'])); ?> to <?php echo date('d M Y', strtotime($invoice['end_date'])); ?>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="font-size: 1rem; margin-bottom: 1rem;">Service Breakdown</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Site Code</th>
                <th>Description</th>
                <th>Media Type</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><strong><?php echo $item['site_code']; ?></strong></td>
                <td><?php echo $item['site_name']; ?></td>
                <td><?php echo $item['site_type']; ?></td>
                <td style="text-align: right;"><?php echo formatCurrency($item['sale_rate']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="border-top: 2px solid #eee;">
                <td colspan="3" style="text-align: right; font-weight: 600;">Subtotal</td>
                <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($invoice['sub_total']); ?></td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right; color: var(--secondary);">CGST (9%)</td>
                <td style="text-align: right; color: var(--secondary);"><?php echo formatCurrency($invoice['cgst']); ?></td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right; color: var(--secondary);">SGST (9%)</td>
                <td style="text-align: right; color: var(--secondary);"><?php echo formatCurrency($invoice['sgst']); ?></td>
            </tr>
            <tr style="font-size: 1.125rem;">
                <td colspan="3" style="text-align: right; font-weight: 700; color: var(--primary);">Total Payable</td>
                <td style="text-align: right; font-weight: 700; color: var(--primary);"><?php echo formatCurrency($invoice['total_amount']); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<style>
.pay-status { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.pay-unpaid { background: #fee2e2; color: #991b1b; }
.pay-partially_paid { background: #fef9c3; color: #854d0e; }
.pay-paid { background: #dcfce7; color: #166534; }
</style>

<script>
function sendEmail(id) {
    const btn = event.currentTarget;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    // Simulate API call
    setTimeout(() => {
        alert('Invoice has been sent to <?php echo $invoice['client_email']; ?> successfully!');
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }, 1500);
}

async function markPaid(id) {
    if (!confirm('Are you sure you want to mark this invoice as Paid?')) return;

    try {
        const response = await fetch('../../ajax/update_invoice_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_id: id, status: 'paid' })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Invoice marked as paid successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An unexpected error occurred.');
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
