<?php
$activePage = 'campaigns';
$pageTitle = 'Campaign Management List';
include_once __DIR__ . '/../../includes/header.php';

// Fetch Campaigns
$campaigns = $pdo->query("
    SELECT c.*, p.name as client_name 
    FROM campaigns c 
    JOIN partners p ON c.client_id = p.id 
    ORDER BY c.id DESC
")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Active & History Campaigns</h2>
        <div class="search-box">
            <input type="text" placeholder="Search Project ID or Client..." class="p-input">
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Project ID</th>
                <th>Client Name</th>
                <th>Campaign Display</th>
                <th>From / To</th>
                <th>Days</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($campaigns as $c): ?>
            <tr>
                <td><strong><?php echo $c['project_id']; ?></strong></td>
                <td><?php echo $c['client_name']; ?></td>
                <td><?php echo $c['display_name']; ?></td>
                <td style="font-size: 0.8rem;">
                    <?php echo date('d M y', strtotime($c['from_date'])); ?> - <?php echo date('d M y', strtotime($c['to_date'])); ?>
                </td>
                <td><?php echo $c['days']; ?></td>
                <td><?php echo formatCurrency($c['amount']); ?></td>
                <td><span class="status-badge <?php echo $c['status']; ?>"><?php echo strtoupper($c['status']); ?></span></td>
                <td>
                    <a href="mounting.php?camp=<?php echo $c['id']; ?>" class="btn-icon" title="View Mounting"><i class="fas fa-tools"></i></a>
                    <button class="btn-icon" title="Report"><i class="fas fa-file-pdf"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.status-badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 800; }
.status-badge.running { background: #dcfce7; color: #166534; }
.status-badge.planned { background: #e0f2fe; color: #0369a1; }
.status-badge.completed { background: #f1f5f9; color: #475569; }
.p-input { padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; width: 250px; }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
