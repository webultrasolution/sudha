<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

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
        $vendor_gst = clean($_POST['vendor_gst'] ?? '');
        $grade = clean($_POST['grade']);
        $available_from = !empty($_POST['available_from']) ? $_POST['available_from'] : date('Y-m-d');

        if ($_POST['action'] === 'add_site') {
            try {
                $stmt = $pdo->prepare("INSERT INTO sites (site_code, name, location, area, city, district, latitude, longitude, type, width, height, facing, light_type, hsn_code, vendor_gst, grade, owner_type, vendor_id, card_rate, purchase_rate, available_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $location, $area, $city, $district, $latitude, $longitude, $type, $width, $height, $facing, $light_type, $hsn_code, $vendor_gst, $grade, $owner_type, $vendor_id, $card_rate, $purchase_rate, $available_from]);
                $site_id = $pdo->lastInsertId();
                
                // Handle Multi-Image Upload
                if (!empty($_FILES['site_images']['name'][0])) {
                    $uploadDir = __DIR__ . '/../../uploads/sites/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    foreach ($_FILES['site_images']['name'] as $key => $val) {
                        $filename = time() . '_' . $site_id . '_' . basename($_FILES['site_images']['name'][$key]);
                        $targetFile = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['site_images']['tmp_name'][$key], $targetFile)) {
                            $pdo->prepare("INSERT INTO site_images (site_id, filename) VALUES (?, ?)")->execute([$site_id, $filename]);
                        }
                    }
                }
                
                header("Location: sites.php?msg=added"); exit;
            } catch (PDOException $e) {
                header("Location: sites.php?error=" . urlencode($e->getMessage())); exit;
            }
        } else {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("UPDATE sites SET site_code=?, name=?, location=?, area=?, city=?, district=?, latitude=?, longitude=?, type=?, width=?, height=?, facing=?, light_type=?, hsn_code=?, vendor_gst=?, grade=?, owner_type=?, vendor_id=?, card_rate=?, purchase_rate=?, available_from=? WHERE id=?");
                $stmt->execute([$code, $name, $location, $area, $city, $district, $latitude, $longitude, $type, $width, $height, $facing, $light_type, $hsn_code, $vendor_gst, $grade, $owner_type, $vendor_id, $card_rate, $purchase_rate, $available_from, $id]);
                
                // Handle Multi-Image Upload (New)
                if (!empty($_FILES['site_images']['name'][0])) {
                    $uploadDir = __DIR__ . '/../../uploads/sites/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    foreach ($_FILES['site_images']['name'] as $key => $val) {
                        $filename = time() . '_' . $id . '_' . basename($_FILES['site_images']['name'][$key]);
                        $targetFile = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['site_images']['tmp_name'][$key], $targetFile)) {
                            $pdo->prepare("INSERT INTO site_images (site_id, filename) VALUES (?, ?)")->execute([$id, $filename]);
                        }
                    }
                }
                
                header("Location: sites.php?msg=updated"); exit;
            } catch (PDOException $e) {
                header("Location: sites.php?error=" . urlencode($e->getMessage())); exit;
            }
        }
    } else if ($_POST['action'] === 'delete_site') {
        $id = intval($_POST['id']);
        try {
            // Also delete images from folder if needed, for now just DB cleanup
            $pdo->prepare("DELETE FROM site_images WHERE site_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM sites WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]); exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
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
if (!empty($search)) {
    $where .= " AND (s.site_code LIKE ? OR s.name LIKE ? OR s.location LIKE ? OR s.city LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}
if (!empty($_GET['from'])) {
    $where .= " AND s.available_from <= ?";
    $params[] = $_GET['from'];
}
if (!empty($_GET['to'])) {
    // Basic logic: if we need it by 'to', it must be available before that date
    // You might want more complex logic depending on booking overlaps
}

// Counts for Tabs
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn(),
    'Hoarding' => $pdo->query("SELECT COUNT(*) FROM sites WHERE type='Hoarding'")->fetchColumn(),
    'Unipole' => $pdo->query("SELECT COUNT(*) FROM sites WHERE type='Unipole'")->fetchColumn(),
    'Gantry' => $pdo->query("SELECT COUNT(*) FROM sites WHERE type='Gantry'")->fetchColumn(),
    'BQS' => $pdo->query("SELECT COUNT(*) FROM sites WHERE type='BQS'")->fetchColumn(),
    'DCP' => $pdo->query("SELECT COUNT(*) FROM sites WHERE type='DCP'")->fetchColumn(),
];

// Fetch Sites
$stmt = $pdo->prepare("SELECT s.*, p.name as vendor_name FROM sites s LEFT JOIN partners p ON s.vendor_id = p.id $where ORDER BY s.id DESC");
$stmt->execute($params);
$sites = $stmt->fetchAll();

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
?>

<div class="inventory-tabs">
    <a href="?media=all" class="tab <?php echo $mediaFilter == 'all' ? 'active' : ''; ?>">All (<?php echo $counts['all']; ?>)</a>
    <a href="?media=Hoarding" class="tab <?php echo $mediaFilter == 'Hoarding' ? 'active' : ''; ?>">Hoarding (<?php echo $counts['Hoarding']; ?>)</a>
    <a href="?media=Unipole" class="tab <?php echo $mediaFilter == 'Unipole' ? 'active' : ''; ?>">Unipole (<?php echo $counts['Unipole']; ?>)</a>
    <a href="?media=Gantry" class="tab <?php echo $mediaFilter == 'Gantry' ? 'active' : ''; ?>">Gantry (<?php echo $counts['Gantry']; ?>)</a>
    <a href="?media=BQS" class="tab <?php echo $mediaFilter == 'BQS' ? 'active' : ''; ?>">BQS (<?php echo $counts['BQS']; ?>)</a>
    <a href="?media=DCP" class="tab <?php echo $mediaFilter == 'DCP' ? 'active' : ''; ?>">DCP (<?php echo $counts['DCP']; ?>)</a>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <div class="search-group" style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" id="site-search" placeholder="Search ID, Location, City..." class="p-input" value="<?php echo $search; ?>" style="width: 250px; padding-left: 35px;">
            </div>
            
            <div style="display: flex; align-items: center; gap: 0.5rem; background: #fff; padding: 2px 10px; border: 1px solid #cbd5e1; border-radius: 8px;">
                <span style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Available Between:</span>
                <input type="date" id="filter-from" class="p-input" style="border:none; width: 130px; font-size: 0.8rem;" value="<?php echo $_GET['from'] ?? ''; ?>">
                <span style="color: #cbd5e1;">-</span>
                <input type="date" id="filter-to" class="p-input" style="border:none; width: 130px; font-size: 0.8rem;" value="<?php echo $_GET['to'] ?? ''; ?>">
            </div>

            <button class="btn btn-primary" onclick="doSearch()" style="padding: 0.6rem 1.5rem;"><i class="fas fa-filter"></i> Apply Filters</button>
            <button class="btn" onclick="window.location.href='sites.php'" style="background: #f1f5f9; color: #475569;"><i class="fas fa-sync-alt"></i></button>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-secondary" onclick="openImportModal()" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                <i class="fas fa-file-import"></i> Bulk Import
            </button>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Add New Site
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table responsive-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
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
                <?php $sn=1; foreach ($sites as $s): ?>
                <tr>
                    <td data-label="#"><?php echo $sn++; ?></td>
                    <td data-label="Preview">
                        <?php 
                        $imgs = $pdo->prepare("SELECT filename FROM site_images WHERE site_id = ? LIMIT 1");
                        $imgs->execute([$s['id']]);
                        $img = $imgs->fetch();
                        if($img):
                        ?>
                            <img src="../../uploads/sites/<?php echo $img['filename']; ?>" style="width: 100px; height: 65px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" onclick="viewPhotos(<?php echo $s['id']; ?>, '<?php echo $s['light_type']; ?>')">
                        <?php else: ?>
                            <div style="width: 100px; height: 65px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8;">
                                <i class="fas fa-image" style="font-size: 1.2rem; margin-bottom: 2px;"></i>
                                <span style="font-size: 0.6rem; font-weight: 700; text-transform: uppercase;">No Photo</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td data-label="City / Code">
                        <div style="font-weight: 700; color: #1e293b;"><?php echo $s['city']; ?></div>
                        <small style="color: #ef3417ff;"><?php echo $s['area'] ?? ''; ?></small>
                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo $s['site_code']; ?></div>
                    </td>
                    <td data-label="Asset Details">
                        <div style="font-weight: 700; color: #1e293b; margin-bottom: 2px;"><?php echo $s['name']; ?></div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 6px;"><?php echo $s['location']; ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">
                            <span class="media-badge <?php echo strtolower($s['type']); ?>"><?php echo $s['type']; ?></span> • 
                            <?php echo $s['light_type']; ?> • 
                            <span style="color: var(--primary);"><?php echo $s['owner_type']; ?></span>
                        </div>
                    </td>
                    <td data-label="Size">
                        <div style="font-weight: 700; color: #475569;"><?php echo $s['width'] . "' x " . $s['height'] . "'"; ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo number_format($s['sqft']); ?> SQFT</div>
                    </td>
                    <td data-label="Pricing">
                        <div style="font-weight: 700; color: #1e293b;"><?php echo formatCurrency($s['card_rate']); ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">Cost: <?php echo formatCurrency($s['purchase_rate']); ?></div>
                    </td>
                    <td data-label="Availability">
                        <div style="font-weight: 600; color: #475569; font-size: 0.8rem;"><?php echo date('d M Y', strtotime($s['available_from'])); ?></div>
                    </td>
                    <td data-label="Status"><span class="status-pill <?php echo $s['status']; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                    <td data-label="Actions" style="text-align: right; white-space: nowrap;">
                        <button class="btn-icon btn-edit" onclick="editSite(<?php echo htmlspecialchars(json_encode($s)); ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon btn-delete" onclick="deleteSite(event, <?php echo $s['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
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
                        <option value="Hoarding">Hoarding</option>
                        <option value="Unipole">Unipole</option>
                        <option value="Gantry">Gantry</option>
                        <option value="BQS">Bus Shelter (BQS)</option>
                        <option value="DCP">Digital City Panel (DCP)</option>
                        <option value="LED Screen">LED Screen</option>
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
                    <label>HSN / SAC Code</label>
                    <input type="text" name="hsn_code" id="f_hsn" value="998366" placeholder="e.g. 998366">
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
                    <select name="vendor_id" id="f_vendor">
                        <option value="">Select Vendor</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 3;">
                    <label><i class="fas fa-images"></i> Site Photos (Multi-upload)</label>
                    <div id="drop-zone" class="drop-zone" onclick="document.getElementById('file-input').click()">
                        <div class="drop-zone-content">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem;"></i>
                            <p>Drag & Drop images here or <span>click to browse</span></p>
                            <small>Supports: JPG, PNG, WEBP (Max 5MB each)</small>
                        </div>
                        <input type="file" name="site_images[]" id="file-input" multiple accept="image/*" style="display: none;" onchange="handleFiles(this.files)">
                    </div>
                    <div id="preview-container" class="preview-grid"></div>
                    <div id="existing-images-label" style="display: none; margin-top: 1rem; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Existing Images</div>
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
        <form id="importForm" action="../../ajax/import_inventory.php" method="POST" enctype="multipart/form-data" style="padding: 1rem 0;">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Select CSV File</label>
                <input type="file" name="file" accept=".csv" required style="padding: 1rem; border: 2px dashed #e2e8f0; background: #f8fafc; text-align: center;">
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Download <a href="../../templates/inventory_template.csv" download style="color: var(--primary); font-weight: 600;">Inventory Template</a> first.
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
    <span class="lb-close" onclick="closeLightbox()">&times;</span>
    <button class="lb-nav lb-prev" onclick="lbPrev()"><i class="fas fa-chevron-left"></i></button>
    <button class="lb-nav lb-next" onclick="lbNext()"><i class="fas fa-chevron-right"></i></button>
    <div class="lb-content">
        <div id="lbSlider" class="lb-slider"></div>
    </div>
    <div id="lbCounter" class="lb-counter">1 / 1</div>
</div>

<style>
.inventory-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.tab { padding: 0.625rem 1.25rem; background: white; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 0.9rem; }
.tab.active { background: var(--primary); color: white; border-color: var(--primary); }
.media-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
.media-badge.hoarding { background: #dcfce7; color: #166534; }
.media-badge.unipole { background: #e0f2fe; color: #0369a1; }
.media-badge.bqs { background: #fef9c3; color: #854d0e; }
.media-badge.dcp { background: #fdf2f8; color: #9d174d; }
.id-badge { background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 4px; font-family: monospace; font-weight: 700; color: #475569; }
.status-pill.available { background: #dcfce7; color: #166534; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; }
.status-pill.booked { background: #fee2e2; color: #991b1b; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; }
.p-input { padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
.modal-content { background: white; margin: 3% auto; padding: 2rem; border-radius: 12px; }
.close { cursor: pointer; float: right; font-size: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.3rem; font-size: 0.85rem; font-weight: 600; color: #475569; }
.form-group input, .form-group select { width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; }

/* Lightbox Styles */
.lb-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.95); z-index: 3000; display: none; align-items: center; justify-content: center; flex-direction: column; backdrop-filter: blur(10px); }
.lb-content { position: relative; width: 100%; height: 85vh; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.lb-slider { display: flex; transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1); height: 100%; width: 100%; }
.lb-slide { min-width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; padding: 0 20px; }
.lb-slide img { max-width: 100%; max-height: 100%; object-fit: contain; box-shadow: 0 0 50px rgba(0,0,0,0.5); border-radius: 12px; }
.lb-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255,255,255,0.1); color: white; width: 64px; height: 64px; border-radius: 50%; cursor: pointer; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; transition: all 0.3s; z-index: 3001; }
.lb-nav:hover { background: var(--primary); border-color: var(--primary); transform: translateY(-50%) scale(1.1); box-shadow: 0 0 20px rgba(28, 173, 169, 0.4); }
.lb-prev { left: 40px; }
.lb-next { right: 40px; }
.lb-close { position: absolute; top: 40px; right: 40px; color: rgba(255,255,255,0.5); font-size: 2.5rem; cursor: pointer; z-index: 3002; transition: all 0.3s; line-height: 1; }
.lb-close:hover { color: white; transform: rotate(90deg); }
.lb-counter { position: absolute; bottom: 40px; color: white; font-weight: 600; letter-spacing: 2px; background: rgba(255,255,255,0.1); padding: 8px 20px; border-radius: 30px; font-size: 0.875rem; border: 1px solid rgba(255,255,255,0.1); }
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
.drop-zone:hover, .drop-zone.dragover {
    border-color: var(--primary);
    background: rgba(13, 148, 136, 0.05);
}
.drop-zone-content p { font-size: 0.9rem; font-weight: 600; color: #475569; margin: 0.5rem 0; }
.drop-zone-content p span { color: var(--primary); text-decoration: underline; }
.drop-zone-content small { color: #94a3b8; font-size: 0.75rem; }

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

function doSearch() {
    const s = document.getElementById('site-search').value;
    const from = document.getElementById('filter-from').value;
    const to = document.getElementById('filter-to').value;
    window.location.href = `?media=<?php echo $mediaFilter; ?>&search=${encodeURIComponent(s)}&from=${from}&to=${to}`;
}
function openModal() { 
    const form = document.getElementById('siteForm');
    form.reset(); 
    form.classList.remove('was-validated');
    document.getElementById('preview-container').innerHTML = '';
    document.getElementById('existing-images').innerHTML = '';
    pendingFiles = []; // Reset pending files
    document.getElementById('file-input').value = '';
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
if(dropZone) {
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
    if(form) form.classList.remove('was-validated');
    
    document.getElementById('formAction').value = 'edit_site';
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
        if(imgs.length > 0) {
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
                if(res.success) {
                    document.getElementById(`img-item-${imgId}`).remove();
                    // If no images left, hide label
                    if(document.getElementById('existing-images').children.length === 0) {
                        document.getElementById('existing-images-label').style.display = 'none';
                    }
                }
            });
        }
    });
}

function deleteSite(e, id) {
    if(e) e.preventDefault();
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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
