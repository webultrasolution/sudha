<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
requireRole('admin');

// Robust Migration: Ensure all columns exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY)");
    
    // Add columns one by one if they don't exist
    $columns = [
        "name" => "VARCHAR(100) NOT NULL AFTER id",
        "username" => "VARCHAR(50) NOT NULL UNIQUE AFTER name",
        "password" => "VARCHAR(255) NOT NULL AFTER username",
        "role" => "ENUM('admin', 'manager', 'sales', 'staff') DEFAULT 'staff' AFTER password",
        "status" => "ENUM('active', 'inactive') DEFAULT 'active' AFTER role",
        "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status"
    ];

    foreach ($columns as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE '$col'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE users ADD $col $definition");
        }
    }
} catch (Exception $e) {
    // Table might be being created for the first time or altered
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = clean($_POST['name']);
    $username = clean($_POST['username']);
    $role = clean($_POST['role']);
    $status = clean($_POST['status'] ?? 'active');

    if ($_POST['action'] === 'add') {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $username, $password, $role, $status]);
        header("Location: index.php?msg=added"); exit;
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name=?, username=?, password=?, role=?, status=? WHERE id=?");
            $stmt->execute([$name, $username, $password, $role, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name=?, username=?, role=?, status=? WHERE id=?");
            $stmt->execute([$name, $username, $role, $status, $id]);
        }
        header("Location: index.php?msg=updated"); exit;
    }
}

$activePage = 'users';
$pageTitle = 'User Management';
include_once __DIR__ . '/../../includes/header.php';

$users = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">System Users & Roles</h2>
        <button class="btn btn-primary" onclick="openUserModal()">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>User Details</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div style="font-weight: 700; color: var(--primary);"><?php echo $u['name']; ?></div>
                    <small style="color: #94a3b8;">Created: <?php echo date('d M Y', strtotime($u['created_at'])); ?></small>
                </td>
                <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?php echo $u['username']; ?></code></td>
                <td>
                    <span style="text-transform: capitalize; font-weight: 600; color: #475569;">
                        <i class="fas <?php echo $u['role'] === 'admin' ? 'fa-shield-alt' : 'fa-user'; ?>" style="margin-right: 5px; opacity: 0.5;"></i>
                        <?php echo $u['role']; ?>
                    </span>
                </td>
                <td>
                    <span class="status-pill <?php echo $u['status']; ?>" style="background: <?php echo $u['status'] === 'active' ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $u['status'] === 'active' ? '#166534' : '#475569'; ?>; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">
                        <?php echo strtoupper($u['status']); ?>
                    </span>
                </td>
                <td style="text-align: right;">
                    <button class="btn-icon" onclick='editUser(<?php echo json_encode($u); ?>)' style="color: var(--primary);"><i class="fas fa-edit"></i></button>
                    <?php if($u['username'] !== 'admin'): ?>
                        <button class="btn-icon" style="color: #ef4444;" onclick="deleteUser(<?php echo $u['id']; ?>)"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="modalTitle">System User</h2>
            <span class="close" onclick="closeUserModal()">&times;</span>
        </div>
        <form method="POST" id="userForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="userId">
            
            <div class="form-group"><label>Full Name</label><input type="text" name="name" id="f_name" required></div>
            <div class="form-group"><label>Username (Login ID)</label><input type="text" name="username" id="f_username" required></div>
            <div class="form-group">
                <label>Password <small id="pw_hint" style="display:none; color: #94a3b8;">(Leave blank to keep current)</small></label>
                <input type="password" name="password" id="f_password">
            </div>
            <div class="form-group">
                <label>System Role</label>
                <select name="role" id="f_role" required>
                    <option value="staff">Staff (Basic Access)</option>
                    <option value="sales">Sales (Proposals & Inventory)</option>
                    <option value="manager">Manager (Operations)</option>
                    <option value="admin">Administrator (Full Access)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Account Status</label>
                <select name="status" id="f_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div style="margin-top: 2rem; text-align: right;">
                <button type="button" class="btn" onclick="closeUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save User Account</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
.modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 12px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; color: #475569; }
.form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 1rem; }
</style>

<script>
function openUserModal() {
    document.getElementById('userForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').innerText = 'Add New User';
    document.getElementById('pw_hint').style.display = 'none';
    document.getElementById('f_password').required = true;
    document.getElementById('userModal').style.display = 'block';
}

function closeUserModal() { document.getElementById('userModal').style.display = 'none'; }

function editUser(u) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('userId').value = u.id;
    document.getElementById('f_name').value = u.name;
    document.getElementById('f_username').value = u.username;
    document.getElementById('f_role').value = u.role;
    document.getElementById('f_status').value = u.status;
    document.getElementById('f_password').required = false;
    document.getElementById('pw_hint').style.display = 'inline';
    document.getElementById('modalTitle').innerText = 'Edit User Account';
    document.getElementById('userModal').style.display = 'block';
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
