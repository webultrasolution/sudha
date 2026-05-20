<?php
$activePage = 'pos';
$pageTitle = 'Purchase Order Management';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

// Handle Filters
$selectedVendorId = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

$whereClauses = [];
$params = [];

if ($selectedVendorId > 0) {
    $whereClauses[] = "po.vendor_id = ?";
    $params[] = $selectedVendorId;
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Fetch filtered POs with attachments
$stmt = $pdo->prepare("
    SELECT po.*, v.name as vendor_name, u.username as creator,
           (SELECT GROUP_CONCAT(filename SEPARATOR '||') FROM po_attachments WHERE po_id = po.id) as attachments
    FROM purchase_orders po 
    JOIN partners v ON po.vendor_id = v.id 
    LEFT JOIN users u ON po.employee_id = u.id 
    $whereSql
    ORDER BY po.id DESC
");
$stmt->execute($params);
$pos = $stmt->fetchAll();

// Fetch all vendors for filter dropdown
$vendorsList = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
?>

<!-- Filter Bar -->
<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin: 0;">
        <div style="flex: 1; min-width: 220px;">
            <label style="display: block; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Filter by Vendor</label>
            <select name="vendor_id" style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; font-weight: 600; background: white; outline: none; transition: border-color 0.2s;">
                <option value="">All Vendors</option>
                <?php foreach ($vendorsList as $v): ?>
                    <option value="<?php echo $v['id']; ?>" <?php echo $selectedVendorId == $v['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.5rem; font-weight: 800; font-size: 0.85rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(13,148,136,0.15);">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if ($selectedVendorId > 0): ?>
                <a href="purchase_orders.php" class="btn" style="height: 42px; padding: 0 1.25rem; font-weight: 800; font-size: 0.85rem; border-radius: 10px; background: #e2e8f0; color: #475569; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; border: none; justify-content: center;">
                    <i class="fas fa-times-circle"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Vendor Purchase Orders</h2>
        <?php if (canAdd('financials')): ?>
        <a href="po_create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New PO
        </a>
        <?php endif; ?>
    </div>

    <!-- Single Hidden File Input for AJAX Uploads -->
    <input type="file" id="po-list-upload-input" style="display: none;" onchange="handlePOUpload(this)" accept=".pdf,.png,.jpg,.jpeg">

    <table class="table">
        <thead>
            <tr>
                <th>PO Number</th>
                <th>Vendor</th>
                <th>Type</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Invoice Attachments</th>
                <th>Created By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pos as $p): ?>
            <tr>
                <td><strong><?php echo $p['po_number']; ?></strong></td>
                <td><?php echo $p['vendor_name']; ?></td>
                <td>
                    <?php 
                    $poType = strtolower(trim($p['type'] ?? 'direct'));
                    if ($poType === 'system') {
                        echo '<span class="badge-type" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem;">System PO</span>';
                    } else {
                        echo '<span class="badge-type" style="background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem;">Direct PO</span>';
                    }
                    ?>
                </td>
                <td><?php echo date('d M Y', strtotime($p['po_date'])); ?></td>
                <td><?php echo formatCurrency($p['total_amount']); ?></td>
                <td>
                    <span class="status-pill status-<?php echo $p['status']; ?>">
                        <?php echo ucfirst($p['status']); ?>
                    </span>
                    <?php if (($p['approval_status'] ?? '') === 'pending_approval'): ?>
                        <div style="margin-top: 4px;">
                            <span style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;">
                                <i class="fas fa-clock"></i> Awaiting Approval
                            </span>
                        </div>
                    <?php elseif (($p['approval_status'] ?? '') === 'rejected'): ?>
                        <div style="margin-top: 4px;">
                            <span style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;" title="<?php echo htmlspecialchars($p['rejection_reason'] ?? ''); ?>">
                                <i class="fas fa-times-circle"></i> Rejected
                            </span>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <!-- Invoice Attachments Section -->
                        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 80px; text-align: right; display: inline-block;">Invoice:</span>
                            <?php 
                            if (!empty($p['attachments'])): 
                                $files = explode('||', $p['attachments']);
                                foreach ($files as $file):
                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    $icon = 'fa-file';
                                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                    if ($ext === 'pdf') $icon = 'fa-file-pdf';
                            ?>
                                    <a href="../../uploads/pos/<?php echo urlencode($file); ?>" target="_blank" class="attachment-badge" title="<?php echo htmlspecialchars($file); ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </a>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                            <?php if (canEdit('financials')): ?>
                            <button class="btn-upload-row" onclick="triggerUpload(<?php echo $p['id']; ?>)" title="Upload Invoice/Scan">
                                <i class="fas fa-cloud-upload-alt"></i> Upload
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Client Tax Invoice Section -->
                        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 80px; text-align: right; display: inline-block;">Tax Invoice:</span>
                            <?php if (!empty($p['client_tax_order'])): 
                                $ext = strtolower(pathinfo($p['client_tax_order'], PATHINFO_EXTENSION));
                                $icon = 'fa-file';
                                if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                if ($ext === 'pdf') $icon = 'fa-file-pdf';
                            ?>
                                <a href="../../uploads/pos/tax_orders/<?php echo urlencode($p['client_tax_order']); ?>" target="_blank" class="attachment-badge" style="background: #e0e7ff; color: #4f46e5;" title="Tax Invoice: <?php echo htmlspecialchars($p['client_tax_order']); ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (canEdit('financials')): ?>
                            <button class="btn-upload-row" style="background: #eef2ff; color: #4f46e5; border-color: #c7d2fe;" onclick="triggerTaxOrderUpload(<?php echo $p['id']; ?>)" title="Upload Tax Invoice">
                                <i class="fas fa-cloud-upload-alt"></i> Upload
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><?php echo $p['creator']; ?></td>
                <td>
                    <a href="po_view.php?id=<?php echo $p['id']; ?>" class="btn-icon" title="View"><i class="fas fa-eye"></i></a>
                    <?php if (($p['approval_status'] ?? '') === 'approved'): ?>
                        <a href="../operations/generate_po.php?po_id=<?php echo $p['id']; ?>" target="_blank" class="btn-icon" style="color: var(--primary);" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                    <?php else: ?>
                        <span class="btn-icon" title="Locked (Awaiting Approval)" style="color: #cbd5e1; cursor: not-allowed;"><i class="fas fa-lock"></i></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pos)): ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 2rem; color: var(--secondary);">No Purchase Orders found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.badge-type { background: #f1f5f9; padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; color: #475569; }
.status-pill { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.status-draft { background: #f1f5f9; color: #475569; }
.status-approved { background: #e0f2fe; color: #0369a1; }
.status-pending { background: #fef9c3; color: #854d0e; }
.status-paid { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
@keyframes pulse-approval { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.btn-icon { background: none; border: none; cursor: pointer; color: var(--secondary); font-size: 1rem; padding: 0.25rem; margin-right: 4px; }
.btn-icon:hover { color: var(--primary); }

.attachment-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.85rem;
    transition: all 0.2s;
}
.attachment-badge:hover {
    background: #e2e8f0;
    color: var(--primary);
    transform: translateY(-1px);
}
.btn-upload-row {
    background: #f0fdf4;
    color: #166534;
    border: 1px dashed #bbf7d0;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}
.btn-upload-row:hover {
    background: #dcfce7;
    border-color: #86efac;
    transform: translateY(-1px);
}
</style>

<script>
let activeUploadPoId = null;
let activeUploadType = 'invoice';

function triggerUpload(poId) {
    activeUploadPoId = poId;
    activeUploadType = 'invoice';
    document.getElementById('po-list-upload-input').click();
}

function triggerTaxOrderUpload(poId) {
    activeUploadPoId = poId;
    activeUploadType = 'tax_order';
    document.getElementById('po-list-upload-input').click();
}

function handlePOUpload(input) {
    if (!input.files || input.files.length === 0 || !activeUploadPoId) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('po_id', activeUploadPoId);
    formData.append('file', file);
    
    Swal.fire({
        title: 'Uploading Document...',
        text: 'Please wait while the invoice or scan is being processed.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const url = activeUploadType === 'tax_order' 
        ? '../../ajax/upload_client_tax_order.php' 
        : '../../ajax/upload_po_attachment.php';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Uploaded Successfully!',
                text: activeUploadType === 'tax_order' ? 'Client Tax Invoice attached.' : 'Invoice/Scan attached to PO.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Upload Failed', res.message || 'Error occurred.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Network error. Please try again.', 'error');
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
