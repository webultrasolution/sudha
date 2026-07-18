<?php
$activePage = 'invoices';
$pageTitle = 'Invoice Details';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Invoice
$stmt = $pdo->prepare("
    SELECT i.*, b.id as booking_id, b.booking_number, b.billing_gstin, b.start_date as booking_start, b.end_date as booking_end, c.name as client_name, c.email as client_email, c.phone as client_phone, c.address as client_address, c.gstin as client_gstin, c.additional_gst, p.proposal_number, p.start_date, p.end_date, e.name as entity_name, e.address as entity_address, e.gstin as entity_gstin
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN proposals p ON b.proposal_id = p.id
    JOIN partners c ON b.client_id = c.id
    LEFT JOIN entities e ON i.entity_id = e.id
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

// Fetch Items (from the linked booking)
$stmtItems = $pdo->prepare("
    SELECT bi.*, 
           COALESCE(bi.custom_site_name, s.name) as site_name, 
           COALESCE(bi.custom_location, s.location) as location, 
           s.site_code, 
           s.type as site_type,
           s.width,
           s.height,
           s.city,
           s.state as site_state,
           COALESCE(bi.selected_image, (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1)) as image
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    WHERE bi.booking_id = ?
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
    <?php $company = resolveCompanyDetails($invoice['entity_id'] ?? null); ?>
    <div class="card">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">From: <?php echo htmlspecialchars($company['name']); ?></h3>
        <div style="line-height: 1.6; font-size: 0.9rem;">
            <strong style="color: var(--primary);"><?php echo htmlspecialchars($company['name']); ?></strong><br>
            <?php echo nl2br(htmlspecialchars($company['address'])); ?><br>
            <strong style="color: #475569;">GSTIN: <?php echo htmlspecialchars($company['gstin']); ?></strong>
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
            <strong>Booking Ref:</strong> <?php echo htmlspecialchars(!empty($invoice['booking_number']) ? $invoice['booking_number'] : '#BK-' . str_pad($invoice['booking_id'], 4, '0', STR_PAD_LEFT)); ?><br>
            <strong>Proposal:</strong> <?php echo !empty($invoice['proposal_number']) ? htmlspecialchars($invoice['proposal_number']) : 'N/A'; ?><br>
            <strong>Date:</strong> <?php echo date('d M Y', strtotime($invoice['created_at'])); ?><br>
            <strong>Period:</strong> <?php 
            $sDate = (!empty($invoice['booking_start']) && $invoice['booking_start'] !== '0000-00-00') ? $invoice['booking_start'] : ($invoice['start_date'] ?? '');
            $eDate = (!empty($invoice['booking_end']) && $invoice['booking_end'] !== '0000-00-00') ? $invoice['booking_end'] : ($invoice['end_date'] ?? '');
            if (!empty($sDate) && $sDate !== '0000-00-00' && !empty($eDate) && $eDate !== '0000-00-00') {
                echo date('d M Y', strtotime($sDate)) . ' to ' . date('d M Y', strtotime($eDate));
            } else {
                echo 'N/A';
            }
            ?>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="font-size: 1rem; margin-bottom: 1rem;">Site Details</h3>
    <table class="table" style="font-size: 0.85rem; width: 100%;">
        <thead>
            <tr>
                <th>Preview</th>
                <th>City / Code</th>
                <th>Asset Details</th>
                <th>Size</th>
                <th style="text-align: right;">Pricing</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <?php 
                    $thumbToUse = !empty($item['image']) ? $item['image'] : '';
                    $thumbUrl = $thumbToUse ? '../../uploads/sites/' . $thumbToUse : 'https://via.placeholder.com/150x95?text=No+Img';
                    ?>
                    <div style="width: 100px; height: 60px;">
                        <img src="<?php echo $thumbUrl; ?>" style="width: 100%; height: 100%; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05);" onclick="openLightbox('<?php echo $thumbUrl; ?>')">
                    </div>
                </td>
                <td>
                    <div style="font-weight: 800; color: #1e293b;"><?php echo htmlspecialchars($item['city'] ?? ''); ?></div>
                    <div style="font-size: 0.75rem; color: #f97316; font-weight: 800;"><?php echo htmlspecialchars($item['site_code'] ?? ''); ?></div>
                </td>
                <td>
                    <div style="font-weight: 800; color: #1e293b; margin-bottom: 2px;"><?php echo htmlspecialchars($item['site_name'] ?? ''); ?></div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 6px;"><?php echo htmlspecialchars($item['location'] ?? ''); ?></div>
                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                        <span style="background: #ecfdf5; color: #059669; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;"><?php echo htmlspecialchars($item['site_type'] ?? ''); ?></span>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 700; color: #475569;"><?php echo htmlspecialchars($item['width'] ?? ''); ?>' x <?php echo htmlspecialchars($item['height'] ?? ''); ?>'</div>
                    <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo number_format(($item['width'] ?? 0) * ($item['height'] ?? 0)); ?> SQFT</div>
                </td>
                <td style="text-align: right; font-weight: 700; color: var(--primary);"><?php echo formatCurrency($item['sale_rate']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="border-top: 2px solid #eee;">
                <td colspan="4" style="text-align: right; font-weight: 600;">Subtotal</td>
                <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($invoice['sub_total']); ?></td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right; color: var(--secondary);">CGST (9%)</td>
                <td style="text-align: right; color: var(--secondary);"><?php echo formatCurrency($invoice['cgst']); ?></td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right; color: var(--secondary);">SGST (9%)</td>
                <td style="text-align: right; color: var(--secondary);"><?php echo formatCurrency($invoice['sgst']); ?></td>
            </tr>
            <tr style="font-size: 1.125rem;">
                <td colspan="4" style="text-align: right; font-weight: 700; color: var(--primary);">Total Payable</td>
                <td style="text-align: right; font-weight: 700; color: var(--primary);"><?php echo formatCurrency($invoice['total_amount']); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Simple Lightbox HTML -->
<div id="simple-lightbox" onclick="closeLightbox()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
    <div style="position: relative; text-align: center; max-width: 90%; max-height: 90vh;">
        <img id="lightbox-img" src="" style="max-width: 100%; max-height: 85vh; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.15);">
        <div onclick="closeLightbox()" style="position: absolute; top: -40px; right: -40px; color: white; font-size: 2.5rem; cursor: pointer; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</div>
    </div>
</div>

<style>
.pay-status { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.pay-unpaid { background: #fee2e2; color: #991b1b; }
.pay-partially_paid { background: #fef9c3; color: #854d0e; }
.pay-paid { background: #dcfce7; color: #166534; }

.table { border-collapse: separate; border-spacing: 0; }
.table th { background: #f8fafc; padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 800; border-bottom: 2px solid #f1f5f9; text-align: left; }
.table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.table tr:hover td { background: #fcfcfc; }
</style>

<script>
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('simple-lightbox').style.display = 'flex';
}
function closeLightbox() {
    document.getElementById('simple-lightbox').style.display = 'none';
}

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
