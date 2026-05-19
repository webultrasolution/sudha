<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $client_id = intval($_POST['client_id']);
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);

        if ($_POST['action'] === 'add') {
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
            $id = intval($_POST['id']);
            $site_id = !empty($_POST['site_id']) ? intval($_POST['site_id']) : null;
            $stmt = $pdo->prepare("UPDATE client_printing_rates SET client_id=?, site_id=?, media_type=?, rate_per_sqft=? WHERE id=?");
            $stmt->execute([$client_id, $site_id, $media_type, $rate, $id]);
            header("Location: client_printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
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
        MAX(r.is_final_invoice) as is_final_invoice
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
            <a href="create_client_printing_po.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none; background: #0d9488; border-color: #0d9488;">
                <i class="fas fa-plus"></i> Add New Client Printing Invoice 
            </a>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 40px;"></th>
                <th>Client</th>
                <th>Site / Dimension</th>
                <th>Media Type</th>
                <th>Rate (per SQFT)</th>
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
                <td style="text-align: center;">
                   
                   
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($r['client_name'], ENT_QUOTES, 'UTF-8', false); ?></strong>
                    <?php if($r['po_number']): ?>
                        <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 2px;">#<?php echo $r['po_number']; ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php foreach($ids as $i => $id): ?>
                        <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                            <div style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($sNames[$i]); ?></div>
                            <small style="color: #64748b;"><?php echo $sCodes[$i]; ?> (<?php echo $widths[$i]; ?>x<?php echo $heights[$i]; ?> = <strong><?php echo floatval($widths[$i]) * floatval($heights[$i]); ?> SQFT</strong>)</small>
                        </div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach($ids as $i => $id): ?>
                        <div style="height: 38px; display: flex; align-items: center;">
                            <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.7rem;"><?php echo htmlspecialchars($mediaTypes[$i]); ?></span>
                        </div>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach($ids as $i => $id): ?>
                        <div style="height: 38px; display: flex; flex-direction: column; justify-content: center;">
                            <strong style="color: var(--primary);">₹<?php echo number_format(floatval($unitRates[$i]), 2); ?></strong>
                            <div style="font-size: 0.65rem; color: #059669; font-weight: 700;">₹<?php echo number_format(floatval($unitRates[$i]) * floatval($widths[$i]) * floatval($heights[$i]), 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(count($ids) > 1): ?>
                        <div style="margin-top: 10px; padding-top: 5px; border-top: 2px solid #e2e8f0;">
                            <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Total Amount</div>
                            <strong style="color: #0f172a; font-size: 0.9rem;">₹<?php echo number_format($totalGroupAmount, 2); ?></strong>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-end;">
                        <?php foreach($ids as $i => $id): ?>
                            <div style="height: 38px; display: flex; align-items: center; gap: 8px;">
                                <?php 
                        $pdfUrl = "../operations/client_printing.php?client_id=" . $r['client_id'] . "&preview=1";
                        foreach($ids as $id) $pdfUrl .= "&rate_ids[]=" . $id;
                    ?>
                    <a href="<?php echo $pdfUrl; ?>" target="_blank" title="Download Group Client PO" style="color: #ef4444; font-size: 1.1rem;">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                                <a href="create_client_printing_po.php?action=edit&id=<?php echo $id; ?>" class="btn-icon" style="color: #0284c7; background: #e0f2fe; padding: 6px; border-radius: 8px; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; text-decoration: none;" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn-icon btn-delete" onclick="deleteRate(<?php echo $id; ?>)" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Row-level Final Tax Invoice Action -->
                        <div style="margin-top: 10px; padding-top: 5px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; width: 100%;">
                            <?php if ($r['is_final_invoice']): ?>
                                <?php 
                                    $taxInvUrl = "../operations/client_printing.php?client_id=" . $r['client_id'] . "&preview=1&is_final=1";
                                    foreach($ids as $id) $taxInvUrl .= "&rate_ids[]=" . $id;
                                ?>
                                <a href="<?php echo $taxInvUrl; ?>" target="_blank" class="btn" style="background: #10b981; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;" title="View Final Tax Invoice">
                                    <i class="fas fa-eye"></i> View Tax Invoice
                                </a>
                            <?php else: ?>
                                <button class="btn" onclick="openPrintingInvoicePopup('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', <?php echo $r['client_id']; ?>, '<?php echo implode(',', $ids); ?>')" style="background: #0f172a; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" title="Final Tax Invoice">
                                    <i class="fas fa-file-invoice-dollar"></i> Final Tax Invoice
                                </button>
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

<script>
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
                return true;
            }).catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let rateIds = rateIdsStr.split(',');
            let taxInvUrl = `../operations/client_printing.php?client_id=${clientId}&preview=1&is_final=1`;
            rateIds.forEach(id => taxInvUrl += `&rate_ids[]=${id}`);
            window.open(taxInvUrl, '_blank');
            window.location.reload();
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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
