<?php
$activePage = 'pos';
$pageTitle = 'View Purchase Order';
include_once __DIR__ . '/../../includes/header.php';

$poId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$po = $pdo->prepare("
    SELECT po.*, v.name as vendor_name, v.address as v_address, v.gstin as v_gstin, v.phone as v_phone,
           c.display_name as camp_name, c.project_id as proj_id
    FROM purchase_orders po
    JOIN partners v ON po.vendor_id = v.id
    JOIN campaigns c ON po.campaign_id = c.id
    WHERE po.id = ?
");
$po->execute([$poId]);
$poData = $po->fetch();

if (!$poData) {
    echo "<div class='card'>PO not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$items = $pdo->prepare("SELECT pi.*, s.site_code, s.location FROM po_items pi JOIN sites s ON pi.site_id = s.id WHERE pi.po_id = ?");
$items->execute([$poId]);
$poItems = $items->fetchAll();

$attachments = $pdo->prepare("SELECT * FROM po_attachments WHERE po_id = ?");
$attachments->execute([$poId]);
$poAttachments = $attachments->fetchAll();
?>

<div style="display: grid; grid-template-columns: 3fr 1fr; gap: 2rem;">
    <div class="card" id="po-print-area">
        <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #f1f5f9; padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
            <div>
                <h1 style="color: var(--primary); margin: 0;">PURCHASE ORDER</h1>
                <p style="color: var(--secondary); margin-top: 0.25rem;">#<?php echo $poData['po_number']; ?></p>
            </div>
            <div style="text-align: right;">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print PO
                </button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-bottom: 2rem;">
            <div>
                <h4 style="text-transform: uppercase; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem;">Vendor / Supplier</h4>
                <div style="font-weight: 700; font-size: 1.1rem;"><?php echo $poData['vendor_name']; ?></div>
                <div style="color: #475569; margin-top: 0.25rem;"><?php echo $poData['v_address']; ?></div>
                <div style="margin-top: 0.5rem; font-size: 0.875rem;">
                    <strong>GSTIN:</strong> <?php echo $poData['v_gstin']; ?><br>
                    <strong>Phone:</strong> <?php echo $poData['v_phone']; ?>
                </div>
            </div>
            <div style="text-align: right;">
                <h4 style="text-transform: uppercase; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem;">PO Details</h4>
                <div style="font-size: 0.9375rem;">
                    <strong>Date:</strong> <?php echo date('d M Y', strtotime($poData['po_date'])); ?><br>
                    <strong>Campaign:</strong> <?php echo $poData['camp_name']; ?> (<?php echo $poData['proj_id']; ?>)<br>
                    <strong>Status:</strong> <span class="status-badge <?php echo $poData['status']; ?>"><?php echo strtoupper($poData['status']); ?></span>
                </div>
            </div>
        </div>

        <table class="table" style="margin-top: 2rem;">
            <thead>
                <tr style="background: #f8fafc;">
                    <th>Description</th>
                    <th>Period</th>
                    <th>Monthly Rate</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($poItems as $item): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600;"><?php echo $item['site_code']; ?></div>
                        <div style="font-size: 0.75rem; color: var(--secondary);"><?php echo $item['location']; ?></div>
                    </td>
                    <td><?php echo date('d/m/y', strtotime($item['start_date'])); ?> - <?php echo date('d/m/y', strtotime($item['end_date'])); ?></td>
                    <td><?php echo formatCurrency($item['monthly_rate']); ?></td>
                    <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($item['cost']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right; padding-top: 1.5rem; font-weight: 600;">Subtotal:</td>
                    <td style="text-align: right; padding-top: 1.5rem;"><?php echo formatCurrency($poData['po_amount']); ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: 600;">CGST (9%):</td>
                    <td style="text-align: right;"><?php echo formatCurrency($poData['cgst_amount']); ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: 600;">SGST (9%):</td>
                    <td style="text-align: right;"><?php echo formatCurrency($poData['sgst_amount']); ?></td>
                </tr>
                <tr style="font-size: 1.125rem; font-weight: 800; color: var(--primary);">
                    <td colspan="3" style="text-align: right; border-top: 2px solid #e2e8f0; padding-top: 1rem;">GRAND TOTAL:</td>
                    <td style="text-align: right; border-top: 2px solid #e2e8f0; padding-top: 1rem;"><?php echo formatCurrency($poData['total_amount']); ?></td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 4rem; border-top: 1px solid #f1f5f9; padding-top: 2rem; font-size: 0.8rem; color: #94a3b8;">
            <p><strong>Terms & Conditions:</strong></p>
            <ol style="padding-left: 1.25rem; margin-top: 0.5rem;">
                <li>Payment will be processed within 30 days of invoice receipt.</li>
                <li>Subject to quality of service (QoS) verification.</li>
                <li>All site photos must be uploaded to the portal upon mounting.</li>
            </ol>
        </div>
    </div>

    <div class="no-print">
        <div class="card">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="fas fa-file-invoice"></i> Vendor Invoice</h3>
            <?php if ($poData['vendor_invoice_no']): ?>
                <div style="background: #f0fdf4; padding: 1rem; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 1rem;">
                    <small style="color: #166534;">Invoice Received</small>
                    <div style="font-weight: 700;"><?php echo $poData['vendor_invoice_no']; ?></div>
                    <small><?php echo date('d M Y', strtotime($poData['vendor_invoice_date'])); ?></small>
                </div>
            <?php else: ?>
                <form id="invoiceForm" style="margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Inv Number</label>
                        <input type="text" id="v_inv_no" class="p-input" required>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Inv Date</label>
                        <input type="date" id="v_inv_date" class="p-input" required>
                    </div>
                    <button type="button" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;" onclick="updateVendorInvoice()">
                        Update Details
                    </button>
                </form>
            <?php endif; ?>

            <h3 style="font-size: 1rem; margin-top: 2rem; margin-bottom: 1rem;"><i class="fas fa-paperclip"></i> Attachments</h3>
            <div id="attachments-list">
                <?php foreach ($poAttachments as $att): ?>
                    <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                        <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                        <span style="flex: 1; overflow: hidden; text-overflow: ellipsis;"><?php echo $att['filename']; ?></span>
                        <a href="../../uploads/pos/<?php echo $att['filename']; ?>" target="_blank" class="btn-icon"><i class="fas fa-download"></i></a>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($poAttachments)): ?>
                    <p style="font-size: 0.8rem; color: #94a3b8;">No documents attached.</p>
                <?php endif; ?>
            </div>
            
            <form id="uploadForm" enctype="multipart/form-data" style="margin-top: 1rem;">
                <input type="file" id="po_file" style="display: none;" onchange="uploadPOFile()">
                <button type="button" class="btn" style="width: 100%; border: 1px dashed var(--primary); color: var(--primary);" onclick="document.getElementById('po_file').click()">
                    <i class="fas fa-upload"></i> Upload Invoice/Scan
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.status-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; }
.status-badge.approved { background: #dcfce7; color: #166534; }
.status-badge.paid { background: #dcfce7; color: #166534; }
.status-badge.draft { background: #f1f5f9; color: #475569; }
.p-input { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; margin-top: 0.25rem; font-size: 0.85rem; }

@media print {
    .sidebar, .header, .btn-primary, .no-print { display: none !important; }
    .main-content { margin: 0; padding: 0; width: 100% !important; }
    .card { border: none; box-shadow: none; }
    body { background: white; }
    #po-print-area { width: 100% !important; margin: 0 !important; }
}
</style>

<script>
function updateVendorInvoice() {
    const no = document.getElementById('v_inv_no').value;
    const dt = document.getElementById('v_inv_date').value;
    if(!no || !dt) return alert('Fill invoice details');

    fetch('../../ajax/update_po_invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=<?php echo $poId; ?>&no=${no}&date=${dt}`
    }).then(r => r.json()).then(res => {
        if(res.success) location.reload();
    });
}

function uploadPOFile() {
    const file = document.getElementById('po_file').files[0];
    if(!file) return;

    const formData = new FormData();
    formData.append('po_id', '<?php echo $poId; ?>');
    formData.append('file', file);

    fetch('../../ajax/upload_po_attachment.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.success) {
            Swal.fire('Uploaded', 'File attached successfully.', 'success').then(() => location.reload());
        } else {
            alert('Upload failed: ' + res.message);
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
