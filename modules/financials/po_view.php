<?php
$activePage = 'pos';
$pageTitle = 'View Purchase Order';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

$poId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$po = $pdo->prepare("
    SELECT po.*, v.name as vendor_name, v.address as v_address, v.gstin as v_gstin, v.phone as v_phone,
           COALESCE(c.display_name, po.campaign_name) as camp_name, 
           COALESCE(c.project_id, 'Direct') as proj_id
    FROM purchase_orders po
    JOIN partners v ON po.vendor_id = v.id
    LEFT JOIN campaigns c ON po.campaign_id = c.id
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

<div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-bottom: 1rem;" class="no-print">
    <?php if (($poData['approval_status'] ?? '') === 'approved'): ?>
        <a href="../operations/generate_po.php?po_id=<?php echo $poId; ?>" target="_blank" class="btn btn-primary" style="background: #0d9488; border-radius: 8px; padding: 0.75rem 1.5rem; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-file-pdf"></i> Professional PDF
        </a>
    <?php else: ?>
        <div class="btn" style="background: #e2e8f0; color: #64748b; border-radius: 8px; padding: 0.75rem 1.5rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; cursor: not-allowed;" title="Awaiting admin approval">
            <i class="fas fa-lock"></i> PDF Locked
        </div>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-primary" style="background: #0f172a; border-radius: 8px; padding: 0.75rem 1.5rem; font-weight: 800;">
        <i class="fas fa-print"></i> PRINT PAGE
    </button>
</div>

<div style="display: grid; grid-template-columns: 3fr 1fr; gap: 2rem;">
    <div class="card" id="po-print-area" style="padding: 0; border: 1px solid #000; overflow: hidden; border-radius: 0;">
        <!-- Header / Letterhead -->
        <?php 
        $company_letterhead = getSetting('company_letterhead');
        if ($company_letterhead): ?>
            <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_letterhead; ?>" style="width: 100%; height: auto; display: block; border-bottom: 1px solid #000;">
        <?php else: ?>
            <div style="text-align: center; padding: 1rem; border-bottom: 1px solid #000; background: #f8fafc;">
                <h2 style="margin: 0; text-transform: uppercase;"><?php echo getSetting('company_name'); ?></h2>
                <p style="margin: 0; font-size: 0.8rem;"><?php echo getSetting('company_address'); ?></p>
            </div>
        <?php endif; ?>

        <div style="padding: 1.5rem;">
            <!-- Info Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; border-bottom: 1px solid #000; padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <h4 style="text-decoration: underline; font-style: italic; font-size: 0.9rem; margin-bottom: 0.5rem;">Supplier / Vendor:</h4>
                    <div style="font-weight: 800; font-size: 1.1rem; color: #000;"><?php echo $poData['vendor_name']; ?></div>
                    <div style="color: #475569; margin-top: 0.25rem; font-size: 0.85rem;"><?php echo $poData['v_address']; ?></div>
                    <div style="margin-top: 0.5rem; font-size: 0.85rem;">
                        <strong>GSTIN:</strong> <?php echo $poData['v_gstin']; ?><br>
                        <strong>Phone:</strong> <?php echo $poData['v_phone']; ?>
                    </div>
                </div>
                <div style="border-left: 1px solid #e2e8f0; padding-left: 2rem;">
                    <div style="display: flex; margin-bottom: 4px;">
                        <span style="width: 100px; font-weight: 600;">PO Number</span><span style="margin: 0 10px;">:</span>
                        <span style="font-weight: 800; color: #000;"><?php echo $poData['po_number']; ?></span>
                    </div>
                    <div style="display: flex; margin-bottom: 4px;">
                        <span style="width: 100px; font-weight: 600;">PO Date</span><span style="margin: 0 10px;">:</span>
                        <span><?php echo date('d M Y', strtotime($poData['po_date'])); ?></span>
                    </div>
                    <div style="display: flex; margin-bottom: 4px;">
                        <span style="width: 100px; font-weight: 600;">Campaign</span><span style="margin: 0 10px;">:</span>
                        <span style="font-weight: 700;"><?php echo $poData['camp_name']; ?></span>
                    </div>
                    <div style="display: flex; margin-bottom: 4px;">
                        <span style="width: 100px; font-weight: 600;">Project ID</span><span style="margin: 0 10px;">:</span>
                        <span style="color: var(--secondary);"><?php echo $poData['proj_id']; ?></span>
                    </div>
                    <div style="display: flex; margin-top: 10px;">
                        <span style="width: 100px; font-weight: 600;">Status</span><span style="margin: 0 10px;">:</span>
                        <span class="status-badge <?php echo $poData['status']; ?>"><?php echo strtoupper($poData['status']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div style="background: #f1f5f9; padding: 0.5rem; text-align: center; font-weight: 800; letter-spacing: 2px; margin-bottom: 1rem; border: 1px solid #e2e8f0;">PURCHASE ORDER DETAILS</div>
            <table class="table" style="border: 1px solid #e2e8f0;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="border-right: 1px solid #e2e8f0;">Description</th>
                        <th style="border-right: 1px solid #e2e8f0;">Period</th>
                        <th style="border-right: 1px solid #e2e8f0;">Rate</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poItems as $item): ?>
                    <tr>
                        <td style="border-right: 1px solid #e2e8f0;">
                            <div style="font-weight: 700; color: #000;"><?php echo $item['site_code']; ?></div>
                            <div style="font-size: 0.75rem; color: #64748b;"><?php echo $item['location']; ?></div>
                        </td>
                        <td style="border-right: 1px solid #e2e8f0; font-size: 0.8rem;">
                            <?php echo date('d/m/y', strtotime($item['start_date'])); ?> - <?php echo date('d/m/y', strtotime($item['end_date'])); ?>
                        </td>
                        <td style="border-right: 1px solid #e2e8f0; font-weight: 600;"><?php echo formatCurrency($item['monthly_rate']); ?></td>
                        <td style="text-align: right; font-weight: 700; color: #000;"><?php echo formatCurrency($item['cost']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td colspan="3" style="text-align: right; padding: 0.75rem; border-right: 1px solid #e2e8f0;">Subtotal (Taxable):</td>
                        <td style="text-align: right;"><?php echo formatCurrency($poData['po_amount']); ?></td>
                    </tr>
                    <?php
                    $cgst_val = floatval($poData['cgst_amount'] ?? 0);
                    $sgst_val = floatval($poData['sgst_amount'] ?? 0);
                    $igst_val = floatval($poData['igst_amount'] ?? 0);
                    $total_tax = $cgst_val + $sgst_val + $igst_val;
                    
                    if ($total_tax > 0) {
                        if ($igst_val > 0) {
                            $tax_label = 'IGST (18%):';
                        } else {
                            $tax_label = 'CGST + SGST (9%+9%):';
                        }
                    } else {
                        $tax_label = 'GST (0%):';
                    }
                    ?>
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td colspan="3" style="text-align: right; padding: 0.75rem; border-right: 1px solid #e2e8f0;"><?php echo $tax_label; ?></td>
                        <td style="text-align: right;"><?php echo formatCurrency($total_tax); ?></td>
                    </tr>
                    <tr style="background: #eff6ff; font-weight: 900; font-size: 1.1rem; color: #1e3a8a;">
                        <td colspan="3" style="text-align: right; padding: 1rem; border-right: 1px solid #e2e8f0; border-top: 2px solid #1e3a8a;">GRAND TOTAL:</td>
                        <td style="text-align: right; border-top: 2px solid #1e3a8a;"><?php echo formatCurrency($poData['total_amount']); ?></td>
                    </tr>
                </tfoot>
            </table>

            <?php if(!empty($poData['remarks'])): ?>
                <div style="margin-top: 1.5rem; padding: 1rem; background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 8px;">
                    <div style="font-weight: 800; font-size: 0.75rem; text-transform: uppercase; color: #92400e; margin-bottom: 0.5rem;">Remarks / Special Instructions</div>
                    <div style="font-style: italic; color: #000;"><?php echo nl2br(htmlspecialchars($poData['remarks'])); ?></div>
                </div>
            <?php endif; ?>
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
                <?php if (canEdit('financials')): ?>
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
                <?php else: ?>
                    <p style="font-size: 0.85rem; color: #94a3b8; font-style: italic;">No vendor invoice details recorded.</p>
                <?php endif; ?>
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
            
            <?php if (canEdit('financials')): ?>
            <form id="uploadForm" enctype="multipart/form-data" style="margin-top: 1rem;">
                <input type="file" id="po_file" style="display: none;" onchange="uploadPOFile()">
                <button type="button" class="btn" style="width: 100%; border: 1px dashed var(--primary); color: var(--primary);" onclick="document.getElementById('po_file').click()">
                    <i class="fas fa-upload"></i> Upload Invoice/Scan
                </button>
            </form>
            <?php endif; ?>
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
