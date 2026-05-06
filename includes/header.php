<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/functions.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php if (!isset($hideSidebar) || !$hideSidebar): ?>
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="<?php echo BASE_URL; ?>assets/img/LOGO.png" alt="Easy Outdoor" class="sidebar-logo">
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>index.php" class="nav-link <?php echo $activePage == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> <span>Dashboard</span>
                </a>
            </li>

            <!-- Master Data Submenu -->
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle <?php echo in_array($activePage, ['clients', 'sites', 'vendors']) ? 'active submenu-open' : ''; ?>">
                    <i class="fas fa-database"></i> <span>Master Data</span> <i class="fas fa-chevron-down toggle-icon" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <ul class="submenu" style="<?php echo in_array($activePage, ['clients', 'sites', 'vendors']) ? 'display: block;' : 'display: none;'; ?>">
                    <li><a href="<?php echo BASE_URL; ?>modules/partners/clients.php" class="<?php echo $activePage == 'clients' ? 'active-sub' : ''; ?>"><i class="fas fa-building"></i> Company/Client</a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/inventory/sites.php" class="<?php echo $activePage == 'sites' ? 'active-sub' : ''; ?>"><i class="fas fa-map-marked-alt"></i> Site/Location</a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/partners/vendors.php" class="<?php echo $activePage == 'vendors' ? 'active-sub' : ''; ?>"><i class="fas fa-truck-loading"></i> Vendors</a></li>
                </ul>
            </li>

            <!-- Operations & Sales Submenu -->
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle <?php echo in_array($activePage, ['proposals', 'bookings']) ? 'active submenu-open' : ''; ?>">
                    <i class="fas fa-briefcase"></i> <span>Operations & Sales</span> <i class="fas fa-chevron-down toggle-icon" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <ul class="submenu" style="<?php echo in_array($activePage, ['proposals', 'bookings']) ? 'display: block;' : 'display: none;'; ?>">
                    <li><a href="<?php echo BASE_URL; ?>modules/proposals/proposals.php" class="<?php echo $activePage == 'proposals' ? 'active-sub' : ''; ?>"><i class="fas fa-file-contract"></i> Sales / Proposals</a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/operations/bookings.php" class="<?php echo $activePage == 'bookings' ? 'active-sub' : ''; ?>"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                </ul>
            </li>

            <!-- Financials Submenu -->
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle <?php echo in_array($activePage, ['invoices', 'payments']) ? 'active submenu-open' : ''; ?>">
                    <i class="fas fa-wallet"></i> <span>Financials</span> <i class="fas fa-chevron-down toggle-icon" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <ul class="submenu" style="<?php echo in_array($activePage, ['invoices', 'payments']) ? 'display: block;' : 'display: none;'; ?>">
                    <li><a href="<?php echo BASE_URL; ?>modules/financials/invoices.php" class="<?php echo $activePage == 'invoices' ? 'active-sub' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i> Invoices</a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/financials/payments.php" class="<?php echo $activePage == 'payments' ? 'active-sub' : ''; ?>"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                </ul>
            </li>
            
            <!-- Tools & Insights Submenu -->
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle <?php echo in_array($activePage, ['reports', 'resources', 'photofactory']) ? 'active submenu-open' : ''; ?>">
                    <i class="fas fa-chart-line"></i> <span>Tools & Insights</span> <i class="fas fa-chevron-down toggle-icon" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <ul class="submenu" style="<?php echo in_array($activePage, ['reports', 'resources', 'photofactory']) ? 'display: block;' : 'display: none;'; ?>">
                    <li><a href="<?php echo BASE_URL; ?>modules/reports/reports.php" class="<?php echo $activePage == 'reports' ? 'active-sub' : ''; ?>"><i class="fas fa-chart-pie"></i> Reports</a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/admin/resources.php" class="<?php echo $activePage == 'resources' ? 'active-sub' : ''; ?>"><i class="fas fa-tools"></i> Resources</a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/inventory/photofactory.php" class="<?php echo $activePage == 'photofactory' ? 'active-sub' : ''; ?>"><i class="fas fa-images"></i> Photofactory</a></li>
                </ul>
            </li>

            <li class="nav-item" style="margin-top: auto;">
                <a href="<?php echo BASE_URL; ?>logout.php" class="nav-link" style="color: #ef4444;">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </li>
        </ul>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toggles = document.querySelectorAll('.submenu-toggle');
                toggles.forEach(toggle => {
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        const submenu = this.nextElementSibling;
                        const icon = this.querySelector('.toggle-icon');
                        
                        if (submenu.style.display === 'block') {
                            submenu.style.display = 'none';
                            icon.style.transform = 'rotate(0deg)';
                            this.classList.remove('submenu-open');
                        } else {
                            submenu.style.display = 'block';
                            icon.style.transform = 'rotate(180deg)';
                            this.classList.add('submenu-open');
                        }
                    });
                });
            });
        </script>
    </div>
    <?php endif; ?>
    
    <div class="main-content" style="<?php echo (isset($hideSidebar) && $hideSidebar) ? 'margin-left: 0; padding: 1.5rem;' : ''; ?>">
        <div class="header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <?php if (!isset($hideSidebar) || !$hideSidebar): ?>
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <?php else: ?>
                <a href="<?php echo BASE_URL; ?>modules/proposals/proposals.php" class="btn btn-secondary" style="border: none; background: #e2e8f0; border-radius: 8px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: #475569; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <?php endif; ?>
                <h1><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h1>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['user_name'] ?? 'Guest'; ?></span>
            </div>
        </div>
