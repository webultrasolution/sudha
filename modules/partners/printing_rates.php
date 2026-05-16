<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $vendor_id = intval($_POST['vendor_id']);
        $site_id = !empty($_POST['site_id']) ? intval($_POST['site_id']) : null;
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);

        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft) VALUES (?, ?, ?, ?)");
            $stmt->execute([$vendor_id, $site_id, $media_type, $rate]);
            header("Location: printing_rates.php?msg=added"); exit;
        } else {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("UPDATE vendor_printing_rates SET vendor_id=?, site_id=?, media_type=?, rate_per_sqft=? WHERE id=?");
            $stmt->execute([$vendor_id, $site_id, $media_type, $rate, $id]);
            header("Location: printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
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

// Fetch Rates
$rates = $pdo->query("
    SELECT r.*, v.name as vendor_name, s.name as site_name, s.site_code, s.width, s.height
    FROM vendor_printing_rates r
    JOIN partners v ON r.vendor_id = v.id
    LEFT JOIN sites s ON r.site_id = s.id
    $queryWhere
    ORDER BY v.name ASC, s.name ASC
")->fetchAll();

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$sites = $pdo->query("SELECT id, name, site_code, width, height, vendor_id FROM sites ORDER BY site_code ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Printing PO</h2>
        <button class="btn btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> Add New Printing PO 
        </button>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 40px;"></th>
                <th>Vendor</th>
                <th>Site / Dimension</th>
                <th>Media Type</th>
                <th>Rate (per SQFT)</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rates as $r): ?>
            <tr>
                <td style="text-align: center;">
                    <a href="../operations/generate_printing_po.php?vendor_id=<?php echo $r['vendor_id']; ?>&rate_ids[]=<?php echo $r['id']; ?>&preview=1" target="_blank" title="Quick PO PDF" style="color: #ef4444; font-size: 1.1rem;">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                </td>
                <td><strong><?php echo htmlspecialchars($r['vendor_name']); ?></strong></td>
                <td>
                    <?php if ($r['site_id']): ?>
                        <div><?php echo htmlspecialchars($r['site_name']); ?></div>
                        <small style="color: #64748b;"><?php echo $r['site_code']; ?> (<?php echo $r['width']; ?>x<?php echo $r['height']; ?> = <strong><?php echo floatval($r['width']) * floatval($r['height']); ?> SQFT</strong>)</small>
                    <?php else: ?>
                        <span style="color: #94a3b8; font-style: italic;">Generic / All Sites</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($r['media_type']); ?></span></td>
                <td>
                    <strong style="color: var(--primary);">₹<?php echo number_format($r['rate_per_sqft'], 2); ?></strong>
                    <?php if ($r['site_id'] && $r['width'] && $r['height']): ?>
                        <?php $sqft = floatval($r['width']) * floatval($r['height']); ?>
                        <div style="font-size: 0.75rem; color: #059669; font-weight: 700; margin-top: 2px;">
                            Total: ₹<?php echo number_format($r['rate_per_sqft'] * $sqft, 2); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">
                    <button class="btn-icon btn-edit" onclick="editRate(<?php echo htmlspecialchars(json_encode($r)); ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn-icon btn-delete" onclick="deleteRate(<?php echo $r['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rates)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 2rem; color: #94a3b8;">No Printing POs found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="rateModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="modalTitle">Printing PO</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="rateForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="rateId">
            
            <div class="form-group">
                <label>Printing Vendor</label>
                <select name="vendor_id" id="f_vendor" required>
                    <option value="">Select Vendor</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Site (Optional - leave empty for generic rate)</label>
                <select name="site_id" id="f_site">
                    <option value="">Generic / All Sites</option>
                    <?php foreach ($sites as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo $s['site_code']; ?> - <?php echo $s['name']; ?> (<?php echo $s['width']; ?>x<?php echo $s['height']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Media Type</label>
                <select name="media_type" id="f_media" required>
                    <option value="Flex">Flex</option>
                    <option value="Vinyl">Vinyl</option>
                    <option value="Star Flex">Star Flex</option>
                    <option value="Backlit Flex">Backlit Flex</option>
                    <option value="One Way Vision">One Way Vision</option>
                    <option value="Canvas">Canvas</option>
                </select>
            </div>

            <div class="form-group">
                <label>Rate (₹ per SQFT)</label>
                <input type="number" step="0.01" name="rate_per_sqft" id="f_rate" required min="0.01">
            </div>

            <div class="form-group" id="sqft_display" style="display: none; background: #f8fafc; padding: 0.75rem; border-radius: 8px; margin-top: 1rem; border: 1px dashed #cbd5e1;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Square Feet:</span>
                    <span id="sqft_value" style="font-weight: 800; color: #0f766e; font-size: 0.85rem;">0</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Estimated Total Price:</span>
                    <span id="total_price_value" style="font-weight: 900; color: var(--primary); font-size: 1rem;">₹0.00</span>
                </div>
            </div>

            <div style="margin-top: 2rem; text-align: right;">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Printing PO</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.modal-content { background: white; margin: 10% auto; padding: 2rem; border-radius: 12px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; color: #475569; }
.form-group select, .form-group input { width: 100%; padding: 0.65rem; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; }
.close { cursor: pointer; float: right; font-size: 1.5rem; }
</style>

<script>
const sitesData = <?php echo json_encode($sites); ?>;

document.getElementById('f_vendor').addEventListener('change', filterSitesByVendor);
document.getElementById('f_site').addEventListener('change', calculateTotal);
document.getElementById('f_rate').addEventListener('input', calculateTotal);

function filterSitesByVendor() {
    const vendorId = document.getElementById('f_vendor').value;
    const siteSelect = document.getElementById('f_site');
    const currentSiteId = siteSelect.value;
    
    // Clear current options except the default one
    siteSelect.innerHTML = '<option value="">Generic / All Sites</option>';
    
    if (vendorId) {
        // Filter sites that belong to the selected vendor
        const vendorSites = sitesData.filter(s => s.vendor_id == vendorId);
        vendorSites.forEach(s => {
            const option = document.createElement('option');
            option.value = s.id;
            option.text = `${s.site_code} - ${s.name} (${s.width}x${s.height})`;
            siteSelect.add(option);
        });
        
        // Restore previously selected site if it's still available in the filtered list
        if (currentSiteId && vendorSites.find(s => s.id == currentSiteId)) {
            siteSelect.value = currentSiteId;
        }
    }
    calculateTotal();
}

function calculateTotal() {
    const siteId = document.getElementById('f_site').value;
    const rate = parseFloat(document.getElementById('f_rate').value) || 0;
    
    if (siteId) {
        const site = sitesData.find(s => s.id == siteId);
        if (site && site.width && site.height) {
            const sqft = parseFloat(site.width) * parseFloat(site.height);
            document.getElementById('sqft_value').innerText = sqft.toLocaleString() + ' SQFT';
            document.getElementById('total_price_value').innerText = '₹' + (sqft * rate).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('sqft_display').style.display = 'block';
            return;
        }
    }
    document.getElementById('sqft_display').style.display = 'none';
}

function openModal() { 
    document.getElementById('rateForm').reset(); 
    document.getElementById('formAction').value = 'add'; 
    document.getElementById('rateId').value = '';
    
    // Auto-select vendor if passed in URL
    const urlParams = new URLSearchParams(window.location.search);
    const vId = urlParams.get('vendor_id');
    if (vId) {
        document.getElementById('f_vendor').value = vId;
        filterSitesByVendor();
    }
    
    calculateTotal();
    document.getElementById('rateModal').style.display = 'block'; 
}
function closeModal() { document.getElementById('rateModal').style.display = 'none'; }

// Auto-open modal if vendor_id is present
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('vendor_id')) {
        openModal();
    }
});

function editRate(r) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('rateId').value = r.id;
    document.getElementById('f_vendor').value = r.vendor_id;
    
    // Trigger vendor change to populate sites for this vendor
    filterSitesByVendor();
    
    document.getElementById('f_site').value = r.site_id || '';
    document.getElementById('f_media').value = r.media_type;
    document.getElementById('f_rate').value = r.rate_per_sqft;
    calculateTotal();
    document.getElementById('rateModal').style.display = 'block';
}

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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
