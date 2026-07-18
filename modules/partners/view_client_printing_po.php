<?php
$activePage = 'client_printing_rates';
$pageTitle = 'View Client Printing PO';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$hideSidebar = true;
include_once __DIR__ . '/../../includes/header.php';

date_default_timezone_set('Asia/Kolkata');

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$po_number = isset($_GET['po_number']) ? $_GET['po_number'] : null;
$rate_ids = isset($_GET['rate_ids']) ? $_GET['rate_ids'] : [];

if (empty($rate_ids) && !$po_number) {
    echo "<div class='card'>Invalid PO reference.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Build query
$where = "r.client_id = :client_id";
$params = [':client_id' => $client_id];

if ($po_number) {
    $where .= " AND r.po_number = :po_number";
    $params[':po_number'] = $po_number;
} else {
    $in = str_repeat('?,', count($rate_ids) - 1) . '?';
    $where .= " AND r.id IN ($in)";
    $params = array_merge([$client_id], $rate_ids);
}

// Fetch Group info
$sql = "SELECT r.*, c.name as client_name, c.email as client_email 
        FROM client_printing_rates r 
        JOIN partners c ON r.client_id = c.id 
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
if (!$isAdmin && $first['approval_status'] !== 'approved') {
    echo "<div class='card'><h3 style='color:red;'>Access Denied</h3><p>This Client Printing Invoice is awaiting admin approval and cannot be viewed yet.</p></div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
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
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">
                #<?php echo htmlspecialchars($po_num_display); ?></h1>
            <span style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700;">
                Client Printing Invoice
            </span>
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
            <?php if ($first['approval_status'] === 'pending_approval'): ?>
                <span style="background: #fff7ed; color: #c2410c; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Awaiting Approval</span>
            <?php elseif ($first['is_final_invoice']): ?>
                <span style="background: #f0fdf4; color: #15803d; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Final Invoice</span>
            <?php else: ?>
                <span style="background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">Draft</span>
            <?php endif; ?>
        </div>
        <p style="color: #64748b; margin: 0; font-size: 0.9rem;">
            Created on <?php echo date('d M Y, h:i A', strtotime($first['created_at'])); ?> by System
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="client_printing_rates.php" class="btn btn-secondary" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <?php 
            $pdfUrl = "../operations/client_printing.php?client_id=" . $first['client_id'] . "&preview=1";
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
                <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0;">Items in this Invoice</h3>
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
        <!-- Client Details -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
            <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
                <h3 style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 1rem 0; font-weight: 800;">Client Details</h3>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: #e0e7ff; color: #4f46e5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800;">
                        <?php echo strtoupper(substr($first['client_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: #1e293b; font-size: 1.1rem;"><?php echo htmlspecialchars($first['client_name']); ?></div>
                        <div style="color: #64748b; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; margin-top: 4px;">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($first['client_email'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
                <h3 style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 1rem 0; font-weight: 800;">Invoice Reference</h3>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php if (!empty($first['custom_invoice_number'])): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-size: 0.85rem;">Custom Invoice #</span>
                        <strong style="color: #0f172a; font-size: 0.9rem;"><?php echo htmlspecialchars($first['custom_invoice_number']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($first['custom_invoice_date'])): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-size: 0.85rem;">Invoice Date</span>
                        <strong style="color: #0f172a; font-size: 0.9rem;"><?php echo date('d M Y', strtotime($first['custom_invoice_date'])); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($first['customer_po_no'])): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #64748b; font-size: 0.85rem;">Customer PO #</span>
                        <strong style="color: #0f172a; font-size: 0.9rem;"><?php echo htmlspecialchars($first['customer_po_no']); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
