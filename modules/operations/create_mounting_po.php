<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$activePage = 'mounting';
$pageTitle = 'Create Client Mounting Invoice';

requirePermission('clients', 'view');

$action = isset($_GET['action']) ? clean($_GET['action']) : 'add';
$po_number = isset($_GET['po_number']) ? clean($_GET['po_number']) : null;
$rate_ids = isset($_GET['rate_ids']) && is_array($_GET['rate_ids']) ? $_GET['rate_ids'] : [];
$client_id_get = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($action === 'edit') {
    requirePermission('clients', 'edit');
} else {
    requirePermission('clients', 'add');
}

$rateData = null;
$selectedSitesData = [];

if ($action === 'edit') {
    if ($po_number) {
        $stmt = $pdo->prepare("SELECT * FROM client_mounting_rates WHERE po_number = ? AND client_id = ?");
        $stmt->execute([$po_number, $client_id_get]);
        $rows = $stmt->fetchAll();
    } elseif (!empty($rate_ids)) {
        $in = str_repeat('?,', count($rate_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM client_mounting_rates WHERE id IN ($in)");
        $stmt->execute($rate_ids);
        $rows = $stmt->fetchAll();
    } else {
        die("Invalid PO reference.");
    }
    if (empty($rows))
        die("Client Mounting Invoice not found.");
    $rateData = $rows[0];
    foreach ($rows as $r)
        $selectedSitesData[$r['site_id']] = $r['rate_per_sqft'];
    $pageTitle = 'Edit Client Mounting Invoice';
}

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = clean($_POST['action'] ?? 'add');
    $client_id_p = intval($_POST['client_id']);
    $mounting_type = clean($_POST['mounting_type']);
    $rate = floatval($_POST['rate_per_sqft'] ?? 0);
    $site_ids_p = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [];
    $ind_rates = $_POST['individual_rates'] ?? [];
    $campaign_name = isset($_POST['campaign_name']) ? clean($_POST['campaign_name']) : null;
    $brand_name = isset($_POST['brand_name']) ? clean($_POST['brand_name']) : null;

    $billing_gstin = !empty($_POST['billing_gstin']) ? clean($_POST['billing_gstin']) : null;

    if ($act === 'add') {
        requirePermission('clients', 'add');
        $po_new = generateSequenceNumber($pdo, 'client_mounting_draft');
        foreach ($site_ids_p as $sid) {
            $sid = !empty($sid) ? intval($sid) : null;
            $r_val = (isset($ind_rates[$sid]) && $ind_rates[$sid] !== '') ? floatval($ind_rates[$sid]) : $rate;
            $pdo->prepare("INSERT INTO client_mounting_rates (client_id, site_id, mounting_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$client_id_p, $sid, $mounting_type, $r_val, $po_new, $campaign_name, $brand_name, $billing_gstin]);
        }
        header("Location: mounting.php?msg=added");
        exit;
    } else {
        requirePermission('clients', 'edit');
        $po_edit = !empty($_POST['po_number']) ? clean($_POST['po_number']) : generateSequenceNumber($pdo, 'client_mounting_draft');
        $rate_ids_post = $_POST['rate_ids'] ?? [];

        if (!$po_edit && !empty($rate_ids_post)) {
            $in = str_repeat('?,', count($rate_ids_post) - 1) . '?';
            $pdo->prepare("UPDATE client_mounting_rates SET po_number=?, campaign_name=?, brand_name=?, billing_gstin=? WHERE id IN ($in)")
                ->execute(array_merge([$po_edit, $campaign_name, $brand_name, $billing_gstin], $rate_ids_post));
        }

        $stmt = $pdo->prepare("SELECT id, site_id FROM client_mounting_rates WHERE po_number = ? AND client_id = ?");
        $stmt->execute([$po_edit, $client_id_p]);
        $existing = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $existing_site_to_id = array_flip($existing);
        $posted_sites = [];

        foreach ($site_ids_p as $sid) {
            if (empty($sid))
                continue;
            $sid = intval($sid);
            $posted_sites[] = $sid;
            $r_val = (isset($ind_rates[$sid]) && $ind_rates[$sid] !== '') ? floatval($ind_rates[$sid]) : $rate;
            if (isset($existing_site_to_id[$sid])) {
                $pdo->prepare("UPDATE client_mounting_rates SET mounting_type=?, rate_per_sqft=?, campaign_name=?, brand_name=?, billing_gstin=? WHERE id=?")
                    ->execute([$mounting_type, $r_val, $campaign_name, $brand_name, $billing_gstin, $existing_site_to_id[$sid]]);
            } else {
                $pdo->prepare("INSERT INTO client_mounting_rates (client_id, site_id, mounting_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$client_id_p, $sid, $mounting_type, $r_val, $po_edit, $campaign_name, $brand_name, $billing_gstin]);
            }
        }
        foreach ($existing_site_to_id as $es => $eid) {
            if (!in_array($es, $posted_sites))
                $pdo->prepare("DELETE FROM client_mounting_rates WHERE id=?")->execute([$eid]);
        }

        // Sync campaign/brand and billing_gstin across all rows of this PO to be safe
        $syncStmt = $pdo->prepare("UPDATE client_mounting_rates SET campaign_name = ?, brand_name = ?, billing_gstin = ? WHERE po_number = ?");
        $syncStmt->execute([$campaign_name, $brand_name, $billing_gstin, $po_edit]);

        header("Location: mounting.php?msg=updated");
        exit;
    }
}

$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
try {
    $sites = $pdo->query("
        SELECT s.id, s.name, s.site_code, s.width, s.height, s.vendor_id, s.city, s.state, s.type, s.light_type, s.owner_type, s.status, s.location,
            s.hsn_code, s.mounting_hsn,
            p.name as vendor_name,
            (SELECT GROUP_CONCAT(filename) FROM site_images WHERE site_id = s.id) as all_images,
            (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail
        FROM sites s LEFT JOIN partners p ON s.vendor_id = p.id
        ORDER BY s.site_code ASC
    ")->fetchAll();
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'mounting_hsn') !== false) {
        try {
            $pdo->exec("ALTER TABLE sites ADD COLUMN mounting_hsn VARCHAR(50) DEFAULT NULL AFTER hsn_code");
            $sites = $pdo->query("
                SELECT s.id, s.name, s.site_code, s.width, s.height, s.vendor_id, s.city, s.state, s.type, s.light_type, s.owner_type, s.status, s.location,
                    s.hsn_code, s.mounting_hsn,
                    p.name as vendor_name,
                    (SELECT GROUP_CONCAT(filename) FROM site_images WHERE site_id = s.id) as all_images,
                    (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail
                FROM sites s LEFT JOIN partners p ON s.vendor_id = p.id
                ORDER BY s.site_code ASC
            ")->fetchAll();
        } catch (Exception $ex) {
            throw $e;
        }
    } else {
        throw $e;
    }
}

$cities = $pdo->query("SELECT DISTINCT city  FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM sites WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type  FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width,'x',height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL ORDER BY width,height")->fetchAll(PDO::FETCH_COLUMN);

include_once __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom:1.5rem;">
    <a href="mounting.php" class="btn btn-secondary"
        style="background:white;border:1px solid #cbd5e1;color:#475569;padding:0.5rem 1rem;border-radius:8px;font-weight:600;text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<div class="card"
    style="border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.05);padding:0;overflow:visible;background:#fff;">
    <form method="POST" action="create_mounting_po.php" id="rateForm"
        style="display:flex;flex-direction:column;margin:0;">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
        <input type="hidden" name="rate_per_sqft" id="f_rate"
            value="<?php echo $rateData ? htmlspecialchars($rateData['rate_per_sqft']) : '0'; ?>">
        <?php if ($action === 'edit'): ?>
            <?php if ($po_number): ?>
                <input type="hidden" name="po_number" value="<?php echo htmlspecialchars($po_number); ?>">
            <?php else: ?>
                <?php foreach ($rate_ids as $r_id): ?>
                    <input type="hidden" name="rate_ids[]" value="<?php echo htmlspecialchars($r_id); ?>">
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
        <input type="hidden" name="rate_per_sqft" id="f_rate"
            value="<?php echo $rateData ? htmlspecialchars($rateData['rate_per_sqft']) : '0'; ?>">

        <!-- Wizard Progress Tracker -->
        <div style="display: flex; justify-content: center; align-items: center; margin-top: 1.5rem; margin-bottom: 1rem; background: white; padding: 0.6rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); max-width: 400px; margin-left: auto; margin-right: auto;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div id="step-tab-1" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem;">
                    <div class="step-circle" style="width: 24px; height: 24px; background: #0d9488; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid white; box-shadow: 0 0 0 2px #0d9488;">1</div>
                    <span class="step-label" style="font-size: 0.55rem; font-weight: 800; color: #0d9488; text-transform: uppercase;">Details</span>
                </div>
                <div style="width: 30px; height: 2px; background: #e2e8f0; position: relative; margin-top: -12px;">
                    <div id="wizard-progress-line" style="position: absolute; left: 0; top: 0; height: 100%; width: 0%; background: #0d9488; transition: width 0.4s;"></div>
                </div>
                <div id="step-tab-2" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem;">
                    <div class="step-circle" id="step-circle-2" style="width: 24px; height: 24px; background: #fff; color: #94a3b8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; border: 2px solid white; box-shadow: 0 0 0 2px #e2e8f0;">2</div>
                    <span class="step-label" id="step-label-2" style="font-size: 0.55rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Assets</span>
                </div>
            </div>
        </div>

        <!-- STEP 1: Details -->
        <div id="step-1">
        <!-- Config: Client + Campaign + Brand + Mounting Type -->
        <div style="background:#f8fafc;padding:1rem 2.5rem;border-bottom:1px solid #e2e8f0;display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1.5rem;align-items:start;">
            <div class="form-group" style="margin:0;">
                <label style="font-weight:800;color:#475569;font-size:0.65rem;margin-bottom:0.35rem;display:block;text-transform:uppercase;letter-spacing:0.05em;">1. Select Client</label>
                <select name="client_id" id="f_client" required style="background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;padding:0.5rem 0.75rem;width:100%;font-weight:600;font-size:0.85rem;height:38px;">
                    <option value="">Choose Client...</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($rateData && $rateData['client_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-weight:800;color:#475569;font-size:0.65rem;margin-bottom:0.35rem;display:block;text-transform:uppercase;letter-spacing:0.05em;">2. Campaign Name</label>
                <input type="text" name="campaign_name" id="f_campaign" value="<?php echo $rateData ? htmlspecialchars($rateData['campaign_name'] ?? '') : ''; ?>" placeholder="e.g. Summer Campaign" style="background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;padding:0.5rem 0.75rem;width:100%;font-weight:600;font-size:0.85rem;height:38px;box-sizing:border-box;">
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-weight:800;color:#475569;font-size:0.65rem;margin-bottom:0.35rem;display:block;text-transform:uppercase;letter-spacing:0.05em;">3. Brand Name</label>
                <input type="text" name="brand_name" id="f_brand" value="<?php echo $rateData ? htmlspecialchars($rateData['brand_name'] ?? '') : ''; ?>" placeholder="e.g. Brand Name" style="background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;padding:0.5rem 0.75rem;width:100%;font-weight:600;font-size:0.85rem;height:38px;box-sizing:border-box;">
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-weight:800;color:#475569;font-size:0.65rem;margin-bottom:0.35rem;display:block;text-transform:uppercase;letter-spacing:0.05em;">4. Mounting Type</label>
                <select name="mounting_type" id="f_media" style="background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;padding:0.5rem 0.75rem;width:100%;font-weight:600;font-size:0.85rem;height:38px;">
                    <?php
                    $mtypes = ['Standard', 'Heavy Duty', 'Re-Mounting', 'Flex Fitting', 'Vinyl Fitting', 'Backlit Fitting'];
                    foreach ($mtypes as $mt):
                        $sel = ($rateData && $rateData['mounting_type'] === $mt) ? 'selected' : ((!$rateData && $mt === 'Standard') ? 'selected' : '');
                        ?>
                        <option value="<?php echo $mt; ?>" <?php echo $sel; ?>><?php echo $mt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- GST Selection for Group Companies / Branches -->
            <div id="gst_selection_container" style="display: none; grid-column: span 4; margin-top: 0.5rem; background: #f0fdfa; padding: 1rem; border-radius: 12px; border: 1px solid #ccfbf1; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 0.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <label style="color: var(--primary); font-weight: 800; font-size: 0.7rem; margin-bottom: 0; display: block; text-transform: uppercase; letter-spacing: 0.05em;">
                        <i class="fas fa-id-card"></i> Billing GSTIN / State Selection
                    </label>
                    <span id="gst_count_badge" style="background: var(--primary); color: white; font-size: 0.65rem; padding: 2px 8px; border-radius: 50px; font-weight: 700;"></span>
                </div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <select id="selected_gstin" name="billing_gstin" style="flex: 1; min-width: 250px; height: 38px; border: 1.5px solid #5eead4; border-radius: 8px; padding: 0.5rem; background: white; font-weight: 600; font-size: 0.85rem;" onchange="handleGstSelectionChange()">
                        <!-- Dynamic Options -->
                    </select>
                    <div id="gst_details_preview" style="flex: 2; min-width: 300px; background: white; border: 1px solid #ccfbf1; border-radius: 8px; padding: 0.5rem 1rem; font-size: 0.75rem; color: #0f766e; display: flex; align-items: center; gap: 0.5rem; min-height: 38px; box-sizing: border-box;">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="gst_preview_text">Select a GSTIN to see location details</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="display: flex; justify-content: flex-end; padding: 2rem 2.5rem; background: white; border-top: 1px solid #f1f5f9;">
            <button type="button" class="btn btn-primary" onclick="goToStep2()" style="width: 250px; height: 48px; border-radius: 12px; font-weight: 800; font-size: 0.95rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: #0d9488; border-color: #0d9488; color: white; cursor: pointer; border: none;">
                Next Step: Select Assets <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div> <!-- /#step-1 -->

        <!-- STEP 2: Assets -->
        <div id="step-2" style="display: none;">
            <!-- Back to Details Header inside Step 2 -->
            <div style="background: white; padding: 1rem 2.5rem 0.5rem 2.5rem; display: flex; align-items: center; gap: 1rem; border-bottom: 1px solid #f1f5f9;">
                <button type="button" onclick="goToStep1()" class="btn btn-secondary" style="height: 38px; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 800; padding: 0 1.2rem; background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569; cursor: pointer;">
                    <i class="fas fa-arrow-left"></i> Back to Details
                </button>
                <span style="font-size: 0.8rem; font-weight: 700; color: #0d9488; text-transform: uppercase; letter-spacing: 0.05em;">Select Assets to Include</span>
            </div>

            <!-- Site Selection -->
            <div style="padding:1.5rem 2.5rem;display:flex;flex-direction:column;">
            <div class="form-group" style="margin:0;display:flex;flex-direction:column;">
                <label
                    style="display:flex;justify-content:space-between;align-items:center;font-weight:800;color:#1e293b;font-size:0.75rem;margin-bottom:1rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <span>Site Selection</span>
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <button type="button" id="toggleSearchBtn" onclick="toggleSearchCriteria()"
                            style="background:#fff;border:1.5px solid #0d9488;border-radius:20px;padding:6px 14px;font-size:0.65rem;font-weight:800;color:#0d9488;cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <i class="fas fa-eye-slash" id="toggleSearchIcon"></i> <span id="toggleSearchText">HIDE
                                SEARCH CRITERIA</span>
                        </button>
                        <span style="font-size:0.65rem;color:#94a3b8;font-weight:500;text-transform:none;">Pick sites to
                            apply rates</span>
                    </div>
                </label>

                <select name="site_id" id="f_site" style="display:none;">
                    <option value="">All Sites</option>
                </select>

                <div id="multi_site_container" style="flex:1;display:flex;flex-direction:column;">
                    <!-- Filters -->
                    <div id="search_criteria_panel"
                        style="background:#f8fafc;padding:0.75rem 1rem;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:0.75rem;box-shadow:inset 0 2px 4px rgba(0,0,0,0.02);">
                        <div
                            style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;flex-wrap:wrap;gap:1rem;">
                            <div style="display:flex;align-items:center;gap:2rem;">
                                <span
                                    style="font-weight:800;color:#0d9488;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.05em;display:flex;align-items:center;gap:6px;"><i
                                        class="fas fa-filter"></i> Filters</span>
                                <!-- Ownership -->
                                <div style="display:flex;align-items:center;gap:0.75rem;">
                                    <label
                                        style="font-size:0.55rem;font-weight:800;color:#475569;margin:0;text-transform:uppercase;">Ownership:</label>
                                    <div style="display:flex;gap:0.75rem;">
                                        <label
                                            style="font-size:0.65rem;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;color:#1e293b;margin:0;"><input
                                                type="radio" name="ownership" value="all" checked
                                                onchange="filterSitesInModal()"
                                                style="width:12px;height:12px;accent-color:#0d9488;cursor:pointer;">
                                            All</label>
                                        <label
                                            style="font-size:0.65rem;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;color:#1e293b;margin:0;"><input
                                                type="radio" name="ownership" value="HA" onchange="filterSitesInModal()"
                                                style="width:12px;height:12px;accent-color:#0d9488;cursor:pointer;">
                                            Self</label>
                                        <label
                                            style="font-size:0.65rem;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;color:#1e293b;margin:0;"><input
                                                type="radio" name="ownership" value="TA" onchange="filterSitesInModal()"
                                                style="width:12px;height:12px;accent-color:#0d9488;cursor:pointer;">
                                            Vendor</label>
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:0.75rem;">
                                    <label
                                        style="font-size:0.55rem;font-weight:800;color:#475569;margin:0;text-transform:uppercase;">Availability:</label>
                                    <div style="display:flex;gap:0.75rem;">
                                        <label
                                            style="font-size:0.65rem;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;color:#1e293b;margin:0;"><input
                                                type="radio" name="availability" value="available" checked
                                                onchange="filterSitesInModal()" style="accent-color:#0d9488;">
                                            Available</label>
                                        <label
                                            style="font-size:0.65rem;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;color:#1e293b;margin:0;"><input
                                                type="radio" name="availability" value="all"
                                                onchange="filterSitesInModal()" style="accent-color:#0d9488;">
                                            All</label>
                                    </div>
                                </div>
                                <!-- Vendor Dropdown -->
                                <div id="vendor_filter_group" style="display:none;align-items:center;gap:0.75rem;">
                                    <label
                                        style="font-size:0.55rem;font-weight:800;color:#475569;margin:0;text-transform:uppercase;">Vendor:</label>
                                    <select id="filter_vendor" onchange="filterSitesInModal()"
                                        style="padding:2px 8px;font-size:0.7rem;border:1px solid #e2e8f0;border-radius:4px;background:#fff;font-weight:600;height:22px;">
                                        <option value="">All Vendors</option>
                                        <?php foreach ($vendors as $v): ?>
                                            <option value="<?php echo $v['id']; ?>">
                                                <?php echo htmlspecialchars($v['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="button" onclick="resetFilters()"
                                style="background:none;border:none;color:#ef4444;font-size:0.6rem;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:4px;padding:0;text-transform:uppercase;"><i
                                    class="fas fa-undo"></i> Reset</button>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:flex-end;width:100%;">
                            <div style="flex:2 1 200px;min-width:150px;margin-bottom:0;position:relative;"><i
                                    class="fas fa-search"
                                    style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:0.7rem;color:#94a3b8;"></i><input
                                    type="text" id="siteSearch" placeholder="Search by name, code, city..."
                                    oninput="filterSitesInModal()"
                                    style="width:100%;padding:4px 10px 4px 28px;font-size:0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-weight:600;background:#fff;height:30px;font-family:inherit;">
                            </div>
                            <div style="flex:1 1 110px;min-width:90px;margin-bottom:0;"><select id="filter_media"
                                    onchange="filterSitesInModal()"
                                    style="width:100%;padding:4px 8px;font-size:0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-weight:600;background:#fff;height:30px;font-family:inherit;">
                                    <option value="">All Media</option><?php foreach ($mediaTypes as $mt): ?>
                                        <option value="<?php echo htmlspecialchars($mt); ?>">
                                            <?php echo htmlspecialchars($mt); ?></option><?php endforeach; ?>
                                </select></div>
                            <div style="flex:1 1 110px;min-width:90px;margin-bottom:0;"><select id="filter_state"
                                    onchange="filterSitesInModal()"
                                    style="width:100%;padding:4px 8px;font-size:0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-weight:600;background:#fff;height:30px;font-family:inherit;">
                                    <option value="">All States</option><?php foreach ($states as $st): ?>
                                        <option value="<?php echo htmlspecialchars($st); ?>">
                                            <?php echo htmlspecialchars($st); ?></option><?php endforeach; ?>
                                </select></div>
                            <div style="flex:1 1 110px;min-width:90px;margin-bottom:0;"><select id="filter_city"
                                    onchange="filterSitesInModal()"
                                    style="width:100%;padding:4px 8px;font-size:0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-weight:600;background:#fff;height:30px;font-family:inherit;">
                                    <option value="">All Cities</option><?php foreach ($cities as $ct): ?>
                                        <option value="<?php echo htmlspecialchars($ct); ?>">
                                            <?php echo htmlspecialchars($ct); ?></option><?php endforeach; ?>
                                </select></div>
                            <div style="flex:1 1 110px;min-width:90px;margin-bottom:0;"><select id="filter_location"
                                    onchange="filterSitesInModal()"
                                    style="width:100%;padding:4px 8px;font-size:0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-weight:600;background:#fff;height:30px;font-family:inherit;">
                                    <option value="">All Locations</option><?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo htmlspecialchars($loc); ?>">
                                            <?php echo htmlspecialchars($loc); ?></option><?php endforeach; ?>
                                </select></div>
                            <div style="flex:1 1 110px;min-width:90px;margin-bottom:0;"><select id="filter_light"
                                    onchange="filterSitesInModal()"
                                    style="width:100%;padding:4px 8px;font-size:0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-weight:600;background:#fff;height:30px;font-family:inherit;">
                                    <option value="">All Lights</option><?php foreach ($illuminations as $il): ?>
                                        <option value="<?php echo htmlspecialchars($il); ?>">
                                            <?php echo htmlspecialchars($il); ?></option><?php endforeach; ?>
                                </select></div>
                            <div style="flex:1 1 110px;min-width:90px;margin-bottom:0;"><select id="filter_size"
                                    onchange="filterSitesInModal()"
                                    style="width:100%;padding:4px 8px;font-size:0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-weight:600;background:#fff;height:30px;font-family:inherit;">
                                    <option value="">All Sizes</option><?php foreach ($sizes as $sz): ?>
                                        <option value="<?php echo htmlspecialchars($sz); ?>">
                                            <?php echo htmlspecialchars($sz); ?></option><?php endforeach; ?>
                                </select></div>
                        </div>
                    </div>

                    <!-- Select All + Bucket -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.75rem;">
                        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                            <label
                                style="display:flex;align-items:center;cursor:pointer;font-size:0.75rem;font-weight:800;color:#0d9488;margin:0;background:#fff;padding:6px 12px;border-radius:20px;border:1.5px solid #0d9488;">
                                <input type="checkbox" id="selectAllSites"
                                    style="width:16px;height:16px;margin-right:8px;accent-color:#0d9488;cursor:pointer;">
                                SELECT ALL (<span id="filtered_sites_count">0</span> matching)
                            </label>
                            
                            <div style="display: flex; align-items: center; gap: 0.5rem; background: #fff; border: 1.5px solid #0d9488; border-radius: 20px; padding: 3px 8px 3px 12px; transition: all 0.2s; box-shadow: 0 2px 4px rgba(13, 148, 136, 0.05);">
                                <span style="font-size: 0.7rem; font-weight: 800; color: #0d9488; font-family: inherit;">Rate Master:</span>
                                <input type="number" id="rateMasterInput" placeholder="Rate" step="0.01" style="width: 80px; height: 24px; font-size: 0.75rem; font-weight: 800; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 0.4rem; color: #0d9488; outline: none; background: #f8fafc; text-align: right;">
                                <button type="button" onclick="applyRateMaster()" style="background: #0d9488; border: none; color: white; padding: 4px 10px; border-radius: 12px; font-weight: 800; font-size: 0.65rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 4px; height: 24px;">
                                    Apply to Selected
                                </button>
                            </div>
                        </div>
                        <?php if ($action !== 'edit'): ?>
                            <button type="button" onclick="openBucket()"
                                style="background:#f0fdfa;border:1.5px solid #0d9488;color:#0d9488;padding:6px 14px;border-radius:20px;font-weight:800;display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.75rem;">
                                <i class="fas fa-shopping-basket"></i> Selected: <span id="selected-count-btm"
                                    style="background:#0d9488;color:white;padding:0.1rem 0.5rem;border-radius:4px;font-size:0.7rem;">0</span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Table -->
                    <div id="site_checkbox_list"
                        style="flex:1;max-height:520px;overflow-y:auto;border:1px solid #f1f5f9;border-radius:12px 12px 0 0;background:#fff;">
                        <table class="po-site-table" id="site-table" style="width:100%;border-collapse:collapse;">
                            <thead style="background:white;position:sticky;top:0;z-index:10;">
                                <tr style="border-bottom:2px solid #f1f5f9;">
                                    <th
                                        style="width:40px;padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;text-align:center;">
                                        #</th>
                                    <th
                                        style="width:50px;padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;text-align:center;">
                                        <i class="far fa-check-square"></i></th>
                                    <th
                                        style="width:130px;padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">
                                        PREVIEW</th>
                                    <th
                                        style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">
                                        CITY / CODE</th>
                                    <th
                                        style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">
                                        ASSET DETAILS</th>
                                    <th
                                        style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">
                                        SIZE</th>
                                    <th
                                        style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;width:100px;">
                                        HSN</th>
                                    <th
                                        style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;width:140px;">
                                        RATE ₹/SQFT</th>
                                    <th
                                        style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;text-align:right;width:130px;">
                                        TOTAL</th>
                                </tr>
                            </thead>
                            <tbody id="site-rows-body">
                                <tr>
                                    <td colspan="9"
                                        style="text-align:center;padding:4rem;color:#94a3b8;font-size:0.9rem;font-style:italic;">
                                        Select a client to see sites...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="po-pagination-wrap"
                        style="padding:0.75rem 1rem;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border:1px solid #f1f5f9;border-top:none;border-radius:0 0 12px 12px;">
                        <div style="font-size:0.75rem;font-weight:700;color:#64748b;">Showing <span
                                id="po-pg-start">0</span>–<span id="po-pg-end">0</span> of <span
                                id="po-pg-total">0</span> sites</div>
                        <div id="po-pg-numbers" style="display:flex;gap:0.35rem;align-items:center;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bar -->
        <div
            style="background:#f8fafc;padding:1.5rem 2.5rem;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;align-items:center;position:sticky;bottom:0;z-index:100;box-shadow:0 -4px 12px rgba(0,0,0,0.05);">
            <a href="mounting.php"
                style="font-weight:700;color:#64748b;margin-right:1.5rem;font-size:1rem;text-decoration:none;">Discard</a>
            <button type="submit"
                style="background:#0d9488;color:white;border:none;padding:1rem 3rem;border-radius:12px;font-weight:800;font-size:1.1rem;cursor:pointer;box-shadow:0 4px 12px rgba(13,148,136,0.2);">
                <i class="fas fa-save" style="margin-right:0.5rem;"></i> Save Mounting Invoice
            </button>
        </div>
        </div> <!-- /#step-2 -->
    </form>
</div>

<!-- Bucket Drawer -->
<div id="bucket-backdrop" onclick="closeBucket()"
    style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);backdrop-filter:blur(4px);z-index:2000;display:none;">
</div>
<div id="selection-bucket-panel"
    style="position:fixed;top:0;right:-1400px;width:1200px;max-width:95vw;height:100%;background:white;z-index:2001;box-shadow:-10px 0 30px rgba(0,0,0,0.1);transition:all 0.4s cubic-bezier(0.4,0,0.2,1);display:flex;flex-direction:column;">
    <div
        style="display:flex;justify-content:space-between;align-items:center;padding:1.5rem;background:#0d9488;color:white;">
        <div style="display:flex;align-items:center;gap:1rem;"><i class="fas fa-shopping-basket"
                style="font-size:1.2rem;"></i><span style="font-size:1.1rem;font-weight:800;">Selection Review</span>
        </div>
        <div style="display:flex;gap:1rem;align-items:center;">
            <div
                style="background:rgba(255,255,255,0.2);color:white;padding:0.3rem 0.8rem;border-radius:6px;font-weight:800;font-size:0.75rem;">
                <span id="bucket-count">0</span> Assets</div>
            <button type="button" onclick="closeBucket()"
                style="background:rgba(0,0,0,0.1);border:none;color:white;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;">&times;</button>
        </div>
    </div>
    <div id="bucket-empty-msg" style="padding:4rem 2rem;text-align:center;color:#94a3b8;font-weight:700;"><i
            class="fas fa-shopping-cart" style="font-size:3rem;margin-bottom:1rem;display:block;opacity:0.3;"></i>Select
        assets from the list.</div>
    <div id="bucket-list" style="flex:1;overflow-y:auto;padding:1rem;"></div>
    <div style="padding:1.5rem;border-top:1px solid #e2e8f0;background:#f8fafc;">
        <button type="button" onclick="closeBucket()"
            style="width:100%;height:45px;border-radius:10px;font-weight:800;background:#0d9488;border:none;color:white;cursor:pointer;">CONTINUE
            SELECTION</button>
    </div>
</div>

<style>
    .po-site-table {
        width: 100%;
        border-collapse: collapse;
    }

    .po-site-table th {
        background: white;
        border-bottom: 2px solid #f1f5f9;
    }

    .po-site-table td {
        padding: 0.7rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f8fafc;
    }

    .site-row {
        transition: background 0.15s;
    }

    .site-row:hover {
        background: #f8fafc !important;
    }

    .site-row.selected {
        background: #f0fdfa !important;
    }

    .site-chk-input {
        width: 18px !important;
        height: 18px !important;
        accent-color: #0d9488;
        cursor: pointer;
    }

    .site-rate-input {
        width: 100px;
        height: 30px;
        font-size: 0.85rem;
        font-weight: 800;
        border-radius: 6px;
        border: 1.5px solid #e2e8f0;
        padding: 0 0.4rem;
        color: #0d9488;
        text-align: right;
        background: #f8fafc;
        transition: border-color 0.2s;
    }

    .site-rate-input:focus {
        background: #fff;
        border-color: #0d9488;
        outline: none;
    }

    .po-pg-btn {
        min-width: 30px;
        height: 30px;
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 7px;
        cursor: pointer;
        font-weight: 700;
        font-size: 0.78rem;
        transition: all 0.2s;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 6px;
    }

    .po-pg-btn:hover:not(:disabled) {
        border-color: #0d9488;
        color: #0d9488;
        background: #f0fdfa;
    }

    .po-pg-btn.active {
        background: #0d9488;
        color: white;
        border-color: #0d9488;
    }

    .po-pg-btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }

    .po-pg-dots {
        color: #94a3b8;
        font-weight: 800;
        padding: 0 2px;
        font-size: 0.8rem;
    }
</style>

<script>
    const sitesData = <?php echo json_encode($sites); ?>;
    const clientsData = <?php echo json_encode($clients); ?>;
    const isEditMode = <?php echo $action === 'edit' ? 'true' : 'false'; ?>;
    const initialRateData = <?php echo $rateData ? json_encode($rateData) : 'null'; ?>;
    const selectedSitesData = <?php echo json_encode($selectedSitesData); ?>;
    const baseUrl = "<?php echo BASE_URL; ?>";
    let currentPage = 1;
    const pageSize = 10;

    // Pagination
    function renderPagination() {
        const all = Array.from(document.querySelectorAll('tr.site-row'));
        const active = all.filter(r => !r.classList.contains('search-hidden'));
        const total = active.length, start = (currentPage - 1) * pageSize, end = Math.min(start + pageSize, total);
        all.forEach(r => r.style.display = 'none');
        active.forEach((r, i) => { const s = r.querySelector('.sno-cell'); if (s) s.innerText = i + 1; });
        active.slice(start, end).forEach(r => r.style.display = '');
        const el = id => document.getElementById(id);
        if (el('po-pg-start')) el('po-pg-start').innerText = total === 0 ? 0 : start + 1;
        if (el('po-pg-end')) el('po-pg-end').innerText = end;
        if (el('po-pg-total')) el('po-pg-total').innerText = total;
        if (el('filtered_sites_count')) el('filtered_sites_count').innerText = total;
        updatePgControls(total);
    }
    function updatePgControls(total) {
        const tp = Math.ceil(total / pageSize), c = document.getElementById('po-pg-numbers');
        if (!c) return; c.innerHTML = ''; if (tp <= 1) return;
        const btn = (html, dis, act, cb) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'po-pg-btn' + (act ? ' active' : ''); b.innerHTML = html; b.disabled = dis; if (!dis) b.onclick = cb; return b; };
        const dots = () => { const s = document.createElement('span'); s.className = 'po-pg-dots'; s.innerText = '…'; return s; };
        c.appendChild(btn('<i class="fas fa-angle-double-left"></i>', currentPage === 1, false, () => { currentPage = 1; renderPagination(); }));
        c.appendChild(btn('<i class="fas fa-angle-left"></i>', currentPage === 1, false, () => { currentPage--; renderPagination(); }));
        let sp = Math.max(1, currentPage - 2), ep = Math.min(tp, sp + 4); if (ep - sp < 4) sp = Math.max(1, ep - 4);
        if (sp > 1) { c.appendChild(btn('1', false, false, () => { currentPage = 1; renderPagination(); })); if (sp > 2) c.appendChild(dots()); }
        for (let i = sp; i <= ep; i++) { const pg = i; c.appendChild(btn(i, false, i === currentPage, () => { currentPage = pg; renderPagination(); })); }
        if (ep < tp) { if (ep < tp - 1) c.appendChild(dots()); c.appendChild(btn(tp, false, false, () => { currentPage = tp; renderPagination(); })); }
        c.appendChild(btn('<i class="fas fa-angle-right"></i>', currentPage === tp, false, () => { currentPage++; renderPagination(); }));
        c.appendChild(btn('<i class="fas fa-angle-double-right"></i>', currentPage === tp, false, () => { currentPage = tp; renderPagination(); }));
    }

    document.getElementById('f_client').addEventListener('change', buildSiteTable);

    document.getElementById('f_rate').addEventListener('input', function () {
        document.querySelectorAll('.site-chk-input:checked').forEach(chk => {
            const row = chk.closest('tr.site-row'), ri = row && row.querySelector('.site-rate-input');
            if (ri && ri.dataset.touched !== 'true') { ri.value = this.value; updateRowTotal(row); }
        });
        calculateTotal(); updateBucketUI();
    });

    document.getElementById('selectAllSites').addEventListener('change', function () {
        const mr = document.getElementById('f_rate').value;
        document.querySelectorAll('tr.site-row').forEach(tr => {
            if (tr.classList.contains('search-hidden') || tr.style.display === 'none') return;
            const chk = tr.querySelector('.site-chk-input'), ri = tr.querySelector('.site-rate-input');
            if (!chk) return;
            chk.checked = this.checked;
            if (this.checked) { tr.classList.add('selected'); if (mr && ri && ri.dataset.touched !== 'true') { ri.value = mr; updateRowTotal(tr); } }
            else tr.classList.remove('selected');
        });
        calculateTotal(); updateBucketUI();
    });

    function setMedia(val) { const s = document.getElementById('f_media'); if (s) s.value = val; }
    function updateRowTotal(tr) {
        const chk = tr.querySelector('.site-chk-input'), ri = tr.querySelector('.site-rate-input'), tc = tr.querySelector('.row-total-cell');
        if (!chk || !ri || !tc) return;
        const t = (parseFloat(chk.dataset.sqft) || 0) * (parseFloat(ri.value) || 0);
        tc.innerText = t > 0 ? '₹' + t.toLocaleString(undefined, { maximumFractionDigits: 0 }) : '₹0';
    }

    function buildSiteTable() {
        const tbody = document.getElementById('site-rows-body');
        const sel = document.getElementById('f_site');
        sel.innerHTML = '<option value="">All Sites</option>';
        tbody.innerHTML = '';

        sitesData.forEach((s, idx) => {
            const sqft = parseFloat(s.width) * parseFloat(s.height);
            const allImgs = s.all_images || s.thumbnail || '';
            const vName = s.vendor_name || '';
            const opt = document.createElement('option'); opt.value = s.id; opt.text = `${s.site_code} - ${s.name}`; sel.add(opt);
            const thumbHtml = s.thumbnail
                ? `<div style="position:relative;width:120px;height:75px;"><img src="${baseUrl}uploads/sites/${s.thumbnail}" onclick="openLightboxSlider('${allImgs}','${s.id}')" style="width:100%;height:100%;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;cursor:pointer;box-shadow:0 2px 4px rgba(0,0,0,0.08);"></div>`
                : `<div style="width:80px;height:50px;border-radius:8px;background:#f8fafc;border:1px dashed #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:#94a3b8;font-weight:700;">No Img</div>`;
            const tb = s.type ? `<span style="background:#ecfdf5;color:#059669;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${s.type}</span>` : '';
            const lb = s.light_type ? `<span style="background:#f1f5f9;color:#475569;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${s.light_type}</span>` : '';
            const ob = s.owner_type ? `<span style="background:#f1f5f9;color:#475569;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${s.owner_type}${vName ? ' - ' + vName : ''}</span>` : '';
            const tr = document.createElement('tr'); tr.className = 'site-row';
            tr.dataset.id = s.id; tr.dataset.code = s.site_code || ''; tr.dataset.name = s.name || '';
            tr.dataset.city = s.city || ''; tr.dataset.state = s.state || ''; tr.dataset.type = s.type || '';
            tr.dataset.illumination = s.light_type || ''; tr.dataset.owner = s.owner_type || '';
            tr.dataset.status = s.status || ''; tr.dataset.size = `${s.width}x${s.height}`;
            tr.dataset.vendorId = s.vendor_id || '';
            tr.dataset.thumbnail = s.thumbnail || ''; tr.dataset.images = allImgs;
            tr.dataset.location = s.location || ''; tr.dataset.width = s.width || ''; tr.dataset.height = s.height || '';
            tr.dataset.hsn = s.mounting_hsn || s.hsn_code || '';
            const hsnDisplay = s.mounting_hsn || s.hsn_code || '';
            tr.innerHTML = `
            <td class="sno-cell" style="padding:0.6rem 1rem;font-weight:700;color:#64748b;text-align:center;font-size:0.85rem;">${idx + 1}</td>
            <td style="padding:0.6rem 1rem;text-align:center;"><input type="checkbox" name="site_ids[]" value="${s.id}" class="site-chk-input" data-sqft="${sqft}"></td>
            <td style="padding:0.6rem 1rem;">${thumbHtml}</td>
            <td style="padding:0.6rem 1rem;"><div style="font-weight:800;color:#1e293b;font-size:0.8rem;margin-bottom:2px;">${s.city || ''}</div><div style="color:#f97316;font-size:0.7rem;font-weight:800;">${s.site_code}</div></td>
            <td style="padding:0.6rem 1rem;"><div style="font-weight:800;color:#1e293b;font-size:0.8rem;margin-bottom:2px;">${s.name}</div>${s.location ? `<div style="font-size:0.65rem;color:#64748b;margin-bottom:3px;">${s.location}</div>` : ''}<div style="display:flex;gap:0.3rem;flex-wrap:wrap;margin-top:2px;">${tb}${lb}${ob}</div></td>
            <td style="padding:0.6rem 1rem;"><div style="font-weight:800;color:#1e293b;font-size:0.8rem;">${s.width}' x ${s.height}'</div><div style="font-size:0.65rem;color:#94a3b8;font-weight:700;">${sqft.toLocaleString()} SQFT</div></td>
            <td style="padding:0.6rem 1rem;">${hsnDisplay ? `<span style="background:#f0fdfa;color:#0d9488;padding:0.2rem 0.5rem;border-radius:4px;font-size:0.7rem;font-weight:800;font-family:monospace;">${hsnDisplay}</span>` : '<span style="color:#cbd5e1;font-size:0.7rem;">—</span>'}</td>
            <td style="padding:0.6rem 1rem;"><div style="font-size:0.5rem;color:#0d9488;font-weight:900;text-transform:uppercase;margin-bottom:3px;">Rate ₹/SQFT</div><input type="number" step="0.01" name="individual_rates[${s.id}]" class="site-rate-input" placeholder="₹" data-touched="false"></td>
            <td style="padding:0.6rem 1rem;text-align:right;"><div style="font-size:0.55rem;color:#64748b;font-weight:800;text-transform:uppercase;">Total</div><div class="row-total-cell" style="font-weight:900;color:#0d9488;font-size:0.9rem;">₹0</div></td>`;
            const chk = tr.querySelector('.site-chk-input'), ri = tr.querySelector('.site-rate-input');
            chk.onchange = function () { const mr = document.getElementById('f_rate').value; if (this.checked) { tr.classList.add('selected'); if (mr && ri.dataset.touched !== 'true') { ri.value = mr; updateRowTotal(tr); } } else tr.classList.remove('selected'); calculateTotal(); updateBucketUI(); };
            ri.oninput = function () { this.dataset.touched = 'true'; if (this.value !== '') { chk.checked = true; tr.classList.add('selected'); } updateRowTotal(tr); calculateTotal(); updateBucketUI(); };
            if (isEditMode && selectedSitesData[s.id] !== undefined) { chk.checked = true; tr.classList.add('selected'); ri.value = selectedSitesData[s.id]; ri.dataset.touched = 'true'; updateRowTotal(tr); }
            tbody.appendChild(tr);
        });
        filterSitesInModal(); calculateTotal(); updateBucketUI();
    }

    function filterSitesInModal() {
        const q = document.getElementById('siteSearch').value.toLowerCase();

        const ownershipEl = document.querySelector('input[name="ownership"]:checked');
        const ownership = ownershipEl ? ownershipEl.value : 'all';

        const vendorGroup = document.getElementById('vendor_filter_group');
        if (vendorGroup) {
            vendorGroup.style.display = (ownership === 'TA') ? 'flex' : 'none';
            if (ownership !== 'TA') document.getElementById('filter_vendor').value = '';
        }

        const availEl = document.querySelector('input[name="availability"]:checked');
        const availability = availEl ? availEl.value : 'all';

        const media = document.getElementById('filter_media').value;
        const state = document.getElementById('filter_state').value;
        const city = document.getElementById('filter_city').value;
        const loc = document.getElementById('filter_location').value;
        const light = document.getElementById('filter_light').value;
        const size = document.getElementById('filter_size').value;
        const vendorId = document.getElementById('filter_vendor') ? document.getElementById('filter_vendor').value : '';

        document.querySelectorAll('tr.site-row').forEach(row => {
            const iCode = (row.dataset.code || '').toLowerCase();
            const iName = (row.dataset.name || '').toLowerCase();
            const iCity = (row.dataset.city || '').toLowerCase();
            const iState = (row.dataset.state || '').toLowerCase();
            const iLoc = (row.dataset.location || '').toLowerCase();
            const itemVendor = row.dataset.vendorId || '';

            const matchesSearch = !q || iCode.includes(q) || iName.includes(q) || iCity.includes(q) || iState.includes(q) || iLoc.includes(q);
            const matchesOwnership = ownership === 'all' || row.dataset.owner === ownership;
            const matchesAvailability = availability === 'all' || (row.dataset.status || '').toLowerCase() === 'available';
            const matchesVendor = !vendorId || itemVendor == vendorId;
            const matchesMedia = !media || row.dataset.type === media;
            const matchesState = !state || iState === state.toLowerCase();
            const matchesCity = !city || iCity === city.toLowerCase();
            const matchesLocation = !loc || iLoc === loc.toLowerCase();
            const matchesLight = !light || row.dataset.illumination === light;
            const matchesSize = !size || row.dataset.size === size;

            const ok = matchesSearch && matchesOwnership && matchesAvailability && matchesVendor && matchesMedia && matchesState && matchesCity && matchesLocation && matchesLight && matchesSize;
            ok ? row.classList.remove('search-hidden') : row.classList.add('search-hidden');
        });
        currentPage = 1; renderPagination();
    }

    function resetFilters() {
        document.getElementById('siteSearch').value = '';
        const av = document.querySelector('input[name="availability"][value="available"]');
        if (av) av.checked = true;
        const ow = document.querySelector('input[name="ownership"][value="all"]');
        if (ow) ow.checked = true;
        ['filter_media', 'filter_state', 'filter_city', 'filter_location', 'filter_light', 'filter_size'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        const vf = document.getElementById('filter_vendor');
        if (vf) vf.value = '';
        filterSitesInModal();
    }

    function calculateTotal() {
        const master = parseFloat(document.getElementById('f_rate').value) || 0;
        let sq = 0, net = 0;
        document.querySelectorAll('.site-chk-input:checked').forEach(chk => {
            const row = chk.closest('tr.site-row'), ri = row && row.querySelector('.site-rate-input');
            const sqft = parseFloat(chk.dataset.sqft) || 0, r = (ri ? parseFloat(ri.value) : 0) || master;
            sq += sqft; net += sqft * r;
        });
    }

    function toggleSearchCriteria() {
        const p = document.getElementById('search_criteria_panel'), i = document.getElementById('toggleSearchIcon'), t = document.getElementById('toggleSearchText'), b = document.getElementById('toggleSearchBtn');
        if (p.style.display === 'none') { p.style.display = 'block'; i.className = 'fas fa-eye-slash'; t.innerText = 'HIDE SEARCH CRITERIA'; b.style.borderColor = '#0d9488'; b.style.color = '#0d9488'; }
        else { p.style.display = 'none'; i.className = 'fas fa-eye'; t.innerText = 'SHOW SEARCH CRITERIA'; b.style.borderColor = '#94a3b8'; b.style.color = '#64748b'; }
    }

    document.getElementById('rateForm').addEventListener('submit', function (e) {
        if (document.querySelectorAll('.site-chk-input:checked').length === 0) { e.preventDefault(); Swal.fire({ title: 'Error', text: 'Please select at least one site.', icon: 'error', confirmButtonColor: '#0d9488' }); }
    });

    function applyRateMaster() {
        const rateVal = document.getElementById('rateMasterInput').value;
        if (rateVal === '' || isNaN(parseFloat(rateVal))) {
            Swal.fire({
                title: 'Error',
                text: 'Please enter a valid rate.',
                icon: 'error',
                confirmButtonColor: '#0d9488'
            });
            return;
        }
        const rate = parseFloat(rateVal);
        const checked = document.querySelectorAll('.site-chk-input:checked');
        if (checked.length === 0) {
            Swal.fire({
                title: 'Error',
                text: 'No locations selected. Please select locations first.',
                icon: 'error',
                confirmButtonColor: '#0d9488'
            });
            return;
        }
        checked.forEach(chk => {
            const row = chk.closest('tr.site-row');
            if (row) {
                const rateInput = row.querySelector('.site-rate-input');
                if (rateInput) {
                    rateInput.value = rate;
                    rateInput.dataset.touched = 'true';
                    updateRowTotal(row);
                }
            }
        });
        calculateTotal();
        updateBucketUI();
        Swal.fire({
            icon: 'success',
            title: 'Rate Applied',
            text: 'Applied ₹' + rate + ' to ' + checked.length + ' selected location(s).',
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    function openBucket() { document.getElementById('selection-bucket-panel').style.right = '0'; document.getElementById('bucket-backdrop').style.display = 'block'; document.body.style.overflow = 'hidden'; }
    function closeBucket() { document.getElementById('selection-bucket-panel').style.right = '-1400px'; document.getElementById('bucket-backdrop').style.display = 'none'; document.body.style.overflow = ''; }

    function uncheckSiteInBucket(siteId) { const row = document.querySelector(`tr.site-row[data-id="${siteId}"]`); if (!row) return; const chk = row.querySelector('.site-chk-input'); if (chk) { chk.checked = false; row.classList.remove('selected'); calculateTotal(); updateBucketUI(); } }
    function syncRateFromBucket(siteId, rate) { const row = document.querySelector(`tr.site-row[data-id="${siteId}"]`); if (!row) return; const ri = row.querySelector('.site-rate-input'), chk = row.querySelector('.site-chk-input'); if (ri) { ri.value = rate; ri.dataset.touched = 'true'; if (rate !== '' && chk) { chk.checked = true; row.classList.add('selected'); } updateRowTotal(row); calculateTotal(); updateBucketUI(); } }

    function updateBucketUI() {
        const bl = document.getElementById('bucket-list'), em = document.getElementById('bucket-empty-msg'), bc = document.getElementById('bucket-count'), sb = document.getElementById('selected-count-btm');
        const checked = document.querySelectorAll('.site-chk-input:checked'), count = checked.length;
        if (bc) bc.innerText = count; if (sb) sb.innerText = count;
        if (count === 0) { if (em) em.style.display = 'block'; if (bl) bl.innerHTML = ''; return; }
        if (em) em.style.display = 'none';
        let html = `<table style="width:100%;border-collapse:collapse;font-family:inherit;"><thead style="background:white;position:sticky;top:0;z-index:10;"><tr style="border-bottom:2px solid #f1f5f9;">
        <th style="width:40px;padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">#</th>
        <th style="width:50px;padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">ACT</th>
        <th style="width:110px;padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">PREVIEW</th>
        <th style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">CITY / CODE</th>
        <th style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">ASSET DETAILS</th>
        <th style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;">SIZE</th>
        <th style="padding:0.8rem 1rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;width:140px;">RATE ₹/SQFT</th>
        <th style="padding:0.8rem 1rem;text-align:right;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;width:130px;">TOTAL</th>
    </tr></thead><tbody>`;
        checked.forEach((chk, idx) => {
            const row = chk.closest('tr.site-row'); if (!row) return;
            const sid = row.dataset.id, code = row.dataset.code, name = row.dataset.name, city = row.dataset.city || '', loc = row.dataset.location || '',
                type = row.dataset.type || '', light = row.dataset.illumination || '', owner = row.dataset.owner || '',
                w = row.dataset.width || '', h = row.dataset.height || '', thumb = row.dataset.thumbnail || '', imgs = row.dataset.images || thumb;
            const sqft = parseFloat(chk.dataset.sqft) || 0, ri = row.querySelector('.site-rate-input');
            const rate = (ri ? ri.value : '') || document.getElementById('f_rate').value || 0;
            const total = sqft * parseFloat(rate);
            const tHtml = thumb ? `<div style="width:90px;height:58px;"><img src="${baseUrl}uploads/sites/${thumb}" onclick="openLightboxSlider('${imgs}','${sid}')" style="width:100%;height:100%;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;cursor:pointer;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'"></div>` : `<div style="width:70px;height:44px;border-radius:8px;background:#f8fafc;border:1px dashed #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:#94a3b8;font-weight:700;">No Img</div>`;
            const tb2 = type ? `<span style="background:#ecfdf5;color:#059669;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${type}</span>` : '';
            const lb2 = light ? `<span style="background:#f1f5f9;color:#475569;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${light}</span>` : '';
            const ob2 = owner ? `<span style="background:#f1f5f9;color:#475569;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${owner}</span>` : '';
            html += `<tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
            <td style="padding:0.7rem 1rem;font-weight:700;color:#64748b;">${idx + 1}</td>
            <td style="padding:0.7rem 1rem;"><button type="button" onclick="uncheckSiteInBucket('${sid}')" style="background:#fee2e2;color:#ef4444;border:none;width:28px;height:28px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fas fa-trash-alt" style="font-size:0.7rem;"></i></button></td>
            <td style="padding:0.7rem 1rem;">${tHtml}</td>
            <td style="padding:0.7rem 1rem;"><div style="font-weight:800;color:#1e293b;font-size:0.8rem;margin-bottom:2px;">${city}</div><div style="color:#f97316;font-size:0.7rem;font-weight:800;">${code}</div></td>
            <td style="padding:0.7rem 1rem;max-width:220px;"><div style="font-weight:800;color:#1e293b;font-size:0.8rem;margin-bottom:2px;">${name}</div>${loc ? `<div style="font-size:0.65rem;color:#64748b;margin-bottom:3px;">${loc}</div>` : ''}<div style="display:flex;gap:0.25rem;flex-wrap:wrap;">${tb2}${lb2}${ob2}</div></td>
            <td style="padding:0.7rem 1rem;"><div style="font-weight:800;color:#1e293b;font-size:0.8rem;">${w}' x ${h}'</div><div style="font-size:0.65rem;color:#94a3b8;font-weight:700;">${sqft.toLocaleString()} SQFT</div></td>
            <td style="padding:0.7rem 1rem;"><div style="font-size:0.5rem;color:#0d9488;font-weight:900;text-transform:uppercase;margin-bottom:3px;">Rate ₹/SQFT</div><input type="number" step="0.01" value="${ri ? ri.value : ''}" oninput="syncRateFromBucket('${sid}',this.value)" placeholder="₹" style="width:100px;height:30px;font-size:0.85rem;font-weight:800;border-radius:6px;border:1.5px solid #e2e8f0;padding:0 0.4rem;color:#0d9488;text-align:right;background:#f8fafc;"></td>
            <td style="padding:0.7rem 1rem;text-align:right;font-weight:900;color:#0d9488;font-size:0.95rem;">₹${total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>`;
        });
        html += '</tbody></table>'; if (bl) bl.innerHTML = html;
    }

function initGstSelection(clientId, savedGst) {
    const gstContainer = document.getElementById('gst_selection_container');
    const gstSelect = document.getElementById('selected_gstin');
    const gstBadge = document.getElementById('gst_count_badge');
    const gstPreview = document.getElementById('gst_preview_text');

    if (!clientId) {
        gstContainer.style.display = 'none';
        return;
    }

    fetch(`../../ajax/get_partner_details.php?id=${clientId}`)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const p = res.data;
            gstSelect.innerHTML = '';
            
            let gsts = [];
            if (p.gstin) {
                gsts.push({ gstin: p.gstin, state: 'Primary', city: '', district: '', address: 'Main Address' });
            }

            if (p.additional_gst) {
                try {
                    const extra = JSON.parse(p.additional_gst);
                    if (Array.isArray(extra)) {
                        gsts = gsts.concat(extra);
                    } else if (typeof extra === 'object') {
                        gsts = gsts.concat(Object.values(extra));
                    }
                } catch(e) { console.error("GST Parse Error", e); }
            }

            if (gsts.length > 0) {
                gstContainer.style.display = 'grid';
                gstBadge.innerText = `${gsts.length} GST Records Found`;
                
                gsts.forEach((g, idx) => {
                    const opt = document.createElement('option');
                    opt.value = g.gstin;
                    opt.text = `${g.gstin} - ${g.state || ''} ${g.city ? '(' + g.city + ')' : ''}`;
                    opt.dataset.details = JSON.stringify(g);
                    if (savedGst && g.gstin === savedGst) {
                        opt.selected = true;
                    }
                    gstSelect.add(opt);
                });
                
                handleGstSelectionChange();
            } else {
                gstContainer.style.display = 'none';
            }
        } else {
            gstContainer.style.display = 'none';
        }
    });
}

function handleGstSelectionChange() {
    const select = document.getElementById('selected_gstin');
    const preview = document.getElementById('gst_preview_text');
    
    if (select.selectedIndex === -1) return;
    
    const data = JSON.parse(select.options[select.selectedIndex].dataset.details);
    let text = "";
    if (data.address) text += data.address;
    if (data.city) text += (text ? ", " : "") + data.city;
    if (data.state) text += (text ? ", " : "") + data.state;
    
    preview.innerText = text || "No specific location details";
}

function goToStep1() {
    document.getElementById('step-2').style.display = 'none';
    document.getElementById('step-1').style.display = 'block';
    document.getElementById('wizard-progress-line').style.width = '0%';
    
    // Reset Step 2 styling in tracker
    const stepCircle2 = document.getElementById('step-circle-2');
    const stepLabel2 = document.getElementById('step-label-2');
    if (stepCircle2) {
        stepCircle2.style.background = '#fff';
        stepCircle2.style.color = '#94a3b8';
        stepCircle2.style.boxShadow = '0 0 0 2px #e2e8f0';
    }
    if (stepLabel2) {
        stepLabel2.style.color = '#94a3b8';
    }
}

function goToStep2() {
    const client = document.getElementById('f_client').value;
    if (!client) {
        Swal.fire({
            title: 'Required',
            text: 'Please select a Client.',
            icon: 'warning',
            confirmButtonColor: '#0d9488'
        });
        return;
    }
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'block';
    document.getElementById('wizard-progress-line').style.width = '100%';
    
    // Update Step 2 styling in tracker
    const stepCircle2 = document.getElementById('step-circle-2');
    const stepLabel2 = document.getElementById('step-label-2');
    if (stepCircle2) {
        stepCircle2.style.background = '#0d9488';
        stepCircle2.style.color = 'white';
        stepCircle2.style.boxShadow = '0 0 0 2px #0d9488';
    }
    if (stepLabel2) {
        stepLabel2.style.color = '#0d9488';
    }
}

    document.addEventListener('DOMContentLoaded', () => {
        resetFilters();
        
        // Listen to client change
        const clientSelect = document.getElementById('f_client');
        if (clientSelect) {
            clientSelect.addEventListener('change', () => {
                initGstSelection(clientSelect.value);
            });
        }

        if (isEditMode && initialRateData) {
            document.getElementById('f_client').value = initialRateData.client_id;
            buildSiteTable();
            setMedia(initialRateData.mounting_type);
            const savedGst = <?php echo json_encode($rateData['billing_gstin'] ?? ''); ?>;
            initGstSelection(initialRateData.client_id, savedGst);
        } else {
            const cId = new URLSearchParams(window.location.search).get('client_id');
            if (cId) {
                document.getElementById('f_client').value = cId;
                initGstSelection(cId);
            }
            buildSiteTable();
        }
        calculateTotal(); updateBucketUI();
        
        // Initialize Searchable Dropdowns
        (function() {
            function tryInit() {
                if (typeof initSearchableSelect === 'function') {
                    initSearchableSelect('f_client', 'Choose Client...');
                    if (document.getElementById('filter_vendor')) {
                        initSearchableSelect('filter_vendor', 'Search Vendor...');
                    }
                    console.log("Searchable selects initialized successfully on create_mounting_po.php");
                } else {
                    console.warn("initSearchableSelect function not available yet, retrying on window load...");
                    window.addEventListener('load', () => {
                        if (typeof initSearchableSelect === 'function') {
                            initSearchableSelect('f_client', 'Choose Client...');
                            if (document.getElementById('filter_vendor')) {
                                initSearchableSelect('filter_vendor', 'Search Vendor...');
                            }
                            console.log("Searchable selects initialized successfully on window load");
                        } else {
                            console.error("initSearchableSelect function could not be loaded!");
                        }
                    });
                }
            }
            tryInit();
        })();
    });
</script>

<!-- Lightbox -->
<div id="po-lightbox" onclick="closeLightbox()"
    style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);z-index:9999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(10px);">
    <div style="position:relative;max-width:90%;max-height:90%;display:flex;align-items:center;justify-content:center;"
        onclick="event.stopPropagation()">
        <button id="lb-prev" onclick="prevSlide(event)"
            style="position:absolute;left:-75px;background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);width:48px;height:48px;border-radius:50%;display:none;align-items:center;justify-content:center;cursor:pointer;font-size:1.4rem;"><i
                class="fas fa-chevron-left"></i></button>
        <div style="position:relative;"><img id="lb-img" src=""
                style="max-width:100%;max-height:85vh;border-radius:16px;box-shadow:0 30px 60px rgba(0,0,0,0.8);border:2px solid rgba(255,255,255,0.15);display:block;">
            <div id="lb-badge"
                style="position:absolute;bottom:16px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.7);color:white;padding:5px 14px;border-radius:50px;font-weight:800;font-size:0.8rem;backdrop-filter:blur(5px);display:none;">
            </div>
        </div>
        <button id="lb-next" onclick="nextSlide(event)"
            style="position:absolute;right:-75px;background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);width:48px;height:48px;border-radius:50%;display:none;align-items:center;justify-content:center;cursor:pointer;font-size:1.4rem;"><i
                class="fas fa-chevron-right"></i></button>
        <div onclick="closeLightbox()"
            style="position:absolute;top:-55px;right:-55px;color:white;font-size:2.5rem;cursor:pointer;opacity:0.6;"
            onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</div>
    </div>
</div>
<script>
    let lbImages = [], lbIndex = 0, lbSiteId = null;
    function openLightboxSlider(imageString, siteId) { if (!imageString) return; lbSiteId = siteId; lbImages = imageString.split(',').filter(i => i.trim() !== ''); lbIndex = 0; renderLbImage(); document.getElementById('po-lightbox').style.display = 'flex'; document.body.style.overflow = 'hidden'; const show = lbImages.length > 1; document.getElementById('lb-prev').style.display = show ? 'flex' : 'none'; document.getElementById('lb-next').style.display = show ? 'flex' : 'none'; }
    function renderLbImage() { const img = document.getElementById('lb-img'), badge = document.getElementById('lb-badge'); if (!img) return; img.src = baseUrl + 'uploads/sites/' + lbImages[lbIndex]; badge.style.display = lbImages.length > 1 ? 'block' : 'none'; badge.innerText = (lbIndex + 1) + ' / ' + lbImages.length; }
    function nextSlide(e) { if (e) e.stopPropagation(); lbIndex = (lbIndex + 1) % lbImages.length; renderLbImage(); }
    function prevSlide(e) { if (e) e.stopPropagation(); lbIndex = (lbIndex - 1 + lbImages.length) % lbImages.length; renderLbImage(); }
    function closeLightbox() { document.getElementById('po-lightbox').style.display = 'none'; document.body.style.overflow = ''; }
    document.addEventListener('keydown', function (e) { const lb = document.getElementById('po-lightbox'); if (lb && lb.style.display === 'flex') { if (e.key === 'ArrowRight') nextSlide(); if (e.key === 'ArrowLeft') prevSlide(); if (e.key === 'Escape') closeLightbox(); } });
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>