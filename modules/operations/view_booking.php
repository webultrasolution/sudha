<?php
$activePage = 'bookings';
$pageTitle = 'Booking Details';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Load Core Libraries (SweetAlert, FontAwesome, CSS) without the sidebar
$hideSidebar = true;
include_once __DIR__ . '/../../includes/header.php';

// Prevent timezone warnings
date_default_timezone_set('Asia/Kolkata');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Booking with advanced stats
$stmt = $pdo->prepare("
    SELECT b.*, c.name as client_name, c.email as client_email, p.proposal_number
    FROM bookings b 
    JOIN partners c ON b.client_id = c.id 
    LEFT JOIN proposals p ON b.proposal_id = p.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$b = $stmt->fetch();

if (!$b) { 
    echo "<div class='card'>Booking not found.</div>"; 
    include_once __DIR__ . '/../../includes/footer.php'; 
    exit; 
}

// Fetch Items from booking_items
$stmtItems = $pdo->prepare("
    SELECT bi.*, s.site_code, s.location, s.city, s.type as media_type, s.owner_type, 
           s.width, s.height, s.light_type, s.vendor_id, v.name as vendor_name, v.contact_person as vendor_contact,
           o.status as op_status, o.id as op_id
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    LEFT JOIN partners v ON s.vendor_id = v.id
    LEFT JOIN operations o ON bi.booking_id = o.booking_id AND bi.site_id = o.site_id
    WHERE bi.booking_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

// Advanced Stats
$totalSQFT = 0;
$taCost = 0; $taSale = 0;
$haCost = 0; $haSale = 0;

foreach ($items as $item) {
    $sqft = $item['width'] * $item['height'];
    $totalSQFT += $sqft;
    if ($item['owner_type'] === 'TA') {
        $taCost += ($item['purchase_amount'] ?? 0);
        $taSale += $item['amount'];
    } else {
        $haCost += ($item['purchase_amount'] ?? 0);
        $haSale += $item['amount'];
    }
}

$taMargin = $taSale - $taCost;
$haMargin = $haSale - $haCost;
$totalMargin = $taMargin + $haMargin;

$grandTotal = $b['grand_total'];
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></h1>
            <span style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700;">
                <?php echo $b['proposal_number'] ?: 'N/A'; ?>
            </span>
            <span style="background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                <?php echo strtoupper($b['status']); ?>
            </span>
        </div>
        <p style="color: #64748b; margin: 0; font-size: 0.95rem; font-weight: 500;">
            <strong style="color: #334155;"><?php echo $b['client_name']; ?></strong> • Tenure: <?php echo date('d M', strtotime($b['start_date'])); ?> to <?php echo date('d M Y', strtotime($b['end_date'])); ?>
        </p>
    </div>
    <div style="display: flex; gap: 1rem; align-items: center;">
        <a href="bookings.php" class="btn" style="background: white; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: pointer; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <button class="btn btn-primary" onclick="window.print()" style="padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800;">
            <i class="fas fa-print"></i> Print Details
        </button>
    </div>
</div>

<!-- Financial Summary & Stats -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-chart-line"></i> Campaign Stats</h4>
        <div class="stat-box">
            <small>Total Sites</small>
            <strong><?php echo count($items); ?> Assets</strong>
        </div>
        <div class="stat-box">
            <small>Total Area</small>
            <strong><?php echo number_format($totalSQFT); ?> <span style="font-size:0.7rem; color:#64748b;">sqft</span></strong>
        </div>
    </div>

    <div class="p-panel">
        <h4 class="p-title"><i class="fas fa-clock"></i> Execution Timeline</h4>
        <div class="p-row"><span>Start Date</span><strong><?php echo date('d M Y', strtotime($b['start_date'])); ?></strong></div>
        <div class="p-row"><span>End Date</span><strong><?php echo date('d M Y', strtotime($b['end_date'])); ?></strong></div>
        <div class="p-row"><span>Mounting</span><strong><?php echo $b['mounting_date'] ? date('d M Y', strtotime($b['mounting_date'])) : 'Pending'; ?></strong></div>
    </div>

    <div class="p-panel" style="background: #f8fafc; border-color: #cbd5e1;">
        <h4 class="p-title"><i class="fas fa-funnel-dollar"></i> Profitability</h4>
        <div class="p-row"><span>Vendor Payout</span><strong><?php echo formatCurrency($taCost); ?></strong></div>
        <div class="p-row"><span>Own Earnings</span><strong><?php echo formatCurrency($haMargin); ?></strong></div>
        <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Margin</span>
            <span style="font-size: 1.1rem; font-weight: 800; color: var(--primary);"><?php echo formatCurrency($totalMargin); ?></span>
        </div>
    </div>

    <div class="p-panel" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border: none;">
        <h4 class="p-title" style="color: #94a3b8; border-bottom-color: rgba(255,255,255,0.1);"><i class="fas fa-receipt"></i> Billing Summary</h4>
        <div class="p-row" style="color: #cbd5e1;"><span>Base Amount</span><strong style="color: white;"><?php echo formatCurrency($b['total_amount']); ?></strong></div>
        <div class="p-row" style="color: #cbd5e1;"><span>Tax (GST)</span><strong style="color: white;"><?php echo formatCurrency($b['tax_amount']); ?></strong></div>
        <div style="background: rgba(255,255,255,0.1); border-radius: 12px; padding: 1rem; margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.8rem; font-weight: 800; color: #cbd5e1; text-transform: uppercase;">Grand Total</span>
            <span style="font-size: 1.5rem; font-weight: 900; color: #34d399;"><?php echo formatCurrency($b['grand_total']); ?></span>
        </div>
    </div>
</div>

<?php
// Group sites by vendor & branch GST for PO generation
$vendorSites = [];
foreach ($items as $item) {
    if ($item['owner_type'] === 'TA' && isset($item['vendor_id'])) {
        $vId = $item['vendor_id'];
        $vgst = $item['vendor_gst'] ?? '';
        $key = $vId . '_' . $vgst;
        
        if (!isset($vendorSites[$key])) {
            $vendorSites[$key] = [
                'id' => $vId,
                'name' => $item['vendor_name'] ?? 'Unknown Vendor',
                'vendor_gst' => $vgst,
                'count' => 0
            ];
        }
        $vendorSites[$key]['count']++;
    }
}
?>

<?php if (!empty($vendorSites)): ?>
<!-- Operational Documents Section -->
<div class="p-panel" style="margin-bottom: 2rem; border-left: 4px solid #f59e0b; width: 100%;">
    <h4 class="p-title" style="color: #b45309;"><i class="fas fa-file-invoice"></i> Vendor Purchase Orders</h4>
    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; padding: 0.5rem 0;">
        <?php foreach ($vendorSites as $v): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; padding: 1rem; border-radius: 12px; display: flex; align-items: center; gap: 1.5rem; min-width: 320px; flex: 1;">
            <div style="background: #f59e0b; color: white; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="fas fa-truck-loading"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 800; color: #92400e; font-size: 0.95rem;">
                    <?php echo $v['name']; ?>
                    <?php if ($v['vendor_gst']): ?>
                        <div style="font-size: 0.65rem; color: #b45309; opacity: 0.8; font-family: monospace;">BRANCH: <?php echo $v['vendor_gst']; ?></div>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.75rem; color: #b45309; font-weight: 600;"><?php echo $v['count']; ?> Assets in this booking</div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="generate_po.php?booking_id=<?php echo $b['id']; ?>&vendor_id=<?php echo $v['id']; ?>&vendor_gst=<?php echo urlencode($v['vendor_gst']); ?>" target="_blank" class="btn" style="background: #0f172a; color: white; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem;" title="View & Print PO">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <button onclick="sendPOEmail(<?php echo $b['id']; ?>, <?php echo $v['id']; ?>)" class="btn" style="background: #3b82f6; color: white; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;" title="Send via Email">
                    <i class="fas fa-envelope"></i>
                </button>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $v['phone'] ?? ''); ?>?text=Dear <?php echo urlencode($v['name'] ?? 'Vendor'); ?>, Please find the Purchase Order for Campaign: <?php echo urlencode($b['campaign_name'] ?? 'General'); ?>. Booking Ref: #BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?>. Thank you." target="_blank" class="btn" style="background: #22c55e; color: white; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem;" title="Send via WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Detailed Site Table -->
<div class="card" style="padding: 0; border-radius: 16px; overflow: hidden;">
    <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9;">
        <h3 style="font-size: 1.1rem; margin: 0; color: #0f172a; font-weight: 800;">Booked Assets & Operations</h3>
    </div>
    <table class="table">
        <thead style="background: #f8fafc;">
            <tr>
                <th>Asset Details</th>
                <th>City / Code</th>
                <th>Dimensions</th>
                <th>Period</th>
                <th>Cost / Margin</th>
                <th style="text-align: right;">Sale Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <?php 
                $itemMargin = $item['amount'] - ($item['purchase_amount'] ?? 0);
                $marginPct = ($item['purchase_amount'] > 0) ? ($itemMargin / $item['purchase_amount'] * 100) : 0;
            ?>
            <tr>
                <td>
                    <div style="font-weight: 700; color: #334155;"><?php echo $item['location']; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <?php echo $item['media_type']; ?> • <?php echo $item['light_type']; ?>
                        <?php if ($item['owner_type'] === 'HA'): ?>
                             • <span class="badge-type"><?php echo $item['owner_type']; ?></span>
                        <?php endif; ?>
                        
                        <?php if ($item['owner_type'] === 'TA' && $item['vendor_name']): ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; width: 100%;">
                                <span class="badge-type" style="margin-right: 0.2rem;"><?php echo $item['owner_type']; ?></span>
                                <span style="color: var(--primary); font-weight: 800; background: #f0fdfa; padding: 0.1rem 0.4rem; border-radius: 4px; border: 1px solid #ccfbf1; display: flex; align-items: center; gap: 0.3rem;">
                                    <i class="fas fa-truck-loading" style="font-size: 0.6rem;"></i> 
                                    <?php echo $item['vendor_name']; ?> 
                                    <?php if ($item['vendor_contact']): ?>
                                        <span style="color: #64748b; font-weight: 500; font-size: 0.65rem;">(<?php echo $item['vendor_contact']; ?>)</span>
                                    <?php endif; ?>
                                </span>
                                <a href="generate_po.php?booking_id=<?php echo $b['id']; ?>&vendor_id=<?php echo $item['vendor_id']; ?>" target="_blank" title="Generate Purchase Order" style="background: #f59e0b; color: white; width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; text-decoration: none;">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 700; color: #1e293b;"><?php echo $item['city']; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo $item['site_code']; ?></div>
                </td>
                <td>
                    <div style="font-weight: 700; color: #475569;"><?php echo $item['width'] . "' x " . $item['height'] . "'"; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo number_format($item['width']*$item['height']); ?> SQFT</div>
                </td>
                <td>
                    <div style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                        <?php echo date('d M', strtotime($item['start_date'])); ?> - <?php echo date('d M Y', strtotime($item['end_date'])); ?>
                    </div>
                    <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo $item['days']; ?> Days</div>
                </td>
                <td>
                    <div style="margin-bottom: 0.5rem;">
                        <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">Purchase Cost</span>
                        <div style="font-weight: 700; color: #475569; font-size: 0.9rem;"><?php echo formatCurrency($item['purchase_amount'] ?? 0); ?></div>
                    </div>
                    <div>
                        <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">Net Profit</span>
                        <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #f0fdf4; color: #166534; padding: 0.25rem 0.6rem; border-radius: 6px; border: 1px solid #bbf7d0;">
                            <i class="fas fa-arrow-up" style="font-size: 0.6rem;"></i>
                            <span style="font-weight: 800; font-size: 0.8rem;"><?php echo formatCurrency($itemMargin); ?></span>
                            <span style="font-size: 0.65rem; font-weight: 600; opacity: 0.8;">(<?php echo number_format($marginPct, 1); ?>%)</span>
                        </div>
                    </div>
                </td>
                <td style="font-weight: 800; color: #0f172a; text-align: right; font-size: 1rem;"><?php echo formatCurrency($item['amount']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.p-panel { background: white; padding: 1.5rem; border-radius: 16px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
.p-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--secondary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }
.p-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; }
.stat-box { margin-bottom: 1rem; }
.stat-box small { display: block; font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
.stat-box strong { font-size: 1.1rem; color: #0f172a; font-weight: 800; }
.table { width: 100%; border-collapse: collapse; }
.table th { text-align: left; padding: 1rem; border-bottom: 2px solid #f1f5f9; font-size: 0.75rem; text-transform: uppercase; color: #64748b; }
.table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.badge-type { background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.65rem; font-weight: 800; color: #475569; }
.exec-status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-pending { background: #fef9c3; color: #854d0e; }
.status-in_progress { background: #dcfce7; color: #166534; }
.status-completed { background: #e0f2fe; color: #0369a1; }
</style>

<script>
function sendPOEmail(bookingId, vendorId) {
    Swal.fire({
        title: 'Sending PO...',
        text: 'Please wait while we prepare the email for the vendor.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Simulate AJAX call to send email
    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: 'PO Sent!',
            text: 'The Purchase Order has been successfully emailed to the vendor.',
            timer: 2000,
            showConfirmButton: false
        });
    }, 1500);
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
