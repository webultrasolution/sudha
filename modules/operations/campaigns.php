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
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--primary);">Campaign Management</h2>
            <p style="font-size: 0.85rem; color: var(--secondary); margin-top: 0.25rem;">Monitor and manage all active outdoor advertising campaigns.</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="search-box" style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" placeholder="Search Project ID or Client..." class="p-input" style="padding-left: 2.5rem; width: 300px;">
            </div>
            <a href="../proposals/create.php" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700;">
                <i class="fas fa-plus-circle"></i> Create New Campaign
            </a>
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
.table { width: 100%; border-collapse: separate; border-spacing: 0; }
.table th { background: #f8fafc; padding: 1.25rem 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; font-weight: 800; border-bottom: 2px solid #f1f5f9; text-align: left; }
.table td { padding: 1.25rem 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; color: #334155; }
.table tr:hover td { background: #fcfcfc; }

.status-badge { padding: 0.35rem 0.75rem; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 0.35rem; }
.status-badge.running { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
.status-badge.planned { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
.status-badge.completed { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

.btn-icon { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: #f1f5f9; color: #64748b; transition: all 0.2s; border: none; cursor: pointer; text-decoration: none; margin-right: 0.5rem; }
.btn-icon:hover { background: var(--primary); color: white; transform: translateY(-1px); }

.p-input { height: 42px; border: 1px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 0.9rem; transition: all 0.2s; }
.p-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
