<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: vendors.php"); exit; }

$vendor = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$vendor->execute([$id]);
$vendor = $vendor->fetch();

if (!$vendor) { echo "Vendor not found."; exit; }

$activePage = 'vendors';
$pageTitle = 'Vendor Profile: ' . $vendor['name'];
include_once __DIR__ . '/../../includes/header.php';

// Fetch Owned Sites
$sites = $pdo->prepare("SELECT * FROM sites WHERE vendor_id = ? ORDER BY name ASC");
$sites->execute([$id]);
$sites = $sites->fetchAll();

// Fetch PO History
$pos = $pdo->prepare("SELECT * FROM purchase_orders WHERE vendor_id = ? ORDER BY id DESC");
$pos->execute([$id]);
$pos = $pos->fetchAll();

// Fetch Payment Summary
$payments = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE partner_id = ? AND type = 'debit'");
$payments->execute([$id]);
$totalPaid = $payments->fetchColumn() ?: 0;

$totalPO = $pdo->prepare("SELECT SUM(total_amount) FROM purchase_orders WHERE vendor_id = ?");
$totalPO->execute([$id]);
$totalLiability = $totalPO->fetchColumn() ?: 0;
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
    <!-- Sidebar Profile -->
    <div>
        <div class="card">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--primary); font-size: 2rem;">
                    <i class="fas fa-truck"></i>
                </div>
                <h2 style="font-size: 1.25rem;"><?php echo $vendor['name']; ?></h2>
                <span class="badge" style="background: #dcfce7; color: #166534; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem;">Active Partner</span>
            </div>
            
            <div style="border-top: 1px solid #f1f5f9; padding-top: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block;">Contact Person</small>
                    <strong><?php echo $vendor['contact_person']; ?></strong>
                </div>
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block;">Email / Phone</small>
                    <div><?php echo $vendor['email']; ?></div>
                    <div><?php echo $vendor['phone']; ?></div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block;">GSTIN / PAN</small>
                    <code><?php echo $vendor['gstin']; ?></code><br>
                    <code><?php echo $vendor['pan']; ?></code>
                </div>
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block;">Payment Terms</small>
                    <strong><?php echo $vendor['payment_terms'] ?: 'N/A'; ?></strong>
                </div>
            </div>
        </div>
        
        <div class="card" style="background: var(--primary); color: white;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;">Financial Summary</h3>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Total POs</span>
                <strong><?php echo formatCurrency($totalLiability); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Total Paid</span>
                <strong><?php echo formatCurrency($totalPaid); ?></strong>
            </div>
            <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 0.5rem; margin-top: 0.5rem; display: flex; justify-content: space-between;">
                <span>Outstanding</span>
                <strong style="font-size: 1.2rem;"><?php echo formatCurrency($totalLiability - $totalPaid); ?></strong>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div>
        <div class="card" style="padding: 0;">
            <div style="display: flex; border-bottom: 1px solid #f1f5f9;">
                <button class="tab-btn active" onclick="showTab('sites-tab')">Owned Sites (<?php echo count($sites); ?>)</button>
                <button class="tab-btn" onclick="showTab('pos-tab')">Purchase Orders (<?php echo count($pos); ?>)</button>
            </div>
            
            <div id="sites-tab" class="tab-content" style="padding: 1.5rem;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Site Code</th>
                            <th>Location</th>
                            <th>Size</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $s): ?>
                        <tr>
                            <td><code><?php echo $s['site_code']; ?></code></td>
                            <td><?php echo $s['location']; ?></td>
                            <td><?php echo $s['width']; ?>'x<?php echo $s['height']; ?>'</td>
                            <td><?php echo formatCurrency($s['purchase_rate']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="pos-tab" class="tab-content" style="padding: 1.5rem; display: none;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>PO #</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pos as $p): ?>
                        <tr>
                            <td><strong><?php echo $p['po_number']; ?></strong></td>
                            <td><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
                            <td><?php echo formatCurrency($p['total_amount']); ?></td>
                            <td><span class="status-pill status-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                            <td><a href="../financials/po_view.php?id=<?php echo $p['id']; ?>" class="btn-icon"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.tab-btn { background: none; border: none; padding: 1rem 1.5rem; font-weight: 600; color: var(--secondary); cursor: pointer; border-bottom: 2px solid transparent; }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-content { animation: fadeIn 0.3s ease; }
</style>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    event.currentTarget.classList.add('active');
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
