<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
if (!$vendor_id) {
    header("Location: ../partners/vendors.php"); exit;
}

// Fetch Vendor Info
$stmtV = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$stmtV->execute([$vendor_id]);
$vendor = $stmtV->fetch();

if (!$vendor) {
    die("Vendor not found.");
}

// Fetch Vendor Sites
$stmtS = $pdo->prepare("SELECT * FROM sites WHERE vendor_id = ? ORDER BY city ASC, location ASC");
$stmtS->execute([$vendor_id]);
$sites = $stmtS->fetchAll();

$activePage = 'vendors';
$pageTitle = 'Direct PO Generator';
include_once __DIR__ . '/../../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Direct Purchase Order</h1>
        <p style="color: #64748b; margin: 0; font-size: 0.9rem;">Generate a PO for <strong><?php echo $vendor['name']; ?></strong> without creating a proposal.</p>
    </div>
    <a href="../partners/vendors.php" class="btn" style="background: #f1f5f9; color: #475569;"><i class="fas fa-arrow-left"></i> Back to Vendors</a>
</div>

<form action="generate_po.php" method="GET" target="_blank">
    <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
    <!-- We will send a special flag 'direct=1' to generate_po.php -->
    <input type="hidden" name="mode" value="direct">

    <div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
        <h4 style="margin-top: 0; margin-bottom: 1rem; color: #1e293b; font-size: 1rem;">1. Campaign Details</h4>
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase;">Campaign / Client Name</label>
                <input type="text" name="campaign_name" placeholder="e.g. Summer Sale 2024 - Nike" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
            </div>
            <div class="form-group">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase;">Start Date</label>
                <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>
            <div class="form-group">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase;">End Date</label>
                <input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>
        </div>
    </div>

    <div class="card" style="padding: 0; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
        <div style="padding: 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; color: #1e293b; font-size: 1rem;">2. Select Sites for PO</h4>
            <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Only sites assigned to this vendor are listed.</span>
        </div>
        <table class="table" style="margin-bottom: 0;">
            <thead style="background: #f1f5f9;">
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll" style="transform: scale(1.2); cursor: pointer;"></th>
                    <th>Site Code</th>
                    <th>Location / City</th>
                    <th>Size</th>
                    <th>Light</th>
                    <th style="width: 150px;">Purchase Rate (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $s): ?>
                <tr>
                    <td style="text-align: center;"><input type="checkbox" name="site_ids[]" value="<?php echo $s['id']; ?>" class="site-check" style="transform: scale(1.2); cursor: pointer;"></td>
                    <td style="font-weight: 700; color: var(--primary);"><?php echo $s['site_code']; ?></td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo $s['location']; ?></div>
                        <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;"><?php echo $s['city']; ?></div>
                    </td>
                    <td><?php echo $s['width'] . "' x " . $s['height'] . "'"; ?></td>
                    <td><span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8;"><?php echo $s['light_type']; ?></span></td>
                    <td>
                        <input type="number" name="rates[<?php echo $s['id']; ?>]" value="<?php echo $s['purchase_rate']; ?>" style="width: 100%; padding: 0.4rem; border: 1px solid #e2e8f0; border-radius: 4px; font-weight: 700; color: #0f172a;">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 2rem; text-align: right; background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
        <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1rem; font-weight: 800; background: #0f172a; border: none; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.3);">
            <i class="fas fa-file-pdf"></i> Generate Proper Purchase Order
        </button>
    </div>
</form>

<script>
document.getElementById('selectAll').addEventListener('change', function(e) {
    document.querySelectorAll('.site-check').forEach(cb => cb.checked = e.target.checked);
});

document.querySelector('form').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.site-check:checked');
    if (checked.length === 0) {
        e.preventDefault();
        Swal.fire('Selection Required', 'Please select at least one site to generate a PO.', 'warning');
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
