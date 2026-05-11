<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$canManage = hasRole(['admin', 'operations']);

// Handle Add/Edit/Delete via POST - MUST BE BEFORE HEADER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $canManage) {
    if (isset($_POST['action'])) {
        $successMsg = "";
        if ($_POST['action'] == 'add') {
            $username = clean($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $fullname = clean($_POST['full_name']);
            $email = clean($_POST['email']);
            $role = clean($_POST['role']);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $fullname, $email, $role]);
            $successMsg = "Staff member added successfully!";
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $username = clean($_POST['username']);
            $fullname = clean($_POST['full_name']);
            $email = clean($_POST['email']);
            $role = clean($_POST['role']);
            
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $password, $fullname, $email, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $fullname, $email, $role, $id]);
            }
            $successMsg = "Staff details updated successfully!";
        } elseif ($_POST['action'] == 'delete') {
            header('Content-Type: application/json');
            $id = intval($_POST['id']);
            if ($id != $_SESSION['user_id']) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    echo json_encode(['success' => true]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'This staff account cannot be deleted as they have recorded history in the system.']);
                }
                exit;
            }
        }
        
        if ($successMsg) {
            header("Location: users.php?msg=" . urlencode($successMsg));
            exit;
        }
    }
}

$activePage = 'users';
$pageTitle = 'Staff Management';
include_once __DIR__ . '/../../includes/header.php';

// Pagination Logic
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

$users = $pdo->prepare("SELECT * FROM users ORDER BY id ASC LIMIT ? OFFSET ?");
$users->execute([$limit, $offset]);
$users = $users->fetchAll();
?>

<?php if (isset($_GET['msg'])): ?>
<script>
    window.onload = () => {
        Swal.fire({
            title: 'Success!',
            text: '<?php echo $_GET['msg']; ?>',
            icon: 'success',
            confirmButtonColor: '#1CADA9'
        });
    }
</script>
<?php endif; ?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;"><i class="fas fa-user-shield"></i> CRM Users & Permissions</h2>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <i class="fas fa-plus"></i> Add New Staff
        </button>
        <?php endif; ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Created At</th>
                <?php if ($canManage): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sn = $offset + 1;
            foreach ($users as $user): ?>
            <tr>
                <td><?php echo $sn++; ?></td>
                <td>
                    <strong><?php echo $user['full_name']; ?></strong><br>
                    <small style="color: var(--secondary);"><?php echo $user['email']; ?></small>
                </td>
                <td><?php echo $user['username']; ?></td>
                <td>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </td>
                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                <?php if ($canManage): ?>
                <td>
                    <button class="btn-icon btn-edit" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <button type="button" class="btn-icon btn-delete" onclick="deleteUser(event, <?php echo $user['id']; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo renderPagination($page, $totalPages, 'users.php'); ?>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <h3>Add New Staff Member</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" class="form-control" required>
                    <option value="sales">Sales</option>
                    <option value="operations">Operations</option>
                    <option value="accounts">Accounts</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Staff Member</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Change Password (leave blank to keep current)</label>
                <input type="password" name="password" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" id="edit_role" class="form-control" required>
                    <option value="sales">Sales</option>
                    <option value="operations">Operations</option>
                    <option value="accounts">Accounts</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<style>
.role-badge {
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.role-admin { background: #fee2e2; color: #991b1b; }
.role-sales { background: #e0f2fe; color: #0369a1; }
.role-operations { background: #fef9c3; color: #854d0e; }
.role-accounts { background: #dcfce7; color: #166534; }

.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 500px; }
.btn-icon { background: none; border: none; cursor: pointer; color: var(--secondary); font-size: 1rem; padding: 0.25rem; transition: color 0.2s; }
.btn-icon:hover { color: var(--primary); }
</style>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_role').value = user.role;
    openModal('editModal');
}

function deleteUser(e, id) {
    if(e) e.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: "This user account will be permanently deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'User account has been removed.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Unable to Delete', data.message, 'error');
                }
            })
            .catch(err => {
                console.error('Delete Error:', err);
                Swal.fire('Error', 'An unexpected error occurred.', 'error');
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
