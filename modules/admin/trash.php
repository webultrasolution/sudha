<?php
$activePage = 'trash';
$pageTitle = 'Trash';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
// Allow users who can view inventory (or admins) to access Trash
requireRole('admin');
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
$excludedTables = ['invoice_items', 'proposal_items', 'operations', 'po_items', 'po_attachments', 'site_images'];
$excludedPlaceholders = implode(',', array_fill(0, count($excludedTables), '?'));

$tablesQuery = "SELECT DISTINCT table_name FROM trash WHERE table_name NOT IN ($excludedPlaceholders) ORDER BY table_name";
$tablesStmt = $pdo->prepare($tablesQuery);
$tablesStmt->execute($excludedTables);
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

$sql = 'SELECT t.*, COALESCE(u.name, u.full_name) as deleted_by_name FROM trash t LEFT JOIN users u ON t.deleted_by = u.id';
$params = [];
if ($filterTable !== '') {
    $sql .= ' WHERE t.table_name = ?';
    $params[] = $filterTable;
} else {
    $sql .= " WHERE t.table_name NOT IN ($excludedPlaceholders)";
    $params = $excludedTables;
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
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <button id="bulkRestoreBtn" class="btn" onclick="bulkRestoreTrash()" style="display: none; background: #dcfce7; color: #059669; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 0.5rem 1rem; font-weight: 700; cursor: pointer; align-items: center; gap: 6px; font-size: 0.85rem; height: 38px; box-sizing: border-box;">
                <i class="fas fa-undo"></i> Restore Selected (<span id="restoreCount">0</span>)
            </button>
            <button id="bulkDeleteBtn" class="btn" onclick="bulkDeleteTrash()" style="display: none; background: #fee2e2; color: #ef4444; border: 1.5px solid #fecaca; border-radius: 8px; padding: 0.5rem 1rem; font-weight: 700; cursor: pointer; align-items: center; gap: 6px; font-size: 0.85rem; height: 38px; box-sizing: border-box;">
                <i class="fas fa-trash-alt"></i> Delete Selected (<span id="deleteCount">0</span>)
            </button>
            <a href="../admin" class="btn btn-secondary" style="margin-right:0.75rem; height: 38px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box;">Back</a>
            <a href="trash.php" class="btn btn-primary" style="background:#0f172a;color:#fff;border:none; height: 38px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box;">Refresh</a>
        </div>
    </div>
    <?php if (empty($items)): ?>
        <div>No trashed items.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="selectAllTrash" onchange="toggleSelectAllTrash(this)" style="width: 16px; height: 16px; accent-color: #059669; cursor: pointer;">
                    </th>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 150px;">Table</th>
                    <th style="width: 120px;">Row ID</th>
                    <th style="width: 180px;">Deleted By</th>
                    <th style="width: 180px;">Deleted At</th>
                    <th>Reason</th>
                    <th style="text-align: center; width: 160px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td style="text-align: center;">
                        <input type="checkbox" class="trash-select-chk" value="<?php echo $it['id']; ?>" onchange="updateBulkTrashButtons()" style="width: 16px; height: 16px; accent-color: #059669; cursor: pointer;">
                    </td>
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
function toggleSelectAllTrash(master) {
    const chks = document.querySelectorAll('.trash-select-chk');
    chks.forEach(chk => chk.checked = master.checked);
    updateBulkTrashButtons();
}

function updateBulkTrashButtons() {
    const checked = document.querySelectorAll('.trash-select-chk:checked');
    const restoreBtn = document.getElementById('bulkRestoreBtn');
    const deleteBtn = document.getElementById('bulkDeleteBtn');
    const restoreCount = document.getElementById('restoreCount');
    const deleteCount = document.getElementById('deleteCount');
    
    if (checked.length > 0) {
        if (restoreBtn) restoreBtn.style.display = 'inline-flex';
        if (deleteBtn) deleteBtn.style.display = 'inline-flex';
        if (restoreCount) restoreCount.innerText = checked.length;
        if (deleteCount) deleteCount.innerText = checked.length;
    } else {
        if (restoreBtn) restoreBtn.style.display = 'none';
        if (deleteBtn) deleteBtn.style.display = 'none';
    }
}

function restoreItem(id) {
    Swal.fire({
        title: 'Restore Item?',
        text: "Are you sure you want to restore this item?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, restore'
    }).then((result) => {
        if (result.isConfirmed) {
            performRestore([id]);
        }
    });
}

function bulkRestoreTrash() {
    const checked = document.querySelectorAll('.trash-select-chk:checked');
    if (checked.length === 0) return;
    const ids = Array.from(checked).map(chk => parseInt(chk.value));
    
    Swal.fire({
        title: 'Restore Selected Items?',
        text: `Are you sure you want to restore the ${ids.length} selected items?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, restore all'
    }).then((result) => {
        if (result.isConfirmed) {
            performRestore(ids);
        }
    });
}

function performRestore(ids) {
    Swal.fire({
        title: 'Restoring...',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('../../ajax/restore_trash.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ trash_ids: ids })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Restored Successfully!',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Restore Failed', res.message || 'Error occurred.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Network error. Please try again.', 'error');
    });
}

function deleteForever(id) {
    Swal.fire({
        title: 'Delete Forever?',
        text: "This action cannot be undone. Are you sure you want to delete this item permanently?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete forever'
    }).then((result) => {
        if (result.isConfirmed) {
            performDeleteForever([id]);
        }
    });
}

function bulkDeleteTrash() {
    const checked = document.querySelectorAll('.trash-select-chk:checked');
    if (checked.length === 0) return;
    const ids = Array.from(checked).map(chk => parseInt(chk.value));
    
    Swal.fire({
        title: 'Delete Selected Forever?',
        text: `This action cannot be undone. Are you sure you want to permanently delete the ${ids.length} selected items?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete all'
    }).then((result) => {
        if (result.isConfirmed) {
            performDeleteForever(ids);
        }
    });
}

function performDeleteForever(ids) {
    Swal.fire({
        title: 'Deleting...',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('../../ajax/delete_trash.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ trash_ids: ids })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted Permanently!',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Delete Failed', res.message || 'Error occurred.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Network error. Please try again.', 'error');
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
