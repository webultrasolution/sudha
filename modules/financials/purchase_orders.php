<?php
$activePage = 'pos';
$pageTitle = 'Purchase Order Management';
include_once __DIR__ . '/../../includes/header.php';

if (!hasRole(['admin', 'accounts'])) {
    echo "<div class='card'>Access Denied.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch POs
$pos = $pdo->query("
    SELECT po.*, v.name as vendor_name, u.username as creator 
    FROM purchase_orders po 
    JOIN partners v ON po.vendor_id = v.id 
    LEFT JOIN users u ON po.employee_id = u.id 
    ORDER BY po.id DESC
")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Vendor Purchase Orders</h2>
        <a href="po_create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New PO
        </a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>PO Number</th>
                <th>Vendor</th>
                <th>Type</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pos as $p): ?>
            <tr>
                <td><strong><?php echo $p['po_number']; ?></strong></td>
                <td><?php echo $p['vendor_name']; ?></td>
                <td><span class="badge-type"><?php echo ucfirst($p['type']); ?></span></td>
                <td><?php echo date('d M Y', strtotime($p['po_date'])); ?></td>
                <td><?php echo formatCurrency($p['total_amount']); ?></td>
                <td>
                    <span class="status-pill status-<?php echo $p['status']; ?>">
                        <?php echo ucfirst($p['status']); ?>
                    </span>
                </td>
                <td><?php echo $p['creator']; ?></td>
                <td>
                    <a href="po_view.php?id=<?php echo $p['id']; ?>" class="btn-icon" title="View"><i class="fas fa-eye"></i></a>
                    <button class="btn-icon" style="color: var(--primary);" title="Download PDF"><i class="fas fa-file-pdf"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pos)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--secondary);">No Purchase Orders found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.badge-type { background: #f1f5f9; padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; color: #475569; }
.status-pill { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.status-draft { background: #f1f5f9; color: #475569; }
.status-approved { background: #e0f2fe; color: #0369a1; }
.status-paid { background: #dcfce7; color: #166534; }
.btn-icon { background: none; border: none; cursor: pointer; color: var(--secondary); font-size: 1rem; padding: 0.25rem; }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
