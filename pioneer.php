<?php
include_once __DIR__ . '/config/db.php';

// Dynamically create contact_leads table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_leads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            subject VARCHAR(255),
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    // Fail silently to avoid breaking page render
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : '';
    $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $phone = isset($_POST['phone']) ? strip_tags(trim($_POST['phone'])) : '';
    $subject = isset($_POST['subject']) ? strip_tags(trim($_POST['subject'])) : '';
    $message = isset($_POST['message']) ? strip_tags(trim($_POST['message'])) : '';

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill out all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_leads (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $subject, $message]);
            $success = true;
        } catch (Exception $e) {
            $error = 'Unable to send message. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pioneer Publicity | Out-of-Home & Experiential Advertising</title>
    <meta name="description" content="Pioneer Publicity is a premier outdoor advertising and experiential media agency in India, offering innovative and path-breaking OOH solutions.">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #090d16;
            --bg-card: rgba(17, 24, 39, 0.65);
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --accent: #f59e0b;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --font-primary: 'Outfit', sans-serif;
            --font-secondary: 'Inter', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            font-family: var(--font-secondary);
            overflow-x: hidden;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-primary);
            font-weight: 700;
        }

        /* Glassmorphic Background Blobs */
        .glow-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            pointer-events: none;
            opacity: 0.45;
        }

        .blob-1 {
            background: var(--primary);
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
        }

        .blob-2 {
            background: #8b5cf6;
            width: 350px;
            height: 350px;
            bottom: 10%;
            left: -100px;
        }

        .blob-3 {
            background: var(--accent);
            width: 300px;
            height: 300px;
            top: 45%;
            right: 10%;
            opacity: 0.2;
        }

        /* Wrapper to keep content on top of blobs */
        .page-content {
            position: relative;
            z-index: 10;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-dark);
        }
        ::-webkit-scrollbar-thumb {
            background: #1f2937;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* ── HEADER & NAVIGATION ── */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 1.25rem 2rem;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        header.scrolled {
            background: rgba(9, 13, 22, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 2rem;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: var(--font-primary);
            font-size: 1.65rem;
            font-weight: 900;
            color: var(--text-light);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }

        .logo i {
            color: var(--primary);
            font-size: 1.8rem;
            filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.6));
        }

        .logo span {
            color: var(--primary);
        }

        nav {
            display: flex;
            align-items: center;
            gap: 2.2rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            padding: 5px 0;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--text-light);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .login-btn {
            background: rgba(99, 102, 241, 0.12);
            color: var(--primary);
            text-decoration: none;
            padding: 0.65rem 1.4rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid rgba(99, 102, 241, 0.4);
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .mobile-menu-btn {
            display: none;
            background: transparent;
            border: none;
            color: var(--text-light);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* ── HERO SECTION ── */
        .hero {
            position: relative;
            min-height: 100vh;
            padding: 9rem 2rem 5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content {
            animation: fadeInUp 1s ease-out;
        }

        .tagline {
            display: inline-block;
            background: var(--primary-glow);
            color: var(--primary);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .hero h1 {
            font-size: 4rem;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #fff 30%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }

        .hero p {
            font-size: 1.15rem;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
            line-height: 1.7;
            max-width: 540px;
        }

        .hero-actions {
            display: flex;
            gap: 1.25rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.9rem 2rem;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeInRight 1s ease-out;
        }

        .visual-frame {
            position: relative;
            width: 100%;
            max-width: 480px;
            border-radius: 24px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            padding: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .visual-frame img {
            width: 100%;
            border-radius: 16px;
            display: block;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .floating-badge {
            position: absolute;
            background: rgba(9, 13, 22, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .badge-1 {
            bottom: -20px;
            left: -20px;
            animation: float 4s ease-in-out infinite;
        }

        .badge-2 {
            top: -20px;
            right: -20px;
            animation: float 4s ease-in-out infinite 2s;
        }

        .badge-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-glow);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .badge-text span {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .badge-text strong {
            font-size: 0.95rem;
            font-weight: 800;
        }

        /* ── SECTION CONFIGS ── */
        section {
            padding: 6.5rem 2rem;
            position: relative;
        }

        .section-header {
            max-width: 700px;
            margin: 0 auto 4.5rem;
            text-align: center;
        }

        .section-header span {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
            display: block;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #fff, var(--text-muted));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        /* ── THE PILLARS (VISION) ── */
        .pillars-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .pillar-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem;
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .pillar-card:hover {
            transform: translateY(-5px);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
        }

        .pillar-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .pillar-card:hover::before {
            opacity: 1;
        }

        .pillar-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            background: var(--primary-glow);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.75rem;
        }

        .pillar-card h3 {
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .pillar-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* ── DIVERSIFICATION ── */
        .div-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2.5rem;
        }

        .div-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 3rem;
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .div-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            transform: translateY(-3px);
        }

        .div-card h3 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
            color: var(--text-light);
        }

        .div-card p {
            color: var(--text-muted);
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 2.5rem;
        }

        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            margin-bottom: 2.5rem;
        }

        .feature-list li {
            font-size: 0.95rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-list i {
            color: var(--primary);
        }

        /* ── PORTFOLIO ── */
        .portfolio-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2.5rem;
        }

        .portfolio-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            overflow: hidden;
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
        }

        .portfolio-card:hover {
            transform: translateY(-5px);
            border-color: rgba(99, 102, 241, 0.25);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }

        .portfolio-img {
            width: 100%;
            height: 280px;
            position: relative;
            overflow: hidden;
        }

        .portfolio-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .portfolio-card:hover .portfolio-img img {
            transform: scale(1.05);
        }

        .portfolio-tag {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            background: rgba(9, 13, 22, 0.85);
            backdrop-filter: blur(6px);
            border: 1px solid var(--border);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .portfolio-body {
            padding: 2.2rem;
        }

        .portfolio-body h3 {
            font-size: 1.4rem;
            margin-bottom: 0.75rem;
        }

        .portfolio-body p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .portfolio-stats {
            display: flex;
            gap: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .stat-item span {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .stat-item strong {
            font-size: 1.1rem;
            color: var(--accent);
        }

        /* ── GEOGRAPHY / PLACES ── */
        .places-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .places-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .place-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.75rem;
            backdrop-filter: blur(12px);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .place-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            transform: scale(1.03);
            background: rgba(99, 102, 241, 0.04);
        }

        .place-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .place-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
        }

        /* ── PEOPLE (LEADERSHIP) ── */
        .people-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .founder-box {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(17, 24, 39, 0.8));
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 3.5rem;
            margin-bottom: 4rem;
            display: grid;
            grid-template-columns: 1fr 2.5fr;
            gap: 3rem;
            align-items: center;
        }

        .founder-icon {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-glow);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            border: 2px solid rgba(99, 102, 241, 0.3);
            box-shadow: 0 0 25px rgba(99, 102, 241, 0.2);
            justify-self: center;
        }

        .founder-info h3 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--accent);
        }

        .founder-info .role {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.25rem;
            display: block;
        }

        .founder-info p {
            font-size: 1.1rem;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .directors-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .director-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem 1.5rem;
            backdrop-filter: blur(12px);
            text-align: center;
            transition: all 0.3s ease;
        }

        .director-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .dir-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.5rem;
        }

        .director-card h4 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: var(--text-light);
        }

        .director-card .dir-role {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            display: block;
        }

        .director-card p {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* ── TESTIMONIALS ── */
        .testimonials-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .test-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.2rem;
            backdrop-filter: blur(12px);
            position: relative;
        }

        .quote-icon {
            font-size: 2.5rem;
            color: rgba(99, 102, 241, 0.15);
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }

        .test-card p {
            font-size: 0.95rem;
            line-height: 1.65;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 5;
        }

        .client-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            border-top: 1px solid var(--border);
            padding-top: 1.25rem;
        }

        .client-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary-glow);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .client-info strong {
            display: block;
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .client-info span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ── CONTACT US ── */
        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 4rem;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .contact-details-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem;
        }

        .contact-item {
            display: flex;
            gap: 18px;
            margin-bottom: 1.75rem;
        }

        .contact-item:last-child {
            margin-bottom: 0;
        }

        .c-item-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: var(--primary-glow);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .c-item-text h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .c-item-text p, .c-item-text a {
            font-size: 0.95rem;
            color: var(--text-muted);
            text-decoration: none;
        }

        .c-item-text a:hover {
            color: var(--primary);
        }

        /* Lead Form */
        .contact-form-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 3rem;
            backdrop-filter: blur(12px);
            position: relative;
        }

        .contact-form-box h3 {
            font-size: 1.65rem;
            margin-bottom: 0.5rem;
        }

        .contact-form-box p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 1.5rem;
        }

        .form-group.full {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.85rem 1rem;
            color: white;
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.03);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.1);
        }

        textarea.form-control {
            resize: none;
            min-height: 120px;
        }

        .submit-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.4);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* ── FOOTER ── */
        footer {
            background: #06090f;
            padding: 4.5rem 2rem 2.5rem;
            border-top: 1px solid var(--border);
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
        }

        .footer-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.03);
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* ── ANIMATIONS ── */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 3rem;
            }
            .hero h1 { font-size: 3rem; }
            .hero p { margin-left: auto; margin-right: auto; }
            .hero-actions { justify-content: center; }
            .hero-visual { order: -1; }
            
            .pillars-grid { grid-template-columns: repeat(2, 1fr); }
            .div-container { grid-template-columns: 1fr; }
            .portfolio-grid { grid-template-columns: 1fr; }
            .places-grid { grid-template-columns: repeat(2, 1fr); }
            .directors-grid { grid-template-columns: repeat(2, 1fr); }
            .testimonials-grid { grid-template-columns: 1fr; }
            .contact-container { grid-template-columns: 1fr; gap: 3rem; }
        }

        @media (max-width: 768px) {
            header { padding: 1rem; }
            header.scrolled { padding: 0.75rem 1rem; }
            nav { display: none; } /* Mobile navigation can be implemented dynamically */
            .mobile-menu-btn { display: block; }
            
            .pillars-grid { grid-template-columns: 1fr; }
            .places-grid { grid-template-columns: 1fr; }
            .directors-grid { grid-template-columns: 1fr; }
            .founder-box { grid-template-columns: 1fr; text-align: center; padding: 2rem; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .form-group.full { grid-column: span 1; }
            
            .footer-container { flex-direction: column; gap: 2rem; text-align: center; }
            .footer-links { flex-direction: column; gap: 1rem; }
            .footer-bottom { flex-direction: column; gap: 1rem; text-align: center; }
        }
    </style>
</head>
<body>

    <!-- Radial Background Glows -->
    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>
    <div class="glow-blob blob-3"></div>

    <div class="page-content">

        <!-- ── HEADER ── -->
        <header id="header">
            <div class="nav-container">
                <a href="#home" class="logo">
                    <i class="fas fa-bullhorn"></i> PIONEER<span>.</span>
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#vision">Vision</a></li>
                        <li><a href="#offerings">Offerings</a></li>
                        <li><a href="#portfolio">Portfolio</a></li>
                        <li><a href="#presence">Presence</a></li>
                        <li><a href="#leadership">Leadership</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                    <a href="login.php" class="login-btn"><i class="fas fa-lock"></i> Client Login</a>
                </nav>
                <button class="mobile-menu-btn" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></button>
            </div>
        </header>

        <!-- ── HERO SECTION ── -->
        <section id="home" class="hero">
            <div class="hero-container">
                <div class="hero-content">
                    <span class="tagline">India's Premier OOH Agency</span>
                    <h1>Pioneer Publicity</h1>
                    <p>Ahead of times since 1961. Offering innovative, persuasive, effective, and path-breaking outdoor advertising solutions that build iconic brand presence.</p>
                    <div class="hero-actions">
                        <a href="#contact" class="btn btn-primary">Get Proposal <i class="fas fa-paper-plane"></i></a>
                        <a href="#portfolio" class="btn btn-secondary">Our Portfolio <i class="fas fa-images"></i></a>
                    </div>
                </div>
                <div class="hero-visual">
                    <div class="visual-frame">
                        <img src="assets/images/billboard_mockup_1.png" alt="Pioneer Digital Billboard Mockup">
                        <div class="floating-badge badge-1">
                            <div class="badge-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="badge-text">
                                <span>Execution Period</span>
                                <strong>Less than 48 Hrs</strong>
                            </div>
                        </div>
                        <div class="floating-badge badge-2">
                            <div class="badge-icon"><i class="fas fa-map-pin"></i></div>
                            <div class="badge-text">
                                <span>Pan-India Sites</span>
                                <strong>500+ Locations</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── VISION SECTION (THE THREE PILLARS) ── -->
        <section id="vision">
            <div class="section-header">
                <span>The Pioneer Vision</span>
                <h2>Three Pillars of Pioneer</h2>
                <p>Extraordinary foundations laid 65 years ago that enable us to build path-breaking campaigns today.</p>
            </div>
            <div class="pillars-grid">
                <div class="pillar-card">
                    <div class="pillar-icon"><i class="fas fa-brain"></i></div>
                    <h3>Intelligence</h3>
                    <p>Insights are the origin point for all of our offerings. We utilize heavy data and location analysis to place your message in high-yield hotspots.</p>
                </div>
                <div class="pillar-card">
                    <div class="pillar-icon"><i class="fas fa-lightbulb"></i></div>
                    <h3>Thoughtfulness</h3>
                    <p>Every quarter of our offerings has an authentic point of contention. We don't just put up billboards; we design experiences that match public spaces.</p>
                </div>
                <div class="pillar-card">
                    <div class="pillar-icon"><i class="fas fa-sliders-h"></i></div>
                    <h3>Transformational</h3>
                    <p>Reassessing, redefining, and remolding our value offerings dynamically. Always evolving with next-gen smart display networks and programmatic OOH.</p>
                </div>
            </div>
        </section>

        <!-- ── OFFERINGS SECTION ── -->
        <section id="offerings" style="background: rgba(255,255,255,0.01);">
            <div class="section-header">
                <span>Services & Offerings</span>
                <h2>Our Diversification</h2>
                <p>Comprehensive strategies backed by data science, storytellers, and operational excellence.</p>
            </div>
            <div class="div-container">
                <div class="div-card">
                    <div>
                        <h3>Out-Of-Home (OOH) Agency</h3>
                        <p>Next-generation media and transit positioning across India. We deliver complete campaigns from visual design to data-driven performance monitoring.</p>
                        <ul class="feature-list">
                            <li><i class="fas fa-check-circle"></i> Pan-India Outdoor Strategy</li>
                            <li><i class="fas fa-check-circle"></i> Media Planning & Buying</li>
                            <li><i class="fas fa-check-circle"></i> Programmatic Digital OOH (DOOH)</li>
                            <li><i class="fas fa-check-circle"></i> Real-time Monitoring & Analytics</li>
                        </ul>
                    </div>
                    <a href="#contact" class="btn btn-secondary" style="align-self: flex-start;">Inquire OOH Solutions</a>
                </div>
                <div class="div-card">
                    <div>
                        <h3>BTL & Experiential Agency</h3>
                        <p>Fulfilling and executing brand promises with high-impact activations. Transforming challenger brands into champions through storytellers and live setups.</p>
                        <ul class="feature-list">
                            <li><i class="fas fa-check-circle"></i> BTL Activations & Roadshows</li>
                            <li><i class="fas fa-check-circle"></i> Data-Backed Consumer Tracking</li>
                            <li><i class="fas fa-check-circle"></i> Creative Storytelling & Content</li>
                            <li><i class="fas fa-check-circle"></i> High-Footfall Airport Installations</li>
                        </ul>
                    </div>
                    <a href="#contact" class="btn btn-secondary" style="align-self: flex-start;">Inquire BTL Solutions</a>
                </div>
            </div>
        </section>

        <!-- ── PORTFOLIO SECTION ── -->
        <section id="portfolio">
            <div class="section-header">
                <span>Featured Campaigns</span>
                <h2>Our Portfolio</h2>
                <p>A glimpse of live high-impact branding solutions executed at airports and prime corridors.</p>
            </div>
            <div class="portfolio-grid">
                <div class="portfolio-card">
                    <div class="portfolio-img">
                        <span class="portfolio-tag">Airport Transit</span>
                        <img src="assets/images/billboard_mockup_2.png" alt="Pioneer Airport Campaign Setup">
                    </div>
                    <div class="portfolio-body">
                        <h3>National Auto Launch (XUV300)</h3>
                        <p>Conceptualized and built interactive digital displays across major Indian airports (Mumbai, Delhi, and Bangalore). A true reflection of premium branding that created a massive buzz among travelers.</p>
                        <div class="portfolio-stats">
                            <div class="stat-item">
                                <span>Impressions</span>
                                <strong>5M+ Monthly</strong>
                            </div>
                            <div class="stat-item">
                                <span>Locations</span>
                                <strong>3 Airports</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="portfolio-card">
                    <div class="portfolio-img">
                        <span class="portfolio-tag">Digital OOH</span>
                        <img src="assets/images/billboard_mockup_1.png" alt="Urban Digital Billboard Grid">
                    </div>
                    <div class="portfolio-body">
                        <h3>Geographic Blitz Campaign</h3>
                        <p>Designed and rolled out over 500 high-visibility branding sites geographically distributed across the country. Executed from contract to complete site installation in less than 48 hours.</p>
                        <div class="portfolio-stats">
                            <div class="stat-item">
                                <span>Installations</span>
                                <strong>500+ Points</strong>
                            </div>
                            <div class="stat-item">
                                <span>Rollout Time</span>
                                <strong>48 Hours</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── GEOGRAPHY PRESENCE SECTION ── -->
        <section id="presence" style="background: rgba(255,255,255,0.01);">
            <div class="section-header">
                <span>Places We Dominate</span>
                <h2>Pan-India Presence</h2>
                <p>Our footprint spreads across key metro hubs and Tier-1 cities ensuring maximum brand reach.</p>
            </div>
            <div class="places-container">
                <div class="places-grid">
                    <div class="place-card">
                        <div class="place-icon"><i class="fas fa-city"></i></div>
                        <h3>Mumbai</h3>
                    </div>
                    <div class="place-card">
                        <div class="place-icon"><i class="fas fa-landmark"></i></div>
                        <h3>Delhi</h3>
                    </div>
                    <div class="place-card">
                        <div class="place-icon"><i class="fas fa-microchip"></i></div>
                        <h3>Bangalore</h3>
                    </div>
                    <div class="place-card">
                        <div class="place-icon"><i class="fas fa-bridge"></i></div>
                        <h3>Kolkata</h3>
                    </div>
                    <div class="place-card">
                        <div class="place-icon"><i class="fas fa-monument"></i></div>
                        <h3>Chennai</h3>
                    </div>
                    <div class="place-card">
                        <div class="place-icon"><i class="fas fa-tree"></i></div>
                        <h3>Pune</h3>
                    </div>
                    <div class="place-card">
                        <div class="place-icon"><i class="fas fa-mosque"></i></div>
                        <h3>Hyderabad</h3>
                    </div>
                    <div class="place-card">
                        <div class="place-icon"><i class="fas fa-sun"></i></div>
                        <h3>Ahmedabad</h3>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── LEADERSHIP SECTION ── -->
        <section id="leadership">
            <div class="section-header">
                <span>The Minds Behind Pioneer</span>
                <h2>Our Leadership</h2>
                <p>Driven by vision, legacy, and a perfectionist mindset to remain ahead of the curve.</p>
            </div>
            <div class="people-container">
                <!-- Founder block -->
                <div class="founder-box">
                    <div class="founder-icon"><i class="fas fa-shield-halved"></i></div>
                    <div class="founder-info">
                        <span class="role">Founding Father</span>
                        <h3>Naresh Vasudeva</h3>
                        <p>"These extraordinary men created Pioneer 65 years ago, and the journey they undertook enabled us today to offer innovative, persuasive, effective, and path-breaking outdoor solutions."</p>
                    </div>
                </div>

                <!-- Directors grid -->
                <div class="directors-grid">
                    <div class="director-card">
                        <div class="dir-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4>Sunil Vasudeva</h4>
                        <span class="dir-role">Chairman & Director</span>
                        <p>Began West operations and expanded Pioneer corporate to insurmountable heights. Big-hearted romanticiser of risk.</p>
                    </div>
                    <div class="director-card">
                        <div class="dir-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4>Rajesh Vasudeva</h4>
                        <span class="dir-role">Managing Director</span>
                        <p>Seasoned expert in OOH, pushing the boundaries of technology to progress the corporate into the future.</p>
                    </div>
                    <div class="director-card">
                        <div class="dir-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4>Mukesh Vasudeva</h4>
                        <span class="dir-role">Director</span>
                        <p>Strong commercial acumen guiding consolidated business operations across the country.</p>
                    </div>
                    <div class="director-card">
                        <div class="dir-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4>Dinesh Vasudeva</h4>
                        <span class="dir-role">Director</span>
                        <p>Positive, practical, and relationship-driven leader dedicated to nurturing long-term brand relationships.</p>
                    </div>
                    <div class="director-card" style="grid-column: span 1;">
                        <div class="dir-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4>Deepak Vasudeva</h4>
                        <span class="dir-role">Director</span>
                        <p>Futuristic approach, a complete perfectionist who handles everything on the board to finish.</p>
                    </div>
                    <div class="director-card">
                        <div class="dir-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4>Puja Pankaj Vasudeva</h4>
                        <span class="dir-role">Director</span>
                        <p>Brings a fresh perspective to corporate welfare, human resources, and brand building.</p>
                    </div>
                    <div class="director-card">
                        <div class="dir-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4>Gaurav Vasudeva</h4>
                        <span class="dir-role">Director</span>
                        <p>The global face of the business, taking forward the philosophy of "ahead of times."</p>
                    </div>
                    <div class="director-card">
                        <div class="dir-avatar"><i class="fas fa-user-tie"></i></div>
                        <h4>Nakul Vasudeva</h4>
                        <span class="dir-role">Director</span>
                        <p>Visualizes the brand in a modern light, mixing legacy lessons with new-age growth strategies.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── TESTIMONIALS SECTION ── -->
        <section id="testimonials" style="background: rgba(255,255,255,0.01);">
            <div class="section-header">
                <span>Client Appreciations</span>
                <h2>What Partners Say</h2>
                <p>Hear from Auto manufacturers and top brands on our operational excellence.</p>
            </div>
            <div class="testimonials-grid">
                <div class="test-card">
                    <i class="fas fa-quote-right quote-icon"></i>
                    <p>"The display set-up at Mumbai, Bangalore and Delhi airports created a massive buzz for our brand. The materials, quality, and technology used were a true reflection of the brand promise. Kudos!"</p>
                    <div class="client-profile">
                        <div class="client-avatar">M</div>
                        <div class="client-info">
                            <strong>Marketing Director</strong>
                            <span>Top Automotive Manufacturer</span>
                        </div>
                    </div>
                </div>
                <div class="test-card">
                    <i class="fas fa-quote-right quote-icon"></i>
                    <p>"Over 500 branding points, geographically located across the country, installed and executed in less than 48 hours. Still seems like an unimaginable feat of success. What a launch!"</p>
                    <div class="client-profile">
                        <div class="client-avatar">F</div>
                        <div class="client-info">
                            <strong>Founder & CEO</strong>
                            <span>Challenger Fintech Brand</span>
                        </div>
                    </div>
                </div>
                <div class="test-card">
                    <i class="fas fa-quote-right quote-icon"></i>
                    <p>"Pioneer is our most obvious choice when we think of outdoor advertising. These 4 years have been splendid in terms of response, delivery, enthusiasm, and operating excellence."</p>
                    <div class="client-profile">
                        <div class="client-avatar">P</div>
                        <div class="client-info">
                            <strong>Brand Head</strong>
                            <span>Leading Consumer Goods Group</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CONTACT SECTION ── -->
        <section id="contact">
            <div class="section-header">
                <span>Get in Touch</span>
                <h2>Let's Build Your Campaign</h2>
                <p>Send us your requirements and our planners will get back to you with custom billboard sites.</p>
            </div>
            <div class="contact-container">
                <div class="contact-info">
                    <div class="contact-details-box">
                        <div class="contact-item">
                            <div class="c-item-icon"><i class="fas fa-map-location-dot"></i></div>
                            <div class="c-item-text">
                                <h4>Head Office</h4>
                                <p>2C/6 New Rohtak Road, Karol Bagh, Near Liberty Cinema, New Delhi: 110 005.</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="c-item-icon"><i class="fas fa-phone-volume"></i></div>
                            <div class="c-item-text">
                                <h4>Call Us</h4>
                                <p>011-440 19000 (Tel)<br>011-440 19001 (Fax)</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="c-item-icon"><i class="fas fa-envelope-open-text"></i></div>
                            <div class="c-item-text">
                                <h4>Email Enquiries</h4>
                                <p><a href="mailto:delhi@pioneerpublicityindia.com">delhi@pioneerpublicityindia.com</a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="contact-form-box">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-circle-check"></i>
                            <span>Thank you! Your request has been successfully recorded. We will connect shortly.</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-circle-exclamation"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="#contact">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" name="name" id="name" class="form-control" placeholder="Your Name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" name="email" id="email" class="form-control" placeholder="Email Address" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" name="phone" id="phone" class="form-control" placeholder="Mobile Number">
                            </div>
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" name="subject" id="subject" class="form-control" placeholder="Campaign Target">
                            </div>
                        </div>
                        <div class="form-group full">
                            <label for="message">Message / Scope *</label>
                            <textarea name="message" id="message" class="form-control" placeholder="Describe your brand requirements, preferred cities, and budget..." required></textarea>
                        </div>
                        <button type="submit" class="submit-btn">
                            <span>Send Message</span> <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- ── FOOTER ── -->
        <footer>
            <div class="footer-container">
                <a href="#home" class="logo">
                    <i class="fas fa-bullhorn"></i> PIONEER<span>.</span>
                </a>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#vision">Vision</a></li>
                    <li><a href="#offerings">Offerings</a></li>
                    <li><a href="#portfolio">Portfolio</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="login.php">Client Login</a></li>
                </ul>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Pioneer Publicity. All Rights Reserved.</p>
                <p>Designed with excellence for Pioneer Publicity OOH Workspace.</p>
            </div>
        </footer>

    </div>

    <script>
        // Header scroll behavior
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle helper
        function toggleMobileMenu() {
            const nav = document.querySelector('nav');
            if (nav.style.display === 'flex') {
                nav.style.display = 'none';
            } else {
                nav.style.display = 'flex';
                nav.style.flexDirection = 'column';
                nav.style.position = 'absolute';
                nav.style.top = '100%';
                nav.style.left = '0';
                nav.style.width = '100%';
                nav.style.background = 'rgba(9, 13, 22, 0.95)';
                nav.style.padding = '2rem';
                nav.style.borderBottom = '1px solid var(--border)';
                nav.style.gap = '1.5rem';
                document.querySelector('.nav-links').style.flexDirection = 'column';
                document.querySelector('.nav-links').style.gap = '1.25rem';
            }
        }
    </script>
</body>
</html>
