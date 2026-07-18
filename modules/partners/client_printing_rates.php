<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('clients', 'view');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $client_id = intval($_POST['client_id']);
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);
        $campaign_name = isset($_POST['campaign_name']) ? clean($_POST['campaign_name']) : null;
        $brand_name = isset($_POST['brand_name']) ? clean($_POST['brand_name']) : null;
        $billing_gstin = !empty($_POST['billing_gstin']) ? clean($_POST['billing_gstin']) : null;

        if ($_POST['action'] === 'add') {
            requirePermission('clients', 'add');
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [null];
            $individual_rates = $_POST['individual_rates'] ?? [];
            $po_number = generateSequenceNumber($pdo, 'client_printing_draft');
            
            foreach ($site_ids as $site_id) {
                $site_id = !empty($site_id) ? intval($site_id) : null;
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                // Insert new rate with PO number to preserve historical PO data
                $insertStmt = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$client_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name, $billing_gstin]);
            }
            header("Location: client_printing_rates.php?msg=added"); exit;
        } else {
            requirePermission('clients', 'edit');
            
            $po_number = !empty($_POST['po_number']) ? clean($_POST['po_number']) : null;
            $rate_ids_post = isset($_POST['rate_ids']) ? $_POST['rate_ids'] : [];
            
            if (!$po_number) {
                $po_number = generateSequenceNumber($pdo, 'client_printing_draft');
                // Assign this new PO number to legacy records first so they are grouped
                if (!empty($rate_ids_post)) {
                    $in = str_repeat('?,', count($rate_ids_post) - 1) . '?';
                    $upd_legacy = $pdo->prepare("UPDATE client_printing_rates SET po_number = ?, campaign_name = ?, brand_name = ?, billing_gstin = ? WHERE id IN ($in)");
                    $upd_legacy->execute(array_merge([$po_number, $campaign_name, $brand_name, $billing_gstin], $rate_ids_post));
                }
            }
            
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [];
            $individual_rates = $_POST['individual_rates'] ?? [];
            
            $stmt = $pdo->prepare("SELECT id, site_id FROM client_printing_rates WHERE po_number = ? AND client_id = ?");
            $stmt->execute([$po_number, $client_id]);
            $existing = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [id => site_id]
            $existing_site_to_id = array_flip($existing); // [site_id => id]
            
            $posted_sites = [];
            foreach ($site_ids as $site_id) {
                if (empty($site_id)) continue;
                $site_id = intval($site_id);
                $posted_sites[] = $site_id;
                
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                if (isset($existing_site_to_id[$site_id])) {
                    // Update
                    $upd = $pdo->prepare("UPDATE client_printing_rates SET media_type=?, rate_per_sqft=?, campaign_name=?, brand_name=?, billing_gstin=? WHERE id=?");
                    $upd->execute([$media_type, $this_rate, $campaign_name, $brand_name, $billing_gstin, $existing_site_to_id[$site_id]]);
                } else {
                    // Insert
                    $meta = $pdo->prepare("SELECT customer_po_no, customer_po_date, email_date, is_final_invoice, approval_status, custom_invoice_number, custom_invoice_date FROM client_printing_rates WHERE po_number = ? AND client_id = ? LIMIT 1");
                    $meta->execute([$po_number, $client_id]);
                    $m = $meta->fetch();
                    
                    if ($m) {
                        $ins = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, customer_po_no, customer_po_date, email_date, is_final_invoice, approval_status, custom_invoice_number, custom_invoice_date, billing_gstin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$client_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name, $m['customer_po_no'], $m['customer_po_date'], $m['email_date'], $m['is_final_invoice'], $m['approval_status'], $m['custom_invoice_number'], $m['custom_invoice_date'], $billing_gstin]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$client_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name, $billing_gstin]);
                    }
                }
            }
            
            // Delete removed
            foreach ($existing_site_to_id as $es_site => $es_id) {
                if (!in_array($es_site, $posted_sites)) {
                    $pdo->prepare("DELETE FROM client_printing_rates WHERE id = ?")->execute([$es_id]);
                }
            }

            // Sync campaign/brand and billing_gstin across all rows of this PO
            // Also reset approval_status to pending_approval if it was rejected so admin can review again
            $syncStmt = $pdo->prepare("UPDATE client_printing_rates SET campaign_name = ?, brand_name = ?, billing_gstin = ?, approval_status = CASE WHEN approval_status = 'rejected' THEN 'pending_approval' ELSE approval_status END WHERE po_number = ?");
            $syncStmt->execute([$campaign_name, $brand_name, $billing_gstin, $po_number]);
            
            // Delete old rejection request if any and insert a new pending request
            $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'client_printing' AND entity_ref = ? AND status = 'pending'")->execute([$po_number]);
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('client_printing', 0, ?, ?, 'pending')");
            $stmtAR->execute([$po_number, $_SESSION['user_id'] ?? 0]);
            
            header("Location: client_printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
        requirePermission('clients', 'delete');
        header('Content-Type: application/json');
        include_once __DIR__ . '/../../includes/trash_helper.php';
        
        $po_number = $_POST['po_number'] ?? '';
        $rate_ids_str = $_POST['rate_ids'] ?? '';
        
        if (!empty($po_number)) {
            $stmt = $pdo->prepare("SELECT id FROM client_printing_rates WHERE po_number = ?");
            $stmt->execute([$po_number]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ids)) {
                move_multiple_rows_to_trash($pdo, 'client_printing_rates', 'id', $ids, $_SESSION['user_id'] ?? null, "Client Printing PO #$po_number deleted");
            }
        } elseif (!empty($rate_ids_str)) {
            $ids = explode('||', $rate_ids_str);
            $ids = array_map('intval', $ids);
            if (!empty($ids)) {
                move_multiple_rows_to_trash($pdo, 'client_printing_rates', 'id', $ids, $_SESSION['user_id'] ?? null, "Client printing rates deleted");
            }
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                move_multiple_rows_to_trash($pdo, 'client_printing_rates', 'id', [$id], $_SESSION['user_id'] ?? null, "Client printing rate ID $id deleted");
            }
        }
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'client_printing_rates';
$pageTitle = 'Client Printing Invoice';
include_once __DIR__ . '/../../includes/header.php';

$selectedClientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');

$queryWhere = "WHERE 1=1";
$params = [];
if ($selectedClientId) {
    $queryWhere .= " AND r.client_id = ?";
    $params[] = $selectedClientId;
}
if ($campaignFilter !== '') {
    $queryWhere .= " AND r.campaign_name LIKE ?";
    $params[] = '%' . $campaignFilter . '%';
}

// Fetch Rates Grouped by PO Number
$ratesStmt = $pdo->prepare("
    SELECT 
        r.po_number,
        r.client_id,
        c.name as client_name,
        GROUP_CONCAT(r.id SEPARATOR '||') as rate_ids,
        GROUP_CONCAT(COALESCE(s.name, 'Generic') SEPARATOR '||') as site_names,
        GROUP_CONCAT(COALESCE(s.site_code, '-') SEPARATOR '||') as site_codes,
        GROUP_CONCAT(COALESCE(s.width, 0) SEPARATOR '||') as widths,
        GROUP_CONCAT(COALESCE(s.height, 0) SEPARATOR '||') as heights,
        GROUP_CONCAT(r.media_type SEPARATOR '||') as media_types,
        GROUP_CONCAT(r.rate_per_sqft SEPARATOR '||') as rates,
        MIN(r.created_at) as created_at,
        SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as total_amount,
        MAX(r.attachments) as attachments,
        MAX(r.client_tax_order) as client_tax_order,
        MAX(r.is_final_invoice) as is_final_invoice,
        MAX(r.approval_status) as approval_status,
        MAX(r.campaign_name) as campaign_name,
        MAX(r.brand_name) as brand_name,
        MAX(r.custom_invoice_number) as custom_invoice_number,
        (SELECT remarks FROM approval_requests WHERE entity_type = 'client_printing' AND entity_ref COLLATE utf8mb4_unicode_ci = r.po_number COLLATE utf8mb4_unicode_ci ORDER BY id DESC LIMIT 1) as rejection_reason
    FROM client_printing_rates r
    JOIN partners c ON r.client_id = c.id
    LEFT JOIN sites s ON r.site_id = s.id
    $queryWhere
    GROUP BY r.po_number, r.client_id, c.name, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY MIN(r.id) DESC
");
$ratesStmt->execute($params);
$rates = $ratesStmt->fetchAll();

$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Client Printing Invoice</h2>
        <div style="display: flex; gap: 0.75rem;">
            <?php if (canAdd('clients')): ?>
            <a href="create_client_printing_po.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none; background: #0d9488; border-color: #0d9488;">
                <i class="fas fa-plus"></i> Add New Client Printing Invoice 
            </a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" action="client_printing_rates.php" style="display:flex; flex-wrap: wrap; gap:1rem; align-items:flex-end; margin-bottom:1.5rem;">
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Client</label>
            <select name="client_id" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:220px;">
                <option value="">All Clients</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>" <?php echo $selectedClientId === intval($client['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.35rem; min-width:280px;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Campaign Name</label>
            <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..." style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:280px;">
        </div>
        <div style="display:flex; gap:0.75rem;">
            <button type="submit" class="btn btn-primary" style="padding:0.85rem 1.25rem;">Filter</button>
            <a href="client_printing_rates.php" class="btn" style="background:#f8fafc; color:#475569; border:1px solid #cbd5e1; padding:0.85rem 1.25rem; text-decoration:none;">Reset</a>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Invoice / PO #</th>
                <th>Campaign / Brand</th>
                <th>Client</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rates)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--secondary); padding: 2rem;">No client printing invoices found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rates as $r): ?>
                 <?php 
                    $ids = explode('||', $r['rate_ids']);
                    
                    // Re-calculate Total Amount
                    $widths = explode('||', $r['widths']);
                    $heights = explode('||', $r['heights']);
                    $unitRates = explode('||', $r['rates']);
                    
                    $totalGroupAmount = 0;
                    foreach($ids as $i => $id) {
                        $sqft = floatval($widths[$i]) * floatval($heights[$i]);
                        $totalGroupAmount += ($sqft * floatval($unitRates[$i]));
                    }
                    $gst = $totalGroupAmount * 0.18;
                    $grandTotal = $totalGroupAmount + $gst;
                ?>
                <tr>
                    <td>
                        <?php if ($r['is_final_invoice'] && !empty($r['custom_invoice_number'])): ?>
                            <strong>#<?php echo $r['custom_invoice_number']; ?></strong>
                        <?php elseif ($r['po_number']): ?>
                            <strong>#<?php echo $r['po_number']; ?></strong>
                        <?php else: ?>
                            <span style="color: #cbd5e1; font-weight: 400;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $camp_brand = [];
                        if (!empty($r['campaign_name'])) $camp_brand[] = trim($r['campaign_name']);
                        if (!empty($r['brand_name'])) $camp_brand[] = trim($r['brand_name']);
                        $display_camp_brand = implode(' / ', $camp_brand);
                        if (!empty($display_camp_brand)): ?>
                            <div style="font-size: 0.72rem; color: #2563eb; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; padding: 2px 6px; border-radius: 4px;">
                                <i class="fas fa-bullhorn" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($r['client_name'], ENT_QUOTES, 'UTF-8', false); ?></div>
                    </td>
                    <td>
                        <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td>
                        <div style="font-weight: 800; color: #059669;">₹<?php echo number_format($totalGroupAmount, 2); ?></div>
                        <div style="font-size: 0.65rem; color: #94a3b8;">+GST: ₹<?php echo number_format($gst, 2); ?></div>
                        <div style="font-size: 0.7rem; font-weight: 800; color: #0f172a;">Total: ₹<?php echo number_format($grandTotal, 2); ?></div>
                    </td>
                    <td>
                        <?php if ($r['approval_status'] === 'pending_approval'): ?>
                            <span style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;" title="Pending Admin Approval">
                                <i class="fas fa-clock"></i> Awaiting Approval
                            </span>
                        <?php elseif ($r['approval_status'] === 'rejected'): ?>
                            <span style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;" title="Rejected by Admin">
                                <i class="fas fa-times-circle"></i> Rejected
                            </span>
                            <?php if (!empty($r['rejection_reason'])): ?>
                                <div style="font-size: 0.65rem; color: #ef4444; margin-top: 4px; font-weight: 600;" title="<?php echo htmlspecialchars($r['rejection_reason']); ?>">
                                    Reason: <?php echo htmlspecialchars($r['rejection_reason']); ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($r['is_final_invoice']): ?>
                            <span style="background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-check-circle"></i> Final Invoice
                            </span>
                        <?php else: ?>
                            <span style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-file-invoice"></i> Draft
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            $viewUrl = "view_client_printing_po.php?client_id=" . $r['client_id'];
                            if ($r['po_number']) {
                                $viewUrl .= "&po_number=" . urlencode($r['po_number']);
                            } else {
                                foreach($ids as $id) $viewUrl .= "&rate_ids[]=" . $id;
                            }
                        ?>
                        <?php if (hasRole('admin') || $r['approval_status'] === 'approved' || $r['approval_status'] === 'rejected'): ?>
                        <a href="<?php echo $viewUrl; ?>" class="btn-icon" style="color: #0d9488;" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($r['is_final_invoice']): ?>
                            <?php 
                                $taxInvUrl = "../operations/client_printing.php?client_id=" . $r['client_id'] . "&preview=1&is_final=1";
                                foreach($ids as $id) $taxInvUrl .= "&rate_ids[]=" . $id;
                            ?>
                            <a href="<?php echo $taxInvUrl; ?>" target="_blank" class="btn-icon" style="color: #10b981;" title="View Final Tax Invoice">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        <?php else: ?>
                            <?php if ($r['approval_status'] !== 'pending_approval'): ?>
                            <button class="btn-icon" onclick="openPrintingInvoicePopup('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', <?php echo $r['client_id']; ?>, '<?php echo implode(',', $ids); ?>')" style="color: #0f172a;" title="Generate Final Tax Invoice">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (hasRole('admin') || ($r['approval_status'] !== 'approved')): ?>
                        <?php 
                            $editUrl = "create_client_printing_po.php?action=edit&client_id=" . $r['client_id'];
                            if ($r['po_number']) {
                                $editUrl .= "&po_number=" . urlencode($r['po_number']);
                            } else {
                                foreach($ids as $id) $editUrl .= "&rate_ids[]=" . $id;
                            }
                        ?>
                        <a href="<?php echo $editUrl; ?>" class="btn-icon" style="color: #0284c7;" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (canDelete('clients')): ?>
                        <button class="btn-icon btn-delete" onclick="deleteRate('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($r['rate_ids'], ENT_QUOTES); ?>')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<input type="file" id="po-list-upload-input" style="display: none;" onchange="handlePOUpload(this)" accept=".pdf,.png,.jpg,.jpeg">

<style>
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
    color: #0d9488;
    transform: translateY(-1px);
}
</style>

<script>
let activeUploadPoNumber = null;
let activeUploadType = 'invoice';

function triggerUpload(poNumber) {
    if (!poNumber) return Swal.fire('Error', 'Invalid PO number for attachment.', 'error');
    activeUploadPoNumber = poNumber;
    activeUploadType = 'invoice';
    document.getElementById('po-list-upload-input').click();
}

function triggerTaxOrderUpload(poNumber) {
    if (!poNumber) return Swal.fire('Error', 'Invalid PO number for attachment.', 'error');
    activeUploadPoNumber = poNumber;
    activeUploadType = 'tax_order';
    document.getElementById('po-list-upload-input').click();
}

function handlePOUpload(input) {
    if (!input.files || input.files.length === 0 || !activeUploadPoNumber) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('po_number', activeUploadPoNumber);
    formData.append('file', file);
    
    Swal.fire({
        title: 'Uploading Document...',
        text: 'Please wait while the file is being processed.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const url = activeUploadType === 'tax_order' 
        ? '../../ajax/upload_client_printing_tax_order.php' 
        : '../../ajax/upload_client_printing_attachment.php';

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
                text: 'Attachment added successfully.',
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

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('client_id')) {
        const cId = urlParams.get('client_id');
        window.location.href = `create_client_printing_po.php?client_id=${cId}`;
    }
    
    // Show success message if present in URL
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        let text = '';
        if (msg === 'added') text = 'Client Printing Invoice created successfully.';
        if (msg === 'updated') text = 'Client Printing Invoice updated successfully.';
        
        if (text) {
            Swal.fire({
                title: 'Success',
                text: text,
                icon: 'success',
                confirmButtonColor: '#0d9488',
                timer: 2500,
                showConfirmButton: false
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});

function deleteRate(poNumber, rateIds) {
    Swal.fire({
        title: 'Delete Client Invoice / PO?',
        text: "Are you sure you want to remove this Client Printing Invoice and all its sites?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('client_printing_rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&po_number=${encodeURIComponent(poNumber)}&rate_ids=${encodeURIComponent(rateIds)}`
            }).then(() => {
                Swal.fire('Deleted!', 'Client Printing Invoice has been removed.', 'success').then(() => location.reload());
            });
        }
    });
}

function openPrintingInvoicePopup(poNumber, clientId, rateIdsStr) {
    Swal.fire({
        title: 'Printing PO Confirmation',
        html: `
            <div style="text-align: left;">
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">INVOICE NUMBER (Last Used: <?php echo getLastSequenceNumber($pdo, 'client_printing_po'); ?>) <span style="color:red;">*</span></label>
                <input id="custom_invoice_number" class="swal2-input" value="<?php echo getPreviewSequenceNumber($pdo, 'client_printing_po'); ?>" placeholder="e.g. SCRP/26-27/0001" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">

                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">INVOICE DATE <span style="color:red;">*</span></label>
                <input id="custom_invoice_date" type="date" class="swal2-input" value="<?php echo date('Y-m-d'); ?>" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">

                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CONFIRMATION TYPE <span style="color:red;">*</span></label>
                <select id="confirmation_type" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;" onchange="toggleConfFields()">
                    <option value="po">Customer Purchase Order (PO)</option>
                    <option value="email">Email Confirmation</option>
                </select>

                <div id="po_fields">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CUSTOMER PO NUMBER <span style="color:red;">*</span></label>
                    <input id="customer_po_no" class="swal2-input" placeholder="Enter PO ID" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                    
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">PO DATE <span style="color:red;">*</span></label>
                    <input id="customer_po_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>

                <div id="email_fields" style="display:none;">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">EMAIL CONFIRMATION DATE <span style="color:red;">*</span></label>
                    <input id="email_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>
                
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">UPLOAD ATTACHMENT (PDF/IMAGE) <span style="color:red;">*</span></label>
                <input id="customer_po_file" type="file" accept=".pdf,image/*" class="swal2-file" style="margin: 0; width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px;">
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Save & Generate Invoice',
        preConfirm: () => {
            const type = document.getElementById('confirmation_type').value;
            const po_no = document.getElementById('customer_po_no').value;
            const po_date = document.getElementById('customer_po_date').value;
            const email_date = document.getElementById('email_date').value;
            const po_file = document.getElementById('customer_po_file').files[0];
            const custom_invoice_number = document.getElementById('custom_invoice_number').value;
            const custom_invoice_date = document.getElementById('custom_invoice_date').value;
            
            if (!custom_invoice_number) { Swal.showValidationMessage(`Invoice Number is mandatory`); return false; }
            if (!custom_invoice_date) { Swal.showValidationMessage(`Invoice Date is mandatory`); return false; }
            
            if (type === 'po') {
                if (!po_no) { Swal.showValidationMessage(`Customer PO Number is mandatory`); return false; }
                if (!po_date) { Swal.showValidationMessage(`PO Date is mandatory`); return false; }
            }
            if (type === 'email') {
                if (!email_date) { Swal.showValidationMessage(`Email Confirmation Date is mandatory`); return false; }
            }
            if (!po_file) {
                Swal.showValidationMessage(`Please upload the PO/Email attachment (PDF/Image)`);
                return false;
            }
            
            let formData = new FormData();
            formData.append('po_number', poNumber);
            formData.append('client_id', clientId);
            formData.append('rate_ids', rateIdsStr);
            formData.append('confirmation_type', type);
            formData.append('customer_po_no', po_no);
            formData.append('customer_po_date', po_date);
            formData.append('email_date', email_date);
            formData.append('custom_invoice_number', custom_invoice_number);
            formData.append('custom_invoice_date', custom_invoice_date);
            formData.append('customer_po_file', po_file);
            
            return fetch('../../ajax/upload_printing_po.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Upload failed');
                }
                return data;
            }).catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let resData = result.value;
            if (resData && resData.approval_status === 'pending_approval') {
                Swal.fire('Approval Sent!', 'The Client Printing Invoice has been sent for admin approval.', 'success').then(() => {
                    window.location.reload();
                });
            } else {
                let rateIds = rateIdsStr.split(',');
                let taxInvUrl = `../operations/client_printing.php?client_id=${clientId}&preview=1&is_final=1`;
                rateIds.forEach(id => taxInvUrl += `&rate_ids[]=${id}`);
                window.open(taxInvUrl, '_blank');
                window.location.reload();
            }
        }
    });
}

function toggleConfFields() {
    const type = document.getElementById('confirmation_type').value;
    const poFields = document.getElementById('po_fields');
    const emailFields = document.getElementById('email_fields');
    if (poFields) poFields.style.display = type === 'po' ? 'block' : 'none';
    if (emailFields) emailFields.style.display = type === 'email' ? 'block' : 'none';
}

function togglePODetails(groupId) {
    const elements = document.querySelectorAll('.collapsible-po-' + groupId);
    const btn = document.getElementById('toggle-btn-' + groupId);
    if (elements.length > 0) {
        const isHidden = elements[0].style.display === 'none';
        elements.forEach(el => {
            el.style.display = isHidden ? 'block' : 'none';
        });
        const count = btn.getAttribute('data-count');
        if (isHidden) {
            btn.innerHTML = `<i class="fas fa-chevron-up"></i> Show less`;
        } else {
            btn.innerHTML = `<i class="fas fa-chevron-down"></i> + ${count} more site(s)`;
        }
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
