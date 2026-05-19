<?php
$activePage = 'dashboard';
$pageTitle = 'Business Intelligence Dashboard';
include_once __DIR__ . '/includes/header.php';

// Check Dashboard Specific Permission
if (!canView('dashboard')) {
    echo "<div class='card' style='padding: 4rem 2rem; text-align: center; border-radius: 16px; margin: 3rem auto; max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; background: white;'>
        <div style='background: #fee2e2; color: #ef4444; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 2rem;'>
            <i class='fas fa-lock'></i>
        </div>
        <h2 style='color: #0f172a; font-weight: 800; font-size: 1.75rem; margin: 0 0 0.5rem 0;'>Dashboard Locked</h2>
        <p style='color: #64748b; line-height: 1.6; margin: 0 0 2rem 0; font-size: 0.95rem;'>Your current user role does not have authorization to view the Business Intelligence Dashboard. Please use the sidebar to navigate to your assigned modules.</p>
        <div style='display: flex; justify-content: center; gap: 1rem;'>
            <a href='modules/inventory/sites.php' class='btn btn-secondary' style='font-weight: 700; border-radius: 8px; padding: 0.6rem 1.25rem; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;'><i class='fas fa-map-marked-alt'></i> View Sites</a>
            <a href='modules/proposals/proposals.php' class='btn btn-primary' style='font-weight: 700; border-radius: 8px; padding: 0.6rem 1.25rem; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;'><i class='fas fa-file-contract'></i> Proposals</a>
        </div>
    </div>";
    include_once __DIR__ . '/includes/footer.php';
    exit;
}

$canViewFinancials = canView('financials');
$canViewInventory = canView('inventory');
$canViewProposals = canView('proposals');
$canViewBookings = canView('bookings');

$revenue = 0;
$cost = 0;
$profit = 0;
$margin = 0;
$chartLabels = '[]';
$chartRevenue = '[]';
$chartProfit = '[]';

if ($canViewFinancials) {
    // Financial Data
    $finStats = $pdo->query("
        SELECT 
            SUM(amount) as revenue,
            SUM(purchase_rate * days) as cost,
            SUM((sale_rate - purchase_rate) * days) as profit
        FROM proposal_items 
        JOIN proposals ON proposal_items.proposal_id = proposals.id
        WHERE proposals.status != 'cancelled'
    ")->fetch();

    $revenue = $finStats['revenue'] ?: 0;
    $cost = $finStats['cost'] ?: 0;
    $profit = $finStats['profit'] ?: 0;
    $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

    // Monthly Data for Chart
    $monthlyData = $pdo->query("
        SELECT DATE_FORMAT(p.created_at, '%b %Y') as month, SUM(pi.amount) as rev, SUM((pi.sale_rate - pi.purchase_rate) * pi.days) as prof
        FROM proposals p JOIN proposal_items pi ON p.id = pi.proposal_id
        WHERE p.status != 'cancelled' GROUP BY month ORDER BY p.created_at ASC LIMIT 6
    ")->fetchAll();

    $chartLabels = json_encode(array_column($monthlyData, 'month'));
    $chartRevenue = json_encode(array_column($monthlyData, 'rev'));
    $chartProfit = json_encode(array_column($monthlyData, 'prof'));
}

$totalSites = 1;
$bookedSites = 0;
if ($canViewInventory) {
    $totalSites = $pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn() ?: 1;
    $bookedSites = $pdo->query("SELECT COUNT(*) FROM sites WHERE status = 'booked'")->fetchColumn();
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-container" style="padding: 10px;">
    <!-- Row 1: Metrics -->
    <div class="metrics-grid">
        <?php if ($canViewFinancials): ?>
        <div class="metric-card g-blue">
            <div class="m-content">
                <i class="fas fa-chart-line"></i>
                <div class="m-data"><span>Revenue</span><h3><?php echo formatCurrency($revenue); ?></h3></div>
            </div>
        </div>
        <div class="metric-card g-orange">
            <div class="m-content">
                <i class="fas fa-shopping-cart"></i>
                <div class="m-data"><span>Cost</span><h3><?php echo formatCurrency($cost); ?></h3></div>
            </div>
        </div>
        <div class="metric-card g-green">
            <div class="m-content">
                <i class="fas fa-wallet"></i>
                <div class="m-data"><span>Profit</span><h3><?php echo formatCurrency($profit); ?></h3></div>
            </div>
            <div class="m-mini-badge"><?php echo number_format($margin, 1); ?>%</div>
        </div>
        <?php endif; ?>
        
        <?php if ($canViewInventory): ?>
        <div class="metric-card g-purple">
            <div class="m-content">
                <i class="fas fa-map-marker-alt"></i>
                <div class="m-data"><span>Occupancy</span><h3><?php echo $bookedSites; ?> / <?php echo $totalSites; ?></h3></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Row 2: Charts -->
    <div class="charts-row" style="<?php echo !$canViewFinancials ? 'grid-template-columns: 1fr;' : ''; ?>">
        <?php if ($canViewFinancials): ?>
        <div class="chart-box main-chart">
            <div class="box-header"><h4>Revenue vs Profit Analytics</h4></div>
            <div class="chart-wrapper"><canvas id="revenueChart"></canvas></div>
        </div>
        <?php endif; ?>
        
        <?php if ($canViewInventory): ?>
        <div class="chart-box mini-chart">
            <div class="box-header"><h4>Media Type Distribution</h4></div>
            <div class="chart-wrapper"><canvas id="typeChart"></canvas></div>
            <?php
            $typeData = $pdo->query("SELECT type, COUNT(*) as count FROM sites GROUP BY type")->fetchAll();
            $typeLabels = json_encode(array_column($typeData, 'type'));
            $typeCounts = json_encode(array_column($typeData, 'count'));
            ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Row 3: Tables & Details -->
    <div class="details-row" style="<?php 
        $cols = 0;
        if ($canViewFinancials) $cols++;
        if ($canViewInventory) $cols += 2; // Vendor sources & Inventory Status
        echo "grid-template-columns: repeat($cols, 1fr);";
    ?>">
        <!-- Top Proposals -->
        <?php if ($canViewFinancials): ?>
        <div class="table-box">
            <div class="box-header"><h4>Top Proposals</h4></div>
            <table class="modern-table">
                <thead><tr><th>Proposal</th><th>Revenue</th><th>Profit</th></tr></thead>
                <tbody>
                    <?php 
                    $topP = $pdo->query("
                        SELECT p.proposal_number, SUM(pi.amount) as rev, SUM((pi.sale_rate-pi.purchase_rate)*pi.days) as prof
                        FROM proposals p JOIN proposal_items pi ON p.id=pi.proposal_id
                        WHERE p.status != 'cancelled' GROUP BY p.id ORDER BY prof DESC LIMIT 5
                    ")->fetchAll();
                    foreach($topP as $tp): ?>
                    <tr><td><strong><?php echo $tp['proposal_number']; ?></strong></td><td><?php echo formatCurrency($tp['rev']); ?></td><td style="color:#10b981; font-weight:800;">+<?php echo formatCurrency($tp['prof']); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Vendor Progress -->
        <?php if ($canViewInventory): ?>
        <div class="table-box">
            <div class="box-header"><h4>Vendor Sources</h4></div>
            <div class="vendor-grid">
                <?php
                $topVendors = $pdo->query("
                    SELECT p.name, COUNT(s.id) as site_count FROM partners p 
                    JOIN sites s ON p.id = s.vendor_id WHERE p.type='vendor' 
                    GROUP BY p.id ORDER BY site_count DESC LIMIT 4
                ")->fetchAll();
                foreach($topVendors as $v): ?>
                <div class="v-item">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span style="font-size:0.75rem; font-weight:700;"><?php echo $v['name']; ?></span>
                        <span style="font-size:0.75rem; font-weight:800;"><?php echo $v['site_count']; ?></span>
                    </div>
                    <div class="v-bar"><div class="v-fill" style="width: <?php echo ($v['site_count']/$totalSites)*100; ?>%;"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Status -->
        <div class="table-box">
            <div class="box-header"><h4>Inventory Status</h4></div>
            <div class="chart-wrapper"><canvas id="statusChart"></canvas></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Row 4: Footer Polished Row -->
    <div class="polished-footer" style="<?php 
        $footCols = 1; // Quick Links is always visible
        if ($canViewFinancials) $footCols++; // Top Clients
        if (hasRole('admin')) $footCols++; // System Activity is admin only
        echo "grid-template-columns: repeat($footCols, 1fr);";
    ?>">
        <?php if ($canViewFinancials): ?>
        <div class="footer-card">
            <h5><i class="fas fa-crown"></i> Top Clients</h5>
            <div class="client-list">
                <?php
                $topClients = $pdo->query("
                    SELECT p.name, SUM(pr.grand_total) as total_spend 
                    FROM partners p JOIN proposals pr ON p.id = pr.client_id 
                    WHERE p.type='client' AND pr.status='confirmed'
                    GROUP BY p.id ORDER BY total_spend DESC LIMIT 3
                ")->fetchAll();
                foreach($topClients as $c): ?>
                <div class="client-mini">
                    <div class="c-icon"><?php echo substr($c['name'],0,1); ?></div>
                    <div class="c-info"><strong><?php echo $c['name']; ?></strong><p><?php echo formatCurrency($c['total_spend']); ?></p></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (hasRole('admin')): ?>
        <div class="footer-card">
            <h5><i class="fas fa-history"></i> System Activity</h5>
            <div class="activity-list">
                <?php
                $activities = $pdo->query("SELECT a.*, u.username FROM activity_log a JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 3")->fetchAll();
                foreach($activities as $a): ?>
                <div class="a-mini"><strong><?php echo $a['username']; ?></strong> <?php echo $a['action']; ?><span><?php echo date('h:i A', strtotime($a['created_at'])); ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="footer-card dark-card">
            <h5>Quick Links</h5>
            <div class="quick-links">
                <?php if (canAdd('proposals')): ?>
                <a href="modules/proposals/create.php"><i class="fas fa-plus"></i> New Proposal</a>
                <?php endif; ?>
                <?php if (canAdd('bookings')): ?>
                <a href="modules/operations/direct_booking.php"><i class="fas fa-plus-circle"></i> Direct Booking</a>
                <?php endif; ?>
                <?php if (canView('inventory')): ?>
                <a href="modules/inventory/sites.php"><i class="fas fa-th"></i> Inventory</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
.metric-card { padding: 1.25rem; border-radius: 16px; color: white; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.m-content { display: flex; align-items: center; gap: 1rem; }
.m-content i { font-size: 1.5rem; background: rgba(255,255,255,0.2); width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 10px; }
.m-data span { font-size: 0.7rem; opacity: 0.8; text-transform: uppercase; font-weight: 700; }
.m-data h3 { font-size: 1.3rem; font-weight: 800; margin: 0; }
.m-mini-badge { position: absolute; top: 10px; right: 10px; background: #10b981; padding: 2px 6px; border-radius: 50px; font-size: 0.6rem; font-weight: 800; }
.g-blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.g-orange { background: linear-gradient(135deg, #f97316, #ea580c); }
.g-green { background: linear-gradient(135deg, #10b981, #059669); }
.g-purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }

.charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.chart-box { background: white; padding: 1.5rem; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.box-header h4 { font-size: 0.9rem; font-weight: 800; color: #1e293b; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; }
.chart-wrapper { position: relative; width: 100%; height: 220px; }

.details-row { display: grid; grid-template-columns: 1fr 1fr 0.8fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.table-box { background: white; padding: 1.5rem; border-radius: 20px; }
.modern-table { width: 100%; border-collapse: collapse; }
.modern-table th { text-align: left; font-size: 0.65rem; color: #94a3b8; padding: 8px; text-transform: uppercase; }
.modern-table td { padding: 10px 8px; font-size: 0.75rem; border-bottom: 1px solid #f8fafc; }

.vendor-grid { display: flex; flex-direction: column; gap: 1rem; }
.v-bar { width: 100%; height: 5px; background: #f1f5f9; border-radius: 10px; }
.v-fill { height: 100%; background: #3b82f6; border-radius: 10px; }

.polished-footer { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; background: #f1f5f9; padding: 1.5rem; border-radius: 24px; }
.footer-card { background: white; padding: 1.25rem; border-radius: 18px; }
.footer-card h5 { font-size: 0.8rem; font-weight: 800; margin-bottom: 1rem; color: #1e293b; }
.client-mini { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
.c-icon { width: 32px; height: 32px; background: #eff6ff; color: #3b82f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.7rem; }
.c-info strong { font-size: 0.75rem; display: block; color: #1e293b; }
.c-info p { font-size: 0.65rem; color: #10b981; font-weight: 700; margin: 0; }
.a-mini { font-size: 0.7rem; color: #475569; padding-bottom: 8px; border-bottom: 1px solid #f8fafc; margin-bottom: 8px; }
.a-mini span { display: block; font-size: 0.6rem; color: #94a3b8; margin-top: 2px; }
.dark-card { background: #0f172a; color: white; }
.dark-card h5 { color: white; }
.quick-links a { display: block; background: rgba(255,255,255,0.1); color: white; padding: 10px; border-radius: 10px; text-decoration: none; margin-bottom: 8px; font-size: 0.75rem; font-weight: 700; }
</style>

<script>
// Line Chart
if (document.getElementById('revenueChart')) {
    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo $chartLabels; ?>,
            datasets: [{
                label: 'Revenue', data: <?php echo $chartRevenue; ?>,
                borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', fill: true, tension: 0.4, borderWidth: 3
            }, {
                label: 'Profit', data: <?php echo $chartProfit; ?>,
                borderColor: '#10b981', backgroundColor: 'transparent', fill: false, tension: 0.4, borderWidth: 3, borderDash: [5, 5]
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
    });
}

// Doughnut
if (document.getElementById('typeChart')) {
    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $typeLabels; ?>,
            datasets: [{ data: <?php echo $typeCounts; ?>, backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'], borderWidth: 0 }]
        },
        options: { maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } }
    });
}

// Pie
if (document.getElementById('statusChart')) {
    new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: {
            labels: ['Booked', 'Available'],
            datasets: [{ data: [<?php echo $bookedSites; ?>, <?php echo ($totalSites-$bookedSites); ?>], backgroundColor: ['#ef4444', '#10b981'], borderWidth: 0 }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } }
    });
}
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
