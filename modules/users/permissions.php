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

// Defined Modules with Descriptions
$modules = [
    'clients'    => ['name' => 'Clients', 'icon' => 'fa-building', 'desc' => 'Manage client database, business parameters, and accounting links.'],
    'vendors'    => ['name' => 'Vendors', 'icon' => 'fa-truck-loading', 'desc' => 'Manage partners, vendors, printing rates, and purchase orders.'],
    'inventory'  => ['name' => 'Inventory', 'icon' => 'fa-map-marked-alt', 'desc' => 'Control outdoor media sites, pricing, specifications, and availability.'],
    'proposals'  => ['name' => 'Proposals', 'icon' => 'fa-file-contract', 'desc' => 'Draft campaign plans, media calculations, and proposals.'],
    'bookings'   => ['name' => 'Bookings', 'icon' => 'fa-calendar-check', 'desc' => 'Track confirmed campaigns, client orders, and performance details.'],
    'financials' => ['name' => 'Financials', 'icon' => 'fa-wallet', 'desc' => 'Oversee general ledgers, account balances, and PO approvals.'],
    'users'      => ['name' => 'Users & Staff', 'icon' => 'fa-users-cog', 'desc' => 'Manage organization staff directory and access credentials.']
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

<div class="card" style="padding: 2rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: none;">
    <!-- Header Area -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="color: #0f172a; margin: 0; display: inline-flex; align-items: center; gap: 10px; font-size: 1.5rem; font-weight: 800;">
                <i class="fas fa-shield-alt" style="color: var(--primary);"></i> Granular Role Permissions
            </h2>
            <p style="color: #64748b; margin: 0.25rem 0 0 0; font-size: 0.9rem;">Configure specific modules and action allowances for each core user group.</p>
        </div>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.6rem 1.25rem; border-radius: 12px; font-size: 0.85rem; font-weight: 700; color: #047857; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-crown"></i> Administrator has unlimited master access
        </div>
    </div>

    <form method="POST">
        <!-- Modern Custom Tabs -->
        <div class="tab-container">
            <button type="button" class="tab-btn active" onclick="switchRoleTab('manager')">
                <i class="fas fa-user-tie"></i> Manager Permissions
            </button>
            <button type="button" class="tab-btn" onclick="switchRoleTab('sales')">
                <i class="fas fa-handshake"></i> Sales Executive
            </button>
            <button type="button" class="tab-btn" onclick="switchRoleTab('staff')">
                <i class="fas fa-tools"></i> Operations & Staff
            </button>
        </div>

        <?php foreach ($roles as $rIdx => $r): ?>
        <!-- Tab Content for Role -->
        <div id="tab-content-<?php echo $r; ?>" class="role-tab-content <?php echo $rIdx === 0 ? 'active' : ''; ?>">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.9rem; color: #475569; font-weight: 600;">
                    <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 6px;"></i> 
                    Currently editing rights for the <strong style="text-transform: uppercase; color: #0f172a; font-weight: 800;"><?php echo $r; ?></strong> role.
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="toggleRoleAll('<?php echo $r; ?>', true)" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; font-weight: 800;">
                        <i class="fas fa-check-double"></i> Allow All
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="toggleRoleAll('<?php echo $r; ?>', false)" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; font-weight: 800; background: #fee2e2; color: #991b1b; border-color: #fca5a5;">
                        <i class="fas fa-ban"></i> Block All
                    </button>
                </div>
            </div>

            <table class="matrix-table">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 45%;">Module & Capabilities</th>
                        <th style="width: 13.75%;">View</th>
                        <th style="width: 13.75%;">Add</th>
                        <th style="width: 13.75%;">Edit</th>
                        <th style="width: 13.75%;">Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $key => $m): ?>
                    <tr>
                        <td style="text-align: left;">
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div class="module-icon-wrapper">
                                    <i class="fas <?php echo $m['icon']; ?>"></i>
                                </div>
                                <div>
                                    <h4 style="margin: 0; color: #1e293b; font-size: 0.95rem; font-weight: 700;"><?php echo $m['name']; ?></h4>
                                    <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: #64748b; line-height: 1.4;"><?php echo $m['desc']; ?></p>
                                </div>
                            </div>
                        </td>
                        <!-- Action Checkboxes Styled as Toggles -->
                        <td>
                            <label class="switch">
                                <input type="checkbox" class="perm-chk-<?php echo $r; ?>" name="perm[<?php echo $r; ?>][<?php echo $key; ?>][view]" value="1" <?php echo (!empty($perms[$r][$key]['can_view'])) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" class="perm-chk-<?php echo $r; ?>" name="perm[<?php echo $r; ?>][<?php echo $key; ?>][add]" value="1" <?php echo (!empty($perms[$r][$key]['can_add'])) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" class="perm-chk-<?php echo $r; ?>" name="perm[<?php echo $r; ?>][<?php echo $key; ?>][edit]" value="1" <?php echo (!empty($perms[$r][$key]['can_edit'])) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" class="perm-chk-<?php echo $r; ?>" name="perm[<?php echo $r; ?>][<?php echo $key; ?>][del]" value="1" <?php echo (!empty($perms[$r][$key]['can_delete'])) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <!-- Footer / Action Area -->
        <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.85rem; color: #475569; font-weight: 600;">
                <i class="fas fa-info-circle" style="color: var(--primary);"></i> Legend: Allow permissions to enable modules for managers, sales personnel, or ops team.
            </div>
            <button type="submit" name="save_permissions" class="btn btn-primary" style="padding: 0.8rem 2.25rem; font-weight: 800; font-size: 0.9rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(13,148,136,0.15); display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-save"></i> Save Permissions Matrix
            </button>
        </div>
    </form>
</div>

<style>
/* Clean Modern Tabs */
.tab-container {
    display: flex;
    gap: 0.5rem;
    border-bottom: 2px solid #f1f5f9;
    margin-bottom: 2rem;
    overflow-x: auto;
}
.tab-btn {
    padding: 1rem 1.75rem;
    font-weight: 800;
    font-size: 0.95rem;
    color: #64748b;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    outline: none;
}
.tab-btn:hover {
    color: var(--primary);
}
.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

/* Tab panes visibility */
.role-tab-content {
    display: none;
}
.role-tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Custom Styled Toggle Switches */
.switch { position: relative; display: inline-block; width: 46px; height: 22px; margin: 0 auto; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; }
input:checked + .slider { background-color: var(--primary); }
input:focus + .slider { box-shadow: 0 0 1px var(--primary); }
input:checked + .slider:before { transform: translateX(24px); }
.slider.round { border-radius: 34px; }
.slider.round:before { border-radius: 50%; }

/* Matrix Table Styling */
.matrix-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}
.matrix-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem;
    border-bottom: 1.5px solid #e2e8f0;
    text-align: center;
}
.matrix-table td {
    padding: 1.25rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    text-align: center;
    background: #fff;
}
.matrix-table tr:hover td {
    background: #f8fafc;
}

/* Icon Containers */
.module-icon-wrapper {
    background: #f1f5f9;
    color: #475569;
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.matrix-table tr:hover .module-icon-wrapper {
    background: #e2e8f0;
    color: var(--primary);
}
</style>

<script>
// Role switching utility
function switchRoleTab(role) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.role-tab-content').forEach(pane => pane.classList.remove('active'));
    
    // Find active button to toggle
    event.currentTarget.classList.add('active');
    document.getElementById('tab-content-' + role).classList.add('active');
}

// Global Allow All / Block All toggling per tab
function toggleRoleAll(role, status) {
    document.querySelectorAll('.perm-chk-' + role).forEach(chk => {
        chk.checked = status;
    });
}

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
    Swal.fire({
        icon: 'success',
        title: 'Permissions Matrix Saved!',
        text: 'Access privileges have been successfully synchronized.',
        timer: 1800,
        showConfirmButton: false
    });
<?php endif; ?>
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
