<?php
$activePage = 'printing_rates';
$pageTitle = 'View Vendor Printing PO';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$hideSidebar = true;
include_once __DIR__ . '/../../includes/header.php';

date_default_timezone_set('Asia/Kolkata');

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$po_number = isset($_GET['po_number']) ? $_GET['po_number'] : null;
$rate_ids = isset($_GET['rate_ids']) ? $_GET['rate_ids'] : [];

if (empty($rate_ids) && !$po_number) {
    echo "<div class='card'>Invalid PO reference.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Build query
$where = "r.vendor_id = :vendor_id";
$params = [':vendor_id' => $vendor_id];

if ($po_number) {
    $where .= " AND r.po_number = :po_number";
    $params[':po_number'] = $po_number;
} else {
    $in = str_repeat('?,', count($rate_ids) - 1) . '?';
    $where .= " AND r.id IN ($in)";
    $params = array_merge([$vendor_id], $rate_ids);
}

// Fetch Group info
$sql = "SELECT r.*, v.name as vendor_name, v.email as vendor_email 
        FROM vendor_printing_rates r 
        JOIN partners v ON r.vendor_id = v.id 
        WHERE $where";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_values($params));
$items = $stmt->fetchAll();

if (empty($items)) {
    echo "<div class='card'>Printing PO not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$first = $items[0];
$po_num_display = $first['po_number'] ? $first['po_number'] : 'Draft-' . $first['id'];

// Block view if not approved and user is not admin
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
if (!$isAdmin && $first['po_number']) {
    $stmtStatus = $pdo->prepare("SELECT approval_status FROM purchase_orders WHERE po_number = ?");
    $stmtStatus->execute([$first['po_number']]);
    $apprStatus = $stmtStatus->fetchColumn();
    if ($apprStatus !== 'approved') {
        echo "<div class='card'><h3 style='color:red;'>Access Denied</h3><p>This Printing PO is awaiting admin approval and cannot be viewed yet.</p></div>";
        include_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
}

// Total Amount
$totalAmount = 0;
foreach ($items as &$item) {
    // Fetch site details if not fetched
    if ($item['site_id']) {
        $st = $pdo->prepare("SELECT name, site_code, width, height, city FROM sites WHERE id = ?");
        $st->execute([$item['site_id']]);
        $s = $st->fetch();
        if ($s) {
            $item['site_name'] = $s['name'];
            $item['site_code'] = $s['site_code'];
            $item['width'] = $s['width'];
            $item['height'] = $s['height'];
            $item['city'] = $s['city'];
        }
    }
    
    $sqft = floatval($item['width'] ?? 0) * floatval($item['height'] ?? 0);
    $amt = $sqft * floatval($item['rate_per_sqft']);
    $item['sqft'] = $sqft;
    $item['amount'] = $amt;
    $totalAmount += $amt;
}
unset($item);

$po_status = 'draft';
if ($po_number) {
    $stmtPO = $pdo->prepare("SELECT approval_status FROM purchase_orders WHERE po_number = ?");
    $stmtPO->execute([$po_number]);
    $po_status = $stmtPO->fetchColumn() ?: 'approved';
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">
                #<?php echo htmlspecialchars($po_num_display); ?></h1>
            <span style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700;">
                Vendor Printing PO
            </span>
            <?php if ($po_status === 'pending_approval'): ?>
                <span style="background: #fff7ed; color: #c2410c; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Awaiting Approval</span>
            <?php elseif ($po_status === 'approved'): ?>
                <span style="background: #f0fdf4; color: #15803d; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Approved</span>
            <?php elseif ($po_status === 'rejected'): ?>
                <span style="background: #fef2f2; color: #b91c1c; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Rejected</span>
            <?php endif; ?>
            <?php 
            $camp_brand = [];
            if (!empty($first['campaign_name'])) $camp_brand[] = trim($first['campaign_name']);
            if (!empty($first['brand_name'])) $camp_brand[] = trim($first['brand_name']);
            $display_camp_brand = implode(' / ', $camp_brand);
            if (!empty($display_camp_brand)): ?>
                <span style="background: #eff6ff; color: #2563eb; padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fas fa-bullhorn" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                </span>
            <?php endif; ?>
        </div>
        <p style="color: #64748b; margin: 0; font-size: 0.9rem;">
            Created on <?php echo date('d M Y, h:i A', strtotime($first['created_at'])); ?> by System
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="printing_rates.php" class="btn btn-secondary" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <?php 
            $pdfUrl = "../operations/generate_printing_po.php?vendor_id=" . $first['vendor_id'] . "&preview=1";
            if ($first['po_number']) {
                $pdfUrl .= "&po_number=" . urlencode($first['po_number']);
            } else {
                foreach($rate_ids as $id) $pdfUrl .= "&rate_ids[]=" . $id;
            }
        ?>
        <a href="<?php echo $pdfUrl; ?>" target="_blank" class="btn btn-primary" style="background: #0f172a; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none;">
            <i class="fas fa-file-pdf"></i> View PDF
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 3fr 1fr; gap: 2rem;">
    <div>
        <!-- Sites Table -->
        <div class="card" style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0;">Items in this PO</h3>
            </div>
            
            <div class="table-responsive">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 1rem; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Site / Location</th>
                            <th style="padding: 1rem; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Media Type</th>
                            <th style="padding: 1rem; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Dimension & SQFT</th>
                            <th style="padding: 1rem; text-align: right; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Rate / SQFT</th>
                            <th style="padding: 1rem; text-align: right; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $idx => $it): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 1rem;">
                                <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($it['site_name'] ?? 'Generic'); ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($it['site_code'] ?? '-'); ?> <?php if(!empty($it['city'])) echo " • " . htmlspecialchars($it['city']); ?></div>
                            </td>
                            <td style="padding: 1rem;">
                                <span style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($it['media_type']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="font-size: 0.85rem; color: #334155; font-weight: 600;">
                                    <?php echo floatval($it['width'] ?? 0); ?> x <?php echo floatval($it['height'] ?? 0); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #0d9488; font-weight: 800;">
                                    <?php echo number_format($it['sqft'], 2); ?> SQFT
                                </div>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <div style="font-size: 0.85rem; font-weight: 600; color: #1e293b;">
                                    ₹<?php echo number_format($it['rate_per_sqft'], 2); ?>
                                </div>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <div style="font-size: 0.95rem; font-weight: 800; color: #059669;">
                                    ₹<?php echo number_format($it['amount'], 2); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                        <tr>
                            <td colspan="4" style="padding: 1rem; text-align: right; font-weight: 800; color: #1e293b;">Grand Total</td>
                            <td style="padding: 1rem; text-align: right; font-weight: 900; color: #0f172a; font-size: 1.1rem;">
                                ₹<?php echo number_format($totalAmount, 2); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <div>
        <!-- Vendor Details -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
            <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
                <h3 style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 1rem 0; font-weight: 800;">Vendor Details</h3>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: #e0e7ff; color: #4f46e5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800;">
                        <?php echo strtoupper(substr($first['vendor_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: #1e293b; font-size: 1.1rem;"><?php echo htmlspecialchars($first['vendor_name']); ?></div>
                        <div style="color: #64748b; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; margin-top: 4px;">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($first['vendor_email'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice & Attachments Card -->
        <div class="card no-print" style="margin-bottom: 1.5rem; padding: 1.5rem;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="fas fa-file-invoice"></i> Vendor Invoice</h3>
            <?php if (!empty($first['vendor_invoice_no'])): ?>
                <div style="background: #f0fdf4; padding: 1rem; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 1rem;">
                    <small style="color: #166534;">Invoice Received</small>
                    <div style="font-weight: 700;"><?php echo htmlspecialchars($first['vendor_invoice_no']); ?></div>
                    <small><?php echo date('d M Y', strtotime($first['vendor_invoice_date'])); ?></small>
                </div>
            <?php else: ?>
                <?php if (canEdit('vendors') && $first['po_number']): ?>
                <form id="invoiceForm" style="margin-bottom: 1.5rem;">
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label style="font-size: 0.75rem;">Inv Number</label>
                        <input type="text" id="v_inv_no" class="p-input" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label style="font-size: 0.75rem;">Inv Date</label>
                        <input type="date" id="v_inv_date" class="p-input" required>
                    </div>
                    <button type="button" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;" onclick="updateVendorInvoice()">
                        Update Details
                    </button>
                </form>
                <?php else: ?>
                    <p style="font-size: 0.85rem; color: #94a3b8; font-style: italic;">No vendor invoice details recorded or draft PO.</p>
                <?php endif; ?>
            <?php endif; ?>

            <h3 style="font-size: 1rem; margin-top: 2rem; margin-bottom: 1rem;"><i class="fas fa-paperclip"></i> Attachments</h3>
            <div id="attachments-list">
                <?php 
                $atts = !empty($first['attachments']) ? explode('||', $first['attachments']) : [];
                foreach ($atts as $file): 
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $icon = 'fa-file';
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                    if ($ext === 'pdf') $icon = 'fa-file-pdf';
                ?>
                    <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                        <i class="fas <?php echo $icon; ?>" style="color: #ef4444;"></i>
                        <span style="flex: 1; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars(basename($file)); ?></span>
                        <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" target="_blank" class="btn-icon"><i class="fas fa-download"></i></a>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($atts)): ?>
                    <p style="font-size: 0.8rem; color: #94a3b8;">No documents attached.</p>
                <?php endif; ?>
            </div>
            
            <?php if (canEdit('vendors') && $first['po_number']): ?>
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
.p-input { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; margin-top: 0.25rem; font-size: 0.85rem; }
@media print {
    .sidebar, .header, .btn-primary, .btn-secondary, .no-print { display: none !important; }
    .main-content { margin: 0; padding: 0; width: 100% !important; }
    .card { border: none; box-shadow: none; }
    body { background: white; }
}
</style>

<script>
function updateVendorInvoice() {
    const no = document.getElementById('v_inv_no').value;
    const dt = document.getElementById('v_inv_date').value;
    if(!no || !dt) return alert('Fill invoice details');

    const formData = new FormData();
    formData.append('po_number', '<?php echo addslashes($first['po_number'] ?? ''); ?>');
    formData.append('no', no);
    formData.append('date', dt);

    fetch('../../ajax/update_printing_po_invoice.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.success) location.reload();
        else alert(res.message);
    });
}

function uploadPOFile() {
    const file = document.getElementById('po_file').files[0];
    if(!file) return;

    const formData = new FormData();
    formData.append('po_number', '<?php echo addslashes($first['po_number'] ?? ''); ?>');
    formData.append('file', file);

    Swal.fire({
        title: 'Uploading...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('../../ajax/upload_printing_attachment.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.success) {
            Swal.fire('Uploaded', 'File attached successfully.', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Upload failed', 'error');
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
