<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    die("Site ID is required.");
}

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Fetch Site Details
$stmt = $pdo->prepare("SELECT s.*, p.name as vendor_name FROM sites s LEFT JOIN partners p ON s.vendor_id = p.id WHERE s.id = ?");
$stmt->execute([$id]);
$site = $stmt->fetch();

if (!$site) {
    die("Site not found.");
}

$sqft = $site['width'] * $site['height'];

$date_condition_b = "";
$date_condition_po = "";
$date_condition_vpr = "";
$params_b = [$id];
$params_po = [$id];
$params_vpr = [$id];

if ($from_date && $to_date) {
    $date_condition_b = " AND DATE(b.created_at) BETWEEN ? AND ?";
    $date_condition_po = " AND DATE(po.created_at) BETWEEN ? AND ?";
    $date_condition_vpr = " AND DATE(vpr.created_at) BETWEEN ? AND ?";
    $params_b[] = $from_date; $params_b[] = $to_date;
    $params_po[] = $from_date; $params_po[] = $to_date;
    $params_vpr[] = $from_date; $params_vpr[] = $to_date;
}

// 1. Fetch Revenue & Booking Costs (Client Invoices)
$stmtRev = $pdo->prepare("
    SELECT b.id as booking_id, bi.amount, bi.purchase_amount, bi.start_date, bi.end_date, c.name as client_name, b.created_at
    FROM booking_items bi
    JOIN bookings b ON bi.booking_id = b.id
    JOIN partners c ON b.client_id = c.id
    WHERE bi.site_id = ? $date_condition_b
    ORDER BY b.created_at DESC
");
$stmtRev->execute($params_b);
$invoices = $stmtRev->fetchAll();

$total_revenue = 0;
$total_booking_costs = 0;
foreach ($invoices as $inv) {
    $total_revenue += floatval($inv['amount']);
    $total_booking_costs += floatval($inv['purchase_amount']);
}

// 2. Fetch Expenses (Vendor POs)
$stmtExp = $pdo->prepare("
    SELECT po.id as po_id, po.po_number, pi.cost, pi.start_date, pi.end_date, v.name as vendor_name, po.created_at
    FROM po_items pi
    JOIN purchase_orders po ON pi.po_id = po.id
    JOIN partners v ON po.vendor_id = v.id
    WHERE pi.site_id = ? $date_condition_po
    ORDER BY po.created_at DESC
");
$stmtExp->execute($params_po);
$pos = $stmtExp->fetchAll();

$total_expenses = $total_booking_costs;
foreach ($pos as $po) {
    $total_expenses += floatval($po['cost']);
}

// 3. Fetch Printing POs (Printing Expenses)
$stmtPrint = $pdo->prepare("
    SELECT vpr.po_number, vpr.rate_per_sqft, vpr.media_type, vpr.created_at, p.name as vendor_name
    FROM vendor_printing_rates vpr
    JOIN partners p ON vpr.vendor_id = p.id
    WHERE vpr.site_id = ? $date_condition_vpr
    ORDER BY vpr.created_at DESC
");
$stmtPrint->execute($params_vpr);
$printing_pos = $stmtPrint->fetchAll();

foreach ($printing_pos as $ppo) {
    $cost = floatval($ppo['rate_per_sqft']) * $sqft;
    $total_expenses += $cost;
}

$profit = $total_revenue - $total_expenses;
$margin = $total_revenue > 0 ? ($profit / $total_revenue) * 100 : 0;

// Export Logic
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Site_Financials_' . $site['site_code'] . '_' . date('Ymd') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Site:', $site['name'], $site['site_code']]);
    fputcsv($output, ['Total Revenue (Rs):', number_format($total_revenue, 2), 'Total Expenses (Rs):', number_format($total_expenses, 2), 'Net Profit (Rs):', number_format($profit, 2)]);
    fputcsv($output, []);
    
    fputcsv($output, ['Date', 'Reference', 'Type', 'Partner', 'Details', 'Credit (Revenue)', 'Debit (Expense)']);
    
    // Combine and sort by date
    $ledger = [];
    foreach ($invoices as $inv) {
        $ledger[] = [
            'date' => strtotime($inv['created_at']),
            'row' => [
                date('d-M-Y', strtotime($inv['created_at'])),
                'INV-' . str_pad($inv['booking_id'], 4, '0', STR_PAD_LEFT),
                'Revenue',
                $inv['client_name'],
                'Period: ' . date('d/m/y', strtotime($inv['start_date'])) . ' - ' . date('d/m/y', strtotime($inv['end_date'])),
                number_format($inv['amount'], 2),
                ''
            ]
        ];
        
        if (floatval($inv['purchase_amount']) > 0) {
            $ledger[] = [
                'date' => strtotime($inv['created_at']),
                'row' => [
                    date('d-M-Y', strtotime($inv['created_at'])),
                    'BK-' . str_pad($inv['booking_id'], 4, '0', STR_PAD_LEFT),
                    'Expense',
                    $site['vendor_name'] ?: 'Vendor',
                    'Direct Booking Cost: ' . date('d/m/y', strtotime($inv['start_date'])) . ' - ' . date('d/m/y', strtotime($inv['end_date'])),
                    '',
                    number_format($inv['purchase_amount'], 2)
                ]
            ];
        }
    }
    foreach ($pos as $po) {
        $ledger[] = [
            'date' => strtotime($po['created_at']),
            'row' => [
                date('d-M-Y', strtotime($po['created_at'])),
                $po['po_number'],
                'Expense',
                $po['vendor_name'],
                'Asset Booking: ' . date('d/m/y', strtotime($po['start_date'])) . ' - ' . date('d/m/y', strtotime($po['end_date'])),
                '',
                number_format($po['cost'], 2)
            ]
        ];
    }
    foreach ($printing_pos as $ppo) {
        $cost = floatval($ppo['rate_per_sqft']) * $sqft;
        $ledger[] = [
            'date' => strtotime($ppo['created_at']),
            'row' => [
                date('d-M-Y', strtotime($ppo['created_at'])),
                $ppo['po_number'],
                'Expense',
                $ppo['vendor_name'],
                'Printing & Mounting: ' . $ppo['media_type'],
                '',
                number_format($cost, 2)
            ]
        ];
    }
    
    usort($ledger, function($a, $b) { return $b['date'] - $a['date']; });
    foreach ($ledger as $l) {
        fputcsv($output, $l['row']);
    }
    
    fclose($output);
    exit;
}

$activePage = 'sites';
$pageTitle = 'Site Financials (P&L)';
include_once __DIR__ . '/../../includes/header.php';
?>

<div style="max-width: 1200px; margin: 0 auto; padding-bottom: 2rem;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; background: #fff; padding: 0.75rem 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); border: 1px solid #e2e8f0;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="sites.php" class="btn btn-secondary" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.35rem 0.75rem; font-size: 0.85rem;"><i class="fas fa-arrow-left"></i> Back</a>
            <h1 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-chart-line" style="color: #0d9488;"></i> Site Financials</h1>
        </div>
        
        <form method="GET" action="site_financials.php" style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div style="display: flex; align-items: center; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px 4px;">
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" style="border: none; background: transparent; padding: 0.25rem; font-size: 0.75rem; font-weight: 600; outline: none; color: #475569;">
                <span style="color: #cbd5e1; font-size: 0.75rem;">to</span>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" style="border: none; background: transparent; padding: 0.25rem; font-size: 0.75rem; font-weight: 600; outline: none; color: #475569;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.75rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem;"><i class="fas fa-filter"></i> Apply</button>
            <a href="site_financials.php?id=<?php echo $id; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&export=csv" class="btn" style="background: #10b981; color: white; border: none; padding: 0.35rem 0.75rem; border-radius: 6px; font-weight: 700; text-decoration: none; font-size: 0.8rem;"><i class="fas fa-file-csv"></i> Export CSV</a>
        </form>
    </div>

    <!-- Stunning Hero Card -->
    <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 8px; padding: 1rem 1.25rem; color: white; margin-bottom: 1rem; position: relative; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(13,148,136,0.3) 0%, rgba(13,148,136,0) 70%); border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -80px; left: 10%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, rgba(59,130,246,0) 70%); border-radius: 50%;"></div>
        
        <div style="position: relative; z-index: 10; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem;">
                    <h2 style="margin: 0; font-size: 1.1rem; font-weight: 800; letter-spacing: -0.01em;"><?php echo htmlspecialchars($site['name']); ?></h2>
                    <span style="background: rgba(255,255,255,0.15); padding: 1px 6px; border-radius: 8px; font-size: 0.65rem; font-weight: 700; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(4px);"><?php echo $site['site_code']; ?></span>
                </div>
                <div style="color: #cbd5e1; font-size: 0.75rem; display: flex; gap: 1rem; font-weight: 600;">
                    <span style="display: flex; align-items: center; gap: 4px;"><i class="fas fa-map-marker-alt" style="color: #0ea5e9;"></i> <?php echo $site['city']; ?></span>
                    <span style="display: flex; align-items: center; gap: 4px;"><i class="fas fa-expand" style="color: #8b5cf6;"></i> <?php echo $site['width']; ?>' x <?php echo $site['height']; ?>'</span>
                    <span style="display: flex; align-items: center; gap: 4px;"><i class="fas fa-tag" style="color: #f59e0b;"></i> <?php echo $site['type']; ?> (<?php echo $site['light_type']; ?>)</span>
                    <?php if($site['owner_type'] === 'TA' && $site['vendor_name']): ?>
                        <span style="display: flex; align-items: center; gap: 4px;"><i class="fas fa-handshake" style="color: #10b981;"></i> Vendor: <?php echo htmlspecialchars($site['vendor_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 0.5rem 1rem; border-radius: 8px; backdrop-filter: blur(10px); text-align: right; display: flex; align-items: center; gap: 1rem;">
                <div>
                    <div style="font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 800; margin-bottom: 0.1rem;">Net Profit / Loss</div>
                    <div style="font-size: 1.25rem; font-weight: 900; color: <?php echo $profit >= 0 ? '#10b981' : '#ef4444'; ?>; line-height: 1;">
                        ₹<?php echo number_format($profit, 2); ?>
                    </div>
                </div>
                <span style="background: <?php echo $profit >= 0 ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)'; ?>; color: <?php echo $profit >= 0 ? '#34d399' : '#fca5a5'; ?>; padding: 2px 6px; border-radius: 8px; font-size: 0.65rem; font-weight: 700; border: 1px solid <?php echo $profit >= 0 ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)'; ?>;">
                    <?php echo $profit >= 0 ? '+' : ''; ?><?php echo number_format($margin, 1); ?>% Margin
                </span>
            </div>
        </div>
    </div>

    <!-- Income & Expense Overview Cards -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
        <div style="background: #fff; border-radius: 8px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; border-left: 3px solid #10b981;">
            <div>
                <div style="color: #64748b; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.15rem; display: flex; align-items: center; gap: 0.35rem;"><i class="fas fa-arrow-down" style="color: #10b981; background: #d1fae5; padding: 2px; border-radius: 50%; font-size: 0.55rem;"></i> Total Revenue</div>
                <div style="font-size: 1.15rem; font-weight: 900; color: #1e293b;">₹<?php echo number_format($total_revenue, 2); ?></div>
            </div>
            <div style="background: #f8fafc; padding: 0.5rem; border-radius: 50%; border: 1px solid #f1f5f9;">
                <i class="fas fa-file-invoice-dollar" style="font-size: 1rem; color: #10b981;"></i>
            </div>
        </div>
        
        <div style="background: #fff; border-radius: 8px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; border-left: 3px solid #ef4444;">
            <div>
                <div style="color: #64748b; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.15rem; display: flex; align-items: center; gap: 0.35rem;"><i class="fas fa-arrow-up" style="color: #ef4444; background: #fee2e2; padding: 2px; border-radius: 50%; font-size: 0.55rem;"></i> Total Expenses</div>
                <div style="font-size: 1.15rem; font-weight: 900; color: #1e293b;">₹<?php echo number_format($total_expenses, 2); ?></div>
            </div>
            <div style="background: #f8fafc; padding: 0.5rem; border-radius: 50%; border: 1px solid #f1f5f9;">
                <i class="fas fa-shopping-cart" style="font-size: 1rem; color: #ef4444;"></i>
            </div>
        </div>
    </div>

    <!-- Detailed Ledger Tables -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        
        <!-- Revenue Table -->
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin: 0;">
            <div style="background: #f8fafc; padding: 0.65rem 1rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 0.85rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-file-invoice" style="color: #10b981;"></i> Revenue</h3>
                <span style="background: #d1fae5; color: #065f46; font-size: 0.6rem; font-weight: 700; padding: 2px 6px; border-radius: 8px;"><?php echo count($invoices); ?> Inv</span>
            </div>
            <div style="padding: 0.75rem; max-height: 350px; overflow-y: auto;">
                <?php if (empty($invoices)): ?>
                    <div style="background: #f1f5f9; padding: 1.5rem; text-align: center; border-radius: 6px; border: 1px dashed #cbd5e1; color: #64748b; font-size: 0.75rem; font-weight: 600;">No revenue recorded.</div>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: separate; border-spacing: 0 0.35rem;">
                        <thead>
                            <tr>
                                <th style="padding: 0 0.75rem 0.35rem; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; text-align: left; border-bottom: 1px solid #e2e8f0;">Ref & Date</th>
                                <th style="padding: 0 0.75rem 0.35rem; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; text-align: left; border-bottom: 1px solid #e2e8f0;">Client</th>
                                <th style="padding: 0 0.75rem 0.35rem; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; text-align: right; border-bottom: 1px solid #e2e8f0;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                                <tr style="background: #f8fafc; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='#f8fafc';">
                                    <td style="padding: 0.65rem 0.75rem; border-top-left-radius: 6px; border-bottom-left-radius: 6px; border: 1px solid #f1f5f9; border-right: none;">
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.8rem;">INV-<?php echo str_pad($inv['booking_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                        <div style="font-size: 0.65rem; color: #64748b;"><?php echo date('M d, y', strtotime($inv['created_at'])); ?></div>
                                    </td>
                                    <td style="padding: 0.65rem 0.75rem; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                                        <div style="font-size: 0.75rem; font-weight: 600; color: #334155; line-height: 1.2; margin-bottom: 2px;"><?php echo htmlspecialchars($inv['client_name']); ?></div>
                                        <div style="font-size: 0.65rem; color: #64748b;"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/y', strtotime($inv['start_date'])); ?> - <?php echo date('d/m/y', strtotime($inv['end_date'])); ?></div>
                                    </td>
                                    <td style="padding: 0.65rem 0.75rem; text-align: right; border-top-right-radius: 6px; border-bottom-right-radius: 6px; border: 1px solid #f1f5f9; border-left: none;">
                                        <div style="font-weight: 800; color: #10b981; font-size: 0.85rem;">₹<?php echo number_format($inv['amount'], 2); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Expenses Table -->
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin: 0;">
            <div style="background: #f8fafc; padding: 0.65rem 1rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 0.85rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-shopping-cart" style="color: #ef4444;"></i> Expenses</h3>
                <span style="background: #fee2e2; color: #991b1b; font-size: 0.6rem; font-weight: 700; padding: 2px 6px; border-radius: 8px;"><?php echo count($pos) + count($printing_pos); ?> POs</span>
            </div>
            <div style="padding: 0.75rem; max-height: 350px; overflow-y: auto;">
                <?php if (empty($pos) && empty($printing_pos)): ?>
                    <div style="background: #f1f5f9; padding: 1.5rem; text-align: center; border-radius: 6px; border: 1px dashed #cbd5e1; color: #64748b; font-size: 0.75rem; font-weight: 600;">No expenses recorded.</div>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: separate; border-spacing: 0 0.35rem;">
                        <thead>
                            <tr>
                                <th style="padding: 0 0.75rem 0.35rem; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; text-align: left; border-bottom: 1px solid #e2e8f0;">Ref & Date</th>
                                <th style="padding: 0 0.75rem 0.35rem; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; text-align: left; border-bottom: 1px solid #e2e8f0;">Vendor & Details</th>
                                <th style="padding: 0 0.75rem 0.35rem; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; text-align: right; border-bottom: 1px solid #e2e8f0;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Booking Purchase Costs -->
                            <?php foreach ($invoices as $inv): 
                                if (floatval($inv['purchase_amount']) > 0):
                            ?>
                                <tr style="background: #fdf4ff; transition: all 0.2s;" onmouseover="this.style.background='#fae8ff';" onmouseout="this.style.background='#fdf4ff';">
                                    <td style="padding: 0.65rem 0.75rem; border-top-left-radius: 6px; border-bottom-left-radius: 6px; border: 1px solid #fdf4ff; border-right: none;">
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.8rem;">BK-<?php echo str_pad($inv['booking_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                        <div style="font-size: 0.65rem; color: #64748b;"><?php echo date('M d, y', strtotime($inv['created_at'])); ?></div>
                                    </td>
                                    <td style="padding: 0.65rem 0.75rem; border-top: 1px solid #fdf4ff; border-bottom: 1px solid #fdf4ff;">
                                        <div style="font-size: 0.75rem; font-weight: 600; color: #334155; line-height: 1.2; margin-bottom: 2px;"><?php echo htmlspecialchars($site['vendor_name'] ?: 'Vendor'); ?></div>
                                        <div style="font-size: 0.6rem; color: #c026d3; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Direct Booking Cost</div>
                                        <div style="font-size: 0.65rem; color: #64748b;"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/y', strtotime($inv['start_date'])); ?> - <?php echo date('d/m/y', strtotime($inv['end_date'])); ?></div>
                                    </td>
                                    <td style="padding: 0.65rem 0.75rem; text-align: right; border-top-right-radius: 6px; border-bottom-right-radius: 6px; border: 1px solid #fdf4ff; border-left: none;">
                                        <div style="font-weight: 800; color: #ef4444; font-size: 0.85rem;">₹<?php echo number_format($inv['purchase_amount'], 2); ?></div>
                                    </td>
                                </tr>
                            <?php endif; endforeach; ?>
                            
                            <!-- Asset POs -->
                            <?php foreach ($pos as $po): ?>
                                <tr style="background: #fef2f2; transition: all 0.2s;" onmouseover="this.style.background='#fee2e2';" onmouseout="this.style.background='#fef2f2';">
                                    <td style="padding: 0.65rem 0.75rem; border-top-left-radius: 6px; border-bottom-left-radius: 6px; border: 1px solid #fff1f2; border-right: none;">
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.8rem;"><?php echo htmlspecialchars($po['po_number']); ?></div>
                                        <div style="font-size: 0.65rem; color: #64748b;"><?php echo date('M d, y', strtotime($po['created_at'])); ?></div>
                                    </td>
                                    <td style="padding: 0.65rem 0.75rem; border-top: 1px solid #fff1f2; border-bottom: 1px solid #fff1f2;">
                                        <div style="font-size: 0.75rem; font-weight: 600; color: #334155; line-height: 1.2; margin-bottom: 2px;"><?php echo htmlspecialchars($po['vendor_name']); ?></div>
                                        <div style="font-size: 0.6rem; color: #ef4444; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Asset Booking</div>
                                        <div style="font-size: 0.65rem; color: #64748b;"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/y', strtotime($po['start_date'])); ?> - <?php echo date('d/m/y', strtotime($po['end_date'])); ?></div>
                                    </td>
                                    <td style="padding: 0.65rem 0.75rem; text-align: right; border-top-right-radius: 6px; border-bottom-right-radius: 6px; border: 1px solid #fff1f2; border-left: none;">
                                        <div style="font-weight: 800; color: #ef4444; font-size: 0.85rem;">₹<?php echo number_format($po['cost'], 2); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Printing POs -->
                            <?php foreach ($printing_pos as $ppo): 
                                $print_cost = floatval($ppo['rate_per_sqft']) * $sqft;
                            ?>
                                <tr style="background: #fffbeb; transition: all 0.2s;" onmouseover="this.style.background='#fef3c7';" onmouseout="this.style.background='#fffbeb';">
                                    <td style="padding: 0.65rem 0.75rem; border-top-left-radius: 6px; border-bottom-left-radius: 6px; border: 1px solid #fffbeb; border-right: none;">
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.8rem;"><?php echo htmlspecialchars($ppo['po_number']); ?></div>
                                        <div style="font-size: 0.65rem; color: #64748b;"><?php echo date('M d, y', strtotime($ppo['created_at'])); ?></div>
                                    </td>
                                    <td style="padding: 0.65rem 0.75rem; border-top: 1px solid #fffbeb; border-bottom: 1px solid #fffbeb;">
                                        <div style="font-size: 0.75rem; font-weight: 600; color: #334155; line-height: 1.2; margin-bottom: 2px;"><?php echo htmlspecialchars($ppo['vendor_name']); ?></div>
                                        <div style="font-size: 0.6rem; color: #d97706; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Print & Mount</div>
                                        <div style="font-size: 0.65rem; color: #64748b;"><?php echo htmlspecialchars($ppo['media_type']); ?> @ ₹<?php echo $ppo['rate_per_sqft']; ?>/sq</div>
                                    </td>
                                    <td style="padding: 0.65rem 0.75rem; text-align: right; border-top-right-radius: 6px; border-bottom-right-radius: 6px; border: 1px solid #fffbeb; border-left: none;">
                                        <div style="font-weight: 800; color: #ef4444; font-size: 0.85rem;">₹<?php echo number_format($print_cost, 2); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
