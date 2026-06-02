<?php
$activePage = 'trash';
$pageTitle = 'Trash';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
// Allow users who can view inventory (or admins) to access Trash
requirePermission('inventory', 'view');
include_once __DIR__ . '/../../includes/header.php';

// Ensure trash table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS trash (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    row_id VARCHAR(100) DEFAULT NULL,
    pk_name VARCHAR(100) DEFAULT 'id',
    row_data LONGTEXT,
    deleted_by INT DEFAULT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT
)");

$filterTable = isset($_GET['filter_table']) ? trim($_GET['filter_table']) : '';
$tables = $pdo->query("SELECT DISTINCT table_name FROM trash ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$sql = 'SELECT t.*, u.full_name as deleted_by_name FROM trash t LEFT JOIN users u ON t.deleted_by = u.id';
$params = [];
if ($filterTable !== '') {
    $sql .= ' WHERE t.table_name = ?';
    $params[] = $filterTable;
}
$sql .= ' ORDER BY deleted_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; gap: 1rem; flex-wrap: wrap;">
        <div style="display:flex; align-items:center; gap: 1rem; flex-wrap: wrap;">
            <h2 style="margin:0;">Trash</h2>
            <form method="GET" style="margin:0;">
                <label for="filter_table" style="font-weight:600; color:#334155;">Show:</label>
                <select id="filter_table" name="filter_table" onchange="this.form.submit()" style="padding:0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #cbd5e1; background:#fff;">
                    <option value="">All Types</option>
                    <?php foreach ($tables as $tableName): ?>
                        <option value="<?php echo htmlspecialchars($tableName); ?>" <?php echo $filterTable === $tableName ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $tableName))); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div>
            <a href="../admin" class="btn btn-secondary" style="margin-right:0.75rem;">Back</a>
            <a href="trash.php" class="btn btn-primary" style="background:#0f172a;color:#fff;border:none;">Refresh</a>
        </div>
    </div>
    <?php if (empty($items)): ?>
        <div>No trashed items.</div>
    <?php else: ?>
        <table class="crs-table" style="table-layout:fixed; width:100%;">
            <thead>
                <tr><th>ID</th><th>Table</th><th>Row ID</th><th>Deleted By</th><th>Deleted At</th><th>Reason</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?php echo $it['id']; ?></td>
                    <td><?php echo htmlspecialchars($it['table_name']); ?></td>
                    <td><?php echo htmlspecialchars($it['row_id']); ?></td>
                    <td><?php echo htmlspecialchars($it['deleted_by_name'] ?? $it['deleted_by']); ?></td>
                    <td><?php echo $it['deleted_at']; ?></td>
                    <td><?php echo htmlspecialchars($it['reason']); ?></td>
                    <td class="action-column" style="width:160px; white-space:nowrap; position:sticky; right:0; background:white;">
                        <div class="action-buttons" style="padding:0.5rem; display:flex; flex-direction:column; gap:0.5rem; align-items:center;">
                            <button class="btn-restore" onclick="restoreItem(<?php echo $it['id']; ?>)">Restore</button>
                            <button class="btn-delete" onclick="deleteForever(<?php echo $it['id']; ?>)">Delete Forever</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function restoreItem(id) {
    if (!confirm('Restore this item?')) return;
    fetch('../../ajax/restore_trash.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ trash_id: id }) })
    .then(r=>r.json()).then(res=>{
        if (res.success) location.reload(); else alert(res.message || 'Restore failed');
    });
}
function deleteForever(id) {
    if (!confirm('Permanently delete this trash item? This cannot be undone.')) return;
    fetch('../../ajax/delete_trash.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ trash_id: id }) })
    .then(r=>r.json()).then(res=>{
        if (res.success) location.reload(); else alert(res.message || 'Delete failed');
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<style>
.crs-table th, .crs-table td { vertical-align: middle; padding: 1rem; }
.action-column { z-index: 5; }
.action-buttons { width: 100%; display:flex; flex-direction:column; gap:0.5rem; align-items:center; }
.action-buttons .btn-restore, .action-buttons .btn-delete { width: 130px; height: 40px; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; }
.action-buttons .btn-restore { background: #059669; color: #fff; box-shadow: 0 6px 18px rgba(5,150,105,0.18); }
.action-buttons .btn-delete { background: #f3f4f6; color: #0f172a; border: 1px solid #e6eef2; box-shadow: none; }
.action-buttons .btn-delete:hover { background: #ececec; }
</style>
