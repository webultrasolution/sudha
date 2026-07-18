<?php
define('BYPASS_AUTH', true);
$activePage = 'setup';
$pageTitle = 'System Environment Setup';
include_once __DIR__ . '/../../includes/header.php';

ob_start();
try {
    // 1. Create Tables (Using the schema)
    $sql = file_get_contents('schema.sql');
    $pdo->exec($sql);
    echo "<div class='setup-msg success'><i class='fas fa-check-circle'></i> Database tables initialized successfully.</div>";

    // 2. Create Demo Users
    $users = [
        ['admin', 'admin123', 'admin', 'System Admin', 'admin@easyoutdoor.com'],
        ['sales', 'sales123', 'sales', 'Sales Manager', 'sales@easyoutdoor.com'],
        ['ops', 'ops123', 'staff', 'Ops Lead', 'ops@easyoutdoor.com'],
        ['accounts', 'accounts123', 'manager', 'Accountant', 'accounts@easyoutdoor.com']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role, full_name, email) VALUES (?, ?, ?, ?, ?)");
    foreach ($users as $user) {
        $password = password_hash($user[1], PASSWORD_DEFAULT);
        $stmt->execute([$user[0], $password, $user[2], $user[3], $user[4]]);
    }
    echo "<div class='setup-msg info'><i class='fas fa-users'></i> Core roles (Admin, Sales, Ops, Accounts) established.</div>";

    // 3. Create Demo Sites
    $sites = [
        ['S-001', 'Elite Billboard - MG Road', 'MG Road Junction', 'Bangalore', 'Billboard', 40, 20, 'HA', 150000, 100000],
        ['S-002', 'Airport Unipole - Gate 1', 'NH-44 Airport Road', 'Bangalore', 'Unipole', 20, 10, 'TA', 80000, 50000],
        ['S-003', 'Bus Queen Square - Central', 'Majestic Bus Stand', 'Bangalore', 'BQS', 10, 8, 'HA', 25000, 15000]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO sites (site_code, name, location, city, type, width, height, owner_type, card_rate, purchase_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($sites as $site) {
        $stmt->execute($site);
    }
    echo "<div class='setup-msg info'><i class='fas fa-map-marker-alt'></i> Seeded inventory with initial OOH assets.</div>";

    // 4. Create Demo Partners
    $partners = [
        ['client', 'Brand Connect Pvt Ltd', '29AAAAA0000A1Z5', 'ABCDE1234F'],
        ['vendor', 'Outdoor Media Solutions', '29BBBBB0000B1Z5', 'FGHIJ5678K']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO partners (type, name, gstin, pan) VALUES (?, ?, ?, ?)");
    foreach ($partners as $partner) {
        $stmt->execute($partner);
    }
    echo "<div class='setup-msg info'><i class='fas fa-handshake'></i> Demo partners (Client/Vendor) synchronized.</div>";

} catch (PDOException $e) {
    echo "<div class='setup-msg error'><i class='fas fa-times-circle'></i> Setup failed: " . $e->getMessage() . "</div>";
}
$output = ob_get_clean();
?>

<div class="card" style="max-width: 700px; margin: 0 auto; padding: 3rem;">
    <div style="text-align: center; margin-bottom: 2.5rem;">
        <div
            style="width: 64px; height: 64px; background: rgba(28, 173, 169, 0.1); color: var(--primary); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">
            <i class="fas fa-rocket"></i>
        </div>
        <h2 style="font-size: 1.75rem; color: var(--dark);">CRM Initialization</h2>
        <p style="color: var(--secondary);">Deploying core architecture and demo data</p>
    </div>

    <div style="display: grid; gap: 1rem;">
        <?php echo $output; ?>
    </div>

    <div style="margin-top: 3rem; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 2rem;">
        <p style="margin-bottom: 1.5rem; font-size: 0.875rem; color: var(--secondary);">The environment is now ready for
            operations.</p>
        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-primary" style="width: 100%; padding: 1rem;">
            Go to Login Portal <i class="fas fa-arrow-right" style="margin-left: 0.5rem;"></i>
        </a>
    </div>
</div>

<style>
    .setup-msg {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-radius: 12px;
        font-size: 0.9375rem;
        font-weight: 500;
    }

    .setup-msg i {
        font-size: 1.1rem;
    }

    .setup-msg.success {
        background: #dcfce7;
        color: #166534;
    }

    .setup-msg.info {
        background: #e0f2fe;
        color: #0369a1;
    }

    .setup-msg.error {
        background: #fee2e2;
        color: #991b1b;
    }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>