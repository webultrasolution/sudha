<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS client_mounting_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    site_id INT DEFAULT NULL,
    po_number VARCHAR(50) DEFAULT NULL,
    mounting_type VARCHAR(50) DEFAULT 'Standard',
    rate_per_sqft DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attachments VARCHAR(255) DEFAULT NULL,
    vendor_invoice_no VARCHAR(100) DEFAULT NULL,
    vendor_invoice_date DATE DEFAULT NULL,
    invoice_number VARCHAR(100) DEFAULT NULL,
    is_final_invoice TINYINT(1) DEFAULT 0,
    approval_status ENUM('draft','pending_approval','approved','rejected') DEFAULT 'draft',
    gst_type ENUM('igst','cgst_sgst') DEFAULT 'igst',
    customer_po_no VARCHAR(100) DEFAULT NULL,
    customer_po_date DATE DEFAULT NULL,
    custom_invoice_number VARCHAR(100) DEFAULT NULL,
    custom_invoice_date DATE DEFAULT NULL,
    invoice_date DATE DEFAULT NULL,
    sub_total DECIMAL(15,2) DEFAULT 0,
    cgst DECIMAL(15,2) DEFAULT 0,
    sgst DECIMAL(15,2) DEFAULT 0,
    igst DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) DEFAULT 0
)");

requirePermission('clients', 'view');

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        requirePermission('clients', 'delete');
        header('Content-Type: application/json');
        $pdo->prepare("DELETE FROM client_mounting_rates WHERE id = ?")->execute([intval($_POST['id'])]);
        echo json_encode(['success' => true]); exit;
    }

    // Handle finalize invoice
    if ($_POST['action'] === 'finalize') {
        requirePermission('clients', 'edit');
        header('Content-Type: application/json');
        $po = clean($_POST['po_number']);
        $cid = intval($_POST['client_id']);
        $customNo   = !empty($_POST['custom_invoice_number']) ? clean($_POST['custom_invoice_number']) : null;
        $customDate = !empty($_POST['custom_invoice_date'])   ? clean($_POST['custom_invoice_date'])   : date('Y-m-d');
        $gstType    = in_array($_POST['gst_type'] ?? 'igst', ['igst','cgst_sgst']) ? $_POST['gst_type'] : 'igst';
        $custPONo   = clean($_POST['customer_po_no'] ?? '');
        $custPODate = !empty($_POST['customer_po_date']) ? clean($_POST['customer_po_date']) : null;

        // Generate invoice number if not provided
        if (!$customNo) {
            $yr   = date('y'); $mn = date('m');
            $last = $pdo->query("SELECT COUNT(*) FROM client_mounting_rates WHERE invoice_number IS NOT NULL")->fetchColumn();
            $customNo = "CMI/{$yr}-" . ($yr+1) . "/" . str_pad($last + 1, 3, '0', STR_PAD_LEFT);
        }

        // Calculate totals for this PO
        $stmt = $pdo->prepare("SELECT r.rate_per_sqft, s.width, s.height FROM client_mounting_rates r LEFT JOIN sites s ON r.site_id = s.id WHERE r.po_number = ? AND r.client_id = ?");
        $stmt->execute([$po, $cid]);
        $rows = $stmt->fetchAll();
        $subTotal = 0;
        foreach ($rows as $row) $subTotal += $row['rate_per_sqft'] * ($row['width'] ?? 0) * ($row['height'] ?? 0);
        $cgst = $sgst = $igst = 0;
        if ($gstType === 'igst') $igst = round($subTotal * 0.18, 2);
        else { $cgst = $sgst = round($subTotal * 0.09, 2); }
        $totalAmt = $subTotal + $cgst + $sgst + $igst;

        $pdo->prepare("UPDATE client_mounting_rates SET is_final_invoice=1, approval_status='approved',
            custom_invoice_number=?, invoice_date=?, gst_type=?, customer_po_no=?, customer_po_date=?,
            sub_total=?, cgst=?, sgst=?, igst=?, total_amount=?
            WHERE po_number=? AND client_id=?")
            ->execute([$customNo, $customDate, $gstType, $custPONo, $custPODate,
                       $subTotal, $cgst, $sgst, $igst, $totalAmt, $po, $cid]);

        echo json_encode(['success' => true, 'invoice_number' => $customNo]); exit;
    }
}

$activePage = 'mounting';
$pageTitle  = 'Client Mounting Invoice';
include_once __DIR__ . '/../../includes/header.php';

$rates = $pdo->query("
    SELECT
        r.po_number,
        r.client_id,
        c.name as client_name,
        GROUP_CONCAT(r.id SEPARATOR '||') as rate_ids,
        GROUP_CONCAT(COALESCE(s.site_code, '-') SEPARATOR '||') as site_codes,
        GROUP_CONCAT(COALESCE(s.width,  0) SEPARATOR '||') as widths,
        GROUP_CONCAT(COALESCE(s.height, 0) SEPARATOR '||') as heights,
        GROUP_CONCAT(r.rate_per_sqft SEPARATOR '||') as rates,
        GROUP_CONCAT(r.mounting_type SEPARATOR '||') as mounting_types,
        MIN(r.created_at) as created_at,
        MAX(r.is_final_invoice) as is_final_invoice,
        MAX(r.approval_status) as approval_status,
        MAX(r.custom_invoice_number) as invoice_number,
        MAX(r.invoice_date) as invoice_date
    FROM client_mounting_rates r
    JOIN partners c ON r.client_id = c.id
    LEFT JOIN sites s ON r.site_id = s.id
    GROUP BY r.po_number, r.client_id, c.name, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY MIN(r.id) DESC
")->fetchAll();
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <div>
            <h2 style="font-size:1.25rem; margin:0;">Client Mounting Invoice</h2>
            <p style="margin:0; font-size:0.8rem; color:#64748b; margin-top:2px;">Manage mounting charges billed to clients</p>
        </div>
        <div style="display:flex; gap:0.75rem;">
            <?php if (canAdd('clients')): ?>
            <a href="create_mounting_po.php" class="btn btn-primary"
               style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; background:#0d9488; border-color:#0d9488;">
                <i class="fas fa-plus"></i> Add New Mounting Invoice
            </a>
            <?php endif; ?>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Invoice / PO #</th>
                <th>Client</th>
                <th>Sites</th>
                <th>Mounting Type</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rates)): ?>
                <tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:2rem;">No Client Mounting Invoices found.</td></tr>
            <?php else: ?>
                <?php foreach ($rates as $r):
                    $ids       = explode('||', $r['rate_ids']);
                    $widths    = explode('||', $r['widths']);
                    $heights   = explode('||', $r['heights']);
                    $unitRates = explode('||', $r['rates']);
                    $siteCodes = explode('||', $r['site_codes']);
                    $mtypes    = array_unique(explode('||', $r['mounting_types']));
                    $subTotal  = 0;
                    foreach ($ids as $i => $id)
                        $subTotal += floatval($widths[$i]) * floatval($heights[$i]) * floatval($unitRates[$i]);
                    $gst      = $subTotal * 0.18;
                    $grandTotal = $subTotal + $gst;
                ?>
                <tr>
                    <td>
                        <?php if ($r['invoice_number']): ?>
                            <strong style="color:#0d9488;"><?php echo htmlspecialchars($r['invoice_number']); ?></strong><br>
                            <span style="font-size:0.65rem; color:#94a3b8;"><?php echo htmlspecialchars($r['po_number'] ?? ''); ?></span>
                        <?php elseif ($r['po_number']): ?>
                            <strong style="color:#0d9488;">#<?php echo htmlspecialchars($r['po_number']); ?></strong>
                        <?php else: ?>
                            <span style="color:#cbd5e1;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><div style="font-weight:700; color:#1e293b;"><?php echo htmlspecialchars($r['client_name']); ?></div></td>
                    <td>
                        <div style="font-size:0.8rem; color:#475569; font-weight:600;"><?php echo count($ids); ?> site<?php echo count($ids)>1?'s':''; ?></div>
                        <div style="font-size:0.7rem; color:#94a3b8;">
                            <?php echo htmlspecialchars(implode(', ', array_slice($siteCodes, 0, 3))); ?>
                            <?php if (count($siteCodes) > 3) echo ' +' . (count($siteCodes)-3) . ' more'; ?>
                        </div>
                    </td>
                    <td>
                        <?php foreach ($mtypes as $mt): ?>
                        <span style="background:#f0fdfa;color:#0d9488;padding:0.1rem 0.5rem;border-radius:4px;font-size:0.65rem;font-weight:800;display:inline-block;margin:1px;"><?php echo htmlspecialchars($mt); ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td style="font-size:0.85rem; color:#475569;">
                        <?php echo $r['invoice_date'] ? date('d M Y', strtotime($r['invoice_date'])) : date('d M Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td>
                        <div style="font-weight:800; color:#059669;">₹<?php echo number_format($subTotal, 2); ?></div>
                        <div style="font-size:0.65rem; color:#94a3b8;">+GST: ₹<?php echo number_format($gst, 2); ?></div>
                        <div style="font-size:0.7rem; font-weight:800; color:#0f172a;">Total: ₹<?php echo number_format($grandTotal, 2); ?></div>
                    </td>
                    <td>
                        <?php if ($r['is_final_invoice']): ?>
                            <span style="background:#f0fdf4;color:#15803d;border:1px solid #dcfce7;padding:0.15rem 0.5rem;border-radius:50px;font-size:0.6rem;font-weight:800;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-check-circle"></i> Final Invoice
                            </span>
                        <?php else: ?>
                            <span style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:0.15rem 0.5rem;border-radius:50px;font-size:0.6rem;font-weight:800;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-file-invoice"></i> Draft
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php
                        $viewUrl = "view_mounting_invoice.php?client_id={$r['client_id']}";
                        if ($r['po_number']) $viewUrl .= "&po_number=" . urlencode($r['po_number']);
                        else foreach ($ids as $id) $viewUrl .= "&rate_ids[]=$id";
                        ?>
                        <a href="<?php echo $viewUrl; ?>" target="_blank" class="btn-icon" style="color:#0d9488;" title="View / Print Invoice"><i class="fas fa-eye"></i></a>

                        <?php if (!$r['is_final_invoice'] && canEdit('clients')): ?>
                        <button class="btn-icon" onclick="openFinalizePopup('<?php echo htmlspecialchars($r['po_number']??'',ENT_QUOTES); ?>',<?php echo $r['client_id']; ?>,'<?php echo implode(',',$ids); ?>')"
                                style="color:#0f172a;" title="Generate Final Invoice">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </button>
                        <?php endif; ?>

                        <?php if ($r['is_final_invoice']): ?>
                        <a href="<?php echo $viewUrl; ?>&final=1" target="_blank" class="btn-icon" style="color:#10b981;" title="Print Final Invoice"><i class="fas fa-print"></i></a>
                        <?php endif; ?>

                        <?php if (canEdit('clients') && !$r['is_final_invoice']): ?>
                        <?php $editUrl = "create_mounting_po.php?action=edit&client_id={$r['client_id']}";
                              if ($r['po_number']) $editUrl .= "&po_number=".urlencode($r['po_number']);
                              else foreach($ids as $id) $editUrl .= "&rate_ids[]=$id"; ?>
                        <a href="<?php echo $editUrl; ?>" class="btn-icon" style="color:#0284c7;" title="Edit"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>

                        <?php if (canDelete('clients')): ?>
                        <button class="btn-icon btn-delete" onclick="deletePO(<?php echo $ids[0]; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function deletePO(id) {
    Swal.fire({ title:'Delete?', text:"This cannot be undone.", icon:'warning', showCancelButton:true, confirmButtonColor:'#ef4444', confirmButtonText:'Yes, delete' })
    .then(r => { if(r.isConfirmed) fetch('mounting.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=delete&id='+id}).then(()=>Swal.fire('Deleted!','','success').then(()=>location.reload())); });
}

function openFinalizePopup(poNumber, clientId, rateIdsStr) {
    Swal.fire({
        title: 'Generate Final Mounting Invoice',
        html: `
            <div style="text-align:left;">
                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">INVOICE NUMBER</label>
                <input id="custom_invoice_number" class="swal2-input" placeholder="Leave empty to auto-generate (CMI/26-27/001)" style="margin:0 0 1rem 0;width:100%;box-sizing:border-box;">

                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">INVOICE DATE</label>
                <input id="custom_invoice_date" type="date" class="swal2-input" value="${new Date().toISOString().split('T')[0]}" style="margin:0 0 1rem 0;width:100%;box-sizing:border-box;">

                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">GST TYPE</label>
                <select id="gst_type" class="swal2-input" style="margin:0 0 1rem 0;width:100%;box-sizing:border-box;" onchange="toggleGSTFields()">
                    <option value="igst">IGST 18%</option>
                    <option value="cgst_sgst">CGST 9% + SGST 9%</option>
                </select>

                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">CUSTOMER PO NUMBER (optional)</label>
                <input id="customer_po_no" class="swal2-input" placeholder="Customer PO reference" style="margin:0 0 1rem 0;width:100%;box-sizing:border-box;">

                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">CUSTOMER PO DATE (optional)</label>
                <input id="customer_po_date" type="date" class="swal2-input" style="margin:0;width:100%;box-sizing:border-box;">
            </div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-file-invoice-dollar"></i> Generate Invoice',
        confirmButtonColor: '#0d9488',
        preConfirm: () => ({
            custom_invoice_number: document.getElementById('custom_invoice_number').value,
            custom_invoice_date:   document.getElementById('custom_invoice_date').value,
            gst_type:              document.getElementById('gst_type').value,
            customer_po_no:        document.getElementById('customer_po_no').value,
            customer_po_date:      document.getElementById('customer_po_date').value,
        })
    }).then(result => {
        if (!result.isConfirmed) return;
        const d = result.value;
        const body = `action=finalize&po_number=${encodeURIComponent(poNumber)}&client_id=${clientId}&custom_invoice_number=${encodeURIComponent(d.custom_invoice_number)}&custom_invoice_date=${d.custom_invoice_date}&gst_type=${d.gst_type}&customer_po_no=${encodeURIComponent(d.customer_po_no)}&customer_po_date=${d.customer_po_date}`;
        fetch('mounting.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire({ title:'Invoice Generated!', text:`Invoice ${res.invoice_number} created.`, icon:'success', confirmButtonColor:'#0d9488' })
                .then(() => location.reload());
            } else { Swal.fire('Error', res.message || 'Failed.', 'error'); }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const p = new URLSearchParams(window.location.search);
    if (p.has('msg')) {
        const msgs = { added:'Client Mounting Invoice created.', updated:'Client Mounting Invoice updated.' };
        const text = msgs[p.get('msg')];
        if (text) { Swal.fire({title:'Success',text,icon:'success',confirmButtonColor:'#0d9488',timer:2500,showConfirmButton:false}); window.history.replaceState({},document.title,window.location.pathname); }
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
