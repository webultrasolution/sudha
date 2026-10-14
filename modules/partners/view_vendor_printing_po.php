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
$rate_ids = isset($_GET['rate_ids']) ? (is_array($_GET['rate_ids']) ? $_GET['rate_ids'] : explode(',', $_GET['rate_ids'])) : [];
$rate_ids = array_values(array_filter(array_map('intval', $rate_ids)));

if (empty($rate_ids) && !$po_number) {
    echo "<div class='card'>Invalid PO reference.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Build query (positional placeholders only; PDO rejects mixing named and positional)
$where = "r.vendor_id = ?";
$params = [$vendor_id];

if ($po_number) {
    $where .= " AND r.po_number = ?";
    $params[] = $po_number;
} else {
    $in = str_repeat('?,', count($rate_ids) - 1) . '?';
    $where .= " AND r.id IN ($in)";
    $params = array_merge($params, $rate_ids);
}

// Fetch Group info
$sql = "SELECT r.*, v.name as vendor_name, v.email as vendor_email,
               po.id as po_id, po.vendor_invoice_amount, po.vendor_invoice_approval_status, po.rejection_reason, po.total_amount as po_total_amount
        FROM vendor_printing_rates r 
        JOIN partners v ON r.vendor_id = v.id 
        LEFT JOIN purchase_orders po ON r.po_number = po.po_number
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
        <div class="card no-print" style="margin-bottom: 1.5rem; padding: 1.5rem; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-file-invoice" style="color: #0d9488;"></i> Vendor Invoice
                </h3>
            </div>
            
            <?php 
            $invoiceStatus = $first['vendor_invoice_approval_status'] ?? '';
            if ($invoiceStatus === 'approved' && !empty($first['vendor_invoice_no'])): 
            ?>
                <div style="background: #ecfdf5; padding: 1.25rem; border-radius: 12px; border: 1px solid #a7f3d0; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #065f46;">
                            <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
                            <strong style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Invoice Approved</strong>
                        </div>
                    </div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 2px;"><?php echo htmlspecialchars($first['vendor_invoice_no'] ?? ''); ?></div>
                    <div style="font-size: 0.75rem; color: #475569; margin-bottom: 6px;"><i class="far fa-calendar-alt"></i> Date: <?php echo date('d M Y', strtotime($first['vendor_invoice_date'])); ?></div>
                    <div style="font-size: 0.95rem; font-weight: 900; color: #065f46; margin-bottom: 0.75rem;">Amount: ₹<?php echo number_format($first['vendor_invoice_amount'], 2); ?></div>

                    <?php if (canEdit('vendors') || hasRole('admin')): ?>
                    <div style="display: flex; gap: 0.5rem; border-top: 1px dashed #a7f3d0; padding-top: 0.75rem;">
                        <button type="button" class="btn" style="flex: 1; border: 1px solid #cbd5e1; color: #334155; background: #fff; font-size: 0.75rem; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 700; cursor: pointer;" onclick="editInvoiceModal('<?php echo htmlspecialchars($first['po_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($first['vendor_invoice_no'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($first['vendor_invoice_date'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($first['vendor_invoice_amount'] ?? '', ENT_QUOTES); ?>')">
                            <i class="fas fa-edit"></i> Edit Details
                        </button>
                        <?php if (canDelete('vendors') || hasRole('admin')): ?>
                        <button type="button" class="btn" style="border: 1px solid #fecaca; color: #dc2626; background: #fff; font-size: 0.75rem; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 700; cursor: pointer;" onclick="resetInvoiceDetails('<?php echo htmlspecialchars($first['po_number'], ENT_QUOTES); ?>')" title="Remove/Reset Invoice Details">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 0.85rem; font-weight: 700; color: #475569; margin: 0;"><i class="fas fa-paperclip"></i> Invoice Attachments</h3>
                </div>
                <div id="attachments-list-approved" style="margin-bottom: 0.75rem;">
                    <?php 
                    $atts = !empty($first['attachments']) ? explode('||', $first['attachments']) : [];
                    foreach ($atts as $file): 
                        if (empty($file)) continue;
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $icon = 'fa-file';
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                        if ($ext === 'pdf') $icon = 'fa-file-pdf';
                    ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.5rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; background: #fff; font-size: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; overflow: hidden;">
                                <i class="fas <?php echo $icon; ?>" style="color: #ef4444; font-size: 1rem; flex-shrink: 0;"></i>
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.78rem; font-weight: 600; color: #334155;" title="<?php echo htmlspecialchars(basename($file)); ?>">
                                    <?php echo htmlspecialchars(basename($file)); ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                                <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" target="_blank" class="table-action-btn action-view" style="width: 26px; height: 26px; font-size: 0.75rem;" title="View"><i class="fas fa-eye"></i></a>
                                <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" download="<?php echo htmlspecialchars($file); ?>" class="table-action-btn" style="width: 26px; height: 26px; font-size: 0.75rem; color: #059669; background: #ecfdf5; border-color: #a7f3d0;" title="Download"><i class="fas fa-download"></i></a>
                                <?php if (canEdit('vendors') || canDelete('vendors') || hasRole('admin')): ?>
                                    <button type="button" class="table-action-btn action-delete" style="width: 26px; height: 26px; font-size: 0.75rem;" onclick="deleteAttachment('<?php echo htmlspecialchars($first['po_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($file, ENT_QUOTES); ?>')" title="Delete Attachment"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($atts)): ?>
                        <p style="font-size: 0.8rem; color: #94a3b8; font-style: italic; margin-bottom: 0.5rem;">No documents attached.</p>
                    <?php endif; ?>
                </div>

                <?php if (canEdit('vendors') || hasRole('admin')): ?>
                <div style="margin-top: 0.5rem;">
                    <input type="file" id="po_file_approved" style="display: none;" onchange="uploadPOFileFromInput(this)">
                    <button type="button" class="btn" style="width: 100%; border: 1px dashed #0d9488; color: #0d9488; background: #f0fdfa; font-size: 0.75rem; padding: 0.45rem; border-radius: 6px; font-weight: 700; cursor: pointer;" onclick="document.getElementById('po_file_approved').click()">
                        <i class="fas fa-plus-circle"></i> Upload Additional Attachment
                    </button>
                </div>
                <?php endif; ?>
                
            <?php elseif ($invoiceStatus === 'pending' && !empty($first['vendor_invoice_no'])): ?>
                <div style="background: #fffbeb; padding: 1.25rem; border-radius: 12px; border: 1px solid #fde68a; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #92400e;">
                            <i class="fas fa-hourglass-half" style="font-size: 1.1rem;"></i>
                            <strong style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Awaiting Admin Approval</strong>
                        </div>
                    </div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 2px;"><?php echo htmlspecialchars($first['vendor_invoice_no'] ?? ''); ?></div>
                    <div style="font-size: 0.75rem; color: #475569; margin-bottom: 6px;"><i class="far fa-calendar-alt"></i> Date: <?php echo date('d M Y', strtotime($first['vendor_invoice_date'])); ?></div>
                    <div style="font-size: 0.95rem; font-weight: 900; color: #d97706; margin-bottom: 0.75rem;">Amount: ₹<?php echo number_format($first['vendor_invoice_amount'], 2); ?></div>

                    <?php if (canEdit('vendors') || hasRole('admin')): ?>
                    <div style="display: flex; gap: 0.5rem; border-top: 1px dashed #fde68a; padding-top: 0.75rem;">
                        <button type="button" class="btn" style="flex: 1; border: 1px solid #cbd5e1; color: #334155; background: #fff; font-size: 0.75rem; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 700; cursor: pointer;" onclick="editInvoiceModal('<?php echo htmlspecialchars($first['po_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($first['vendor_invoice_no'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($first['vendor_invoice_date'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($first['vendor_invoice_amount'] ?? '', ENT_QUOTES); ?>')">
                            <i class="fas fa-edit"></i> Edit Details
                        </button>
                        <?php if (canDelete('vendors') || hasRole('admin')): ?>
                        <button type="button" class="btn" style="border: 1px solid #fecaca; color: #dc2626; background: #fff; font-size: 0.75rem; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 700; cursor: pointer;" onclick="resetInvoiceDetails('<?php echo htmlspecialchars($first['po_number'], ENT_QUOTES); ?>')" title="Remove/Reset Invoice Details">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 0.85rem; font-weight: 700; color: #475569; margin: 0;"><i class="fas fa-paperclip"></i> Invoice Attachments</h3>
                </div>
                <div id="attachments-list-pending" style="margin-bottom: 0.75rem;">
                    <?php 
                    $atts = !empty($first['attachments']) ? explode('||', $first['attachments']) : [];
                    foreach ($atts as $file): 
                        if (empty($file)) continue;
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $icon = 'fa-file';
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                        if ($ext === 'pdf') $icon = 'fa-file-pdf';
                    ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.5rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; background: #fff; font-size: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; overflow: hidden;">
                                <i class="fas <?php echo $icon; ?>" style="color: #ef4444; font-size: 1rem; flex-shrink: 0;"></i>
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.78rem; font-weight: 600; color: #334155;" title="<?php echo htmlspecialchars(basename($file)); ?>">
                                    <?php echo htmlspecialchars(basename($file)); ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                                <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" target="_blank" class="table-action-btn action-view" style="width: 26px; height: 26px; font-size: 0.75rem;" title="View"><i class="fas fa-eye"></i></a>
                                <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" download="<?php echo htmlspecialchars($file); ?>" class="table-action-btn" style="width: 26px; height: 26px; font-size: 0.75rem; color: #059669; background: #ecfdf5; border-color: #a7f3d0;" title="Download"><i class="fas fa-download"></i></a>
                                <?php if (canEdit('vendors') || canDelete('vendors') || hasRole('admin')): ?>
                                    <button type="button" class="table-action-btn action-delete" style="width: 26px; height: 26px; font-size: 0.75rem;" onclick="deleteAttachment('<?php echo htmlspecialchars($first['po_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($file, ENT_QUOTES); ?>')" title="Delete Attachment"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($atts)): ?>
                        <p style="font-size: 0.8rem; color: #94a3b8; font-style: italic; margin-bottom: 0.5rem;">No documents attached.</p>
                    <?php endif; ?>
                </div>

                <?php if (canEdit('vendors') || hasRole('admin')): ?>
                <div style="margin-top: 0.5rem;">
                    <input type="file" id="po_file_pending" style="display: none;" onchange="uploadPOFileFromInput(this)">
                    <button type="button" class="btn" style="width: 100%; border: 1px dashed #0d9488; color: #0d9488; background: #f0fdfa; font-size: 0.75rem; padding: 0.45rem; border-radius: 6px; font-weight: 700; cursor: pointer;" onclick="document.getElementById('po_file_pending').click()">
                        <i class="fas fa-plus-circle"></i> Upload Additional Attachment
                    </button>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <?php if ($invoiceStatus === 'rejected'): ?>
                    <div style="background: #fef2f2; padding: 1rem; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 1rem; font-size: 0.8rem; color: #991b1b;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i> <strong>Invoice Rejected</strong>
                        <?php if (!empty($first['rejection_reason'])): ?>
                            <p style="margin: 4px 0 0 0; font-style: italic;"><?php echo htmlspecialchars($first['rejection_reason']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (canEdit('vendors') && $first['po_number']): ?>
                <form id="invoiceForm" style="margin-bottom: 1.5rem;">
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">Inv Number <span style="color:red;">*</span></label>
                        <input type="text" id="v_inv_no" class="p-input" value="<?php echo htmlspecialchars($first['vendor_invoice_no'] ?? ''); ?>" placeholder="e.g. INV-12345" required>
                    </div>
                    <div class="form-group" style="margin-top: 0.75rem; margin-bottom: 0.75rem;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">Inv Date <span style="color:red;">*</span></label>
                        <input type="date" id="v_inv_date" class="p-input" value="<?php echo htmlspecialchars($first['vendor_invoice_date'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group" style="margin-top: 0.75rem; margin-bottom: 0.75rem;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">Invoice Amount (₹) <span style="color:red;">*</span></label>
                        <input type="number" id="v_inv_amount" step="0.01" class="p-input" value="<?php echo htmlspecialchars($first['vendor_invoice_amount'] ?? $first['po_total_amount'] ?? ''); ?>" placeholder="e.g. 10220.00" required>
                    </div>

                    <!-- Attachments inside the Form -->
                    <h3 style="font-size: 0.85rem; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #475569;"><i class="fas fa-paperclip"></i> Upload Invoice Scan <span style="color:red;">*</span></h3>
                    <div id="attachments-list" style="margin-bottom: 0.75rem;">
                        <?php 
                        $atts = !empty($first['attachments']) ? explode('||', $first['attachments']) : [];
                        foreach ($atts as $file): 
                            if (empty($file)) continue;
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            $icon = 'fa-file';
                            if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                            if ($ext === 'pdf') $icon = 'fa-file-pdf';
                        ?>
                            <div id="att-item-<?php echo htmlspecialchars($file); ?>" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.5rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; background: #fff; font-size: 0.85rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; overflow: hidden;">
                                    <i class="fas <?php echo $icon; ?>" style="color: #ef4444; font-size: 1rem; flex-shrink: 0;"></i>
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.78rem; font-weight: 600; color: #334155;" title="<?php echo htmlspecialchars(basename($file)); ?>">
                                        <?php echo htmlspecialchars(basename($file)); ?>
                                    </span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                                    <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" target="_blank" class="table-action-btn action-view" style="width: 26px; height: 26px; font-size: 0.75rem;" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" download="<?php echo htmlspecialchars($file); ?>" class="table-action-btn" style="width: 26px; height: 26px; font-size: 0.75rem; color: #059669; background: #ecfdf5; border-color: #a7f3d0;" title="Download"><i class="fas fa-download"></i></a>
                                    <button type="button" class="table-action-btn action-delete" style="width: 26px; height: 26px; font-size: 0.75rem;" onclick="deleteAttachment('<?php echo htmlspecialchars($first['po_number'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($file, ENT_QUOTES); ?>')" title="Delete Attachment"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($atts)): ?>
                            <p id="no-attachments-msg" style="font-size: 0.8rem; color: #94a3b8; font-style: italic; margin-bottom: 0.5rem;">No files uploaded yet.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Upload dashed button -->
                    <div style="margin-bottom: 1.5rem;">
                        <input type="file" id="po_file" style="display: none;" onchange="uploadPOFile()">
                        <button type="button" class="btn" style="width: 100%; border: 1px dashed var(--primary); color: var(--primary); font-size: 0.8rem; padding: 0.5rem;" onclick="document.getElementById('po_file').click()">
                            <i class="fas fa-upload"></i> Upload Invoice/Scan
                        </button>
                    </div>

                    <!-- Submit button at the very bottom -->
                    <button type="button" class="btn btn-primary" style="width: 100%; background: var(--primary); padding: 0.75rem; font-weight: 700; border-radius: 8px;" onclick="updateVendorInvoice()">
                        <i class="fas fa-paper-plane"></i> Submit for Approval
                    </button>
                </form>
                <?php else: ?>
                    <p style="font-size: 0.85rem; color: #94a3b8; font-style: italic;">No vendor invoice details recorded.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.p-input { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; margin-top: 0.25rem; font-size: 0.85rem; box-sizing: border-box; }

/* Table Action Buttons */
.table-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    font-size: 0.8rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    text-decoration: none;
}
.table-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}
.table-action-btn.action-view:hover {
    background: #f0fdfa;
    border-color: #99f6e4;
    color: #0d9488;
}
.table-action-btn.action-delete:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

@media print {
    .sidebar, .header, .btn-primary, .btn-secondary, .no-print { display: none !important; }
    .main-content { margin: 0; padding: 0; width: 100% !important; }
    .card { border: none; box-shadow: none; }
    body { background: white; }
}
</style>

<script>
let attachmentCount = <?php echo count($atts ?? []); ?>;

function updateVendorInvoice() {
    const no = document.getElementById('v_inv_no').value;
    const dt = document.getElementById('v_inv_date').value;
    const amt = document.getElementById('v_inv_amount').value;
    
    if(!no || !dt || !amt) {
        return Swal.fire('Error', 'Please fill all invoice details (Number, Date, Amount).', 'error');
    }
    if(attachmentCount === 0) {
        return Swal.fire('Attachment Required', 'Please upload at least one invoice scan/file before submitting.', 'warning');
    }

    Swal.showLoading();
    
    const formData = new FormData();
    formData.append('po_number', '<?php echo addslashes($first['po_number'] ?? ''); ?>');
    formData.append('no', no);
    formData.append('date', dt);
    formData.append('amount', amt);

    fetch('../../ajax/update_printing_po_invoice.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.success) {
            const msg = res.auto_approved ? 'Vendor invoice updated and approved.' : 'Vendor invoice submitted for admin approval.';
            Swal.fire('Success!', msg, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Failed to submit invoice.', 'error');
        }
    }).catch(err => {
        console.error(err);
        Swal.fire('Error', 'Network error. Please try again.', 'error');
    });
}

function uploadPOFile() {
    const fileInput = document.getElementById('po_file');
    uploadPOFileFromInput(fileInput);
}

function uploadPOFileFromInput(input) {
    if(!input || !input.files || input.files.length === 0) return;
    const file = input.files[0];

    Swal.showLoading();

    const formData = new FormData();
    formData.append('po_number', '<?php echo addslashes($first['po_number'] ?? ''); ?>');
    formData.append('file', file);

    fetch('../../ajax/upload_printing_attachment.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.success) {
            Swal.fire('Uploaded', 'File attached successfully.', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Upload failed', 'error');
        }
    }).catch(err => {
        console.error(err);
        Swal.fire('Error', 'Network error. Please try again.', 'error');
    });
}

function deleteAttachment(poNumber, filename) {
    Swal.fire({
        title: 'Delete Attachment?',
        text: "Are you sure you want to remove this attachment?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const formData = new FormData();
            formData.append('po_number', poNumber);
            formData.append('file', filename);

            fetch('../../ajax/delete_printing_attachment.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    Swal.fire('Deleted!', 'Attachment has been deleted.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to delete attachment', 'error');
                }
            }).catch(err => {
                console.error(err);
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        }
    });
}

function editInvoiceModal(poNumber, currentNo, currentDate, currentAmount) {
    Swal.fire({
        title: 'Edit Vendor Invoice Details',
        html: `
            <div style="text-align: left; display: flex; flex-direction: column; gap: 0.75rem;">
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Invoice Number</label>
                    <input type="text" id="swal-edit-inv-no" class="swal2-input" value="${currentNo || ''}" placeholder="e.g. INV-1234" style="margin: 0; width: 100%; box-sizing: border-box;">
                </div>
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Invoice Date</label>
                    <input type="date" id="swal-edit-inv-date" class="swal2-input" value="${currentDate || ''}" style="margin: 0; width: 100%; box-sizing: border-box;">
                </div>
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Invoice Amount (₹)</label>
                    <input type="number" step="0.01" id="swal-edit-inv-amount" class="swal2-input" value="${currentAmount || ''}" placeholder="e.g. 10220" style="margin: 0; width: 100%; box-sizing: border-box;">
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        confirmButtonColor: '#0d9488',
        cancelButtonColor: '#64748b',
        preConfirm: () => {
            const no = document.getElementById('swal-edit-inv-no').value;
            const date = document.getElementById('swal-edit-inv-date').value;
            const amount = document.getElementById('swal-edit-inv-amount').value;
            
            if (!no) {
                Swal.showValidationMessage('Please enter invoice number');
                return false;
            }
            if (!date) {
                Swal.showValidationMessage('Please select invoice date');
                return false;
            }
            if (!amount || parseFloat(amount) <= 0) {
                Swal.showValidationMessage('Please enter valid invoice amount');
                return false;
            }
            return { no, date, amount };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const data = result.value;
            const formData = new FormData();
            formData.append('po_number', poNumber);
            formData.append('no', data.no);
            formData.append('date', data.date);
            formData.append('amount', data.amount);

            fetch('../../ajax/update_printing_po_invoice.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    Swal.fire('Updated!', 'Invoice details updated successfully.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to update invoice.', 'error');
                }
            }).catch(err => {
                console.error(err);
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        }
    });
}

function resetInvoiceDetails(poNumber) {
    Swal.fire({
        title: 'Delete / Reset Invoice Details?',
        text: "This will remove the invoice number, date, amount, and approval status for this PO. Attachments will be kept unless deleted individually.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, reset details'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const formData = new FormData();
            formData.append('po_number', poNumber);
            formData.append('action', 'delete_invoice');

            fetch('../../ajax/update_printing_po_invoice.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    Swal.fire('Reset!', 'Invoice details have been reset.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to reset invoice.', 'error');
                }
            }).catch(err => {
                console.error(err);
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
