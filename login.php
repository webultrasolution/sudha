<?php
session_start();
include_once __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'inactive') {
    $error = 'Your account has been deactivated. Please contact the administrator.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if (($user['status'] ?? 'active') === 'inactive') {
            $error = 'Your account has been deactivated. Please contact the administrator.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Suudha Creative & Advertising CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
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

        /* Decorative animated blobs */
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
            margin-bottom: 2.5rem;
        }

        .login-header img {
            max-width: 220px;
            height: auto;
            margin-bottom: 1rem;
            border-radius: 4px;
            transition: transform 0.3s;
        }

        .login-header img:hover {
            transform: scale(1.02);
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

        .alert-error i {
            font-size: 1.1rem;
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
            transition: color 0.2s;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: var(--text-dark);
            transition: all 0.2s;
            box-sizing: border-box;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--primary);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(123, 22, 28, 0.08);
        }

        .form-control:focus + i {
            color: var(--primary);
        }

        .login-btn {
            width: 100%;
            padding: 0.95rem;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            border: none;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(123, 22, 28, 0.2);
            transition: all 0.2s;
            margin-top: 2rem;
        }

        .login-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(123, 22, 28, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
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
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-header">
            <img src="assets/images/sudha_logo.jpg" alt="Suudha Creative & Advertising">
            <h2>SUUDHA CREATIVE & ADVERTISING</h2>
            <p>OOH Management CRM Portal</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                    <i class="fas fa-user"></i>
                </div>
            </div>
            
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <label class="form-label" style="margin-bottom: 0;">Password</label>
                    <a href="forgot_password.php" style="font-size: 0.8rem; color: var(--primary); text-decoration: none; font-weight: 600; font-family: 'Outfit', sans-serif;">Forgot Password?</a>
                </div>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <i class="fas fa-lock"></i>
                </div>
            </div>

            <button type="submit" class="login-btn">
                <span>Sign In to CRM</span>
                <i class="fas fa-arrow-right-long"></i>
            </button>
        </form>
        
        <a href="index.php" class="back-to-web">
            <i class="fas fa-arrow-left"></i> Back to Homepage
        </a>
        
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Suudha Creative. CRM Workspace v2.5
        </div>
    </div>
</body>
</html>
