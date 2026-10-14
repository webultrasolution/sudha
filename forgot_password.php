<?php
session_start();
include_once __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = clean($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Query user (both active and inactive)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if (($user['status'] ?? 'active') === 'inactive') {
                $error = 'Your account has been deactivated. Please contact the administrator.';
            } else {
                // Generate secure reset token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Update user in DB
                $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $updateStmt->execute([$token, $expiry, $user['id']]);

                $resetLink = BASE_URL . "reset_password.php?token=" . $token;

                // Email body template
                $subject = 'CRM Password Reset Request - Suudha Creative';
                $message = "
                <html>
                <head>
                    <title>Password Reset Request</title>
                    <style>
                        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; }
                        .header { text-align: center; margin-bottom: 25px; }
                        .header img { max-width: 150px; border-radius: 4px; }
                        .btn-container { text-align: center; margin: 30px 0; }
                        .btn-reset { background-color: #7b161c; color: #ffffff !important; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px rgba(123, 22, 28, 0.15); }
                        .footer { font-size: 0.8rem; color: #64748b; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 15px; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Suudha Creative & Advertising</h2>
                            <p style='color: #64748b; margin: 0;'>OOH Management CRM Portal</p>
                        </div>
                        <p>Dear <strong>" . htmlspecialchars($user['name'] ?: $user['username']) . "</strong>,</p>
                        <p>We received a request to reset your password for the Suudha Creative CRM Portal.</p>
                        <p>Please click the button below to create a new password:</p>
                        <div class='btn-container'>
                            <a class='btn-reset' href='" . htmlspecialchars($resetLink) . "'>Reset Password</a>
                        </div>
                        <p style='font-size: 0.85rem; color: #64748b;'>This link is valid for 1 hour. If the button doesn't work, copy and paste this URL into your browser:</p>
                        <p style='font-size: 0.85rem; word-break: break-all;'><a href='" . htmlspecialchars($resetLink) . "'>" . htmlspecialchars($resetLink) . "</a></p>
                        <p>If you did not request this password reset, you can safely ignore this email.</p>
                        <div class='footer'>
                            &copy; " . date('Y') . " Suudha Creative. All rights reserved.
                        </div>
                    </div>
                </body>
                </html>
                ";

                if (sendSystemEmail($email, $subject, $message)) {
                    $success = 'A password reset link has been sent to your registered email address. Please check your inbox and spam folder.';
                } else {
                    $error = 'Failed to send reset email. Please contact the system administrator.';
                }
            }
        } else {
            $error = 'User not available';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Suudha Creative & Advertising CRM</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #7b161c;
            --primary-hover: #9e232b;
            --accent: #fca61a;
            --text-dark: #0f172a;
            --text-muted: #475569;
        }

        body.login-page {
            background: 
                radial-gradient(circle at 15% 15%, rgba(252, 166, 26, 0.06) 0%, rgba(255, 255, 255, 0) 50%),
                radial-gradient(circle at 85% 85%, rgba(123, 22, 28, 0.06) 0%, rgba(255, 255, 255, 0) 50%),
                linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        body.login-page::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(252, 166, 26, 0.08);
            filter: blur(80px);
            top: -50px;
            left: -50px;
            z-index: 1;
        }

        body.login-page::after {
            content: '';
            position: absolute;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            background: rgba(123, 22, 28, 0.08);
            filter: blur(100px);
            bottom: -50px;
            right: -50px;
            z-index: 1;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 24px;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.03), 
                0 20px 25px -5px rgba(123, 22, 28, 0.06), 
                0 10px 10px -5px rgba(123, 22, 28, 0.03);
            width: 100%;
            max-width: 440px;
            padding: 3rem 2.5rem;
            z-index: 10;
            position: relative;
            box-sizing: border-box;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header img {
            max-width: 220px;
            height: auto;
            margin-bottom: 1rem;
            border-radius: 4px;
        }

        .login-header h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            letter-spacing: 0.5px;
        }

        .login-header p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
            font-weight: 500;
        }

        .alert-error {
            background-color: #fee2e2;
            border: 1.5px solid #fca5a5;
            color: #991b1b;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .alert-success {
            background-color: #f0fdf4;
            border: 1.5px solid #bbf7d0;
            color: #166534;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group i {
            position: absolute;
            left: 1.1rem;
            color: #94a3b8;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            font-size: 0.95rem;
            color: var(--text-dark);
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            transition: all 0.2s ease-in-out;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(123, 22, 28, 0.1);
            background: #ffffff;
        }

        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, #9e232b 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 0.9rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: 0 4px 12px rgba(123, 22, 28, 0.25);
            transition: all 0.2s ease-in-out;
            margin-top: 1rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(123, 22, 28, 0.35);
        }

        .back-to-web {
            display: block;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-to-web:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 500;
            border-top: 1px solid rgba(226, 232, 240, 0.5);
            padding-top: 1.5rem;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-header">
            <img src="assets/images/sudha_logo.jpg" alt="Suudha Creative & Advertising">
            <h2>PASSWORD RECOVERY</h2>
            <p>Enter your email to receive a temporary login password</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success">
                <i class="fas fa-circle-check"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <input type="email" name="email" class="form-control" placeholder="yourname@domain.com" required autofocus>
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <span>Send Temporary Password</span>
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        <?php endif; ?>
        
        <a href="login.php" class="back-to-web">
            <i class="fas fa-arrow-left"></i> Back to Login Page
        </a>
        
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Suudha Creative. CRM Workspace v2.5
        </div>
    </div>
</body>
</html>
