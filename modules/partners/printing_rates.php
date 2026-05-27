<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('vendors', 'view');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $vendor_id = intval($_POST['vendor_id']);
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);

        if ($_POST['action'] === 'add') {
            requirePermission('vendors', 'add');
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [null];
            $individual_rates = $_POST['individual_rates'] ?? [];
            $po_number = "PPO-" . date('ymd') . "-" . rand(100, 999);
            
            foreach ($site_ids as $site_id) {
                $site_id = !empty($site_id) ? intval($site_id) : null;
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                // Insert new rate with PO number to preserve historical PO data
                $insertStmt = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft, po_number) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->execute([$vendor_id, $site_id, $media_type, $this_rate, $po_number]);
            }
            header("Location: printing_rates.php?msg=added"); exit;
        } else {
            requirePermission('vendors', 'edit');
            
            $po_number = !empty($_POST['po_number']) ? clean($_POST['po_number']) : null;
            $rate_ids_post = isset($_POST['rate_ids']) ? $_POST['rate_ids'] : [];
            
            if (!$po_number) {
                $po_number = "PPO-" . date('ymd') . "-" . rand(100, 999);
                // Assign this new PO number to legacy records first so they are grouped
                if (!empty($rate_ids_post)) {
                    $in = str_repeat('?,', count($rate_ids_post) - 1) . '?';
                    $upd_legacy = $pdo->prepare("UPDATE vendor_printing_rates SET po_number = ? WHERE id IN ($in)");
                    $upd_legacy->execute(array_merge([$po_number], $rate_ids_post));
                }
            }
            
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [];
            $individual_rates = $_POST['individual_rates'] ?? [];
            
            $stmt = $pdo->prepare("SELECT id, site_id FROM vendor_printing_rates WHERE po_number = ? AND vendor_id = ?");
            $stmt->execute([$po_number, $vendor_id]);
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
                    $upd = $pdo->prepare("UPDATE vendor_printing_rates SET media_type=?, rate_per_sqft=? WHERE id=?");
                    $upd->execute([$media_type, $this_rate, $existing_site_to_id[$site_id]]);
                } else {
                    // Insert
                    $meta = $pdo->prepare("SELECT client_tax_order, attachments FROM vendor_printing_rates WHERE po_number = ? AND vendor_id = ? LIMIT 1");
                    $meta->execute([$po_number, $vendor_id]);
                    $m = $meta->fetch();
                    
                    if ($m) {
                        $ins = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft, po_number, client_tax_order, attachments) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$vendor_id, $site_id, $media_type, $this_rate, $po_number, $m['client_tax_order'], $m['attachments']]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft, po_number) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$vendor_id, $site_id, $media_type, $this_rate, $po_number]);
                    }
                }
            }
            
            // Delete removed
            foreach ($existing_site_to_id as $es_site => $es_id) {
                if (!in_array($es_site, $posted_sites)) {
                    $pdo->prepare("DELETE FROM vendor_printing_rates WHERE id = ?")->execute([$es_id]);
                }
            }
            
            header("Location: printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
        requirePermission('vendors', 'delete');
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM vendor_printing_rates WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'printing_rates';
$pageTitle = 'Printing PO';
include_once __DIR__ . '/../../includes/header.php';

$selectedVendorId = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$queryWhere = $selectedVendorId ? "WHERE r.vendor_id = $selectedVendorId" : "";

// Fetch Rates Grouped by PO Number
$rates = $pdo->query("
    SELECT 
        r.po_number,
        r.vendor_id,
        v.name as vendor_name,
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
        MAX(r.client_tax_order) as client_tax_order
    FROM vendor_printing_rates r
    JOIN partners v ON r.vendor_id = v.id
    LEFT JOIN sites s ON r.site_id = s.id
    $queryWhere
    GROUP BY r.po_number, r.vendor_id, v.name, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY r.id DESC
")->fetchAll();

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$sites = $pdo->query("SELECT id, name, site_code, width, height, vendor_id, city, state, type, light_type, owner_type, status FROM sites ORDER BY site_code ASC")->fetchAll();

// Fetch filter values for advanced search criteria in the modal
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL AND width != '' AND height != '' ORDER BY width, height")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Printing PO</h2>
        <div style="display: flex; gap: 0.75rem;">
            <?php if (canAdd('vendors')): ?>
            <a href="create_printing_po.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <i class="fas fa-plus"></i> Add New Printing PO 
            </a>
            <?php endif; ?>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>PO Number</th>
                <th>Vendor</th>
                <th>Date</th>
                <th>Amount</th>
                <th style="text-align: center; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">Invoice Attachments</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rates)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--secondary); padding: 2rem;">No Vendor Printing POs found.</td>
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
                        <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($r['vendor_name']); ?></div>
                    </td>
                    <td>
                        <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td style="font-weight: 800; color: #059669; white-space: nowrap;">
                        ₹<?php echo number_format($totalGroupAmount, 2); ?>
                    </td>
                    <td>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <!-- Invoice Attachments Section -->
                            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 80px; text-align: right; display: inline-block;">Invoice:</span>
                                <?php
                                if (!empty($r['attachments'])):
                                    $atts = json_decode($r['attachments'], true);
                                    if ($atts && count($atts) > 0): 
                                        $file = $atts[0]['path'];
                                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                        $icon = 'fa-file';
                                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                        if ($ext === 'pdf') $icon = 'fa-file-pdf';
                                        ?>
                                        <a href="<?php echo htmlspecialchars($file); ?>" target="_blank"
                                            class="attachment-badge" title="View Attachment">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </a>
                                        <?php
                                    endif;
                                endif;
                                ?>
                                <?php if (canEdit('vendors') && $r['po_number']): ?>
                                    <button class="btn-upload-row" onclick="triggerUpload('<?php echo htmlspecialchars($r['po_number'], ENT_QUOTES); ?>')"
                                        title="Upload Invoice/Scan">
                                        <i class="fas fa-cloud-upload-alt"></i> Upload
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Tax Invoice Section -->
                            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 80px; text-align: right; display: inline-block;">Tax Invoice:</span>
                                <?php if (!empty($r['client_tax_order'])):
                                    $file = $r['client_tax_order'];
                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    $icon = 'fa-file';
                                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                    if ($ext === 'pdf') $icon = 'fa-file-pdf';
                                    ?>
                                    <a href="../../uploads/pos/tax_orders/<?php echo rawurlencode($file); ?>"
                                        target="_blank" class="attachment-badge" style="background: #e0e7ff; color: #4f46e5;"
                                        title="View Tax Invoice">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (canEdit('vendors') && $r['po_number']): ?>
                                    <button class="btn-upload-row"
                                        style="background: #eef2ff; color: #4f46e5; border-color: #c7d2fe;"
                                        onclick="triggerTaxOrderUpload('<?php echo htmlspecialchars($r['po_number'], ENT_QUOTES); ?>')" title="Upload Tax Invoice">
                                        <i class="fas fa-cloud-upload-alt"></i> Upload
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php 
                            $viewUrl = "view_vendor_printing_po.php?vendor_id=" . $r['vendor_id'];
                            if ($r['po_number']) {
                                $viewUrl .= "&po_number=" . urlencode($r['po_number']);
                            } else {
                                foreach($ids as $id) $viewUrl .= "&rate_ids[]=" . $id;
                            }
                        ?>
                        <a href="<?php echo $viewUrl; ?>" class="btn-icon" style="color: #0d9488;" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if (canEdit('vendors')): ?>
                        <?php 
                            $editUrl = "create_printing_po.php?action=edit&vendor_id=" . $r['vendor_id'];
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
                        
                        <?php if (canDelete('vendors')): ?>
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
        ? '../../ajax/upload_printing_tax_order.php' 
        : '../../ajax/upload_printing_attachment.php';

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
// Check for parameters on load
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('vendor_id')) {
        const vId = urlParams.get('vendor_id');
        window.location.href = `create_printing_po.php?vendor_id=${vId}`;
    }
    
    // Show success message if present in URL
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        let title = 'Success';
        let text = '';
        if (msg === 'added') text = 'Printing PO created successfully.';
        if (msg === 'updated') text = 'Printing PO updated successfully.';
        
        if (text) {
            Swal.fire({
                title: title,
                text: text,
                icon: 'success',
                confirmButtonColor: '#0d9488',
                timer: 2500,
                showConfirmButton: false
            });
            // Clean URL without reloading
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});

function deleteRate(id) {
    Swal.fire({
        title: 'Delete Rate?',
        text: "Are you sure you want to remove this Printing PO?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('printing_rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Rate has been removed.', 'success').then(() => location.reload());
            });
        }
    });
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
