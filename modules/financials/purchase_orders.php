<?php
// Handle AJAX Actions before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    include_once __DIR__ . '/../../config/db.php';
    include_once __DIR__ . '/../../includes/functions.php';
    include_once __DIR__ . '/../../includes/trash_helper.php';
    checkAuth();
    requirePermission('financials', 'delete');
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    try {
        $pdo->beginTransaction();

        // Move po_items to trash
        $itemStmt = $pdo->prepare("SELECT id FROM po_items WHERE po_id = ?");
        $itemStmt->execute([$id]);
        while ($item = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
            move_row_to_trash($pdo, 'po_items', 'id', $item['id'], $_SESSION['user_id'] ?? null, 'PO deleted - item moved to trash');
        }

        // Move po_attachments to trash
        $attStmt = $pdo->prepare("SELECT id FROM po_attachments WHERE po_id = ?");
        $attStmt->execute([$id]);
        while ($att = $attStmt->fetch(PDO::FETCH_ASSOC)) {
            move_row_to_trash($pdo, 'po_attachments', 'id', $att['id'], $_SESSION['user_id'] ?? null, 'PO deleted - attachment moved to trash');
        }

        // Move purchase order itself to trash
        $trashId = move_row_to_trash($pdo, 'purchase_orders', 'id', $id, $_SESSION['user_id'] ?? null, 'Purchase order deleted via UI');
        if (!$trashId) {
            throw new Exception('Failed to move purchase order to trash');
        }

        logActivity('deleted PO', 'financials', $id, "Purchase Order #$id was deleted.");

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resubmit') {
    include_once __DIR__ . '/../../config/db.php';
    include_once __DIR__ . '/../../includes/functions.php';
    checkAuth();
    requirePermission('financials', 'edit');
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    try {
        $pdo->beginTransaction();

        // 1. Update PO status back to pending_approval / draft status
        $stmt = $pdo->prepare("UPDATE purchase_orders SET approval_status = 'pending_approval', status = 'draft', rejection_reason = NULL WHERE id = ?");
        $stmt->execute([$id]);

        // 2. Clear old request and insert a new approval request in queue
        $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'purchase_order' AND entity_id = ?")->execute([$id]);
        
        $poRef = $pdo->query("SELECT po_number FROM purchase_orders WHERE id = $id")->fetchColumn();
        $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')");
        $stmtAR->execute([$id, $poRef, $_SESSION['user_id'] ?? 0]);

        logActivity('resubmitted PO for approval', 'financials', $id, "Purchase Order #$poRef resubmitted.");

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$activePage = 'pos';
$pageTitle = 'Purchase Order Management';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

// Handle Filters
$selectedVendorId = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');
$invoiceStatusFilter = trim($_GET['invoice_status'] ?? '');

$whereClauses = [];
$params = [];

$activeEntityId = $_SESSION['active_entity_id'] ?? null;
if ($activeEntityId) {
    $whereClauses[] = "po.entity_id = ?";
    $params[] = $activeEntityId;
}

if ($selectedVendorId > 0) {
    $whereClauses[] = "po.vendor_id = ?";
    $params[] = $selectedVendorId;
}
if ($campaignFilter !== '') {
    $whereClauses[] = "po.campaign_name LIKE ?";
    $params[] = '%' . $campaignFilter . '%';
}
if ($invoiceStatusFilter === 'uploaded') {
    $whereClauses[] = "(po.vendor_invoice_no IS NOT NULL AND po.vendor_invoice_no != '')";
} elseif ($invoiceStatusFilter === 'pending') {
    $whereClauses[] = "(po.vendor_invoice_no IS NULL OR po.vendor_invoice_no = '')";
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Fetch filtered POs with attachments
$stmt = $pdo->prepare("
    SELECT po.*, po.campaign_name, v.name as vendor_name, v.email as vendor_email, v.phone as vendor_phone, u.username as creator, e.name as entity_name,
           (SELECT MIN(start_date) FROM po_items WHERE po_id = po.id) as start_date,
           (SELECT MAX(end_date) FROM po_items WHERE po_id = po.id) as end_date,
           (SELECT GROUP_CONCAT(filename SEPARATOR '||') FROM po_attachments WHERE po_id = po.id) as attachments
    FROM purchase_orders po 
    JOIN partners v ON po.vendor_id = v.id 
    LEFT JOIN users u ON po.employee_id = u.id 
    LEFT JOIN entities e ON po.entity_id = e.id
    $whereSql
    ORDER BY po.id DESC
");
$stmt->execute($params);
$pos = $stmt->fetchAll();

// Fetch all vendors for filter dropdown
$vendorsList = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
?>

<!-- Filter Bar -->
<div
    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin: 0;">
        <div style="flex: 1; min-width: 220px;">
            <label
                style="display: block; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Filter
                by Vendor</label>
            <select name="vendor_id"
                style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; font-weight: 600; background: white; outline: none; transition: border-color 0.2s;">
                <option value="">All Vendors</option>
                <?php foreach ($vendorsList as $v): ?>
                    <option value="<?php echo $v['id']; ?>" <?php echo $selectedVendorId == $v['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($v['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 1; min-width: 220px;">
            <label
                style="display: block; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Campaign Name</label>
            <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..."
                style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; font-weight: 600; background: white; outline: none; transition: border-color 0.2s;">
        </div>
        <div style="flex: 1; min-width: 220px;">
            <label
                style="display: block; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Invoice Status</label>
            <select name="invoice_status"
                style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; font-weight: 600; background: white; outline: none; transition: border-color 0.2s;">
                <option value="">All</option>
                <option value="uploaded" <?php echo $invoiceStatusFilter === 'uploaded' ? 'selected' : ''; ?>>Uploaded</option>
                <option value="pending" <?php echo $invoiceStatusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            </select>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary"
                style="height: 42px; padding: 0 1.5rem; font-weight: 800; font-size: 0.85rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(13,148,136,0.15);">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if ($selectedVendorId > 0 || $campaignFilter !== '' || $invoiceStatusFilter !== ''): ?>
                <a href="purchase_orders.php" class="btn"
                    style="height: 42px; padding: 0 1.25rem; font-weight: 800; font-size: 0.85rem; border-radius: 10px; background: #e2e8f0; color: #475569; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; border: none; justify-content: center;">
                    <i class="fas fa-times-circle"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Vendor Purchase Orders</h2>
    </div>

    <!-- Single Hidden File Input for AJAX Uploads -->
    <input type="file" id="po-list-upload-input" style="display: none;" onchange="handlePOUpload(this)"
        accept=".pdf,.png,.jpg,.jpeg">

    <table class="table">
        <thead>
            <tr>
                <th>PO Number</th>
                <th>Campaign</th>
                <th>Period</th>
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
                    <td>
                        <strong><?php echo $p['po_number']; ?></strong>
                        <?php if (!empty($p['entity_name'])): ?>
                            <div style="font-size: 0.7rem; color: #0d9488; font-weight: 800; margin-top: 3px;">
                                <i class="fas fa-building" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($p['entity_name']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($p['campaign_name'] ?: '—'); ?></td>
                    <td style="font-size: 0.8rem; white-space: nowrap; vertical-align: middle;">
                        <?php 
                        if (!empty($p['start_date']) && $p['start_date'] !== '0000-00-00' && !empty($p['end_date']) && $p['end_date'] !== '0000-00-00') {
                            echo '<span style="font-weight: 600; color: #475569;"><i class="fas fa-calendar-alt" style="color: #94a3b8; margin-right: 4px;"></i>' . date('d M', strtotime($p['start_date'])) . ' - ' . date('d M Y', strtotime($p['end_date'])) . '</span>';
                        } else {
                            echo '<span style="color: #cbd5e1;">—</span>';
                        }
                        ?>
                    </td>
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
                    <td style="font-size: 0.85rem; line-height: 1.4; white-space: nowrap; text-align: left; vertical-align: middle;">
                        <div style="font-weight: 500; color: #64748b;">Base: <span style="font-weight: 700; color: #334155;"><?php echo formatCurrency($p['po_amount']); ?></span></div>
                        <div style="font-weight: 500; color: #64748b;">Tax: <span style="font-weight: 700; color: #334155;"><?php echo formatCurrency($p['cgst_amount'] + $p['sgst_amount'] + $p['igst_amount']); ?></span></div>
                        <div style="font-weight: 700; color: #059669; border-top: 1px dashed #cbd5e1; margin-top: 2px; padding-top: 2px;">Total: <span><?php echo formatCurrency($p['total_amount']); ?></span></div>
                    </td>
                    <td>
                        <span class="status-pill status-<?php echo $p['status']; ?>">
                            <?php echo ucfirst($p['status']); ?>
                        </span>
                        <?php if (($p['approval_status'] ?? '') === 'pending_approval'): ?>
                            <div style="margin-top: 4px;">
                                <span
                                    style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;">
                                    <i class="fas fa-clock"></i> Awaiting Approval
                                </span>
                            </div>
                        <?php elseif (($p['approval_status'] ?? '') === 'rejected'): ?>
                            <div style="margin-top: 4px;">
                                <span
                                    style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;"
                                    title="<?php echo htmlspecialchars($p['rejection_reason'] ?? ''); ?>">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            </div>
                            <?php if (!empty($p['rejection_reason'])): ?>
                                <div style="font-size: 0.7rem; color: #ef4444; margin-top: 4px; max-width: 150px; word-break: break-word; font-weight: 500;">
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($p['rejection_reason']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <!-- Invoice Attachments Section -->
                            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                <span
                                    style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform:
                                         uppercase; margin-right: 4px; width: 95px; text-align: right; display: inline-block;">Vendor
                                    Invoice:</span>
                                <?php
                                if (!empty($p['attachments'])):
                                    $files = explode('||', $p['attachments']);
                                    $jsonFilesSafe = htmlspecialchars(json_encode($files), ENT_QUOTES, 'UTF-8');
                                    $poNumSafe = htmlspecialchars($p['po_number'] ?: ('PO-' . $p['id']), ENT_QUOTES, 'UTF-8');
                                    if (count($files) > 1):
                                    ?>
                                        <button type="button" onclick="showPoAttachments(<?php echo $jsonFilesSafe; ?>, '<?php echo $poNumSafe; ?>', <?php echo $p['id']; ?>)"
                                            class="attachment-badge" style="background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; cursor: pointer; padding: 2px 6px; font-weight: 700; font-size: 0.72rem;" title="View <?php echo count($files); ?> Attachments">
                                            <i class="fas fa-paperclip"></i> <?php echo count($files); ?>
                                        </button>
                                    <?php else: 
                                        $file = $files[0];
                                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                        $icon = 'fa-file';
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']))
                                            $icon = 'fa-file-image';
                                        if ($ext === 'pdf')
                                            $icon = 'fa-file-pdf';
                                        ?>
                                        <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" target="_blank"
                                            class="attachment-badge" title="<?php echo htmlspecialchars($file); ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </a>
                                    <?php endif;
                                endif;
                                ?>
                                <?php if (!empty($p['vendor_invoice_no'])): ?>
                                    <span onclick="openVendorInvoiceModal(<?php echo $p['id']; ?>)" style="font-size: 0.75rem; font-weight: 700; color: #1e293b; background: #e2e8f0; padding: 0.15rem 0.45rem; border-radius: 4px; margin-left: 4px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#cbd5e1';" onmouseout="this.style.background='#e2e8f0';" title="Click to Edit Vendor Invoice & Attachments">
                                        <i class="fas fa-file-invoice" style="font-size: 0.7rem; color: #4f46e5;"></i> #<?php echo htmlspecialchars($p['vendor_invoice_no']); ?>
                                        <?php if (!empty($p['vendor_invoice_date']) && $p['vendor_invoice_date'] !== '0000-00-00'): ?>
                                            <span style="font-weight: 500; color: #64748b; font-size: 0.7rem;">(<?php echo date('d M Y', strtotime($p['vendor_invoice_date'])); ?>)</span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['vendor_invoice_amount']) && floatval($p['vendor_invoice_amount']) > 0): ?>
                                            <span style="font-weight: 800; color: #0d9488; font-size: 0.7rem; border-left: 1px solid #cbd5e1; padding-left: 4.5px; margin-left: 2px;">₹<?php echo number_format($p['vendor_invoice_amount'], 2); ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (canEdit('financials')): ?>
                                    <button type="button" class="btn-upload-row" onclick="openVendorInvoiceModal(<?php echo $p['id']; ?>)"
                                        title="Manage Vendor Invoice & Attachments">
                                        <i class="fas <?php echo !empty($p['vendor_invoice_no']) ? 'fa-edit' : 'fa-cloud-upload-alt'; ?>"></i> <?php echo !empty($p['vendor_invoice_no']) ? 'Edit' : 'Upload'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Client Tax Invoice Section -->
                            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                <span
                                    style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 95px; text-align: right; display: inline-block;">Tax
                                    Invoice:</span>
                                <?php if (!empty($p['client_tax_order'])):
                                    $ext = strtolower(pathinfo($p['client_tax_order'], PATHINFO_EXTENSION));
                                    $icon = 'fa-file';
                                    if (in_array($ext, ['jpg', 'jpeg', 'png']))
                                        $icon = 'fa-file-image';
                                    if ($ext === 'pdf')
                                        $icon = 'fa-file-pdf';
                                    ?>
                                    <a href="../../uploads/pos/tax_orders/<?php echo rawurlencode($p['client_tax_order']); ?>"
                                        target="_blank" class="attachment-badge" style="background: #e0e7ff; color: #4f46e5;"
                                        title="Tax Invoice: <?php echo htmlspecialchars($p['client_tax_order']); ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (canEdit('financials')): ?>
                                    <button class="btn-upload-row"
                                        style="background: #eef2ff; color: #4f46e5; border-color: #c7d2fe;"
                                        onclick="triggerTaxOrderUpload(<?php echo $p['id']; ?>)" title="Upload Tax Invoice">
                                        <i class="fas fa-cloud-upload-alt"></i> Upload
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <td><?php echo htmlspecialchars($p['creator'] ?? '—'); ?></td>
                    <td style="white-space: nowrap;">
                        <?php 
                        $poStartDate = !empty($p['start_date']) && $p['start_date'] !== '0000-00-00' ? date('d M Y', strtotime($p['start_date'])) : '';
                        $poEndDate = !empty($p['end_date']) && $p['end_date'] !== '0000-00-00' ? date('d M Y', strtotime($p['end_date'])) : '';
                        $poDateStr = !empty($p['po_date']) && $p['po_date'] !== '0000-00-00' ? date('d M Y', strtotime($p['po_date'])) : date('d M Y', strtotime($p['created_at']));
                        $vendorEmailStr = addslashes($p['vendor_email'] ?? '');
                        $vendorPhoneStr = addslashes($p['vendor_phone'] ?? '');
                        $vendorNameStr = addslashes($p['vendor_name'] ?? 'Vendor');
                        ?>
                        <button type="button" onclick="sendPoEmail(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['po_number']); ?>', '<?php echo number_format($p['total_amount'], 2); ?>', '<?php echo $poDateStr; ?>', '<?php echo $poStartDate; ?>', '<?php echo $poEndDate; ?>', '<?php echo $vendorEmailStr; ?>', '<?php echo $vendorNameStr; ?>')" class="btn-icon" style="color: #ea4335; border: none; background: none; cursor: pointer; padding: 4px;" title="Send PO via Email (SMTP)">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        <button type="button" onclick="sendPoWhatsApp(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['po_number']); ?>', '<?php echo number_format($p['total_amount'], 2); ?>', '<?php echo $poDateStr; ?>', '<?php echo $poStartDate; ?>', '<?php echo $poEndDate; ?>', '<?php echo $vendorPhoneStr; ?>', '<?php echo $vendorNameStr; ?>')" class="btn-icon" style="color: #25D366; border: none; background: none; cursor: pointer; padding: 4px;" title="Send PO via WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        <a href="po_view.php?id=<?php echo $p['id']; ?>" class="btn-icon" title="View Purchase Order"><i
                                class="fas fa-eye"></i></a>
                        <?php if (canEdit('financials')): ?>
                            <button type="button" class="btn-icon" onclick="openVendorInvoiceModal(<?php echo $p['id']; ?>)" title="Manage Vendor Invoice & Attachments (Admin)" style="color: #4f46e5; background: #eef2ff;"><i class="fas fa-file-invoice"></i></button>
                        <?php endif; ?>
                        <?php if (($p['approval_status'] ?? '') === 'approved'): ?>
                            <a href="../operations/generate_po.php?po_id=<?php echo $p['id']; ?>" target="_blank"
                                class="btn-icon" style="color: var(--primary);" title="Download PDF"><i
                                    class="fas fa-file-pdf"></i></a>
                        <?php else: ?>
                            <span class="btn-icon" title="Locked (Awaiting Approval)"
                                style="color: #cbd5e1; cursor: not-allowed;"><i class="fas fa-lock"></i></span>
                        <?php endif; ?>
                        <?php if (canDelete('financials')): ?>
                            <button class="btn-icon" onclick="deletePO(<?php echo $p['id']; ?>)" title="Delete PO"
                                style="color: #ef4444;"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pos)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 2rem; color: var(--secondary);">No Purchase Orders
                        found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .badge-type {
        background: #f1f5f9;
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
    }

    .status-pill {
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-draft {
        background: #f1f5f9;
        color: #475569;
    }

    .status-approved {
        background: #e0f2fe;
        color: #0369a1;
    }

    .status-pending {
        background: #fef9c3;
        color: #854d0e;
    }

    .status-paid {
        background: #dcfce7;
        color: #166534;
    }

    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    @keyframes pulse-approval {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--secondary);
        font-size: 1rem;
        padding: 0.25rem;
        margin-right: 4px;
    }

    .btn-icon:hover {
        color: var(--primary);
    }

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

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<iframe id="po-pdf-render-iframe" style="position: fixed; left: -9999px; top: 0; width: 794px; height: 1200px; border: none; opacity: 0; pointer-events: none; z-index: -1;"></iframe>

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

    function deletePO(id) {
        Swal.fire({
            title: 'Delete Purchase Order?',
            text: "This will permanently remove the PO and all its items.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('purchase_orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&id=${id}`
                }).then(r => r.json()).then(res => {
                    if (res.success) {
                        Swal.fire('Deleted!', 'Purchase Order has been deleted.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                });
            }
        });
    }

    function resubmitPO(id) {
        Swal.fire({
            title: 'Resubmit Purchase Order?',
            text: "This will clear the rejection status and submit the PO again for approval.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Yes, submit it'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Submitting...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                fetch('purchase_orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=resubmit&id=${id}`
                }).then(r => r.json()).then(res => {
                    if (res.success) {
                        Swal.fire('Submitted!', 'Purchase Order has been resubmitted for approval.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }).catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Something went wrong.', 'error');
                });
            }
        });
    }

    // Helper function to render official Purchase Order to PDF Blob
    async function generatePoPdfBlob(poId) {
        try {
            const poResp = await fetch(`../../modules/operations/generate_po.php?po_id=${poId}`);
            if (!poResp.ok) return null;
            const poHtml = await poResp.text();

            let iframe = document.getElementById('po-pdf-render-iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'po-pdf-render-iframe';
                iframe.style.cssText = 'position: fixed; left: -9999px; top: 0; width: 794px; height: 1200px; border: none; opacity: 0; pointer-events: none; z-index: -1;';
                document.body.appendChild(iframe);
            }

            const iframeDoc = iframe.contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(poHtml);
            iframeDoc.close();

            // Inject overrides for perfect A4 rendering
            const styleElem = iframeDoc.createElement('style');
            styleElem.innerHTML = `
                * { box-sizing: border-box !important; }
                html, body {
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #ffffff !important;
                    font-family: Arial, sans-serif !important;
                    width: 794px !important;
                }
                .po-wrapper {
                    width: 794px !important;
                    max-width: 794px !important;
                    min-height: auto !important;
                    margin: 0 auto !important;
                    padding: 6mm 8mm !important;
                    box-shadow: none !important;
                    border: none !important;
                    box-sizing: border-box !important;
                    background: #ffffff !important;
                }
                .btn-print, .no-print { display: none !important; }
                table { width: 100% !important; border-collapse: collapse !important; }
                img { max-width: 100% !important; }
            `;
            iframeDoc.head.appendChild(styleElem);

            // Wait for all images inside iframe to load (letterhead logo, signature, stamps)
            const images = Array.from(iframeDoc.images);
            if (images.length > 0) {
                await Promise.all(images.map(img => {
                    if (img.complete && img.naturalHeight !== 0) return Promise.resolve();
                    return new Promise(resolve => {
                        img.onload = resolve;
                        img.onerror = resolve;
                        setTimeout(resolve, 2000);
                    });
                }));
            }

            // Stabilization pause
            await new Promise(resolve => setTimeout(resolve, 300));

            const targetElem = iframeDoc.querySelector('.po-wrapper') || iframeDoc.body;
            const canvas = await html2canvas(targetElem, {
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff',
                width: 794,
                windowWidth: 794
            });

            const JsPdfConstructor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : ((typeof jsPDF !== 'undefined') ? jsPDF : (window.jsPDF || null));
            if (!JsPdfConstructor) {
                console.error('jsPDF not loaded');
                return null;
            }

            const pdf = new JsPdfConstructor({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4',
                compress: true
            });

            const pageWidth = 210; // mm
            const pageHeight = 297; // mm
            const imgWidth = pageWidth;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            const imgData = canvas.toDataURL('image/jpeg', 0.95);

            let heightLeft = imgHeight;
            let position = 0;

            pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
            heightLeft -= pageHeight;

            while (heightLeft > 5) {
                position = heightLeft - imgHeight;
                pdf.addPage('a4', 'portrait');
                pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
                heightLeft -= pageHeight;
            }

            return pdf.output('blob');
        } catch (e) {
            console.error('Error generating PO PDF blob:', e);
            return null;
        }
    }

    // Helper function to render official Purchase Order to Base64 PDF
    async function generatePoPdfBase64(poId) {
        const blob = await generatePoPdfBlob(poId);
        if (!blob) return null;
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => {
                const dataUrl = reader.result;
                resolve(dataUrl.split(',')[1] || null);
            };
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(blob);
        });
    }

    // Send Single PO via Email (SMTP)
    async function sendPoEmail(poId, poNumber, totalAmt, poDate, poStartDate = '', poEndDate = '', vendorEmail = '', vendorName = 'Vendor') {
        const defaultSubject = `Purchase Order: ${poNumber} - Sudha Creative`;
        const periodText = (poStartDate && poEndDate) ? `for the period from **${poStartDate} to ${poEndDate}** ` : '';
        const defaultTemplateText = `Dear Sir/Madam,

Please be informed that the Purchase Order **${poNumber}** has been issued ${periodText}at a rate of **₹${totalAmt}**.

Please find the official Purchase Order PDF and booking details attached for your reference and records.

Thank you.

Regards,

Sudha Creative`;

        const { value: formValues } = await Swal.fire({
            title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#ea4335;"><i class="fas fa-paper-plane"></i> Send Purchase Order via Email</div>',
            html: `
                <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                    <div style="margin-bottom: 0.85rem;">
                        <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Recipient Email Address (Vendor) <span style="color:#ef4444;">*</span></label>
                        <input id="smtp_po_recipient_email" type="text" value="${vendorEmail}" placeholder="vendor@example.com" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                    </div>
                    <div style="margin-bottom: 0.85rem;">
                        <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Email Subject</label>
                        <input id="smtp_po_subject" type="text" value="${defaultSubject}" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                    </div>
                    <div style="margin-bottom: 0.85rem;">
                        <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Message Body</label>
                        <textarea id="smtp_po_message" style="width: 100%; box-sizing: border-box; height: 130px; font-size: 0.86rem; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; resize: vertical; line-height: 1.45; font-family: inherit;">${defaultTemplateText}</textarea>
                    </div>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; color: #166534; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.3rem;"></i>
                        <span>Official <b>${poNumber}.pdf</b> document will be rendered and attached directly to the email.</span>
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Email (SMTP)',
            confirmButtonColor: '#0d9488',
            cancelButtonText: '<i class="fab fa-google"></i> Open in Gmail Web',
            cancelButtonColor: '#ea4335',
            showCloseButton: true,
            preConfirm: () => {
                const emailVal = document.getElementById('smtp_po_recipient_email').value.trim();
                const subjectVal = document.getElementById('smtp_po_subject').value.trim();
                const msgVal = document.getElementById('smtp_po_message').value.trim();
                if (!emailVal) {
                    Swal.showValidationMessage('Please enter a valid recipient email address.');
                    return false;
                }
                return { email: emailVal, subject: subjectVal, message: msgVal };
            }
        });

        if (formValues) {
            Swal.fire({
                title: 'Preparing & Sending PO...',
                html: `
                    <div style="padding: 10px 0; text-align: center;">
                        <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #0d9488; margin-bottom: 12px;"></i><br>
                        Rendering official <b>${poNumber}.pdf</b> & dispatching via SMTP to <b>${formValues.email}</b>...
                    </div>
                `,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const poPdfBase64 = await generatePoPdfBase64(poId);

                const formData = new FormData();
                formData.append('po_id', poId);
                formData.append('email', formValues.email);
                formData.append('subject', formValues.subject);
                formData.append('message', formValues.message);
                if (poPdfBase64) {
                    const cleanPoNum = poNumber.replace(/[\/\\?%*:|"<>]/g, '_');
                    formData.append('po_pdf_base64', poPdfBase64);
                    formData.append('po_pdf_name', `Purchase_Order_${cleanPoNum}.pdf`);
                }

                const resp = await fetch('../../ajax/send_po_email.php', {
                    method: 'POST',
                    body: formData
                });
                const rawText = await resp.text();
                let res;
                try {
                    res = JSON.parse(rawText);
                } catch(jsonErr) {
                    const cleanError = rawText.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
                    throw new Error(cleanError || 'Invalid response from server.');
                }

                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'PO Sent Successfully!',
                        html: `<b>${res.message || 'Purchase Order sent successfully!'}</b><br><br><span style="color:#059669;font-size:0.88rem;"><i class="fas fa-paperclip"></i> Official PDF Attached (${poNumber}.pdf)</span><br><span style="color:#64748b;font-size:0.82rem;">Sender: sudhacreativemalda@gmail.com</span>`,
                        confirmButtonColor: '#0d9488'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sending Failed',
                        text: res.message || 'Could not send PO email via SMTP server.'
                    });
                }
            } catch (err) {
                Swal.fire('SMTP Error', err.message || 'Network error occurred while communicating with SMTP server.', 'error');
            }
        } else if (Swal.getDismissReason() === Swal.DismissReason.cancel) {
            const emailInput = document.getElementById('smtp_po_recipient_email')?.value.trim() || vendorEmail;
            const subjectInput = document.getElementById('smtp_po_subject')?.value.trim() || defaultSubject;
            const msgInput = document.getElementById('smtp_po_message')?.value.trim() || defaultTemplateText;
            const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailInput)}&su=${encodeURIComponent(subjectInput)}&body=${encodeURIComponent(msgInput)}`;
            window.open(gmailUrl, '_blank');
        }
    }

    // Send Single PO via WhatsApp with Attached Official PDF
    async function sendPoWhatsApp(poId, poNumber, totalAmt, poDate, poStartDate = '', poEndDate = '', vendorPhone = '', vendorName = 'Vendor') {
        const cleanPoNum = poNumber.replace(/[\/\\?%*:|"<>]/g, '_');
        const pdfFileName = `Purchase_Order_${cleanPoNum}.pdf`;

        await Swal.fire({
            title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#25D366;"><i class="fab fa-whatsapp"></i> Share PO PDF via WhatsApp</div>',
            html: `
                <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                    <div style="margin-bottom: 1rem;">
                        <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Vendor WhatsApp Number</label>
                        <div style="position: relative;">
                            <i class="fab fa-whatsapp" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #25D366; font-size: 1.1rem;"></i>
                            <input id="wa_po_phone" type="text" value="${vendorPhone}" placeholder="e.g. 9876543210 (or leave blank for chat selection)" style="width: 100%; box-sizing: border-box; font-size: 0.95rem; font-weight: 600; padding: 0.6rem 0.75rem 0.6rem 2.4rem; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none;">
                        </div>
                    </div>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.8rem; flex-shrink: 0;"></i>
                        <div style="flex: 1; overflow: hidden;">
                            <div style="font-weight: 800; color: #0f172a; font-size: 0.9rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${pdfFileName}</div>
                            <div style="font-size: 0.75rem; color: #15803d; margin-top: 2px;">PO #${poNumber} ${totalAmt ? '• ₹' + totalAmt : ''} • Ready for WhatsApp</div>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                        <button type="button" id="btnShareWaWeb" style="width: 100%; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.92rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(37,211,102,0.35); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                            <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Open & Share in WhatsApp Web
                        </button>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" id="btnShareWaApp" style="flex: 1; background: #0284c7; color: white; border: none; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                                <i class="fas fa-share-alt"></i> WhatsApp App / Mobile
                            </button>
                            <button type="button" id="btnDownloadPoPdf" style="flex: 1; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                                <i class="fas fa-download"></i> Download PDF Only
                            </button>
                        </div>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-times"></i> Close',
            cancelButtonColor: '#94a3b8',
            showCloseButton: true,
            didOpen: () => {
                document.getElementById('btnShareWaWeb')?.addEventListener('click', () => {
                    executePoShare('web');
                });
                document.getElementById('btnShareWaApp')?.addEventListener('click', () => {
                    executePoShare('app');
                });
                document.getElementById('btnDownloadPoPdf')?.addEventListener('click', () => {
                    executePoShare('download');
                });
            }
        });

        async function executePoShare(mode) {
            const phoneInput = document.getElementById('wa_po_phone')?.value.trim().replace(/[^0-9]/g, '') || '';
            let phoneFormatted = phoneInput;
            if (phoneFormatted && phoneFormatted.length === 10) phoneFormatted = '91' + phoneFormatted;

            if (mode === 'download') {
                Swal.fire({
                    title: 'Generating PDF...',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                const pdfBlob = await generatePoPdfBlob(poId);
                if (pdfBlob) {
                    const blobUrl = URL.createObjectURL(pdfBlob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = pdfFileName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(blobUrl);
                    Swal.fire({
                        icon: 'success',
                        title: 'PO PDF Downloaded!',
                        text: `${pdfFileName} has been saved to your downloads.`,
                        confirmButtonColor: '#0d9488'
                    });
                } else {
                    Swal.fire('Error', 'Failed to generate PDF.', 'error');
                }
                return;
            }

            Swal.fire({
                title: 'Preparing PO PDF...',
                html: `
                    <div style="padding: 10px 0; text-align: center;">
                        <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #25D366; margin-bottom: 12px;"></i><br>
                        Rendering official <b>${pdfFileName}</b> for WhatsApp...
                    </div>
                `,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const pdfBlob = await generatePoPdfBlob(poId);
                if (!pdfBlob) throw new Error('Could not generate PO PDF document.');

                const pdfFile = new File([pdfBlob], pdfFileName, { type: 'application/pdf' });

                if (mode === 'app') {
                    if (navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
                        Swal.close();
                        try {
                            await navigator.share({
                                files: [pdfFile]
                            });
                        } catch(shareErr) {
                            if (shareErr.name !== 'AbortError') {
                                triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                            }
                        }
                    } else {
                        triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                    }
                } else {
                    // WhatsApp Web mode
                    triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'web');
                }
            } catch(err) {
                Swal.fire('Error', err.message || 'Failed to prepare PO PDF for WhatsApp.', 'error');
            }
        }
    }

    function triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, target = 'web') {
        // 1. Download PDF to user's device
        const blobUrl = URL.createObjectURL(pdfBlob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = pdfFileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(blobUrl);

        // 2. Open WhatsApp Web or Desktop App (no prefilled message)
        let waUrl = '';
        if (target === 'app') {
            waUrl = phoneFormatted ? `whatsapp://send?phone=${phoneFormatted}` : `whatsapp://send`;
        } else {
            waUrl = phoneFormatted 
                ? `https://web.whatsapp.com/send?phone=${phoneFormatted}`
                : `https://web.whatsapp.com/`;
        }
        window.open(waUrl, '_blank');

        Swal.fire({
            icon: 'success',
            title: 'PDF Downloaded & WhatsApp Web Opened!',
            html: `
                <div style="text-align: left; font-size: 0.9rem; line-height: 1.5;">
                    <p style="margin: 0 0 8px 0;">📄 <b>${pdfFileName}</b> has been downloaded to your computer.</p>
                    <p style="margin: 0 0 8px 0;">💬 <b>${target === 'app' ? 'WhatsApp App' : 'WhatsApp Web'}</b> has been opened in a new window/tab.</p>
                    <div style="color: #065f46; font-weight: 600; background: #ecfdf5; padding: 10px 14px; border-radius: 8px; border: 1px solid #a7f3d0; margin-top: 10px;">
                        👉 Simply drag & drop the downloaded PDF into WhatsApp chat or click 📎 to attach and send!
                    </div>
                </div>
            `,
            confirmButtonText: 'Got It!',
            confirmButtonColor: '#25D366'
        });
    }
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>