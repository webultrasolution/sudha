<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('inventory', 'view');

// Handle Form Submissions (AJAX & POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_site' || $_POST['action'] === 'edit_site') {
        $code = clean($_POST['site_code']);
        $name = clean($_POST['name']);
        $location = clean($_POST['location']);
        $city = clean($_POST['city']);
        $district = clean($_POST['district'] ?? '');
        $area = clean($_POST['area'] ?? '');
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $type = clean($_POST['type']);
        $width = floatval($_POST['width']);
        $height = floatval($_POST['height']);
        $owner_type = clean($_POST['owner_type']);
        $vendor_id = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
        $card_rate = floatval($_POST['card_rate']);
        $purchase_rate = floatval($_POST['purchase_rate']);
        $facing = clean($_POST['facing']);
        $light_type = clean($_POST['light_type']);
        $hsn_code = clean($_POST['hsn_code'] ?? '998366');
        $mounting_hsn = clean($_POST['mounting_hsn'] ?? '');
        $vendor_gst = clean($_POST['vendor_gst'] ?? '');
        $grade = clean($_POST['grade']);
        $available_from = !empty($_POST['available_from']) ? $_POST['available_from'] : date('Y-m-d');

        if ($_POST['action'] === 'add_site') {
            requirePermission('inventory', 'add');
            $checkStmt = $pdo->prepare("SELECT id FROM sites WHERE site_code = ?");
            $checkStmt->execute([$code]);
            if ($checkStmt->fetch()) {
                header("Location: sites.php?error=" . urlencode("Asset code '$code' already exists. Duplicate asset codes are not allowed."));
                exit;
            }
            try {
                $stmt = $pdo->prepare("INSERT INTO sites (site_code, name, location, area, city, district, latitude, longitude, type, width, height, facing, light_type, hsn_code, mounting_hsn, vendor_gst, grade, owner_type, vendor_id, card_rate, purchase_rate, available_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $location, $area, $city, $district, $latitude, $longitude, $type, $width, $height, $facing, $light_type, $hsn_code, $mounting_hsn, $vendor_gst, $grade, $owner_type, $vendor_id, $card_rate, $purchase_rate, $available_from]);
                $site_id = $pdo->lastInsertId();

                // Handle Multi-Image Upload
                if (!empty($_FILES['site_images']['name'][0])) {
                    $uploadDir = __DIR__ . '/../../uploads/sites/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0777, true);

                    foreach ($_FILES['site_images']['name'] as $key => $val) {
                        $filename = time() . '_' . $site_id . '_' . str_replace(',', '', basename($_FILES['site_images']['name'][$key]));
                        $targetFile = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['site_images']['tmp_name'][$key], $targetFile)) {
                            $pdo->prepare("INSERT INTO site_images (site_id, filename) VALUES (?, ?)")->execute([$site_id, $filename]);
                        }
                    }
                }

                header("Location: sites.php?msg=added");
                exit;
            } catch (PDOException $e) {
                header("Location: sites.php?error=" . urlencode($e->getMessage()));
                exit;
            }
        } else {
            requirePermission('inventory', 'edit');
            $id = intval($_POST['id']);
            $checkStmt = $pdo->prepare("SELECT id FROM sites WHERE site_code = ? AND id != ?");
            $checkStmt->execute([$code, $id]);
            if ($checkStmt->fetch()) {
                header("Location: sites.php?error=" . urlencode("Asset code '$code' already exists on another asset."));
                exit;
            }
            try {
                $stmt = $pdo->prepare("UPDATE sites SET site_code=?, name=?, location=?, area=?, city=?, district=?, latitude=?, longitude=?, type=?, width=?, height=?, facing=?, light_type=?, hsn_code=?, mounting_hsn=?, vendor_gst=?, grade=?, owner_type=?, vendor_id=?, card_rate=?, purchase_rate=?, available_from=? WHERE id=?");
                $stmt->execute([$code, $name, $location, $area, $city, $district, $latitude, $longitude, $type, $width, $height, $facing, $light_type, $hsn_code, $mounting_hsn, $vendor_gst, $grade, $owner_type, $vendor_id, $card_rate, $purchase_rate, $available_from, $id]);

                // Handle Multi-Image Upload (New)
                if (!empty($_FILES['site_images']['name'][0])) {
                    $uploadDir = __DIR__ . '/../../uploads/sites/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0777, true);

                    foreach ($_FILES['site_images']['name'] as $key => $val) {
                        $filename = time() . '_' . $id . '_' . str_replace(',', '', basename($_FILES['site_images']['name'][$key]));
                        $targetFile = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['site_images']['tmp_name'][$key], $targetFile)) {
                            $pdo->prepare("INSERT INTO site_images (site_id, filename) VALUES (?, ?)")->execute([$id, $filename]);
                        }
                    }
                }

                header("Location: sites.php?msg=updated");
                exit;
            } catch (PDOException $e) {
                header("Location: sites.php?error=" . urlencode($e->getMessage()));
                exit;
            }
        }
    } else if ($_POST['action'] === 'delete_site') {
        requirePermission('inventory', 'delete');
        include_once __DIR__ . '/../../includes/trash_helper.php';
        $id = intval($_POST['id']);
        try {
            // Move site_images to trash first
            $stmtImgs = $pdo->prepare("SELECT id FROM site_images WHERE site_id = ?");
            $stmtImgs->execute([$id]);
            $imgs = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);
            foreach ($imgs as $img) {
                move_row_to_trash($pdo, 'site_images', 'id', $img['id'], $_SESSION['user_id'] ?? null, 'Site deleted - image moved to trash');
            }

            // Then move site row to trash
            $trashId = move_row_to_trash($pdo, 'sites', 'id', $id, $_SESSION['user_id'] ?? null, 'Site deleted via inventory UI');
            if (!$trashId) throw new Exception('Failed to move site to trash');

            echo json_encode(['success' => true, 'trash_id' => $trashId]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$activePage = 'sites';
$pageTitle = 'HA Inventory Master List';
include_once __DIR__ . '/../../includes/header.php';

// Filtering Logic
$mediaFilter = isset($_GET['media']) ? $_GET['media'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where = "WHERE 1=1";
$params = [];

if ($mediaFilter !== 'all') {
    $where .= " AND s.type = ?";
    $params[] = $mediaFilter;
}
if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $where .= " AND (s.site_code LIKE ? OR s.name LIKE ? OR s.location LIKE ? OR s.city LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}
if (!empty($_GET['availability']) && $_GET['availability'] === 'available') {
    $where .= " AND s.status = 'available'";
}
if (!empty($_GET['owner']) && $_GET['owner'] !== 'all') {
    $where .= " AND s.owner_type = ?";
    $params[] = $_GET['owner'];
}
if (!empty($_GET['vendor_id'])) {
    $where .= " AND s.vendor_id = ?";
    $params[] = $_GET['vendor_id'];
}
if (!empty($_GET['district'])) {
    $where .= " AND s.district = ?";
    $params[] = $_GET['district'];
}
if (!empty($_GET['state'])) {
    $where .= " AND s.state = ?";
    $params[] = $_GET['state'];
}
if (!empty($_GET['city'])) {
    $where .= " AND s.city = ?";
    $params[] = $_GET['city'];
}
if (!empty($_GET['location'])) {
    $where .= " AND s.location = ?";
    $params[] = $_GET['location'];
}
if (!empty($_GET['light'])) {
    $where .= " AND s.light_type = ?";
    $params[] = $_GET['light'];
}
if (!empty($_GET['size'])) {
    $parts = explode('x', strtolower($_GET['size']));
    if (count($parts) === 2) {
        $where .= " AND s.width = ? AND s.height = ?";
        $params[] = floatval(trim($parts[0]));
        $params[] = floatval(trim($parts[1]));
    } else {
        // Fallback just in case
        $where .= " AND CONCAT(s.width, 'x', s.height) COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci";
        $params[] = $_GET['size'];
    }
}

// Query unique values for filters
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$districts = $pdo->query("SELECT DISTINCT district FROM sites WHERE district IS NOT NULL AND district != '' ORDER BY district")->fetchAll(PDO::FETCH_COLUMN);
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM sites WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$light_types = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width > 0 AND height > 0 ORDER BY size")->fetchAll(PDO::FETCH_COLUMN);
$media_types = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);

// Fetch Media Types Master List for Add/Edit Form
$all_media_types = $pdo->query("SELECT name FROM media_types ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

// Counts for Tabs
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn()
];
foreach ($all_media_types as $mtype) {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE type = ?");
    $stmtCount->execute([$mtype]);
    $counts[$mtype] = $stmtCount->fetchColumn();
}

// Fetch Sites
$stmt = $pdo->prepare("SELECT s.*, p.name as vendor_name, p.email as vendor_email,
    (SELECT filename FROM site_images WHERE site_id = s.id ORDER BY id DESC LIMIT 1) as thumbnail_photo,
    (SELECT b.campaign_name FROM booking_items bi JOIN bookings b ON bi.booking_id = b.id WHERE bi.site_id = s.id AND CURDATE() BETWEEN b.start_date AND b.end_date LIMIT 1) as current_campaign,
    (SELECT c.name FROM booking_items bi JOIN bookings b ON bi.booking_id = b.id JOIN partners c ON b.client_id = c.id WHERE bi.site_id = s.id AND CURDATE() BETWEEN b.start_date AND b.end_date LIMIT 1) as current_client,
    (SELECT b.end_date FROM booking_items bi JOIN bookings b ON bi.booking_id = b.id WHERE bi.site_id = s.id AND CURDATE() BETWEEN b.start_date AND b.end_date LIMIT 1) as current_end_date
FROM sites s LEFT JOIN partners p ON s.vendor_id = p.id $where ORDER BY s.id DESC");
$stmt->execute($params);
$sites = $stmt->fetchAll();

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
?>

<div class="inventory-tabs">
    <a href="?media=all" class="tab <?php echo $mediaFilter == 'all' ? 'active' : ''; ?>">All
        (<?php echo $counts['all']; ?>)</a>
    <?php foreach ($all_media_types as $mtype): ?>
        <a href="?media=<?php echo urlencode($mtype); ?>" class="tab <?php echo $mediaFilter == $mtype ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($mtype); ?> (<?php echo $counts[$mtype] ?? 0; ?>)
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
        <button type="button" class="btn" onclick="sendSelectedSitesViaEmail()" style="background: white; border: 1px solid #ea4335; color: #ea4335; padding: 0.6rem 1.15rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 1px 3px rgba(234,67,53,0.12); transition: all 0.2s;" onmouseover="this.style.background='#ea4335'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#ea4335';" title="Send stamped photos directly to vendor via SMTP Email">
            <i class="fas fa-paper-plane" style="color: inherit;"></i> Send via Email (SMTP)
        </button>
        <?php if (canAdd('inventory')): ?>
            <?php if (hasRole('admin')): ?>
            <a href="../admin/media_types.php" class="btn btn-secondary" style="background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; text-decoration: none;">
                <i class="fas fa-list-alt"></i> Manage Media Types
            </a>
            <?php endif; ?>
            <?php if (hasRole('admin')): ?>
            <a href="../admin/trash.php" class="btn btn-warning" style="background: #fffbeb; color: #92400e; border: 1px solid #fde68a; text-decoration: none;">
                <i class="fas fa-trash-alt"></i> Trash
            </a>
            <?php endif; ?>
            <button class="btn btn-secondary" onclick="openImportModal()"
                style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                <i class="fas fa-file-import"></i> Bulk Import
            </button>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Add New Site
            </button>
        <?php endif; ?>
    </div>

    <div
        style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 2rem;">
                <div
                    style="display: flex; align-items: center; gap: 0.5rem; color: #0d9488; font-weight: 800; font-size: 0.85rem; letter-spacing: 0.5px;">
                    <i class="fas fa-filter"></i> FILTERS
                </div>

                <div
                    style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.8rem; font-weight: 700; color: #475569;">
                    <span style="text-transform: uppercase; font-size: 0.75rem;">Ownership:</span>
                    <label
                        style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; color: #0f172a;"><input
                            type="radio" name="owner" value="all" <?php echo empty($_GET['owner']) || $_GET['owner'] === 'all' ? 'checked' : ''; ?> onchange="toggleVendorFilter(); doSearch();">
                        All</label>
                    <label
                        style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; color: #0f172a;"><input
                            type="radio" name="owner" value="HA" <?php echo ($_GET['owner'] ?? '') === 'HA' ? 'checked' : ''; ?> onchange="toggleVendorFilter(); doSearch();"> Self</label>
                    <label
                        style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; color: #0f172a;"><input
                            type="radio" name="owner" id="vendor-radio" value="TA" <?php echo ($_GET['owner'] ?? '') === 'TA' ? 'checked' : ''; ?> onchange="toggleVendorFilter(); doSearch();"> Vendor</label>

                    <select id="filter-vendor"
                        style="display: <?php echo ($_GET['owner'] ?? '') === 'TA' ? 'block' : 'none'; ?>; padding: 0.25rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.75rem; font-weight: 600; color: #0f172a; margin-left: 0.5rem; width: 140px;"
                        onchange="doSearch()">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo ($_GET['vendor_id'] ?? '') == $v['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div
                    style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.8rem; font-weight: 700; color: #475569;">
                    <span style="text-transform: uppercase; font-size: 0.75rem;">Availability:</span>
                    <label
                        style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; color: #0f172a;"><input
                            type="radio" name="availability" value="available" <?php echo ($_GET['availability'] ?? '') === 'available' ? 'checked' : ''; ?> onchange="doSearch()"> Available</label>
                    <label
                        style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; color: #0f172a;"><input
                            type="radio" name="availability" value="all" <?php echo empty($_GET['availability']) || $_GET['availability'] === 'all' ? 'checked' : ''; ?> onchange="doSearch()"> All</label>
                </div>
            </div>

            <div>
                <button onclick="window.location.href='sites.php'"
                    style="background: none; border: none; color: #ef4444; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; text-transform: uppercase;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr; gap: 0.75rem;">
            <div class="search-group" style="position: relative;">
                <i class="fas fa-search"
                    style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer;"
                    onclick="doSearch()"></i>
                <input type="text" id="site-search" placeholder="Search by name, code, city..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="width: 100%; padding: 0.5rem 0.5rem 0.5rem 35px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;"
                    onkeypress="if(event.key === 'Enter') doSearch()" onchange="doSearch()">
            </div>

            <select id="filter-media"
                style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #0f172a;"
                onchange="doSearch()">
                <option value="all">All Media</option>
                <?php foreach ($media_types as $m): ?>
                    <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $mediaFilter === $m ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-district"
                style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #0f172a;"
                onchange="doSearch()">
                <option value="">All Districts</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($_GET['district'] ?? '') === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-state"
                style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #0f172a;"
                onchange="doSearch()">
                <option value="">All States</option>
                <?php foreach ($states as $st): ?>
                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($_GET['state'] ?? '') === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-city"
                style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #0f172a;"
                onchange="doSearch()">
                <option value="">All Cities</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($_GET['city'] ?? '') === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-location"
                style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #0f172a;"
                onchange="doSearch()">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($_GET['location'] ?? '') === $loc ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-light"
                style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #0f172a;"
                onchange="doSearch()">
                <option value="">All Lights</option>
                <?php foreach ($light_types as $lt): ?>
                    <option value="<?php echo htmlspecialchars($lt); ?>" <?php echo ($_GET['light'] ?? '') === $lt ? 'selected' : ''; ?>><?php echo htmlspecialchars($lt); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-size"
                style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #0f172a;"
                onchange="doSearch()">
                <option value="">All Sizes</option>
                <?php foreach ($sizes as $sz): ?>
                    <option value="<?php echo htmlspecialchars($sz); ?>" <?php echo ($_GET['size'] ?? '') === $sz ? 'selected' : ''; ?>><?php echo htmlspecialchars($sz); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table responsive-table">
            <thead>
                <tr>
                    <th style="width: 45px; text-align: center;">
                        <input type="checkbox" id="selectAllSites" onchange="toggleSelectAllSites(this)" style="cursor: pointer; width: 18px; height: 18px; accent-color: var(--primary);" title="Select All">
                    </th>
                    <th>Preview</th>
                    <th>City / Code</th>
                    <th>Asset Details</th>
                    <th>Size</th>
                    <th>Pricing</th>
                    <th>Availability</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1;
                foreach ($sites as $s): ?>
                    <tr>
                        <td data-label="Select" style="text-align: center; vertical-align: middle;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                <input type="checkbox" class="site-select-chk" value="<?php echo $s['id']; ?>"
                                    data-site='<?php echo htmlspecialchars(json_encode([
                                        'id' => $s['id'],
                                        'site_code' => $s['site_code'],
                                        'city' => $s['city'],
                                        'area' => $s['area'] ?? '',
                                        'name' => $s['name'],
                                        'location' => $s['location'],
                                        'type' => $s['type'],
                                        'light_type' => $s['light_type'],
                                        'owner_type' => $s['owner_type'],
                                        'vendor_id' => $s['vendor_id'] ?? '',
                                        'vendor_name' => $s['vendor_name'] ?? '',
                                        'vendor_email' => $s['vendor_email'] ?? '',
                                        'width' => $s['width'],
                                        'height' => $s['height'],
                                        'sqft' => $s['sqft'],
                                        'card_rate' => $s['card_rate'],
                                        'photo' => !empty($s['thumbnail_photo']) ? $s['thumbnail_photo'] : ''
                                    ]), ENT_QUOTES, 'UTF-8'); ?>'
                                    onchange="updateSelectionBar()" style="cursor: pointer; width: 18px; height: 18px; accent-color: var(--primary);">
                                <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;"><?php echo $sn++; ?></span>
                            </div>
                        </td>
                        <td data-label="Preview">
                            <?php if (!empty($s['thumbnail_photo'])): ?>
                                <img src="../../uploads/sites/<?php echo htmlspecialchars($s['thumbnail_photo']); ?>"
                                    style="width: 100px; height: 65px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"
                                    onclick="viewPhotos(<?php echo $s['id']; ?>, '<?php echo addslashes($s['light_type']); ?>')">
                            <?php else: ?>
                                <div
                                    style="width: 100px; height: 65px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8;">
                                    <i class="fas fa-image" style="font-size: 1.2rem; margin-bottom: 2px;"></i>
                                    <span style="font-size: 0.6rem; font-weight: 700; text-transform: uppercase;">No Photo</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="City / Code">
                            <div style="font-weight: 700; color: #1e293b;"><?php echo $s['city']; ?></div>
                            <small style="color: #ef3417ff;"><?php echo $s['area'] ?? ''; ?></small>
                            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo $s['site_code']; ?>
                            </div>
                        </td>
                        <td data-label="Asset Details">
                            <div style="font-weight: 700; color: #1e293b; margin-bottom: 2px;"><?php echo $s['name']; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 6px;">
                                <?php echo $s['location']; ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">
                                <span
                                    class="media-badge <?php echo strtolower($s['type']); ?>"><?php echo $s['type']; ?></span>
                                •
                                <?php echo $s['light_type']; ?> •
                                <span style="color: var(--primary); font-weight: 700;">
                                    <?php echo $s['owner_type']; ?>
                                    <?php if ($s['owner_type'] === 'TA' && !empty($s['vendor_name']))
                                        echo " - " . htmlspecialchars($s['vendor_name']); ?>
                                </span>
                            </div>
                            <?php if (!empty($s['current_campaign'])): ?>
                                <div style="margin-top: 6px; font-size: 0.7rem; background: #fffbeb; border: 1px solid #fef3c7; color: #b45309; padding: 4px 8px; border-radius: 6px; font-weight: 700; display: inline-block;">
                                    <i class="fas fa-business-time"></i> Occupied by: <strong><?php echo htmlspecialchars($s['current_client']); ?></strong>
                                    <br><span style="font-weight: 600; color: #d97706;">(Campaign: <?php echo htmlspecialchars($s['current_campaign']); ?>)</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Size">
                            <div style="font-weight: 700; color: #475569;">
                                <?php echo $s['width'] . "' x " . $s['height'] . "'"; ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo number_format($s['sqft']); ?> SQFT
                            </div>
                        </td>
                        <td data-label="Pricing">
                            <div style="font-weight: 700; color: #1e293b;"><?php echo formatCurrency($s['card_rate']); ?>
                            </div>
                            <div style="font-size: 0.7rem; color: #94a3b8;">Cost:
                                <?php echo formatCurrency($s['purchase_rate']); ?></div>
                        </td>
                        <td data-label="Availability">
                            <?php if (!empty($s['current_campaign'])): ?>
                                <div style="font-weight: 700; color: #c2410c; font-size: 0.8rem;">
                                    Till <?php echo date('d M Y', strtotime($s['current_end_date'])); ?></div>
                            <?php else: ?>
                                <div style="font-weight: 600; color: #475569; font-size: 0.8rem;">
                                    <?php echo date('d M Y', strtotime($s['available_from'])); ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <?php if (!empty($s['current_campaign'])): ?>
                                <span class="status-pill occupied" style="background: #ffedd5; color: #c2410c; font-weight: 700; border-radius: 9999px; padding: 0.25rem 0.625rem; font-size: 0.7rem;">Occupied</span>
                            <?php else: ?>
                                <span class="status-pill <?php echo $s['status']; ?>"><?php echo ucfirst($s['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions" style="text-align: right; white-space: nowrap;">
                            <a href="site_financials.php?id=<?php echo $s['id']; ?>" class="btn-icon"
                                style="color: #0d9488; background: #ccfbf1; display: inline-flex; text-decoration: none;"
                                title="Site Financials (P&L)"><i class="fas fa-file-invoice-dollar"></i></a>
                            <?php if (canEdit('inventory')): ?>
                                <button class="btn-icon btn-edit"
                                    onclick="editSite(<?php echo htmlspecialchars(json_encode($s)); ?>)" title="Edit"><i
                                        class="fas fa-edit"></i></button>
                            <?php endif; ?>
                            <?php if (canDelete('inventory')): ?>
                                <button class="btn-icon btn-delete" onclick="deleteSite(event, <?php echo $s['id']; ?>)"
                                    title="Delete"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Same as before but with available_from field -->
<div id="siteModal" class="modal">
    <div class="modal-content" style="max-width: 850px;">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Advertising Asset</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="siteForm" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="add_site">
            <input type="hidden" name="id" id="siteId">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1rem;">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" id="f_city" required>
                </div>
                <div class="form-group">
                    <label>District</label>
                    <input type="text" name="district" id="f_district">
                </div>
                <div class="form-group">
                    <label>Media ID / Code</label>
                    <input type="text" name="site_code" id="f_code" required>
                </div>

                <div class="form-group">
                    <label>Media Type</label>
                    <select name="type" id="f_type" required>
                        <option value="">Select Type</option>
                        <?php foreach ($all_media_types as $mt): ?>
                            <option value="<?php echo htmlspecialchars($mt); ?>"><?php echo htmlspecialchars($mt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Inventory Type</label>
                    <select name="owner_type" id="owner_toggle" onchange="toggleVendor()" required>
                        <option value="HA">Home Asset (HA)</option>
                        <option value="TA">Vendor Asset (TA)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Grade</label>
                    <select name="grade" id="f_grade" required>
                        <option value="A">Grade A</option>
                        <option value="B">Grade B</option>
                        <option value="C">Grade C</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label>Site Location</label>
                    <input type="text" name="name" id="f_name" required>
                </div>
                <div class="form-group">
                    <label>Facing</label>
                    <input type="text" name="facing" id="f_facing" required>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label>Location Landmark</label>
                    <input type="text" name="location" id="f_location" required>
                </div>
                <div class="form-group">
                    <label>Area</label>
                    <input type="text" name="area" id="f_area">
                </div>

                <div class="form-group">
                    <label>Width (ft)</label>
                    <input type="number" step="0.1" name="width" id="w_input" required min="1">
                </div>
                <div class="form-group">
                    <label>Height (ft)</label>
                    <input type="number" step="0.1" name="height" id="h_input" required min="1">
                </div>
                <div class="form-group">
                    <label>Light Type</label>
                    <select name="light_type" id="f_light" required>
                        <option value="NL">Non-Lit (NL)</option>
                        <option value="BL">Back-Lit (BL)</option>
                        <option value="FL">Front-Lit (FL)</option>
                    </select>
                </div>
                <div class="form-group" id="vendor_gst_group" style="display: none;">
                    <label>Vendor Branch GST (for Groups)</label>
                    <input type="text" name="vendor_gst" id="f_vendor_gst" placeholder="Branch GSTIN">
                </div>
                <div class="form-group">
                    <label>HSN / SAC Code (Space Rental)</label>
                    <input type="text" name="hsn_code" id="f_hsn" value="998366" placeholder="e.g. 998366">
                </div>

                <div class="form-group">
                    <label>Mounting HSN Code</label>
                    <input type="text" name="mounting_hsn" id="f_mounting_hsn" placeholder="e.g. 995479">
                </div>

                <div class="form-group">
                    <label>Monthly Card Rate (₹)</label>
                    <input type="number" step="1" name="card_rate" id="f_card" required min="0">
                </div>
                <div class="form-group">
                    <label>Cost to Company (₹)</label>
                    <input type="number" step="1" name="purchase_rate" id="f_purchase" required min="0">
                </div>
                <div class="form-group">
                    <label>Available From</label>
                    <input type="date" name="available_from" id="f_avail" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Latitude</label>
                    <input type="number" step="0.00000001" name="latitude" id="f_lat" placeholder="e.g. 19.0760">
                </div>
                <div class="form-group">
                    <label>Longitude</label>
                    <input type="number" step="0.00000001" name="longitude" id="f_lng" placeholder="e.g. 72.8777">
                </div>
                <div class="form-group" id="vendor_select" style="display: none;">
                    <label>Vendor</label>
                    <select name="vendor_id" id="f_vendor" onchange="autoGenerateSiteCode()">
                        <option value="">Select Vendor</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 3;">
                    <label><i class="fas fa-images"></i> Site Photos (Multi-upload)</label>
                    <div id="drop-zone" class="drop-zone" onclick="document.getElementById('file-input').click()">
                        <div class="drop-zone-content">
                            <i class="fas fa-cloud-upload-alt"
                                style="font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem;"></i>
                            <p>Drag & Drop images here or <span>click to browse</span></p>
                            <small>Supports: JPG, PNG, WEBP (Max 5MB each)</small>
                        </div>
                        <input type="file" name="site_images[]" id="file-input" multiple accept="image/*"
                            style="display: none;" onchange="handleFiles(this.files)">
                    </div>
                    <div id="preview-container" class="preview-grid"></div>
                    <div id="existing-images-label"
                        style="display: none; margin-top: 1rem; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">
                        Existing Images</div>
                    <div id="existing-images" class="preview-grid"></div>
                </div>
            </div>
            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Site Information</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Import Sites Modal -->
<div id="importModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Bulk Import Sites</h2>
            <span class="close" onclick="closeImportModal()">&times;</span>
        </div>
        <form id="importForm" action="../../ajax/import_inventory.php" method="POST" enctype="multipart/form-data"
            style="padding: 1rem 0;">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Select CSV File</label>
                <input type="file" name="file" accept=".csv" required
                    style="padding: 1rem; border: 2px dashed #e2e8f0; background: #f8fafc; text-align: center;">
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Download <a href="../../templates/inventory_template.csv"
                        download style="color: var(--primary); font-weight: 600;">Inventory Template</a> first.
                </div>
            </div>
            <div style="text-align: right;">
                <button type="button" class="btn" onclick="closeImportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Start Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Lightbox -->
<div id="lbOverlay" class="lb-overlay">
    <div style="position: absolute; top: 30px; right: 40px; display: flex; align-items: center; gap: 1.2rem; z-index: 3002;">
        <button id="lb-download-top" onclick="downloadMasterLightboxImage(); event.stopPropagation();" style="background: rgba(255,255,255,0.18); color: white; border: 1px solid rgba(255,255,255,0.35); padding: 8px 18px; border-radius: 8px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; backdrop-filter: blur(8px); transition: all 0.2s;" onmouseover="this.style.background='var(--primary)'; this.style.borderColor='var(--primary)'" onmouseout="this.style.background='rgba(255,255,255,0.18)'; this.style.borderColor='rgba(255,255,255,0.35)'">
            <i class="fas fa-download"></i> Download Full Size (JPEG)
        </button>
        <span onclick="closeLightbox()" style="font-size: 2.5rem; cursor: pointer; color: white; opacity: 0.7; line-height: 1; transition: all 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">&times;</span>
    </div>
    <button class="lb-nav lb-prev" onclick="lbPrev()"><i class="fas fa-chevron-left"></i></button>
    <button class="lb-nav lb-next" onclick="lbNext()"><i class="fas fa-chevron-right"></i></button>
    <div class="lb-content">
        <div id="lbSlider" class="lb-slider"></div>
    </div>
    <div style="position: absolute; bottom: 30px; display: flex; align-items: center; gap: 1.2rem; z-index: 3002;">
        <div id="lbCounter" class="lb-counter" style="position: static; bottom: auto;">1 / 1</div>
        <button id="lb-download-bottom" onclick="downloadMasterLightboxImage(); event.stopPropagation();" style="background: rgba(15, 23, 42, 0.85); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 18px; border-radius: 8px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='var(--primary)'; this.style.borderColor='var(--primary)'" onmouseout="this.style.background='rgba(15, 23, 42, 0.85)'; this.style.borderColor='rgba(255,255,255,0.25)'">
            <i class="fas fa-download"></i> Download Full Size (JPEG)
        </button>
    </div>
</div>

<!-- Floating Selection Bar -->
<div id="selectionFloatingBar" style="display: none; position: fixed; bottom: 25px; left: 50%; transform: translateX(-50%); background: #0f172a; color: white; padding: 0.75rem 1.5rem; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.35); z-index: 2000; align-items: center; gap: 1rem; border: 1px solid rgba(255,255,255,0.15); backdrop-filter: blur(10px); animation: fadeInUp 0.3s ease;">
    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem;">
        <span id="selectedCountBadge" style="background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800;">0</span>
        <span>Sites Selected</span>
    </div>
    
    <div style="height: 24px; width: 1px; background: rgba(255,255,255,0.2);"></div>
    
    <button type="button" onclick="sendSelectedSitesViaEmail()" style="background: #ea4335; color: white; border: none; padding: 0.5rem 1.15rem; border-radius: 30px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(234,67,53,0.35);" onmouseover="this.style.background='#d93025'; this.style.transform='scale(1.03)';" onmouseout="this.style.background='#ea4335'; this.style.transform='scale(1)';" title="Send stamped photos to vendor via SMTP">
        <i class="fas fa-paper-plane"></i> Send via SMTP
    </button>

    <button type="button" onclick="downloadSelectedPhotosZip(true)" style="background: #0284c7; color: white; border: none; padding: 0.5rem 1.15rem; border-radius: 30px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(2,132,199,0.35);" onmouseover="this.style.background='#0369a1'; this.style.transform='scale(1.03)';" onmouseout="this.style.background='#0284c7'; this.style.transform='scale(1)';">
        <i class="fas fa-id-card"></i> Download Stamped JPEGs (ZIP)
    </button>

    <button type="button" onclick="openWhatsAppShareModal()" style="background: #25D366; color: white; border: none; padding: 0.5rem 1.2rem; border-radius: 30px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(37,211,102,0.3);" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
        <i class="fab fa-whatsapp" style="font-size: 1.1rem;"></i> Share via WhatsApp
    </button>
    
    <button type="button" onclick="quickCopyFormattedDetails()" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 0.5rem 1rem; border-radius: 30px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
        <i class="fas fa-copy"></i> Copy Text
    </button>
    
    <button type="button" onclick="clearSiteSelection()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 0.85rem; font-weight: 600; padding: 0 0.25rem;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#94a3b8'" title="Deselect All">
        <i class="fas fa-times"></i>
    </button>
</div>

<!-- WhatsApp Share Modal -->
<div id="whatsappShareModal" class="modal">
    <div class="modal-content" style="max-width: 880px; border-radius: 16px; padding: 1.75rem;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 42px; height: 42px; background: #dcfce7; color: #15803d; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">Share Sites via WhatsApp</h2>
                    <small style="color: #64748b;">Share selected advertising assets with photo, city name, asset details, and size</small>
                </div>
            </div>
            <span class="close" onclick="closeWhatsAppModal()" style="font-size: 1.75rem; cursor: pointer; color: #94a3b8;">&times;</span>
        </div>

        <!-- 2 Clear Sharing Methods Banner -->
        <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.25rem;">
            <div style="font-weight: 800; font-size: 0.88rem; color: #166534; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem;">
                <i class="fas fa-lightbulb" style="color: #eab308;"></i> 2 Recommended Ways to Send Each Photo with its Info:
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.8rem; color: #1e293b; line-height: 1.4;">
                <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 8px; padding: 0.65rem;">
                    <strong style="color: #0369a1; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 2px;">
                        <i class="fas fa-id-card"></i> Option 1: Info-Stamped Photo (Zero Typing)
                    </strong>
                    Click <span style="background:#e0f2fe;color:#0369a1;padding:1px 5px;border-radius:4px;font-weight:700;">🖼️ Info-Stamped JPEG</span> on each asset & press <kbd style="background:#f1f5f9;border:1px solid #cbd5e1;padding:1px 5px;border-radius:4px;font-weight:700;">Ctrl+V</kbd> in WhatsApp. City, Size & Rate are permanently stamped right on the photo!
                </div>
                <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 8px; padding: 0.65rem;">
                    <strong style="color: #15803d; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 2px;">
                        <i class="fas fa-comment-dots"></i> Option 2: WhatsApp Individual Captions
                    </strong>
                    When attaching multiple photos in WhatsApp Web, click each photo thumbnail at the bottom, click <span style="background:#dcfce7;color:#15803d;padding:1px 5px;border-radius:4px;font-weight:700;">📝 Copy Caption</span> here, and press <kbd style="background:#f1f5f9;border:1px solid #cbd5e1;padding:1px 5px;border-radius:4px;font-weight:700;">Ctrl+V</kbd> into <em>"Add a caption..."</em>!
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
            <div>
                <label style="display: block; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem;">
                    Recipient WhatsApp Number (Optional):
                </label>
                <div style="position: relative;">
                    <input type="text" id="waRecipientPhone" placeholder="e.g. 9876543210 (Leave blank for any chat)" class="p-input" style="width: 100%; padding-left: 2.25rem; font-size: 0.9rem;" oninput="generateWhatsAppMessage()">
                    <i class="fab fa-whatsapp" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #22c55e;"></i>
                </div>
                <small style="color: #64748b; font-size: 0.75rem; margin-top: 4px; display: block;">
                    Leave empty to select any contact or group inside WhatsApp.
                </small>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.85rem;">
                <label style="display: block; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.5rem;">
                    Message Details Options:
                </label>
                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.85rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #334155;">
                        <input type="checkbox" id="waIncludePricing" onchange="refreshWhatsAppModalData()" checked style="cursor: pointer; accent-color: var(--primary);">
                        Include Card Rate / Rate Card Price
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #334155;">
                        <input type="checkbox" id="waIncludePhotoLinks" onchange="refreshWhatsAppModalData()" style="cursor: pointer; accent-color: var(--primary);">
                        Include Direct Photo URLs in Text Message
                    </label>
                </div>
            </div>
        </div>

        <!-- Selected Sites Cards List -->
        <div style="margin-bottom: 1.25rem;">
            <label style="display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.5rem;">
                <span>Selected Media Assets (<span id="waModalCount">0</span>):</span>
                <span style="font-size: 0.75rem; color: #64748b;">Send each site individually or copy captions/photos</span>
            </label>
            <div id="waSelectedCardsList" style="max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem; background: #ffffff; display: flex; flex-direction: column; gap: 0.75rem;">
                <!-- Dynamically Populated -->
            </div>
        </div>

        <!-- Formatted WhatsApp Message Preview -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <label style="font-weight: 700; font-size: 0.85rem; color: #334155; margin: 0;">
                    Complete WhatsApp Proposal Summary Preview:
                </label>
                <button type="button" onclick="copyWhatsAppMessageText()" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; padding: 3px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem;">
                    <i class="fas fa-copy"></i> Copy Full Summary
                </button>
            </div>
            <textarea id="waMessagePreview" rows="4" readonly style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.75rem; font-family: monospace; font-size: 0.82rem; background: #f8fafc; color: #0f172a; resize: vertical; line-height: 1.4; box-sizing: border-box;"></textarea>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; border-top: 1px solid #e2e8f0; padding-top: 1.25rem;">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" onclick="downloadSelectedPhotosZip(true)" class="btn btn-secondary" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; font-size: 0.82rem;" title="Download all photos with details stamped on them">
                    <i class="fas fa-id-card"></i> Download Info-Stamped JPEGs (ZIP)
                </button>
                <button type="button" onclick="downloadSelectedPhotosZip(false)" class="btn btn-secondary" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 600; font-size: 0.82rem;" title="Download original raw photos">
                    <i class="fas fa-file-archive"></i> Download Raw Photos (ZIP)
                </button>
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <button type="button" id="btnWebShareFiles" onclick="shareWithImagesWebShare()" class="btn" style="display: none; background: #0284c7; color: white; font-weight: 700; font-size: 0.88rem; padding: 0.6rem 1.15rem; border-radius: 8px; border: none; cursor: pointer; align-items: center; gap: 0.4rem; box-shadow: 0 4px 12px rgba(2,132,199,0.3);">
                    <i class="fas fa-share-alt"></i> Share Stamped JPEGs (WhatsApp / OS)
                </button>
                <button type="button" onclick="openWhatsAppDesktopApp()" class="btn" style="background: #128C7E; color: white; font-weight: 700; font-size: 0.88rem; padding: 0.6rem 1.15rem; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fab fa-whatsapp"></i> WhatsApp App
                </button>
                <button type="button" onclick="openWhatsAppWeb()" class="btn btn-primary" style="background: #25D366; border-color: #25D366; color: white; font-weight: 800; font-size: 0.88rem; padding: 0.6rem 1.35rem; border-radius: 8px; display: flex; align-items: center; gap: 0.4rem; box-shadow: 0 4px 12px rgba(37,211,102,0.3);">
                    <i class="fab fa-whatsapp" style="font-size: 1.05rem;"></i> Open WhatsApp Web
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .inventory-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .tab {
        padding: 0.625rem 1.25rem;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        text-decoration: none;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .media-badge {
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .media-badge.Billboard {
        background: #dcfce7;
        color: #166534;
    }

    .media-badge.unipole {
        background: #e0f2fe;
        color: #0369a1;
    }

    .media-badge.bqs {
        background: #fef9c3;
        color: #854d0e;
    }

    .media-badge.dcp {
        background: #fdf2f8;
        color: #9d174d;
    }

    .id-badge {
        background: #f1f5f9;
        padding: 0.2rem 0.4rem;
        border-radius: 4px;
        font-family: monospace;
        font-weight: 700;
        color: #475569;
    }

    .status-pill.available {
        background: #dcfce7;
        color: #166534;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
    }

    .status-pill.booked {
        background: #fee2e2;
        color: #991b1b;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
    }

    .p-input {
        padding: 0.5rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        overflow-y: auto;
    }

    #whatsappShareModal.modal {
        z-index: 4000;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(6px);
        padding: 30px 15px;
        box-sizing: border-box;
    }

    #whatsappShareModal .modal-content {
        margin: 0 auto;
        max-width: 880px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
    }

    .modal-content {
        background: white;
        margin: 3% auto;
        padding: 2rem;
        border-radius: 12px;
    }

    .close {
        cursor: pointer;
        float: right;
        font-size: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.3rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.6rem;
        border: 1px solid #ddd;
        border-radius: 6px;
    }

    /* Lightbox Styles */
    .lb-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        z-index: 3000;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        backdrop-filter: blur(10px);
    }

    .lb-content {
        position: relative;
        width: 100%;
        height: 85vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .lb-slider {
        display: flex;
        transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        height: 100%;
        width: 100%;
    }

    .lb-slide {
        min-width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 20px;
    }

    .lb-slide img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
        border-radius: 12px;
    }

    .lb-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        z-index: 3001;
    }

    .lb-nav:hover {
        background: var(--primary);
        border-color: var(--primary);
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 0 20px rgba(28, 173, 169, 0.4);
    }

    .lb-prev {
        left: 40px;
    }

    .lb-next {
        right: 40px;
    }

    .lb-close {
        position: absolute;
        top: 40px;
        right: 40px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 2.5rem;
        cursor: pointer;
        z-index: 3002;
        transition: all 0.3s;
        line-height: 1;
    }

    .lb-close:hover {
        color: white;
        transform: rotate(90deg);
    }

    .lb-counter {
        position: absolute;
        bottom: 40px;
        color: white;
        font-weight: 600;
        letter-spacing: 2px;
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 20px;
        border-radius: 30px;
        font-size: 0.875rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .drop-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }

    .drop-zone:hover,
    .drop-zone.dragover {
        border-color: var(--primary);
        background: rgba(13, 148, 136, 0.05);
    }

    .drop-zone-content p {
        font-size: 0.9rem;
        font-weight: 600;
        color: #475569;
        margin: 0.5rem 0;
    }

    .drop-zone-content p span {
        color: var(--primary);
        text-decoration: underline;
    }

    .drop-zone-content small {
        color: #94a3b8;
        font-size: 0.75rem;
    }

    .preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .preview-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .remove-preview {
        position: absolute;
        top: 4px;
        right: 4px;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        cursor: pointer;
        border: none;
    }
</style>

<script>
    let currentLbIndex = 0;
    let lbImages = [];
    let pendingFiles = []; // Global to store all selected files for upload

    function toggleVendorFilter() {
        const isVendor = document.getElementById('vendor-radio').checked;
        const vendorSelect = document.getElementById('filter-vendor');
        vendorSelect.style.display = isVendor ? 'block' : 'none';
        if (!isVendor) vendorSelect.value = '';
    }

    function doSearch() {
        const s = document.getElementById('site-search').value;
        const owner = document.querySelector('input[name="owner"]:checked')?.value || 'all';
        const vendor_id = document.getElementById('filter-vendor').value;
        const availability = document.querySelector('input[name="availability"]:checked')?.value || 'all';
        const media = document.getElementById('filter-media').value;
        const district = document.getElementById('filter-district') ? document.getElementById('filter-district').value : '';
        const state = document.getElementById('filter-state').value;
        const city = document.getElementById('filter-city').value;
        const locationFilter = document.getElementById('filter-location') ? document.getElementById('filter-location').value : '';
        const light = document.getElementById('filter-light').value;
        const size = document.getElementById('filter-size').value;

        window.location.href = `?search=${encodeURIComponent(s)}&owner=${encodeURIComponent(owner)}&vendor_id=${encodeURIComponent(vendor_id)}&availability=${encodeURIComponent(availability)}&media=${encodeURIComponent(media)}&district=${encodeURIComponent(district)}&state=${encodeURIComponent(state)}&city=${encodeURIComponent(city)}&location=${encodeURIComponent(locationFilter)}&light=${encodeURIComponent(light)}&size=${encodeURIComponent(size)}`;
    }

    // Auto-search on type with debounce
    let searchTimeout;
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('site-search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(doSearch, 600);
            });
        }
    });

    function autoGenerateSiteCode() {
        const action = document.getElementById('formAction').value;
        if (action !== 'add_site') {
            return;
        }
        const ownerType = document.getElementById('owner_toggle').value;
        const vendorId = document.getElementById('f_vendor').value;
        
        if (ownerType === 'TA' && !vendorId) {
            document.getElementById('f_code').value = '';
            return;
        }
        
        fetch(`../../ajax/get_next_site_code.php?owner_type=${ownerType}&vendor_id=${vendorId}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('f_code').value = res.site_code;
                }
            })
            .catch(err => console.error('Error fetching next site code:', err));
    }

    function openModal() {
        const form = document.getElementById('siteForm');
        form.reset();
        document.getElementById('formAction').value = 'add_site';
        document.getElementById('modalTitle').innerText = 'Add New Advertising Asset';
        form.classList.remove('was-validated');
        document.getElementById('preview-container').innerHTML = '';
        document.getElementById('existing-images').innerHTML = '';
        pendingFiles = []; // Reset pending files
        document.getElementById('file-input').value = '';
        toggleVendor();
        autoGenerateSiteCode();
        document.getElementById('siteModal').style.display = 'block';
    }
    function closeModal() { document.getElementById('siteModal').style.display = 'none'; }

    function openImportModal() { document.getElementById('importModal').style.display = 'block'; }
    function closeImportModal() { document.getElementById('importModal').style.display = 'none'; }
    function toggleVendor() {
        const type = document.getElementById('owner_toggle').value;
        const vendorSelect = document.getElementById('vendor_select');
        const vendorInput = document.getElementById('f_vendor');
        const gstGroup = document.getElementById('vendor_gst_group');

        if (type === 'TA') {
            vendorSelect.style.display = 'block';
            gstGroup.style.display = 'block';
            vendorInput.required = true;
        } else {
            vendorSelect.style.display = 'none';
            gstGroup.style.display = 'none';
            vendorInput.required = false;
            vendorInput.value = '';
            document.getElementById('f_vendor_gst').value = '';
        }
        autoGenerateSiteCode();
    }

    function viewPhotos(siteId, lightType) {
        fetch(`../../ajax/get_site_images.php?id=${siteId}`)
            .then(r => r.json())
            .then(imgs => {
                if (!imgs.length) {
                    Swal.fire('Info', 'No photos available for this site.', 'info');
                    return;
                }
                openLightbox(imgs, 0);
            });
    }

    function openLightbox(images, index) {
        const slider = document.getElementById('lbSlider');
        lbImages = images; // Update global for nav
        slider.innerHTML = images.map(img => `
        <div class="lb-slide">
            <img src="${img.src || '../../uploads/sites/' + img.filename}" alt="Site Photo">
        </div>
    `).join('');

        currentLbIndex = index;
        updateLightbox();
        document.getElementById('lbOverlay').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        document.getElementById('lbOverlay').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function updateLightbox() {
        const slider = document.getElementById('lbSlider');
        const counter = document.getElementById('lbCounter');
        slider.style.transform = `translateX(-${currentLbIndex * 100}%)`;
        counter.innerText = `${currentLbIndex + 1} / ${lbImages.length}`;

        // Toggle nav buttons visibility
        document.querySelector('.lb-prev').style.display = currentLbIndex === 0 ? 'none' : 'flex';
        document.querySelector('.lb-next').style.display = currentLbIndex === lbImages.length - 1 ? 'none' : 'flex';
    }

    function lbPrev() {
        if (currentLbIndex > 0) {
            currentLbIndex--;
            updateLightbox();
        }
    }

    function lbNext() {
        if (currentLbIndex < lbImages.length - 1) {
            currentLbIndex++;
            updateLightbox();
        }
    }

    async function downloadMasterLightboxImage() {
        if (!lbImages || !lbImages[currentLbIndex]) return;
        const imgObj = lbImages[currentLbIndex];
        const imgSrc = imgObj.src || ('../../uploads/sites/' + imgObj.filename);
        
        try {
            const topBtn = document.getElementById('lb-download-top');
            const botBtn = document.getElementById('lb-download-bottom');
            const origTop = topBtn ? topBtn.innerHTML : '';
            const origBot = botBtn ? botBtn.innerHTML : '';
            if (topBtn) topBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
            if (botBtn) botBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';

            const response = await fetch(imgSrc);
            if (!response.ok) throw new Error('Network error');
            const blob = await response.blob();
            const blobUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            
            let rawName = (imgObj.filename || imgSrc.split('/').pop()).split('?')[0];
            let baseName = rawName.replace(/\.[^/.]+$/, "");
            a.download = baseName + '.jpg';
            
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(blobUrl);

            if (topBtn) topBtn.innerHTML = origTop;
            if (botBtn) botBtn.innerHTML = origBot;
        } catch (e) {
            const a = document.createElement('a');
            a.href = imgSrc;
            let rawName = (imgObj.filename || imgSrc.split('/').pop()).split('?')[0];
            let baseName = rawName.replace(/\.[^/.]+$/, "");
            a.download = baseName + '.jpg';
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (document.getElementById('lbOverlay').style.display === 'flex') {
            if (e.key === 'ArrowLeft') lbPrev();
            if (e.key === 'ArrowRight') lbNext();
            if (e.key === 'Escape') closeLightbox();
        }
    });

    // Drop Zone Logic
    const dropZone = document.getElementById('drop-zone');
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }, false);
    }

    function handleFiles(files) {
        const container = document.getElementById('preview-container');
        const fileArray = Array.from(files);

        fileArray.forEach((file) => {
            if (!file.type.startsWith('image/')) return;

            // Add to global pending files
            pendingFiles.push(file);
            const currentIdx = pendingFiles.length - 1;

            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.id = 'pending-img-' + currentIdx;
                div.innerHTML = `
                <img src="${e.target.result}" style="cursor:zoom-in;">
                <button type="button" class="remove-preview" onclick="removePendingFile(${currentIdx})">×</button>
            `;
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
        updateFileInput();
    }

    function removePendingFile(index) {
        // We don't splice to keep indices stable for existing previews, 
        // but we can mark as null or use a better list management
        pendingFiles[index] = null;
        document.getElementById('pending-img-' + index).remove();
        updateFileInput();
    }

    function updateFileInput() {
        const dt = new DataTransfer();
        pendingFiles.forEach(file => {
            if (file) dt.items.add(file);
        });
        document.getElementById('file-input').files = dt.files;
    }

    function editSite(site) {
        const form = document.getElementById('siteForm');
        if (form) form.classList.remove('was-validated');

        document.getElementById('formAction').value = 'edit_site';
        document.getElementById('modalTitle').innerText = 'Edit Advertising Asset';
        document.getElementById('siteId').value = site.id;
        document.getElementById('f_type').value = site.type;
        document.getElementById('f_code').value = site.site_code;
        document.getElementById('owner_toggle').value = site.owner_type;
        document.getElementById('f_name').value = site.name;
        document.getElementById('f_vendor').value = site.vendor_id || '';
        document.getElementById('f_location').value = site.location;
        document.getElementById('f_area').value = site.area || '';
        document.getElementById('f_city').value = site.city;
        document.getElementById('f_district').value = site.district || '';
        document.getElementById('f_lat').value = site.latitude || '';
        document.getElementById('f_lng').value = site.longitude || '';
        document.getElementById('w_input').value = site.width;
        document.getElementById('h_input').value = site.height;
        document.getElementById('f_light').value = site.light_type;
        document.getElementById('f_hsn').value = site.hsn_code || '998366';
        document.getElementById('f_mounting_hsn').value = site.mounting_hsn || '';
        document.getElementById('f_vendor_gst').value = site.vendor_gst || '';
        document.getElementById('f_facing').value = site.facing;
        document.getElementById('f_grade').value = site.grade;
        document.getElementById('f_avail').value = site.available_from;
        document.getElementById('f_card').value = site.card_rate;
        document.getElementById('f_purchase').value = site.purchase_rate;

        // Clear dynamic previews
        document.getElementById('preview-container').innerHTML = '';
        pendingFiles = []; // Reset pending files
        document.getElementById('file-input').value = '';

        // Load Existing Image Thumbnails
        fetch(`../../ajax/get_site_images.php?id=${site.id}`)
            .then(r => r.json())
            .then(imgs => {
                const label = document.getElementById('existing-images-label');
                if (imgs.length > 0) {
                    label.style.display = 'block';
                    let html = imgs.map((i, idx) => `
                <div class="preview-item" id="img-item-${i.id}">
                    <img src="../../uploads/sites/${i.filename}" onclick="openLightbox(editSite.currentImages, ${idx})" style="cursor:zoom-in;">
                    <button type="button" class="remove-preview" onclick="deleteImage(${i.id})">×</button>
                </div>
            `).join('');
                    document.getElementById('existing-images').innerHTML = html;
                    editSite.currentImages = imgs;
                } else {
                    label.style.display = 'none';
                    document.getElementById('existing-images').innerHTML = '';
                    editSite.currentImages = [];
                }
            });

        toggleVendor();
        document.getElementById('siteModal').style.display = 'block';
    }

    function deleteImage(imgId) {
        Swal.fire({
            title: 'Delete Image?',
            text: "This image will be permanently removed.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`../../ajax/delete_site_image.php?id=${imgId}`)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            document.getElementById(`img-item-${imgId}`).remove();
                            // If no images left, hide label
                            if (document.getElementById('existing-images').children.length === 0) {
                                document.getElementById('existing-images-label').style.display = 'none';
                            }
                        }
                    });
            }
        });
    }

    function deleteSite(e, id) {
        if (e) e.preventDefault();
        Swal.fire({
            title: 'Delete Site?',
            text: "This site will be removed from inventory. Are you sure?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#1CADA9',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('sites.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_site&id=${id}`
                }).then(() => {
                    Swal.fire('Removed!', 'Site has been deleted.', 'success').then(() => location.reload());
                });
            }
        });
    }
    // Notifications
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            const msg = urlParams.get('msg');
            if (msg === 'added') Swal.fire('Success', 'Site added successfully!', 'success');
            if (msg === 'updated') Swal.fire('Success', 'Site updated successfully!', 'success');
            if (msg === 'deleted') Swal.fire('Success', 'Site deleted successfully!', 'success');
        }
        if (urlParams.has('error')) {
            Swal.fire('Error', decodeURIComponent(urlParams.get('error')), 'error');
        }
    });

    // --- Multi-Site Selection & WhatsApp Sharing Logic ---
    function toggleSelectAllSites(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.site-select-chk');
        checkboxes.forEach(chk => {
            chk.checked = masterCheckbox.checked;
        });
        updateSelectionBar();
    }

    function updateSelectionBar() {
        const selected = getSelectedSitesData();
        const bar = document.getElementById('selectionFloatingBar');
        const badge = document.getElementById('selectedCountBadge');
        const masterChk = document.getElementById('selectAllSites');
        const totalChk = document.querySelectorAll('.site-select-chk').length;
        
        if (badge) badge.innerText = selected.length;
        if (masterChk) {
            masterChk.checked = totalChk > 0 && selected.length === totalChk;
            masterChk.indeterminate = selected.length > 0 && selected.length < totalChk;
        }
        
        if (bar) {
            bar.style.display = selected.length > 0 ? 'flex' : 'none';
        }
    }

    function clearSiteSelection() {
        document.querySelectorAll('.site-select-chk').forEach(chk => chk.checked = false);
        const masterChk = document.getElementById('selectAllSites');
        if (masterChk) {
            masterChk.checked = false;
            masterChk.indeterminate = false;
        }
        updateSelectionBar();
    }

    function getSelectedSitesData() {
        const checkboxes = document.querySelectorAll('.site-select-chk:checked');
        const list = [];
        checkboxes.forEach(chk => {
            try {
                const data = JSON.parse(chk.getAttribute('data-site'));
                if (data) list.push(data);
            } catch (e) {
                console.error('Failed to parse site data', e);
            }
        });
        return list;
    }

    async function sendSelectedSitesViaEmail() {
        const selected = getSelectedSitesData();
        if (!selected.length) {
            Swal.fire({
                icon: 'info',
                title: 'No Sites Selected',
                text: 'Please select at least one site from the table checkbox first.',
                confirmButtonColor: '#0d9488'
            });
            return;
        }

        const validSites = selected.filter(s => s.photo);
        if (validSites.length === 0) {
            Swal.fire('No Photos', 'Selected sites do not have photos to send.', 'warning');
            return;
        }

        // Fetch Vendor Emails from selected sites
        const vendorEmails = [...new Set(validSites.map(s => (s.vendor_email || '').trim()).filter(Boolean))];
        const initialEmail = vendorEmails.join(', ');

        const totalAmtVal = validSites.reduce((sum, s) => sum + (Number(s.card_rate) || 0), 0);
        const amountText = totalAmtVal > 0 ? Number(totalAmtVal).toLocaleString('en-IN') : '{{Amount}}';

        const defaultSubject = `Site Photos - ${validSites.length === 1 ? (validSites[0].city + ' - ' + validSites[0].site_code) : (validSites.length + ' Sites Selection')}`;

        const defaultTemplateText = `Dear Sir/Madam,

Please be informed that the attached site has been booked for the period from {{Start Date}} to {{End Date}} at a rate of ₹${amountText}.

Please find the booking details and stamped photos attached for your reference.

Thank you.

Regards,

Sudha Creative`;

        const { value: formValues } = await Swal.fire({
            title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#ea4335;"><i class="fas fa-paper-plane"></i> Send Stamped Photos via Email</div>',
            html: `
                <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                    <div style="margin-bottom: 0.85rem;">
                        <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Recipient Email Address (Vendor) <span style="color:#ef4444;">*</span></label>
                        <input id="smtp_inv_recipient_email" type="text" value="${initialEmail}" placeholder="vendor@example.com" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                    </div>
                    <div style="margin-bottom: 0.85rem;">
                        <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Email Subject</label>
                        <input id="smtp_inv_subject" type="text" value="${defaultSubject}" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                    </div>
                    <div style="margin-bottom: 0.85rem;">
                        <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Message Body</label>
                        <textarea id="smtp_inv_message" style="width: 100%; box-sizing: border-box; height: 145px; font-size: 0.86rem; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; resize: vertical; line-height: 1.45; font-family: inherit;">${defaultTemplateText}</textarea>
                    </div>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; color: #166534; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-paperclip" style="color: #16a34a; font-size: 1rem;"></i>
                        <span><b>${validSites.length} Stamped Photo Card(s)</b> will be attached directly.</span>
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Email (SMTP)',
            confirmButtonColor: '#0d9488',
            cancelButtonText: '<i class="fab fa-google"></i> Open in Gmail Web',
            cancelButtonColor: '#ea4335',
            showCloseButton: true,
            preConfirm: () => {
                const emailVal = document.getElementById('smtp_inv_recipient_email').value.trim();
                const subjectVal = document.getElementById('smtp_inv_subject').value.trim();
                const msgVal = document.getElementById('smtp_inv_message').value.trim();
                if (!emailVal) {
                    Swal.showValidationMessage('Please enter a valid recipient email address.');
                    return false;
                }
                return { email: emailVal, subject: subjectVal, message: msgVal };
            }
        });

        if (formValues) {
            Swal.fire({
                title: 'Sending Stamped Photos...',
                html: `Generating stamped JPEG cards and sending via SMTP to <b>${formValues.email}</b>...`,
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const formData = new FormData();
                formData.append('site_ids', validSites.map(s => s.id).join(','));
                formData.append('email', formValues.email);
                formData.append('subject', formValues.subject);
                formData.append('message', formValues.message);

                const resp = await fetch('../../ajax/send_inventory_stamped_email.php', {
                    method: 'POST',
                    body: formData
                });
                const rawText = await resp.text();
                let res;
                try {
                    res = JSON.parse(rawText);
                } catch(jsonErr) {
                    const cleanError = rawText.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
                    throw new Error(cleanError || 'Invalid response from server.');
                }

                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Sent Successfully!',
                        html: `<b>${res.message || 'Email sent successfully!'}</b><br><br><span style="color:#64748b;font-size:0.85rem;">Sender: sudhacreativemalda@gmail.com</span>`,
                        confirmButtonColor: '#0d9488'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sending Failed',
                        text: res.message || 'Could not send email via SMTP server.'
                    });
                }
            } catch (err) {
                Swal.fire('SMTP Error', err.message || 'Network error occurred while communicating with SMTP server.', 'error');
            }
        } else if (Swal.getDismissReason() === Swal.DismissReason.cancel) {
            // Compose in Gmail Web fallback
            const emailInput = document.getElementById('smtp_inv_recipient_email')?.value.trim() || initialEmail;
            const subjectInput = document.getElementById('smtp_inv_subject')?.value.trim() || defaultSubject;
            const msgInput = document.getElementById('smtp_inv_message')?.value.trim() || defaultTemplateText;
            const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailInput)}&su=${encodeURIComponent(subjectInput)}&body=${encodeURIComponent(msgInput)}`;
            window.open(gmailUrl, '_blank');
        }
    }

    function openWhatsAppShareModal() {
        const selected = getSelectedSitesData();
        if (!selected.length) {
            Swal.fire('Info', 'Please select at least one site from the list.', 'info');
            return;
        }
        
        document.getElementById('waModalCount').innerText = selected.length;
        
        // Check if Web Share API with files is supported
        const webShareBtn = document.getElementById('btnWebShareFiles');
        const topWebShareBtn = document.getElementById('btnTopWebShare');
        if (navigator.canShare) {
            if (webShareBtn) webShareBtn.style.display = 'inline-flex';
            if (topWebShareBtn) topWebShareBtn.style.display = 'inline-flex';
        }
        
        refreshWhatsAppModalData();
        const bar = document.getElementById('selectionFloatingBar');
        if (bar) bar.style.display = 'none';
        document.getElementById('whatsappShareModal').style.display = 'block';
    }

    function refreshWhatsAppModalData() {
        const selected = getSelectedSitesData();
        if (!selected.length) return;
        
        const includePricing = document.getElementById('waIncludePricing') ? document.getElementById('waIncludePricing').checked : true;
        const cardsContainer = document.getElementById('waSelectedCardsList');
        if (cardsContainer) {
            cardsContainer.innerHTML = selected.map((s, idx) => {
                const captionText = generateSingleSiteCaption(s, idx, includePricing);
                return `
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.85rem; box-shadow: 0 2px 5px rgba(0,0,0,0.03); display: flex; flex-direction: column; gap: 0.6rem;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 0.85rem; flex: 1; min-width: 260px;">
                            <div style="width: 85px; height: 58px; border-radius: 8px; overflow: hidden; background: #000; flex-shrink: 0; border: 1px solid #cbd5e1; position: relative;">
                                ${s.photo ? `<img src="../../uploads/sites/${s.photo}" style="width: 100%; height: 100%; object-fit: cover;">` : `<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #94a3b8; font-size: 0.6rem; font-weight: 700;">NO PHOTO</div>`}
                                <span style="position: absolute; top: 2px; left: 2px; background: rgba(15,23,42,0.8); color: white; padding: 1px 5px; border-radius: 4px; font-size: 0.65rem; font-weight: 800;">#${idx + 1}</span>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a; display: flex; align-items: center; gap: 0.4rem;">
                                    ${s.city} <span style="font-family: monospace; background: #f1f5f9; padding: 1px 6px; border-radius: 4px; font-size: 0.75rem; color: #475569; border: 1px solid #e2e8f0;">${s.site_code}</span>
                                </div>
                                <div style="font-size: 0.8rem; color: #475569; font-weight: 600; margin-top: 1px;">
                                    ${s.name} ${s.location ? '• ' + s.location : ''}
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                    <span style="font-weight: 700; color: #0f172a;">${s.width}' x ${s.height}'</span> (${Number(s.sqft).toLocaleString()} SQFT) • <span class="media-badge ${s.type ? s.type.toLowerCase() : ''}" style="font-size: 0.65rem; padding: 1px 4px;">${s.type}</span> • ${s.light_type}
                                    ${includePricing && s.card_rate && Number(s.card_rate) > 0 ? ` • <strong style="color: #16a34a;">₹${Number(s.card_rate).toLocaleString()}/mo</strong>` : ''}
                                </div>
                            </div>
                        </div>

                        <!-- Individual Quick Action Buttons -->
                        <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                            ${s.photo ? `
                                <button type="button" onclick="copyStampedImageToClipboard(${idx})" class="btn" style="background: #0284c7; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; box-shadow: 0 2px 6px rgba(2,132,199,0.35);" title="Copy photo with info details stamped on it">
                                    <i class="fas fa-id-card"></i> 🖼️ Copy Stamped JPEG
                                </button>
                                <button type="button" onclick="downloadSingleSitePhoto('${s.photo}', '${s.site_code}', '${s.city}', ${s.id})" class="btn" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 6px 10px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem; cursor: pointer;" title="Download Stamped JPEG photo file">
                                    <i class="fas fa-download"></i> Download Stamped JPEG
                                </button>
                            ` : ''}
                            <button type="button" onclick="sendSingleSiteToWhatsApp(${idx})" class="btn" style="background: #25D366; color: white; border: none; padding: 6px 11px; border-radius: 6px; font-size: 0.78rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.3rem; cursor: pointer; box-shadow: 0 2px 6px rgba(37,211,102,0.3);" title="Send this single site directly to WhatsApp">
                                <i class="fab fa-whatsapp"></i> Send on WA
                            </button>
                            <button type="button" onclick="copySingleSiteDetails(${idx})" class="btn" style="background: #f0fdf4; color: #166534; border: 1px solid #86efac; padding: 6px 11px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem; cursor: pointer;" title="Copy this specific image's caption text for WhatsApp">
                                <i class="fas fa-comment-dots"></i> Copy Caption
                            </button>
                        </div>
                    </div>

                    <!-- Live Caption Preview Snippet for this Image -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.5rem 0.75rem; font-family: monospace; font-size: 0.78rem; color: #334155; white-space: pre-line; line-height: 1.35;">
${captionText}
                    </div>
                </div>
                `;
            }).join('');
        }
        generateWhatsAppMessage();
    }

    function closeWhatsAppModal() {
        document.getElementById('whatsappShareModal').style.display = 'none';
        updateSelectionBar();
    }

    function generateSingleSiteCaption(s, idx, includePricing = true) {
        let lines = [];
        lines.push(`📍 *${idx + 1}. ${s.city.toUpperCase()}* (${s.site_code})`);
        if (s.area) {
            lines.push(`*Area/Zone:* ${s.area}`);
        }
        lines.push(`🏢 *Asset Details:* ${s.name}`);
        if (s.location) {
            lines.push(`📌 *Location Landmark:* ${s.location}`);
        }
        lines.push(`📐 *Size:* ${s.width}' x ${s.height}' (${Number(s.sqft).toLocaleString()} SQFT)`);
        lines.push(`💡 *Media Type:* ${s.type} • ${s.light_type} • ${s.owner_type || 'TA'}`);
        if (includePricing && s.card_rate && Number(s.card_rate) > 0) {
            lines.push(`💰 *Rate:* ₹${Number(s.card_rate).toLocaleString()}/month`);
        }
        return lines.join('\n');
    }

    function generateWhatsAppMessage() {
        const selected = getSelectedSitesData();
        if (!selected.length) return '';
        
        const includePricing = document.getElementById('waIncludePricing') ? document.getElementById('waIncludePricing').checked : true;
        const includePhotoLinks = document.getElementById('waIncludePhotoLinks') ? document.getElementById('waIncludePhotoLinks').checked : false;
        
        const origin = window.location.origin;
        let lines = [];
        
        lines.push(`🚀 *MEDIA INVENTORY PROPOSAL / SITE DETAILS*`);
        lines.push(`━━━━━━━━━━━━━━━━━━━━━━━`);
        lines.push(``);
        
        selected.forEach((s, idx) => {
            lines.push(generateSingleSiteCaption(s, idx, includePricing));
            
            if (includePhotoLinks && s.photo) {
                const photoUrl = `${origin}/uploads/sites/${s.photo}`;
                lines.push(`🖼️ *Site Photo:* ${photoUrl}`);
            }
            
            lines.push(``);
            lines.push(`───────────────────────`);
            lines.push(``);
        });
        
        lines.push(`Sudha Creative & Advertising | sudhacreative.com`);
        
        const msg = lines.join('\n');
        const previewEl = document.getElementById('waMessagePreview');
        if (previewEl) previewEl.value = msg;
        return msg;
    }

    async function copySiteImageToClipboard(photoFilename, siteCode, city) {
        if (!photoFilename) return;
        const imgUrl = `${window.location.origin}/uploads/sites/${photoFilename}`;
        
        try {
            const promiseOfBlob = new Promise(async (resolve, reject) => {
                try {
                    const resp = await fetch(imgUrl);
                    if (!resp.ok) throw new Error('Image fetch error');
                    const blob = await resp.blob();
                    
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    const objectUrl = URL.createObjectURL(blob);
                    img.src = objectUrl;
                    
                    await new Promise((res, rej) => {
                        img.onload = res;
                        img.onerror = rej;
                    });
                    
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    URL.revokeObjectURL(objectUrl);
                    
                    canvas.toBlob((pngBlob) => {
                        if (pngBlob) resolve(pngBlob);
                        else reject(new Error('Canvas toBlob failed'));
                    }, 'image/png');
                } catch (err) {
                    reject(err);
                }
            });

            if (typeof ClipboardItem !== 'undefined' && navigator.clipboard && navigator.clipboard.write) {
                const item = new ClipboardItem({ 'image/png': promiseOfBlob });
                await navigator.clipboard.write([item]);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Photo Copied!',
                    html: `Photo for <strong>${siteCode} (${city})</strong> copied to clipboard.<br><br>👉 Go to WhatsApp Web chat and press <kbd style="background:#e2e8f0;border:1px solid #cbd5e1;padding:3px 8px;border-radius:6px;font-weight:800;font-size:1rem;color:#0f172a;">Ctrl + V</kbd> to paste the image directly!`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fab fa-whatsapp"></i> Open WhatsApp Web',
                    cancelButtonText: 'Done',
                    confirmButtonColor: '#25D366'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.open('https://web.whatsapp.com', '_blank');
                    }
                });
            } else {
                throw new Error('Clipboard API not supported');
            }
        } catch (e) {
            console.warn('Clipboard error, downloading photo:', e);
            downloadSingleSitePhoto(photoFilename, siteCode, city);
            Swal.fire({
                icon: 'info',
                title: 'Photo Downloaded',
                html: `Photo <strong>${siteCode}</strong> has been downloaded to your computer.<br>👉 Drag and drop it into WhatsApp!`
            });
        }
    }

    async function copyStampedImageToClipboard(idx) {
        const selected = getSelectedSitesData();
        if (!selected[idx]) return;
        const s = selected[idx];
        if (!s.photo) {
            Swal.fire('No Photo', 'This site does not have a photo.', 'info');
            return;
        }
        
        try {
            const promiseOfBlob = new Promise(async (resolve, reject) => {
                try {
                    const imgUrl = `${window.location.origin}/uploads/sites/${s.photo}`;
                    const resp = await fetch(imgUrl);
                    if (!resp.ok) throw new Error('Could not load image');
                    const blob = await resp.blob();
                    
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    const objectUrl = URL.createObjectURL(blob);
                    img.src = objectUrl;
                    
                    await new Promise((res, rej) => {
                        img.onload = res;
                        img.onerror = rej;
                    });
                    
                    const srcW = img.naturalWidth || 1200;
                    const srcH = img.naturalHeight || 800;
                    const targetW = Math.max(1200, srcW);
                    const scale = targetW / srcW;
                    const targetH = Math.round(srcH * scale);
                    const footerH = Math.max(165, Math.round(targetW * 0.145));
                    
                    const canvas = document.createElement('canvas');
                    canvas.width = targetW;
                    canvas.height = targetH + footerH;
                    const ctx = canvas.getContext('2d');
                    
                    // Background
                    ctx.fillStyle = '#0b1220';
                    ctx.fillRect(0, 0, targetW, targetH + footerH);
                    
                    // Draw photo
                    ctx.drawImage(img, 0, 0, targetW, targetH);
                    URL.revokeObjectURL(objectUrl);
                    
                    // Top Accent Divider Bar (Sky blue with Amber corner)
                    ctx.fillStyle = '#0ea5e9';
                    ctx.fillRect(0, targetH, targetW, 6);
                    ctx.fillStyle = '#f59e0b';
                    ctx.fillRect(0, targetH, Math.round(targetW * 0.18), 6);
                    
                    // Banner background
                    ctx.fillStyle = '#0b1220';
                    ctx.fillRect(0, targetH + 6, targetW, footerH - 6);
                    
                    const padX = Math.round(targetW * 0.035);
                    const startY = targetH + Math.round(targetW * 0.018);
                    
                    // --- LINE 1: CITY NAME (Very Big, Bold, Crisp White) ---
                    const cityText = (s.city || 'CITY').toUpperCase();
                    ctx.fillStyle = '#ffffff';
                    ctx.font = `bold ${Math.round(targetW * 0.027)}px sans-serif`;
                    ctx.textAlign = 'left';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(cityText, padX, startY + Math.round(targetW * 0.022));
                    
                    // --- LINE 2: LOCATION / ASSET (Thana More - Big & Clean) ---
                    const row2Y = startY + Math.round(targetW * 0.062);
                    ctx.font = `bold ${Math.round(targetW * 0.021)}px sans-serif`;
                    ctx.fillStyle = '#cbd5e1';
                    
                    const nameStr = (s.name || '').trim();
                    const locStr = (s.location || '').trim();
                    let locVal = nameStr || locStr || 'Main Location';
                    if (locVal.length > 65) locVal = locVal.substring(0, 62) + '...';
                    ctx.fillText(locVal, padX, row2Y);
                    
                    // --- LINE 3: SIZE (Size: 20' x 18' - Vibrant Lime Green) ---
                    const row3Y = startY + Math.round(targetW * 0.104);
                    ctx.font = `bold ${Math.round(targetW * 0.022)}px sans-serif`;
                    ctx.fillStyle = '#cbd5e1';
                    ctx.fillText('Size: ', padX, row3Y);
                    const sizeLabelMetrics = ctx.measureText('Size: ');
                    
                    const sizeVal = `${s.width}' x ${s.height}'`;
                    ctx.fillStyle = '#4ade80';
                    ctx.fillText(sizeVal, padX + sizeLabelMetrics.width, row3Y);
                    
                    canvas.toBlob((pngBlob) => {
                        if (pngBlob) resolve(pngBlob);
                        else reject(new Error('Canvas toBlob failed'));
                    }, 'image/png');
                } catch (err) {
                    reject(err);
                }
            });

            if (typeof ClipboardItem !== 'undefined' && navigator.clipboard && navigator.clipboard.write) {
                const item = new ClipboardItem({ 'image/png': promiseOfBlob });
                await navigator.clipboard.write([item]);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Photo Copied!',
                    html: `Stamped photo for <strong>${s.site_code} (${s.city})</strong> with all details is copied to clipboard.<br><br>👉 Go to WhatsApp Web chat and press <kbd style="background:#e2e8f0;border:1px solid #cbd5e1;padding:3px 8px;border-radius:6px;font-weight:800;font-size:1rem;color:#0f172a;">Ctrl + V</kbd> to paste!`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fab fa-whatsapp"></i> Open WhatsApp Web',
                    cancelButtonText: 'Done',
                    confirmButtonColor: '#25D366'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.open('https://web.whatsapp.com', '_blank');
                    }
                });
            } else {
                throw new Error('Clipboard API not supported');
            }
        } catch (e) {
            console.warn('Clipboard error, downloading stamped photo:', e);
            downloadSingleSitePhoto(s.photo, s.site_code, s.city);
            Swal.fire({
                icon: 'info',
                title: 'Photo Downloaded',
                html: `Photo <strong>${s.site_code}</strong> has been downloaded to your computer.<br>👉 Drag and drop it into WhatsApp!`
            });
        }
    }

    function downloadSingleSitePhoto(photoFilename, siteCode, city, siteId = 0) {
        if (siteId) {
            window.location.href = `../../ajax/get_site_card_jpeg.php?id=${siteId}&download=1`;
            return;
        }
        if (!photoFilename) return;
        const imgUrl = `${window.location.origin}/uploads/sites/${photoFilename}`;
        const a = document.createElement('a');
        a.href = imgUrl;
        const ext = photoFilename.split('.').pop() || 'jpg';
        a.download = `${siteCode}_${city}.${ext}`;
        a.target = '_blank';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function copySingleSiteDetails(idx) {
        const selected = getSelectedSitesData();
        if (!selected[idx]) return;
        const s = selected[idx];
        const includePricing = document.getElementById('waIncludePricing') ? document.getElementById('waIncludePricing').checked : true;
        
        const txt = generateSingleSiteCaption(s, idx, includePricing);
        navigator.clipboard.writeText(txt).then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Caption Copied!',
                html: `Caption for <strong>${s.site_code} (${s.city})</strong> copied.<br><br>👉 In WhatsApp, click <strong>"Add a caption..."</strong> under this photo and press <kbd style="background:#e2e8f0;border:1px solid #cbd5e1;padding:2px 6px;border-radius:4px;font-weight:bold;">Ctrl+V</kbd>!`,
                timer: 2200,
                showConfirmButton: false
            });
        });
    }

    function openWhatsAppWeb() {
        const msg = generateWhatsAppMessage();
        if (!msg) {
            Swal.fire('Warning', 'Please select at least one site.', 'warning');
            return;
        }
        
        let phone = document.getElementById('waRecipientPhone') ? document.getElementById('waRecipientPhone').value.trim().replace(/[^0-9]/g, '') : '';
        let waUrl = '';
        if (phone) {
            if (phone.length === 10) phone = '91' + phone;
            waUrl = `https://web.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(msg)}`;
        } else {
            waUrl = `https://web.whatsapp.com/send?text=${encodeURIComponent(msg)}`;
        }
        window.open(waUrl, '_blank');
    }

    function openWhatsAppDesktopApp() {
        const msg = generateWhatsAppMessage();
        if (!msg) {
            Swal.fire('Warning', 'Please select at least one site.', 'warning');
            return;
        }
        
        let phone = document.getElementById('waRecipientPhone') ? document.getElementById('waRecipientPhone').value.trim().replace(/[^0-9]/g, '') : '';
        let waUrl = '';
        if (phone) {
            if (phone.length === 10) phone = '91' + phone;
            waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(msg)}`;
        } else {
            waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(msg)}`;
        }
        window.open(waUrl, '_blank');
    }

    async function shareWithImagesWebShare() {
        const selected = getSelectedSitesData();
        if (!selected.length) return;
        
        const includePricing = document.getElementById('waIncludePricing') ? document.getElementById('waIncludePricing').checked : true;
        const msg = generateWhatsAppMessage();
        const btn = document.getElementById('btnWebShareFiles');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Stamped JPEGs...';
        
        try {
            const filesArray = [];
            for (const s of selected) {
                if (s.photo) {
                    const imgUrl = `../../ajax/get_site_card_jpeg.php?id=${s.id}&price=${includePricing ? 1 : 0}`;
                    try {
                        const resp = await fetch(imgUrl);
                        if (resp.ok) {
                            const blob = await resp.blob();
                            const filename = `${s.site_code}_${s.city}_Info.jpg`;
                            const file = new File([blob], filename, { type: 'image/jpeg' });
                            filesArray.push(file);
                        }
                    } catch(err) {
                        console.warn('Could not fetch stamped image for web share:', s.photo, err);
                    }
                }
            }
            
            if (filesArray.length > 0 && navigator.canShare && navigator.canShare({ files: filesArray })) {
                await navigator.share({
                    files: filesArray
                });
            } else if (navigator.share) {
                await navigator.share({
                    text: msg
                });
            } else {
                openWhatsAppWeb();
            }
        } catch(e) {
            if (e.name !== 'AbortError') {
                console.error('Web share error:', e);
                openWhatsAppWeb();
            }
        } finally {
            if (btn) btn.innerHTML = origHtml;
        }
    }

    function sendSingleSiteToWhatsApp(idx) {
        const selected = getSelectedSitesData();
        if (!selected[idx]) return;
        const s = selected[idx];
        const includePricing = document.getElementById('waIncludePricing') ? document.getElementById('waIncludePricing').checked : true;
        
        let caption = generateSingleSiteCaption(s, idx, includePricing);
        if (s.photo) {
            caption += `\n🖼️ *Photo:* ${window.location.origin}/uploads/sites/${s.photo}`;
        }
        
        let phone = document.getElementById('waRecipientPhone') ? document.getElementById('waRecipientPhone').value.trim().replace(/[^0-9]/g, '') : '';
        let waUrl = '';
        if (phone) {
            if (phone.length === 10) phone = '91' + phone;
            waUrl = `https://web.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(caption)}`;
        } else {
            waUrl = `https://web.whatsapp.com/send?text=${encodeURIComponent(caption)}`;
        }
        window.open(waUrl, '_blank');
    }

    function downloadSelectedPhotosZip(isStamped = false) {
        const selected = getSelectedSitesData();
        if (!selected.length) {
            Swal.fire('Warning', 'Please select at least one site.', 'warning');
            return;
        }
        const ids = selected.map(s => s.id).join(',');
        const includePricing = document.getElementById('waIncludePricing') ? (document.getElementById('waIncludePricing').checked ? 1 : 0) : 1;
        const stampedParam = isStamped ? `&stamped=1&price=${includePricing}` : '';
        window.location.href = `../../ajax/download_sites_zip.php?ids=${ids}${stampedParam}`;
    }

    function copyWhatsAppMessageText() {
        const msg = generateWhatsAppMessage();
        if (!msg) return;
        navigator.clipboard.writeText(msg).then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: 'Formatted WhatsApp message copied to clipboard.',
                timer: 1500,
                showConfirmButton: false
            });
        });
    }

    function quickCopyFormattedDetails() {
        const msg = generateWhatsAppMessage();
        if (!msg) {
            Swal.fire('Warning', 'Please select at least one site.', 'warning');
            return;
        }
        navigator.clipboard.writeText(msg).then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Copied to Clipboard!',
                text: 'Site details and image links copied. You can now paste directly into WhatsApp.',
                timer: 2000,
                showConfirmButton: false
            });
        });
    }
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>