<?php
$activePage = 'reports';
$pageTitle = 'Business Analytics & Reports';
include_once __DIR__ . '/../../includes/header.php';
?>

<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
    <div class="card report-box">
        <div class="report-icon"><i class="fas fa-map-marker-alt"></i></div>
        <h3>Site Report</h3>
        <p>Occupancy rate, Revenue per site, and availability timeline.</p>
        <button class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Generate Excel</button>
    </div>
    <div class="card report-box">
        <div class="report-icon" style="color: #6366f1;"><i class="fas fa-user-tie"></i></div>
        <h3>Client Report</h3>
        <p>Total campaigns, Billing history, and Outstanding balances.</p>
        <button class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Generate Excel</button>
    </div>
    <div class="card report-box">
        <div class="report-icon" style="color: #f59e0b;"><i class="fas fa-truck"></i></div>
        <h3>Vendor Report</h3>
        <p>Vendor payouts, Profit margins, and Site usage stats.</p>
        <button class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Generate Excel</button>
    </div>
</div>

<style>
.report-box { text-align: center; padding: 2rem; }
.report-icon { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; }
.report-box h3 { margin-bottom: 0.5rem; font-weight: 700; }
.report-box p { font-size: 0.9rem; color: var(--secondary); height: 3rem; }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
