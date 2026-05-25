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

        if ($_POST['action'] === 'add') {
            requirePermission('clients', 'add');
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [null];
            $individual_rates = $_POST['individual_rates'] ?? [];
            $po_number = "CPPO-" . date('ymd') . "-" . rand(100, 999);
            
            foreach ($site_ids as $site_id) {
                $site_id = !empty($site_id) ? intval($site_id) : null;
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                // Insert new rate with PO number to preserve historical PO data
                $insertStmt = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->execute([$client_id, $site_id, $media_type, $this_rate, $po_number]);
            }
            header("Location: client_printing_rates.php?msg=added"); exit;
        } else {
            requirePermission('clients', 'edit');
            $id = intval($_POST['id']);
            $site_id = !empty($_POST['site_id']) ? intval($_POST['site_id']) : null;
            $stmt = $pdo->prepare("UPDATE client_printing_rates SET client_id=?, site_id=?, media_type=?, rate_per_sqft=? WHERE id=?");
            $stmt->execute([$client_id, $site_id, $media_type, $rate, $id]);
            header("Location: client_printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
        requirePermission('clients', 'delete');
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM client_printing_rates WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'client_printing_rates';
$pageTitle = 'Client Printing Invoice';
include_once __DIR__ . '/../../includes/header.php';

$selectedClientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$queryWhere = $selectedClientId ? "WHERE r.client_id = $selectedClientId" : "";

// Fetch Rates Grouped by PO Number
$rates = $pdo->query("
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
        MAX(r.attachments) as attachments,
        MAX(r.client_tax_order) as client_tax_order,
        MAX(r.is_final_invoice) as is_final_invoice,
        MAX(r.approval_status) as approval_status
    FROM client_printing_rates r
    JOIN partners c ON r.client_id = c.id
    LEFT JOIN sites s ON r.site_id = s.id
    $queryWhere
    GROUP BY r.po_number, r.client_id, c.name, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY r.id DESC
")->fetchAll();

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

    <table class="table">
        <thead>
            <tr>
                <th>Client</th>
                <th>Site / Dimension</th>
                <th>Media Type</th>
                <th>Rate (per SQFT)</th>
                <th>Attachments</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rates as $r): ?>
            <?php 
                $ids = explode('||', $r['rate_ids']);
                $sNames = explode('||', $r['site_names']);
                $sCodes = explode('||', $r['site_codes']);
                $widths = explode('||', $r['widths']);
                $heights = explode('||', $r['heights']);
                $mediaTypes = explode('||', $r['media_types']);
                $unitRates = explode('||', $r['rates']);
                
                $totalGroupSqft = 0;
                $totalGroupAmount = 0;
                foreach($ids as $i => $id) {
                    $sqft = floatval($widths[$i]) * floatval($heights[$i]);
                    $totalGroupSqft += $sqft;
                    $totalGroupAmount += ($sqft * floatval($unitRates[$i]));
                }
            ?>
            <tr class="rate-row" data-client-id="<?php echo $r['client_id']; ?>">
                <td>
                    <strong><?php echo htmlspecialchars($r['client_name'], ENT_QUOTES, 'UTF-8', false); ?></strong>
                    <?php if($r['po_number']): ?>
                        <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 2px;">#<?php echo $r['po_number']; ?></div>
                    <?php endif; ?>
                </td>
                <?php 
                $has_multiple = count($ids) > 1;
                $groupId = $r['po_number'] ? $r['po_number'] : 'rate-' . $ids[0];
                ?>
                <td>
                    <!-- First site (always visible) -->
                    <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($sNames[0]); ?></div>
                        <small style="color: #64748b;"><?php echo $sCodes[0]; ?> (<?php echo $widths[0]; ?>x<?php echo $heights[0]; ?> = <strong><?php echo floatval($widths[0]) * floatval($heights[0]); ?> SQFT</strong>)</small>
                    </div>
                    
                    <!-- Collapsible sites -->
                    <?php if ($has_multiple): ?>
                        <div class="collapsible-po-<?php echo $groupId; ?>" style="display: none;">
                            <?php for($i = 1; $i < count($ids); $i++): ?>
                                <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                                    <div style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($sNames[$i]); ?></div>
                                    <small style="color: #64748b;"><?php echo $sCodes[$i]; ?> (<?php echo $widths[$i]; ?>x<?php echo $heights[$i]; ?> = <strong><?php echo floatval($widths[$i]) * floatval($heights[$i]); ?> SQFT</strong>)</small>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <a href="javascript:void(0);" onclick="togglePODetails('<?php echo $groupId; ?>')" id="toggle-btn-<?php echo $groupId; ?>" data-count="<?php echo (count($ids) - 1); ?>" style="font-size: 0.72rem; color: var(--primary); font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; background: #f0fdfa; padding: 4px 8px; border-radius: 6px; border: 1px solid #ccfbf1;">
                            <i class="fas fa-chevron-down"></i> + <?php echo (count($ids) - 1); ?> more site(s)
                        </a>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- First media type -->
                    <div style="height: 38px; display: flex; align-items: center;">
                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.7rem;"><?php echo htmlspecialchars($mediaTypes[0]); ?></span>
                    </div>
                    
                    <!-- Collapsible media types -->
                    <?php if ($has_multiple): ?>
                        <div class="collapsible-po-<?php echo $groupId; ?>" style="display: none;">
                            <?php for($i = 1; $i < count($ids); $i++): ?>
                                <div style="height: 38px; display: flex; align-items: center;">
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.7rem;"><?php echo htmlspecialchars($mediaTypes[$i]); ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- First rate -->
                    <div style="height: 38px; display: flex; flex-direction: column; justify-content: center;">
                        <strong style="color: var(--primary);">₹<?php echo number_format(floatval($unitRates[0]), 2); ?></strong>
                        <div style="font-size: 0.65rem; color: #059669; font-weight: 700;">₹<?php echo number_format(floatval($unitRates[0]) * floatval($widths[0]) * floatval($heights[0]), 2); ?></div>
                    </div>
                    
                    <!-- Collapsible rates -->
                    <?php if ($has_multiple): ?>
                        <div class="collapsible-po-<?php echo $groupId; ?>" style="display: none;">
                            <?php for($i = 1; $i < count($ids); $i++): ?>
                                <div style="height: 38px; display: flex; flex-direction: column; justify-content: center;">
                                    <strong style="color: var(--primary);">₹<?php echo number_format(floatval($unitRates[$i]), 2); ?></strong>
                                    <div style="font-size: 0.65rem; color: #059669; font-weight: 700;">₹<?php echo number_format(floatval($unitRates[$i]) * floatval($widths[$i]) * floatval($heights[$i]), 2); ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(count($ids) > 1): ?>
                        <div style="margin-top: 10px; padding-top: 5px; border-top: 2px solid #e2e8f0;">
                            <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Total Amount</div>
                            <strong style="color: #0f172a; font-size: 0.9rem;">₹<?php echo number_format($totalGroupAmount, 2); ?></strong>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($r['po_number'])): ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <!-- Invoice Attachments Section -->
                        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 100px; text-align: right;">Client Invoice:</span>
                            <?php 
                            if (!empty($r['attachments'])): 
                                $files = explode('||', $r['attachments']);
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
                            <?php if (canEdit('clients')): ?>
                            <button class="btn-upload-row" onclick="triggerUpload('<?php echo $r['po_number']; ?>')" title="Upload Client Invoice/Scan">
                                <i class="fas fa-cloud-upload-alt"></i> Upload
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Client Tax Invoice Section -->
                        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 100px; text-align: right;">Tax Invoice:</span>
                            <?php if (!empty($r['client_tax_order'])): 
                                $ext = strtolower(pathinfo($r['client_tax_order'], PATHINFO_EXTENSION));
                                $icon = 'fa-file';
                                if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                if ($ext === 'pdf') $icon = 'fa-file-pdf';
                            ?>
                                <a href="../../uploads/pos/tax_orders/<?php echo urlencode($r['client_tax_order']); ?>" target="_blank" class="attachment-badge" style="background: #e0e7ff; color: #4f46e5;" title="Tax Invoice: <?php echo htmlspecialchars($r['client_tax_order']); ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (canEdit('clients')): ?>
                            <button class="btn-upload-row" style="background: #eef2ff; color: #4f46e5; border-color: #c7d2fe;" onclick="triggerTaxOrderUpload('<?php echo $r['po_number']; ?>')" title="Upload Tax Invoice">
                                <i class="fas fa-cloud-upload-alt"></i> Upload
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <span style="color: #94a3b8; font-size: 0.75rem;">Requires PO</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-end;">
                        <!-- Group PDF Action -->
                        <div style="height: 38px; display: flex; align-items: center; gap: 8px;">
                            <?php 
                                $pdfUrl = "../operations/client_printing.php?client_id=" . $r['client_id'] . "&preview=1";
                                foreach($ids as $pdfId) $pdfUrl .= "&rate_ids[]=" . $pdfId;
                            ?>
                            <a href="<?php echo $pdfUrl; ?>" target="_blank" class="btn-icon" style="color: #ef4444; background: #fee2e2; padding: 6px; border-radius: 8px; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; text-decoration: none;" title="Download Group Client PO">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <?php if (canEdit('clients')): ?>
                            <a href="create_client_printing_po.php?action=edit&id=<?php echo $ids[0]; ?>" class="btn-icon" style="color: #0284c7; background: #e0f2fe; padding: 6px; border-radius: 8px; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; text-decoration: none;" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (canDelete('clients')): ?>
                            <button class="btn-icon btn-delete" onclick="deleteRate(<?php echo $ids[0]; ?>)" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Collapsible Actions -->
                        <?php if ($has_multiple): ?>
                            <div class="collapsible-po-<?php echo $groupId; ?>" style="display: none;">
                                <?php for($i = 1; $i < count($ids); $i++): ?>
                                    <div style="height: 38px; display: flex; align-items: center; gap: 8px;">
                                        <?php if (canEdit('clients')): ?>
                                        <a href="create_client_printing_po.php?action=edit&id=<?php echo $ids[$i]; ?>" class="btn-icon" style="color: #0284c7; background: #e0f2fe; padding: 6px; border-radius: 8px; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; text-decoration: none;" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (canDelete('clients')): ?>
                                        <button class="btn-icon btn-delete" onclick="deleteRate(<?php echo $ids[$i]; ?>)" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Row-level Final Tax Invoice Action -->
                        <div style="margin-top: 10px; padding-top: 5px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; width: 100%;">
                            <?php if ($r['approval_status'] === 'pending_approval'): ?>
                                <button class="btn" style="background: #f8fafc; color: #94a3b8; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid #cbd5e1; cursor: not-allowed; display: inline-flex; align-items: center; gap: 4px;" title="Pending Admin Approval">
                                    <i class="fas fa-lock"></i> Pending Approval
                                </button>
                            <?php elseif ($r['is_final_invoice']): ?>
                                <?php 
                                    $taxInvUrl = "../operations/client_printing.php?client_id=" . $r['client_id'] . "&preview=1&is_final=1";
                                    foreach($ids as $id) $taxInvUrl .= "&rate_ids[]=" . $id;
                                ?>
                                <a href="<?php echo $taxInvUrl; ?>" target="_blank" class="btn" style="background: #10b981; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;" title="View Final Tax Invoice">
                                    <i class="fas fa-eye"></i> View Tax Invoice
                                </a>
                            <?php else: ?>
                                <?php if (canEdit('clients')): ?>
                                <button class="btn" onclick="openPrintingInvoicePopup('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', <?php echo $r['client_id']; ?>, '<?php echo implode(',', $ids); ?>')" style="background: #0f172a; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" title="Final Tax Invoice">
                                    <i class="fas fa-file-invoice-dollar"></i> Final Tax Invoice
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rates)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 2rem; color: #94a3b8;">No Client Printing Invoices found.</td>
            </tr>
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

function deleteRate(id) {
    Swal.fire({
        title: 'Delete Client Rate?',
        text: "Are you sure you want to remove this Client Printing Invoice?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('client_printing_rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Client rate has been removed.', 'success').then(() => location.reload());
            });
        }
    });
}

function openPrintingInvoicePopup(poNumber, clientId, rateIdsStr) {
    Swal.fire({
        title: 'Printing PO Confirmation',
        html: `
            <div style="text-align: left;">
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CONFIRMATION TYPE</label>
                <select id="confirmation_type" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;" onchange="toggleConfFields()">
                    <option value="po">Customer Purchase Order (PO)</option>
                    <option value="email">Email Confirmation</option>
                </select>

                <div id="po_fields">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CUSTOMER PO NUMBER</label>
                    <input id="customer_po_no" class="swal2-input" placeholder="Enter PO ID" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                    
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">PO DATE</label>
                    <input id="customer_po_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>

                <div id="email_fields" style="display:none;">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">EMAIL CONFIRMATION DATE</label>
                    <input id="email_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>
                
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">UPLOAD ATTACHMENT (PDF/IMAGE)</label>
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
