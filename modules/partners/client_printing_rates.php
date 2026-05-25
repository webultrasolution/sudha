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
            
            $po_number = !empty($_POST['po_number']) ? clean($_POST['po_number']) : null;
            $rate_ids_post = isset($_POST['rate_ids']) ? $_POST['rate_ids'] : [];
            
            if (!$po_number) {
                $po_number = "CPPO-" . date('ymd') . "-" . rand(100, 999);
                // Assign this new PO number to legacy records first so they are grouped
                if (!empty($rate_ids_post)) {
                    $in = str_repeat('?,', count($rate_ids_post) - 1) . '?';
                    $upd_legacy = $pdo->prepare("UPDATE client_printing_rates SET po_number = ? WHERE id IN ($in)");
                    $upd_legacy->execute(array_merge([$po_number], $rate_ids_post));
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
                    $upd = $pdo->prepare("UPDATE client_printing_rates SET media_type=?, rate_per_sqft=? WHERE id=?");
                    $upd->execute([$media_type, $this_rate, $existing_site_to_id[$site_id]]);
                } else {
                    // Insert
                    $meta = $pdo->prepare("SELECT customer_po_no, customer_po_date, email_date, is_final_invoice, approval_status, custom_invoice_number, custom_invoice_date FROM client_printing_rates WHERE po_number = ? AND client_id = ? LIMIT 1");
                    $meta->execute([$po_number, $client_id]);
                    $m = $meta->fetch();
                    
                    if ($m) {
                        $ins = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number, customer_po_no, customer_po_date, email_date, is_final_invoice, approval_status, custom_invoice_number, custom_invoice_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$client_id, $site_id, $media_type, $this_rate, $po_number, $m['customer_po_no'], $m['customer_po_date'], $m['email_date'], $m['is_final_invoice'], $m['approval_status'], $m['custom_invoice_number'], $m['custom_invoice_date']]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$client_id, $site_id, $media_type, $this_rate, $po_number]);
                    }
                }
            }
            
            // Delete removed
            foreach ($existing_site_to_id as $es_site => $es_id) {
                if (!in_array($es_site, $posted_sites)) {
                    $pdo->prepare("DELETE FROM client_printing_rates WHERE id = ?")->execute([$es_id]);
                }
            }
            
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
        MIN(r.created_at) as created_at,
        SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as total_amount,
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
                <th>Invoice / PO #</th>
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
                    <td colspan="6" style="text-align: center; color: var(--secondary); padding: 2rem;">No client printing invoices found.</td>
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
                ?>
                <tr>
                    <td>
                        <?php if($r['po_number']): ?>
                            <strong>#<?php echo $r['po_number']; ?></strong>
                        <?php else: ?>
                            <span style="color: #cbd5e1; font-weight: 400;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($r['client_name'], ENT_QUOTES, 'UTF-8', false); ?></div>
                    </td>
                    <td>
                        <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td style="font-weight: 800; color: #059669; white-space: nowrap;">
                        ₹<?php echo number_format($totalGroupAmount, 2); ?>
                    </td>
                    <td>
                        <?php if ($r['approval_status'] === 'pending_approval'): ?>
                            <span style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;" title="Pending Admin Approval">
                                <i class="fas fa-clock"></i> Awaiting Approval
                            </span>
                        <?php elseif ($r['is_final_invoice']): ?>
                            <span style="background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-check-circle"></i> Final Invoice
                            </span>
                        <?php else: ?>
                            <span style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-file-invoice"></i> Proforma
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
                        <a href="<?php echo $viewUrl; ?>" class="btn-icon" style="color: #0d9488;" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if ($r['is_final_invoice']): ?>
                            <?php 
                                $taxInvUrl = "../operations/client_printing.php?client_id=" . $r['client_id'] . "&preview=1&is_final=1";
                                foreach($ids as $id) $taxInvUrl .= "&rate_ids[]=" . $id;
                            ?>
                            <a href="<?php echo $taxInvUrl; ?>" target="_blank" class="btn-icon" style="color: #10b981;" title="View Final Tax Invoice">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        <?php else: ?>
                            <?php if (canEdit('clients') && $r['approval_status'] !== 'pending_approval'): ?>
                            <button class="btn-icon" onclick="openPrintingInvoicePopup('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', <?php echo $r['client_id']; ?>, '<?php echo implode(',', $ids); ?>')" style="color: #0f172a;" title="Generate Final Tax Invoice">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (canEdit('clients')): ?>
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
                        <button class="btn-icon btn-delete" onclick="deleteRate(<?php echo $ids[0]; ?>)" title="Delete">
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
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">INVOICE NUMBER</label>
                <input id="custom_invoice_number" class="swal2-input" placeholder="e.g. SCR/26-27/001 (Leave empty to auto-generate)" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">

                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">INVOICE DATE</label>
                <input id="custom_invoice_date" type="date" class="swal2-input" value="<?php echo date('Y-m-d'); ?>" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">

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
            const custom_invoice_number = document.getElementById('custom_invoice_number').value;
            const custom_invoice_date = document.getElementById('custom_invoice_date').value;
            
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
