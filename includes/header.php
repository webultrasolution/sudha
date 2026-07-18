<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/functions.php';
checkAuth();
$activeEntity = getActiveEntity();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- PWA Integrations -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Sudha CRM">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/logo.png">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js')
                    .then(reg => console.log('PWA Service Worker registered!', reg))
                    .catch(err => console.log('PWA Service Worker registration failed:', err));
            });
        }
    </script>
</head>
<body>
    <?php if (!isset($hideSidebar) || !$hideSidebar): ?>
    <div class="sidebar">
        <div class="sidebar-brand" style="margin-bottom: 1.5rem; padding: 0 0.5rem; margin-top: -0.5rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; width: 100%;">
            <?php if ($activeEntity && $activeEntity['logo']): ?>
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $activeEntity['logo']; ?>" alt="<?php echo htmlspecialchars($activeEntity['name']); ?>" class="sidebar-logo" style="max-width: 100px; max-height: 45px; margin: 0;">
            <?php else: ?>
                <img src="<?php echo BASE_URL; ?>assets/img/LOGO.png" alt="Sudha Creative" class="sidebar-logo" style="max-width: 100px; max-height: 45px; margin: 0;">
            <?php endif; ?>
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="nav-link <?php echo $activePage == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> <span>Dashboard</span>
                </a>
            </li>
            <?php if (hasRole('admin')): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>modules/admin/enquiries.php" class="nav-link <?php echo $activePage == 'enquiries' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open-text"></i> <span>Enquiries / Leads</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Master Data Submenu -->
            <?php if (canView('clients') || canView('inventory') || canView('vendors')): ?>
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle <?php echo in_array($activePage, ['clients', 'sites', 'vendors']) ? 'active submenu-open' : ''; ?>">
                    <i class="fas fa-database"></i> <span>Master Data</span> <i class="fas fa-chevron-down toggle-icon" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <ul class="submenu" style="<?php echo in_array($activePage, ['clients', 'sites', 'vendors']) ? 'display: block;' : 'display: none;'; ?>">
                    <?php if (canView('clients')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/partners/clients.php" class="<?php echo $activePage == 'clients' ? 'active-sub' : ''; ?>"><i class="fas fa-building"></i> Company/Client</a></li>
                    <?php endif; ?>
                    <?php if (canView('inventory')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/inventory/sites.php" class="<?php echo $activePage == 'sites' ? 'active-sub' : ''; ?>"><i class="fas fa-map-marked-alt"></i> Site/Location</a></li>
                    <?php endif; ?>
                    <?php if (canView('vendors')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/partners/vendors.php" class="<?php echo $activePage == 'vendors' ? 'active-sub' : ''; ?>"><i class="fas fa-truck-loading"></i> Vendors</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Operations & Sales Submenu -->
            <?php if (canView('proposals') || canView('bookings') || canView('vendors_printing_po') || canView('client_printing_invoice') || canView('client_mounting_invoice')): ?>
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle <?php echo in_array($activePage, ['proposals', 'bookings', 'printing_rates', 'client_printing', 'client_printing_rates', 'mounting']) ? 'active submenu-open' : ''; ?>">
                    <i class="fas fa-briefcase"></i> <span>Operations & Sales</span> <i class="fas fa-chevron-down toggle-icon" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <ul class="submenu" style="<?php echo in_array($activePage, ['proposals', 'bookings', 'direct_booking', 'printing_rates', 'client_printing', 'client_printing_rates', 'mounting']) ? 'display: block;' : 'display: none;'; ?>">
                    <?php if (canView('proposals')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/proposals/proposals.php" class="<?php echo $activePage == 'proposals' ? 'active-sub' : ''; ?>"><i class="fas fa-file-contract"></i> Sales / Proposals</a></li>
                    <?php endif; ?>
                    <?php if (canView('bookings')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/operations/bookings.php" class="<?php echo $activePage == 'bookings' ? 'active-sub' : ''; ?>"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                    <?php endif; ?>
                    <?php if (canView('vendors_printing_po')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/partners/printing_rates.php" class="<?php echo $activePage == 'printing_rates' ? 'active-sub' : ''; ?>"><i class="fas fa-print"></i> Vendors Printing PO</a></li>
                    <?php endif; ?>
                    <?php if (canView('client_printing_invoice')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/partners/client_printing_rates.php" class="<?php echo $activePage == 'client_printing_rates' ? 'active-sub' : ''; ?>"><i class="fas fa-file-invoice"></i> Client Printing Invoice</a></li>
                    <?php endif; ?>
                    <?php if (canView('client_mounting_invoice')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/operations/mounting.php" class="<?php echo $activePage == 'mounting' ? 'active-sub' : ''; ?>"><i class="fas fa-tools"></i> Client Mounting Invoice</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Financials Submenu (Dynamic Permissions) -->
            <?php if (canAccess('financials') || canView('invoices') || canView('payments') || canView('vendors_purchase_orders') || canView('ledger')): ?>
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle <?php echo in_array($activePage, ['invoices', 'payments', 'pos', 'ledger']) ? 'active submenu-open' : ''; ?>">
                    <i class="fas fa-wallet"></i> <span>Financials</span> <i class="fas fa-chevron-down toggle-icon" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <ul class="submenu" style="<?php echo in_array($activePage, ['invoices', 'payments', 'ledger', 'pos']) ? 'display: block;' : 'display: none;'; ?>">
                    <?php if (canView('invoices')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/financials/invoices.php" class="<?php echo $activePage == 'invoices' ? 'active-sub' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i> Invoices</a></li>
                    <?php endif; ?>
                    <?php if (canView('payments')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/financials/payments.php" class="<?php echo $activePage == 'payments' ? 'active-sub' : ''; ?>"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                    <?php endif; ?>
                    <?php if (canView('vendors_purchase_orders')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/financials/purchase_orders.php" class="<?php echo $activePage == 'pos' ? 'active-sub' : ''; ?>"><i class="fas fa-shopping-cart"></i>Vendors Purchase Orders</a></li>
                    <?php endif; ?>
                    <?php if (canView('ledger')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/financials/ledgers.php" class="<?php echo $activePage == 'ledger' ? 'active-sub' : ''; ?>"><i class="fas fa-book"></i>  Ledger</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            
            <!-- Tools & Insights Submenu -->
            <?php if (canView('reports') || canView('trash') || canView('approval_queue') || canView('admin_settings') || canView('multi_content') || canView('users') || canView('role_permissions') || canView('system_activity_logs')): ?>
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle <?php echo in_array($activePage, ['reports', 'resources', 'photofactory', 'entities', 'settings', 'approvals', 'trash', 'users', 'activity_logs']) ? 'active submenu-open' : ''; ?>">
                    <i class="fas fa-chart-line"></i> <span>Tools & Insights</span> <i class="fas fa-chevron-down toggle-icon" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <ul class="submenu" style="<?php echo in_array($activePage, ['reports', 'resources', 'photofactory', 'entities', 'settings', 'approvals', 'trash', 'users', 'activity_logs']) ? 'display: block;' : 'display: none;'; ?>">
                    <?php if (canView('reports')): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/reports/reports.php" class="<?php echo $activePage == 'reports' ? 'active-sub' : ''; ?>"><i class="fas fa-chart-pie"></i> Reports</a></li>
                    <?php endif; ?>
                    <?php if (canView('trash')): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/admin/trash.php" class="<?php echo $activePage == 'trash' ? 'active-sub' : ''; ?>"><i class="fas fa-trash-alt"></i> Trash</a></li>
                    <?php endif; ?>
                    <?php if (canView('approval_queue')): ?>
                        <?php
                        // Fetch pending approval count for badge
                        $pendingApprovalCount = 0;
                        try {
                            $pendingApprovalCount += $pdo->query("SELECT COUNT(*) FROM proposals WHERE approval_status = 'pending_approval'")->fetchColumn();
                            $pendingApprovalCount += $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE approval_status = 'pending_approval'")->fetchColumn();
                            $pendingApprovalCount += $pdo->query("SELECT COUNT(*) FROM invoices WHERE approval_status = 'pending_approval'")->fetchColumn();
                        } catch(Exception $e) {}
                        ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/admin/approvals.php" class="<?php echo $activePage == 'approvals' ? 'active-sub' : ''; ?>" style="display: flex; align-items: center; justify-content: space-between;">
                            <span><i class="fas fa-clipboard-check"></i> Approval Queue</span>
                            <?php if ($pendingApprovalCount > 0): ?>
                                <span style="background: #ef4444; color: #fff; font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 50px; min-width: 20px; text-align: center; animation: pulse 2s infinite;"><?php echo $pendingApprovalCount; ?></span>
                            <?php endif; ?>
                        </a></li>
                    <?php endif; ?>
                    <?php if (canView('admin_settings')): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/admin/settings.php" class="<?php echo $activePage == 'settings' ? 'active-sub' : ''; ?>"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <?php endif; ?>
                    <?php if (canView('multi_content')): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/admin/entities.php" class="<?php echo $activePage == 'entities' ? 'active-sub' : ''; ?>"><i class="fas fa-layer-group"></i> Multi Content</a></li>
                    <?php endif; ?>
                    <?php if (canView('users')): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/users/index.php" class="<?php echo $activePage == 'users' ? 'active-sub' : ''; ?>"><i class="fas fa-users-cog"></i> User Management</a></li>
                    <?php endif; ?>
                    <?php if (canView('role_permissions')): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/users/permissions.php" class="<?php echo $activePage == 'users' ? 'active-sub' : ''; ?>"><i class="fas fa-user-shield"></i> Role Permissions</a></li>
                    <?php endif; ?>
                    <?php if (canView('system_activity_logs')): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/admin/activity_logs.php" class="<?php echo $activePage == 'activity_logs' ? 'active-sub' : ''; ?>"><i class="fas fa-history"></i> System Activity Logs</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <li class="nav-item" style="margin-top: auto;">
                <a href="<?php echo BASE_URL; ?>logout.php" class="nav-link" style="color: #ef4444;">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </li>
        </ul>
        
        <script>
            function toggleSidebar() {
                if (window.innerWidth > 1024) {
                    document.body.classList.toggle('sidebar-collapsed');
                } else {
                    document.body.classList.toggle('sidebar-open');
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const toggles = document.querySelectorAll('.submenu-toggle');
                toggles.forEach(toggle => {
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        const submenu = this.nextElementSibling;
                        const icon = this.querySelector('.toggle-icon');
                        const isAlreadyOpen = submenu.style.display === 'block';
                        
                        // Close all submenus
                        toggles.forEach(otherToggle => {
                            const otherSubmenu = otherToggle.nextElementSibling;
                            const otherIcon = otherToggle.querySelector('.toggle-icon');
                            otherSubmenu.style.display = 'none';
                            if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
                            otherToggle.classList.remove('submenu-open');
                        });
                        
                        // If it wasn't open, open it now
                        if (!isAlreadyOpen) {
                            submenu.style.display = 'block';
                            if (icon) icon.style.transform = 'rotate(180deg)';
                            this.classList.add('submenu-open');
                        }
                    });
                });
            });
        </script>
    </div>
    <?php endif; ?>
    
    <div class="main-content" style="<?php echo (isset($hideSidebar) && $hideSidebar) ? 'margin-left: 0; padding: 1.5rem;' : ''; ?>">
        <div class="header" style="position: sticky; top: 0; z-index: 900; background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); padding: 1rem 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <?php if (!isset($hideSidebar) || !$hideSidebar): ?>
                <button class="menu-toggle" onclick="toggleSidebar()" style="background: var(--primary); color: white; border: none; padding: 0.5rem 0.75rem; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-bars"></i>
                </button>
                <?php endif; ?>
                <a href="javascript:history.back()" class="btn btn-secondary" style="border: none; background: #e2e8f0; border-radius: 8px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: #475569; text-decoration: none;" title="Go Back">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;"><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h1>
            </div>
            <div class="user-info hide-mobile" style="display: flex; align-items: center; gap: 1.5rem;">
                <!-- Content Switcher -->
                <?php
                $allEntities = $pdo->query("SELECT id, name FROM entities")->fetchAll();
                if (count($allEntities) > 1):
                ?>
                <div class="entity-switcher" style="position: relative;">
                    <select onchange="const urlParams = new URLSearchParams(window.location.search); urlParams.set('set_entity', this.value); window.location.search = urlParams.toString();" style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 0.4rem 1rem; border-radius: 8px; font-size: 0.8rem; font-weight: 700; color: #475569; cursor: pointer;">
                        <?php foreach ($allEntities as $e): ?>
                            <option value="<?php echo $e['id']; ?>" <?php echo ($_SESSION['active_entity_id'] ?? '') == $e['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Financial Year Switcher -->
                <div class="fy-switcher" style="position: relative;">
                    <select onchange="const urlParams = new URLSearchParams(window.location.search); urlParams.set('set_fy', this.value); window.location.search = urlParams.toString();" style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 0.4rem 1rem; border-radius: 8px; font-size: 0.8rem; font-weight: 700; color: #475569; cursor: pointer;">
                        <?php 
                        $fyOptions = ['25-26', '26-27', '27-28'];
                        $currentSessionFY = $_SESSION['active_financial_year'] ?? '26-27';
                        foreach ($fyOptions as $fy): 
                        ?>
                            <option value="<?php echo $fy; ?>" <?php echo $currentSessionFY === $fy ? 'selected' : ''; ?>>
                                FY 20<?php echo explode('-', $fy)[0] . '-20' . explode('-', $fy)[1]; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="profile-dropdown" style="position: relative; display: inline-block;">
                    <button class="profile-btn" style="background: none; border: none; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; transition: background 0.2s; font-family: inherit; font-size: 1rem;">
                        <i class="fas fa-user-circle" style="font-size: 1.25rem; color: var(--primary);"></i>
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest', ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem; opacity: 0.7;"></i>
                    </button>
                    <div class="profile-dropdown-content" style="display: none; position: absolute; right: 0; background-color: #ffffff; min-width: 200px; box-shadow: 0px 8px 24px rgba(0,0,0,0.12); border: 1px solid #cbd5e1; border-radius: 8px; z-index: 1000; margin-top: 8px; overflow: hidden;">
                        <a href="<?php echo BASE_URL; ?>modules/users/profile.php" style="color: #334155; padding: 12px 16px; text-decoration: none; display: block; font-size: 0.9rem; font-weight: 500; transition: background 0.2s; text-align: left;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
                            <i class="fas fa-user-cog" style="margin-right: 10px; color: var(--primary); width: 16px;"></i> My Profile
                        </a>
                        <?php if (canView('users')): ?>
                        <a href="<?php echo BASE_URL; ?>modules/users/index.php" style="color: #334155; padding: 12px 16px; text-decoration: none; display: block; font-size: 0.9rem; font-weight: 500; transition: background 0.2s; text-align: left;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
                            <i class="fas fa-users-cog" style="margin-right: 10px; color: var(--primary); width: 16px;"></i> Manage Users
                        </a>
                        <?php endif; ?>
                        <?php if (canView('role_permissions')): ?>
                        <a href="<?php echo BASE_URL; ?>modules/users/permissions.php" style="color: #334155; padding: 12px 16px; text-decoration: none; display: block; font-size: 0.9rem; font-weight: 500; transition: background 0.2s; text-align: left;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
                            <i class="fas fa-user-shield" style="margin-right: 10px; color: var(--primary); width: 16px;"></i> Permissions
                        </a>
                        <?php endif; ?>
                        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 0;">
                        <a href="<?php echo BASE_URL; ?>logout.php" style="color: #ef4444; padding: 12px 16px; text-decoration: none; display: block; font-size: 0.9rem; font-weight: 600; transition: background 0.2s; text-align: left;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='none'">
                            <i class="fas fa-sign-out-alt" style="margin-right: 10px; width: 16px;"></i> Logout
                        </a>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const btn = document.querySelector('.profile-btn');
                    const content = document.querySelector('.profile-dropdown-content');
                    
                    if (btn && content) {
                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const isOpen = content.style.display === 'block';
                            content.style.display = isOpen ? 'none' : 'block';
                        });
                        
                        document.addEventListener('click', function() {
                            content.style.display = 'none';
                        });
                    }
                });
                </script>
            </div>
        </div>
