<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Fetch currently logged in user info
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $username = clean($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($name)) {
        $error = 'Name cannot be empty.';
    } elseif (empty($username)) {
        $error = 'Username cannot be empty.';
    } else {
        // Check if username is already taken by another user
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $checkStmt->execute([$username, $userId]);
        $usernameExists = $checkStmt->fetchColumn() > 0;

        if ($usernameExists) {
            $error = 'Username is already taken.';
        } else {
            $email = clean($_POST['email'] ?? '');
            // Check if password change is requested
            $passwordUpdated = false;
            if (!empty($newPassword)) {
                if ($newPassword !== $confirmPassword) {
                    $error = 'Passwords do not match.';
                } elseif (strlen($newPassword) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, password = ? WHERE id = ?");
                    $updateStmt->execute([$name, $username, $email, $hashedPassword, $userId]);
                    $passwordUpdated = true;
                }
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ? WHERE id = ?");
                $updateStmt->execute([$name, $username, $email, $userId]);
            }

            if (empty($error)) {
                // Update session variables
                $_SESSION['user_name'] = $name;
                $success = 'Profile updated successfully!';
                
                // Refetch updated user data
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            }
        }
    }
}

$activePage = 'profile';
$pageTitle = 'Profile Management';
include_once __DIR__ . '/../../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto; padding: 1.5rem 0;">
    <div class="card" style="box-shadow: 0 4px 20px -2px rgba(0,0,0,0.08); border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; background: #fff;">
        <div style="background: linear-gradient(135deg, var(--primary) 0%, #a82c35 100%); padding: 2rem; text-align: center; color: #fff; position: relative;">
            <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2.5rem; border: 2px solid rgba(255,255,255,0.8); box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                <i class="fas fa-user-circle"></i>
            </div>
            <h2 style="margin: 0; font-size: 1.5rem; font-weight: 700;"><?php echo htmlspecialchars($user['name']); ?></h2>
            <p style="margin: 5px 0 0; opacity: 0.8; font-size: 0.9rem; text-transform: uppercase; font-weight: 600; letter-spacing: 1px;">
                <i class="fas <?php echo $user['role'] === 'admin' ? 'fa-shield-alt' : 'fa-user-tag'; ?>" style="margin-right: 5px;"></i>
                Role: <?php echo htmlspecialchars($user['role']); ?>
            </p>
        </div>

        <div style="padding: 2rem;">
            <?php if (!empty($error)): ?>
                <div style="background: #fef2f2; color: #b91c1c; border-left: 4px solid #ef4444; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 500; font-size: 0.9rem;">
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div style="background: #ecfdf5; color: #047857; border-left: 4px solid #10b981; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 500; font-size: 0.9rem;">
                    <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.5rem; font-size: 0.9rem;">Full Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #1e293b; box-sizing: border-box;" placeholder="Enter your full name">
                </div>

                <div class="form-group">
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.5rem; font-size: 0.9rem;">Username / Login ID <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #1e293b; box-sizing: border-box;" placeholder="Enter username">
                </div>

                <div class="form-group">
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.5rem; font-size: 0.9rem;">Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #1e293b; box-sizing: border-box;" placeholder="Enter email address">
                </div>

                <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; margin-top: 0.5rem;">
                    <h3 style="margin: 0 0 1rem; font-size: 1rem; color: #0f172a; font-weight: 700;"><i class="fas fa-lock" style="margin-right: 8px; color: #64748b;"></i>Change Password</h3>
                    <p style="margin: 0 0 1.25rem; font-size: 0.8rem; color: #64748b;">Leave password fields blank if you do not want to change your current password.</p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.5rem; font-size: 0.9rem;">New Password</label>
                            <input type="password" name="new_password" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #1e293b; box-sizing: border-box;" placeholder="Min 6 characters">
                        </div>

                        <div class="form-group">
                            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.5rem; font-size: 0.9rem;">Confirm New Password</label>
                            <input type="password" name="confirm_password" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: #1e293b; box-sizing: border-box;" placeholder="Re-enter new password">
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1rem; border-top: 1px solid #f1f5f9; padding-top: 1.5rem;">
                    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-secondary" style="border: none; background: #e2e8f0; border-radius: 8px; padding: 0.75rem 1.5rem; display: flex; align-items: center; justify-content: center; color: #475569; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none; border-radius: 8px; padding: 0.75rem 2rem; color: white; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background 0.2s;">
                        <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
