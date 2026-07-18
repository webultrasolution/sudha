<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
requirePermission('users', 'view');

// Robust Migration: Ensure all columns exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY)");
    
    // Add columns one by one if they don't exist
    $columns = [
        "name" => "VARCHAR(100) NOT NULL AFTER id",
        "username" => "VARCHAR(50) NOT NULL UNIQUE AFTER name",
        "email" => "VARCHAR(150) NULL AFTER username",
        "password" => "VARCHAR(255) NOT NULL AFTER email",
        "role" => "ENUM('admin', 'manager', 'sales', 'staff') DEFAULT 'staff' AFTER password",
        "status" => "ENUM('active', 'inactive') DEFAULT 'active' AFTER role",
        "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status"
    ];

    foreach ($columns as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE users ADD $col $definition");
        } else {
            if ($col === 'role') {
                $type = strtolower($check['Type']);
                if (strpos($type, 'operations') !== false || strpos($type, 'accounts') !== false) {
                    $pdo->exec("UPDATE users SET role = 'staff' WHERE role = 'operations' OR role = '' OR role IS NULL");
                    $pdo->exec("UPDATE users SET role = 'manager' WHERE role = 'accounts'");
                    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'sales', 'staff') DEFAULT 'staff'");
                }
            }
        }
    }
} catch (Exception $e) {
    // Table might be being created for the first time or altered
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $name = clean($_POST['name']);
        $username = clean($_POST['username']);
        $role = clean($_POST['role']);
        // Map deprecated or invalid role values to synchronized ENUM values
        if ($role === 'operations') {
            $role = 'staff';
        } elseif ($role === 'accounts') {
            $role = 'manager';
        } elseif (!in_array($role, ['admin', 'manager', 'sales', 'staff'])) {
            $role = 'staff';
        }
        
        $status = strtolower(clean($_POST['status'] ?? 'active'));
        if (!in_array($status, ['active', 'inactive'])) {
            $status = 'active';
        }

        if ($_POST['action'] === 'add') {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $email = !empty($_POST['email']) ? clean($_POST['email']) : null;
            $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $username, $email, $password, $role, $status]);
            header("Location: index.php?msg=added"); exit;
        } elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $email = !empty($_POST['email']) ? clean($_POST['email']) : null;
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name=?, username=?, email=?, password=?, role=?, status=? WHERE id=?");
                $stmt->execute([$name, $username, $email, $password, $role, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name=?, username=?, email=?, role=?, status=? WHERE id=?");
                $stmt->execute([$name, $username, $email, $role, $status, $id]);
            }
            header("Location: index.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
        header('Content-Type: application/json');
        try {
            $id = intval($_POST['id']);
            
            // Prevent deleting admin
            $stmtCheck = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmtCheck->execute([$id]);
            $uname = $stmtCheck->fetchColumn();
            
            if ($uname === 'admin') {
                echo json_encode(['success' => false, 'message' => 'Administrator account cannot be deleted.']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'This user cannot be deleted because they are referenced in other records (such as bookings or activity logs). You can deactivate their account instead.']);
            exit;
        }
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
        <?php if (canAdd('users')): ?>
        <button class="btn btn-primary" onclick="openUserModal()">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
        <?php endif; ?>
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
                    <?php if(!empty($u['email'])): ?>
                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;"><i class="fas fa-envelope" style="opacity: 0.5; margin-right: 3px;"></i> <?php echo htmlspecialchars($u['email']); ?></div>
                    <?php endif; ?>
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
                    <?php if (canEdit('users')): ?>
                    <button class="btn-icon" onclick='editUser(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)' style="color: var(--primary);"><i class="fas fa-edit"></i></button>
                    <?php endif; ?>
                    <?php if($u['username'] !== 'admin' && canDelete('users')): ?>
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
            <div class="form-group"><label>Email Address</label><input type="email" name="email" id="f_email"></div>
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
    document.getElementById('f_email').value = u.email || '';
    document.getElementById('f_role').value = u.role;
    document.getElementById('f_status').value = u.status;
    document.getElementById('f_password').required = false;
    document.getElementById('pw_hint').style.display = 'inline';
    document.getElementById('modalTitle').innerText = 'Edit User Account';
    document.getElementById('userModal').style.display = 'block';
}

function deleteUser(id) {
    Swal.fire({
        title: 'Delete User?',
        text: "Are you sure you want to delete this user account? This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(r => r.text().then(text => {
                try {
                    const res = JSON.parse(text);
                    if (res.success) {
                        Swal.fire('Deleted!', 'User account has been deleted.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                } catch(e) {
                    console.error("Server raw response:", text);
                    Swal.fire('Error', 'Server response invalid: ' + text.substring(0, 200), 'error');
                }
            })).catch(err => {
                Swal.fire('Error', 'Something went wrong: ' + err.message, 'error');
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
