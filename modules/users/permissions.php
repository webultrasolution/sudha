<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
requireRole('admin');

// Table Creation (Migration with Granular Permissions)
$pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'manager', 'sales', 'staff') NOT NULL,
    module_key VARCHAR(50) NOT NULL,
    can_view TINYINT(1) DEFAULT 0,
    can_add TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    UNIQUE KEY (role, module_key)
)");

// Defined Modules
$modules = [
    'clients' => ['name' => 'Clients', 'icon' => 'fa-building'],
    'vendors' => ['name' => 'Vendors', 'icon' => 'fa-truck-loading'],
    'inventory' => ['name' => 'Inventory', 'icon' => 'fa-map-marked-alt'],
    'proposals' => ['name' => 'Proposals', 'icon' => 'fa-file-contract'],
    'bookings' => ['name' => 'Bookings', 'icon' => 'fa-calendar-check'],
    'financials' => ['name' => 'Financials', 'icon' => 'fa-wallet'],
    'users' => ['name' => 'Users', 'icon' => 'fa-users-cog']
];

$roles = ['manager', 'sales', 'staff']; // Admin has all by default

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    foreach ($roles as $role) {
        foreach ($modules as $key => $m) {
            $view = isset($_POST['perm'][$role][$key]['view']) ? 1 : 0;
            $add = isset($_POST['perm'][$role][$key]['add']) ? 1 : 0;
            $edit = isset($_POST['perm'][$role][$key]['edit']) ? 1 : 0;
            $del = isset($_POST['perm'][$role][$key]['del']) ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role, module_key, can_view, can_add, can_edit, can_delete) 
                                    VALUES (?, ?, ?, ?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE can_view = ?, can_add = ?, can_edit = ?, can_delete = ?");
            $stmt->execute([$role, $key, $view, $add, $edit, $del, $view, $add, $edit, $del]);
        }
    }
    header("Location: permissions.php?msg=saved"); exit;
}

// Fetch Existing
$perms = [];
$stmt = $pdo->query("SELECT * FROM role_permissions");
while ($row = $stmt->fetch()) {
    $perms[$row['role']][$row['module_key']] = $row;
}

$activePage = 'users';
$pageTitle = 'Detailed Permission Matrix';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
        <div>
            <h2 style="color: #1e293b;"><i class="fas fa-key" style="color: var(--primary);"></i> Granular Access Control</h2>
            <p style="color: #64748b;">Set specific permissions for View, Add, Edit and Delete per module.</p>
        </div>
        <div style="background: #f1f5f9; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.8rem; font-weight: 600; color: #475569;">
            <i class="fas fa-shield-alt"></i> Administrator has Full Access
        </div>
    </div>

    <form method="POST">
        <table class="table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead style="background: #f8fafc;">
                <tr>
                    <th rowspan="2" style="width: 250px; border-bottom: 1px solid #e2e8f0;">Module / Function</th>
                    <?php foreach ($roles as $r): ?>
                        <th colspan="4" style="text-align: center; text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem; border-left: 1px solid #e2e8f0; background: #fff;"><?php echo $r; ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr style="font-size: 0.7rem; text-align: center; color: #94a3b8;">
                    <?php foreach ($roles as $r): ?>
                        <th style="border-left: 1px solid #f1f5f9; padding: 5px;">V</th>
                        <th style="padding: 5px;">A</th>
                        <th style="padding: 5px;">E</th>
                        <th style="padding: 5px;">D</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $key => $m): ?>
                <tr>
                    <td style="font-weight: 700; color: #334155;">
                        <i class="fas <?php echo $m['icon']; ?>" style="width: 20px; color: #94a3b8; margin-right: 8px;"></i>
                        <?php echo $m['name']; ?>
                    </td>
                    <?php foreach ($roles as $r): ?>
                        <td style="text-align: center; border-left: 1px solid #f1f5f9; background: <?php echo ($r == 'staff' ? '#fafafa' : '#fff'); ?>;">
                            <input type="checkbox" name="perm[<?php echo $r; ?>][<?php echo $key; ?>][view]" value="1" <?php echo (!empty($perms[$r][$key]['can_view'])) ? 'checked' : ''; ?>>
                        </td>
                        <td style="text-align: center; background: <?php echo ($r == 'staff' ? '#fafafa' : '#fff'); ?>;">
                            <input type="checkbox" name="perm[<?php echo $r; ?>][<?php echo $key; ?>][add]" value="1" <?php echo (!empty($perms[$r][$key]['can_add'])) ? 'checked' : ''; ?>>
                        </td>
                        <td style="text-align: center; background: <?php echo ($r == 'staff' ? '#fafafa' : '#fff'); ?>;">
                            <input type="checkbox" name="perm[<?php echo $r; ?>][<?php echo $key; ?>][edit]" value="1" <?php echo (!empty($perms[$r][$key]['can_edit'])) ? 'checked' : ''; ?>>
                        </td>
                        <td style="text-align: center; background: <?php echo ($r == 'staff' ? '#fafafa' : '#fff'); ?>;">
                            <input type="checkbox" name="perm[<?php echo $r; ?>][<?php echo $key; ?>][del]" value="1" <?php echo (!empty($perms[$r][$key]['can_delete'])) ? 'checked' : ''; ?>>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px;">
            <div style="font-size: 0.85rem; color: #92400e;">
                <i class="fas fa-lightbulb"></i> <strong>Legend:</strong> V = View, A = Add, E = Edit, D = Delete
            </div>
            <button type="submit" name="save_permissions" class="btn btn-primary" style="padding: 0.8rem 2rem;">
                <i class="fas fa-save"></i> Save Permissions Matrix
            </button>
        </div>
    </form>
</div>


<style>
/* Custom Toggle Switch */
.switch { position: relative; display: inline-block; width: 50px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; }
input:checked + .slider { background-color: var(--primary); }
input:focus + .slider { box-shadow: 0 0 1px var(--primary); }
input:checked + .slider:before { transform: translateX(26px); }
.slider.round { border-radius: 34px; }
.slider.round:before { border-radius: 50%; }
input:disabled + .slider { background-color: #e2e8f0; cursor: not-allowed; opacity: 0.6; }
</style>

<script>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
    Swal.fire('Updated!', 'Permissions have been saved successfully.', 'success');
<?php endif; ?>
</script>

<?php include_once __DIR__ . '/../../includes/header.php'; ?>
