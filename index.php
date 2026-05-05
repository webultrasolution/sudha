<?php
$activePage = 'dashboard';
$pageTitle = 'Dashboard Overview';
include_once __DIR__ . '/includes/header.php';

// Fetch Statistics
$totalSites = $pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn();
$occupiedSites = $pdo->query("SELECT COUNT(*) FROM sites WHERE status = 'booked'")->fetchColumn();
$activeCampaigns = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'running'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(total_amount) FROM invoices")->fetchColumn() ?: 0;
$pendingPayments = $pdo->query("SELECT SUM(total_amount) FROM invoices WHERE payment_status != 'paid'")->fetchColumn() ?: 0;

// Recent Activity (Activity Log)
$activities = $pdo->query("
    SELECT a.*, u.username 
    FROM activity_log a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.id DESC LIMIT 6
")->fetchAll();

// Recent Campaigns
$recentCampaigns = $pdo->query("
    SELECT c.*, p.name as client_name 
    FROM campaigns c 
    JOIN partners p ON c.client_id = p.id 
    ORDER BY c.id DESC LIMIT 5
")->fetchAll();
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--primary);">
            <i class="fas fa-coins"></i>
        </div>
        <div class="stat-info">
            <h3>Total Revenue</h3>
            <p><?php echo formatCurrency($totalRevenue); ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
            <i class="fas fa-satellite-dish"></i>
        </div>
        <div class="stat-info">
            <h3>Active Campaigns</h3>
            <p><?php echo $activeCampaigns; ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
            <i class="fas fa-map-marked-alt"></i>
        </div>
        <div class="stat-info">
            <h3>Occupancy</h3>
            <p><?php echo $occupiedSites; ?> / <?php echo $totalSites; ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-info">
            <h3>Pending Payments</h3>
            <p><?php echo formatCurrency($pendingPayments); ?></p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
    <!-- Recent Campaigns -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem;">Live Campaigns</h2>
            <a href="modules/operations/bookings.php" class="btn btn-primary" style="font-size: 0.8rem;">View All</a>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Project ID</th>
                    <th>Client</th>
                    <th>Days</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentCampaigns as $c): ?>
                <tr>
                    <td><strong><?php echo $c['project_id']; ?></strong></td>
                    <td><?php echo $c['client_name']; ?></td>
                    <td><?php echo $c['days']; ?> Days</td>
                    <td><span class="status-badge <?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Right Column -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Activity Log -->
        <div class="card" style="margin-bottom: 0;">
            <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">System Activity</h2>
            <div class="activity-feed">
                <?php foreach ($activities as $a): ?>
                <div class="activity-item">
                    <div class="activity-dot"></div>
                    <div class="activity-content">
                        <strong><?php echo $a['username']; ?></strong> <?php echo $a['action']; ?>
                        <div class="activity-time"><?php echo date('h:i A', strtotime($a['created_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h2 style="font-size: 1.25rem; margin-bottom: 1.5rem;">Quick Actions</h2>
            <div style="display: grid; gap: 0.75rem;">
                <a href="<?php echo BASE_URL; ?>modules/proposals/create.php" class="action-btn">
                    <i class="fas fa-plus-circle"></i> New Proposal
                </a>
                <a href="<?php echo BASE_URL; ?>modules/financials/po_create.php" class="action-btn" style="background: var(--warning);">
                    <i class="fas fa-file-contract"></i> Generate PO
                </a>
                <a href="<?php echo BASE_URL; ?>modules/financials/invoice_create.php" class="action-btn" style="background: var(--success);">
                    <i class="fas fa-receipt"></i> Create Invoice
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.status-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; }
.status-badge.running { background: #dcfce7; color: #166534; }
.status-badge.planned { background: #e0f2fe; color: #0369a1; }

.activity-feed { position: relative; padding-left: 1rem; }
.activity-feed::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px; background: #f1f5f9; }
.activity-item { position: relative; padding-bottom: 1.25rem; }
.activity-dot { position: absolute; left: -1.25rem; top: 0.25rem; width: 10px; height: 10px; border-radius: 50%; background: var(--primary); border: 2px solid white; }
.activity-content { font-size: 0.85rem; color: #475569; }
.activity-time { font-size: 0.7rem; color: #94a3b8; margin-top: 0.25rem; }

.action-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: var(--primary); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; }
.action-btn i { font-size: 1.1rem; }
</style>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
