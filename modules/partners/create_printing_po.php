<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$activePage = 'printing_rates';
$pageTitle = 'Create Printing PO';

requirePermission('vendors', 'view');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? clean($_GET['action']) : 'add';

if ($action === 'edit') {
    requirePermission('vendors', 'edit');
} else {
    requirePermission('vendors', 'add');
}

$po_number = isset($_GET['po_number']) ? clean($_GET['po_number']) : null;
$rate_ids = isset($_GET['rate_ids']) && is_array($_GET['rate_ids']) ? $_GET['rate_ids'] : [];
$vendor_id_get = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

$rateData = null;
$selectedSitesData = [];

if ($action === 'edit') {
    if ($po_number) {
        $stmt = $pdo->prepare("SELECT * FROM vendor_printing_rates WHERE po_number = ? AND vendor_id = ?");
        $stmt->execute([$po_number, $vendor_id_get]);
        $rows = $stmt->fetchAll();
    } else if (!empty($rate_ids)) {
        $in = str_repeat('?,', count($rate_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM vendor_printing_rates WHERE id IN ($in)");
        $stmt->execute($rate_ids);
        $rows = $stmt->fetchAll();
    } else {
        die("Invalid PO reference.");
    }

    if (empty($rows)) {
        die("Vendor PO not found.");
    }

    $rateData = $rows[0];
    foreach($rows as $r) {
        $selectedSitesData[$r['site_id']] = $r['rate_per_sqft'];
    }
    $pageTitle = 'Edit Printing PO';
}

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$sites = $pdo->query("
    SELECT s.id, s.name, s.site_code, s.width, s.height, s.vendor_id, s.city, s.state, s.type, s.light_type, s.owner_type, s.status, s.location,
        p.name as vendor_name,
        (SELECT GROUP_CONCAT(filename) FROM site_images WHERE site_id = s.id) as all_images,
        (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail
    FROM sites s
    LEFT JOIN partners p ON s.vendor_id = p.id
    ORDER BY s.site_code ASC
")->fetchAll();

// Fetch filter values
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL AND width != '' AND height != '' ORDER BY width, height")->fetchAll(PDO::FETCH_COLUMN);

include_once __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="printing_rates.php" class="btn btn-secondary" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none;">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<div class="card" style="border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 0; overflow: hidden; background: #fff;">

    <form method="POST" action="printing_rates.php" id="rateForm" style="display: flex; flex-direction: column; margin: 0;">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
        <?php if ($action === 'edit'): ?>
            <?php if ($po_number): ?>
                <input type="hidden" name="po_number" value="<?php echo htmlspecialchars($po_number); ?>">
            <?php else: ?>
                <?php foreach($rate_ids as $r_id): ?>
                    <input type="hidden" name="rate_ids[]" value="<?php echo htmlspecialchars($r_id); ?>">
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <input type="hidden" name="rate_per_sqft" id="f_rate" value="0">
            <!-- Full-Width Horizontal Header Config Panel -->
            <div style="background: #f8fafc; padding: 1rem 2.5rem; border-bottom: 1px solid #e2e8f0; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;">
                <!-- Column 1: Select Vendor -->
                <div class="form-group" style="margin: 0;">
                    <label style="font-weight: 800; color: #475569; font-size: 0.65rem; margin-bottom: 0.35rem; display: block; text-transform: uppercase; letter-spacing: 0.05em;">1. Select Vendor</label>
                    <select name="vendor_id" id="f_vendor" required style="background: #fff; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 0.5rem 0.75rem; width: 100%; font-weight: 600; font-size: 0.85rem; height: 38px;">
                        <option value="">Choose Printing Partner...</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo ($rateData && $rateData['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Column 2: Media Type Select Dropdown -->
                <div class="form-group" style="margin: 0;">
                    <label style="font-weight: 800; color: #475569; font-size: 0.65rem; margin-bottom: 0.35rem; display: block; text-transform: uppercase; letter-spacing: 0.05em;">2. Media Type</label>
                    <select name="media_type" id="f_media" style="background: #fff; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 0.5rem 0.75rem; width: 100%; font-weight: 600; font-size: 0.85rem; height: 38px;">
                        <option value="Flex" <?php echo (!$rateData || $rateData['media_type'] === 'Flex') ? 'selected' : ''; ?>>Flex</option>
                        <option value="Vinyl" <?php echo ($rateData && $rateData['media_type'] === 'Vinyl') ? 'selected' : ''; ?>>Vinyl</option>
                        <option value="Star Flex" <?php echo ($rateData && $rateData['media_type'] === 'Star Flex') ? 'selected' : ''; ?>>Star Flex</option>
                        <option value="Backlit Flex" <?php echo ($rateData && ($rateData['media_type'] === 'Backlit Flex' || $rateData['media_type'] === 'Backlit')) ? 'selected' : ''; ?>>Backlit</option>
                        <option value="One Way Vision" <?php echo ($rateData && ($rateData['media_type'] === 'One Way Vision' || $rateData['media_type'] === 'OWV')) ? 'selected' : ''; ?>>OWV</option>
                        <option value="Canvas" <?php echo ($rateData && $rateData['media_type'] === 'Canvas') ? 'selected' : ''; ?>>Canvas</option>
                    </select>
                </div>
            </div>

            <!-- Full-Width Bottom Content Area -->
            <div style="padding: 1.5rem 2.5rem; display: flex; flex-direction: column;">
                <div class="form-group" id="site_selection_group" style="margin: 0; display: flex; flex-direction: column;">
                    <label id="site_label" style="display: flex; justify-content: space-between; align-items: center; font-weight: 800; color: #1e293b; font-size: 0.75rem; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <span>Site Selection</span>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <button type="button" id="toggleSearchBtn" onclick="toggleSearchCriteria()" style="background: #fff; border: 1.5px solid #0d9488; border-radius: 20px; padding: 6px 14px; font-size: 0.65rem; font-weight: 800; color: #0d9488; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(13, 148, 136, 0.05);">
                                    <i class="fas fa-eye-slash" id="toggleSearchIcon"></i> <span id="toggleSearchText">HIDE SEARCH CRITERIA</span>
                                </button>
                                <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 500; text-transform: none;">Pick sites to apply rates</span>
                            </div>
                        </label>

                        <select name="site_id" id="f_site" style="display: none;">
                            <option value="">Generic / All Sites</option>
                        </select>

                        <!-- Multi-Site Container -->
                        <div id="multi_site_container" style="flex: 1; display: flex; flex-direction: column;">
                            <!-- Advanced Search Criteria Filter Menu -->
                            <div id="search_criteria_panel" style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 0.75rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); transition: all 0.25s ease-in-out;">
                                <!-- Unified Top Row: Title, Radios, Reset -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 2rem;">
                                        <span style="font-weight: 800; color: #0d9488; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                                            <i class="fas fa-filter"></i> Filters
                                        </span>

                                        <!-- Ownership -->
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <label style="font-size: 0.55rem; font-weight: 800; color: #475569; margin: 0; text-transform: uppercase; letter-spacing: 0.025em;">Ownership:</label>
                                            <div style="display: flex; gap: 0.75rem;">
                                                <label style="font-size: 0.65rem; font-weight: 600; display: flex; align-items: center; gap: 4px; cursor: pointer; color: #1e293b; margin: 0;"><input type="radio" name="ownership" value="all" checked onchange="filterSitesInModal()" style="width: 12px; height: 12px; accent-color: #0d9488; cursor: pointer;"> All</label>
                                                <label style="font-size: 0.65rem; font-weight: 600; display: flex; align-items: center; gap: 4px; cursor: pointer; color: #1e293b; margin: 0;"><input type="radio" name="ownership" value="HA" onchange="filterSitesInModal()" style="width: 12px; height: 12px; accent-color: #0d9488; cursor: pointer;"> Self</label>
                                                <label style="font-size: 0.65rem; font-weight: 600; display: flex; align-items: center; gap: 4px; cursor: pointer; color: #1e293b; margin: 0;"><input type="radio" name="ownership" value="TA" onchange="filterSitesInModal()" style="width: 12px; height: 12px; accent-color: #0d9488; cursor: pointer;"> Vendor</label>
                                            </div>
                                        </div>

                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <label style="font-size: 0.55rem; font-weight: 800; color: #475569; margin: 0; text-transform: uppercase; letter-spacing: 0.025em;">Availability:</label>
                                            <div style="display: flex; gap: 0.75rem;">
                                                <label style="font-size: 0.65rem; font-weight: 600; display: flex; align-items: center; gap: 4px; cursor: pointer; color: #1e293b; margin: 0;"><input type="radio" name="availability" value="available" checked onchange="filterSitesInModal()" style="width: 12px; height: 12px; accent-color: #0d9488; cursor: pointer;"> Available</label>
                                                <label style="font-size: 0.65rem; font-weight: 600; display: flex; align-items: center; gap: 4px; cursor: pointer; color: #1e293b; margin: 0;"><input type="radio" name="availability" value="all" onchange="filterSitesInModal()" style="width: 12px; height: 12px; accent-color: #0d9488; cursor: pointer;"> All</label>
                                            </div>
                                        </div>

                                        <!-- Vendor Dropdown (Hidden by default) -->
                                        <div id="vendor_filter_group" style="display: none; align-items: center; gap: 0.75rem;">
                                            <label style="font-size: 0.55rem; font-weight: 800; color: #475569; margin: 0; text-transform: uppercase; letter-spacing: 0.025em;">Vendor:</label>
                                            <select id="filter_vendor" onchange="filterSitesInModal()" style="padding: 2px 8px; font-size: 0.7rem; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff; font-weight: 600; height: 22px;">
                                                <option value="">All Vendors</option>
                                                <?php foreach ($vendors as $v): ?>
                                                    <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <button type="button" onclick="resetFilters()" style="background: none; border: none; color: #ef4444; font-size: 0.6rem; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 4px; padding: 0; text-transform: uppercase;">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>

                                <!-- Search & Dropdowns Grid -->
                                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 0.5rem; align-items: flex-end;">
                                    <div style="margin-bottom: 0;">
                                        <div style="position: relative;">
                                            <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: #94a3b8;"></i>
                                            <input type="text" id="siteSearch" placeholder="Search by name, code, city..." oninput="filterSitesInModal()" style="width: 100%; padding: 4px 10px 4px 28px; font-size: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; background: #fff; height: 30px; font-family: inherit;">
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 0;">
                                        <select id="filter_media" onchange="filterSitesInModal()" style="width: 100%; padding: 4px 8px; font-size: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; background: #fff; height: 30px; font-family: inherit;">
                                            <option value="">All Media</option>
                                            <?php foreach ($mediaTypes as $mt): ?>
                                                <option value="<?php echo htmlspecialchars($mt); ?>"><?php echo htmlspecialchars($mt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: 0;">
                                        <select id="filter_state" onchange="filterSitesInModal()" style="width: 100%; padding: 4px 8px; font-size: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; background: #fff; height: 30px; font-family: inherit;">
                                            <option value="">All States</option>
                                            <?php foreach ($states as $st): ?>
                                                <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: 0;">
                                        <select id="filter_city" onchange="filterSitesInModal()" style="width: 100%; padding: 4px 8px; font-size: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; background: #fff; height: 30px; font-family: inherit;">
                                            <option value="">All Cities</option>
                                            <?php foreach ($cities as $ct): ?>
                                                <option value="<?php echo htmlspecialchars($ct); ?>"><?php echo htmlspecialchars($ct); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: 0;">
                                        <select id="filter_light" onchange="filterSitesInModal()" style="width: 100%; padding: 4px 8px; font-size: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; background: #fff; height: 30px; font-family: inherit;">
                                            <option value="">All Lights</option>
                                            <?php foreach ($illuminations as $il): ?>
                                                <option value="<?php echo htmlspecialchars($il); ?>"><?php echo htmlspecialchars($il); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: 0;">
                                        <select id="filter_size" onchange="filterSitesInModal()" style="width: 100%; padding: 4px 8px; font-size: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; background: #fff; height: 30px; font-family: inherit;">
                                            <option value="">All Sizes</option>
                                            <?php foreach ($sizes as $sz): ?>
                                                <option value="<?php echo htmlspecialchars($sz); ?>"><?php echo htmlspecialchars($sz); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Select All + Count Header -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <label style="display: flex; align-items: center; cursor: pointer; font-size: 0.75rem; font-weight: 800; color: #0d9488; margin: 0; background: #fff; padding: 6px 12px; border-radius: 20px; border: 1.5px solid #0d9488; transition: all 0.2s;">
                                    <input type="checkbox" id="selectAllSites" style="width: 16px; height: 16px; margin-right: 8px; accent-color: #0d9488; cursor: pointer;"> SELECT ALL (<span id="filtered_sites_count">0</span> matching)
                                </label>
                                <?php if ($action !== 'edit'): ?>
                                <button type="button" onclick="openBucket()" id="review-bucket-btn" style="background: #f0fdfa; border: 1.5px solid #0d9488; color: #0d9488; padding: 6px 14px; border-radius: 20px; font-weight: 800; display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.75rem;">
                                    <i class="fas fa-shopping-basket"></i>
                                    Selected: <span id="selected-count-btm" style="background: #0d9488; color: white; padding: 0.1rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">0</span>
                                </button>
                                <?php endif; ?>
                            </div>

                            <!-- Table-based site list -->
                            <div id="site_checkbox_list" style="flex: 1; max-height: 520px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 12px; background: #fff;">
                                <table class="po-site-table" id="site-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                                    <thead style="background: white; position: sticky; top: 0; z-index: 10;">
                                        <tr style="border-bottom: 2px solid #f1f5f9;">
                                            <th style="width: 40px; padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase; text-align: center;">#</th>
                                            <th style="width: 50px; padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase; text-align: center;"><i class="far fa-check-square"></i></th>
                                            <th style="width: 130px; padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">PREVIEW</th>
                                            <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">CITY / CODE</th>
                                            <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">ASSET DETAILS</th>
                                            <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">SIZE</th>
                                            <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase; width: 140px;">RATE ₹/SQFT</th>
                                            <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase; text-align: right; width: 130px;">TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody id="site-rows-body">
                                        <tr><td colspan="8" style="text-align: center; padding: 4rem; color: #94a3b8; font-size: 0.9rem; font-style: italic;">Select a vendor to see sites...</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Bar -->
                            <div id="po-pagination-wrap" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border: 1px solid #f1f5f9; border-top: none; border-radius: 0 0 12px 12px;">
                                <div style="font-size: 0.75rem; font-weight: 700; color: #64748b;">
                                    Showing <span id="po-pg-start">0</span>–<span id="po-pg-end">0</span> of <span id="po-pg-total">0</span> sites
                                </div>
                                <div id="po-pg-numbers" style="display: flex; gap: 0.35rem; align-items: center;"></div>
                            </div>
                        </div>
                    </div>
                </div>

        <div style="background: #f8fafc; padding: 1.5rem 2.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; align-items: center; position: sticky; bottom: 0; z-index: 100; box-shadow: 0 -4px 12px rgba(0,0,0,0.05);">
            <div>
                <a href="printing_rates.php" class="btn" style="font-weight: 700; color: #64748b; margin-right: 1.5rem; font-size: 1rem; border: none; background: transparent; padding: 0.5rem 1.5rem; text-decoration: none;">Discard Changes</a>
                <button type="submit" class="btn btn-primary" style="background: #0d9488; color: white; border: none; padding: 1rem 3rem; border-radius: 12px; font-weight: 800; font-size: 1.1rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2); cursor: pointer;">
                    <i class="fas fa-save" style="margin-right: 0.5rem;"></i> Save Printing PO
                </button>
            </div>
        </div>
    </form>
</div>

<!-- SELECTION BUCKET (Slide-out Drawer) -->
<div id="bucket-backdrop" onclick="closeBucket()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 2000; display: none;"></div>
<div id="selection-bucket-panel" style="position: fixed; top: 0; right: -1400px; width: 1200px; max-width: 95vw; height: 100%; background: white; z-index: 2001; box-shadow: -10px 0 30px rgba(0,0,0,0.1); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; box-sizing: border-box;">
    <div class="p-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: #0d9488; color: white; border: none; margin: 0;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <i class="fas fa-shopping-basket" style="font-size: 1.2rem;"></i>
            <span style="font-size: 1.1rem; color: white; font-weight: 800;">Selection Review</span>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="selection-stats" style="background: rgba(255,255,255,0.2); color: white; padding: 0.3rem 0.8rem; border-radius: 6px; font-weight: 800; font-size: 0.75rem;">
                <span id="bucket-count">0</span> Assets Selected
            </div>
            <button type="button" onclick="closeBucket()" style="background: rgba(0,0,0,0.1); border: none; color: white; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
    </div>
    <div id="bucket-empty-msg" style="padding: 4rem 2rem; text-align: center; color: #94a3b8; font-weight: 700;">
        <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.3;"></i>
        Your bucket is empty. Select assets from the list.
    </div>
    <div id="bucket-list" style="flex: 1; overflow-y: auto; padding: 1rem;"></div>
    <div style="padding: 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc;">
        <button type="button" onclick="closeBucket()" class="btn btn-primary" style="width: 100%; height: 45px; border-radius: 10px; font-weight: 800; background: #0d9488; border: none; color: white; cursor: pointer;">CONTINUE SELECTION</button>
    </div>
</div>

<style>
.po-site-table { width: 100%; border-collapse: collapse; }
.po-site-table th { background: white; border-bottom: 2px solid #f1f5f9; }
.po-site-table td { padding: 0.7rem 1rem; vertical-align: middle; border-bottom: 1px solid #f8fafc; }

.site-row { transition: background 0.15s; cursor: default; }
.site-row:hover { background: #f8fafc !important; }
.site-row.selected { background: #f0fdfa !important; }

.site-chk-input { width: 18px !important; height: 18px !important; accent-color: #0d9488; cursor: pointer; }

.site-rate-input {
    width: 100px; height: 30px; font-size: 0.85rem; font-weight: 800;
    border-radius: 6px; border: 1.5px solid #e2e8f0; padding: 0 0.4rem;
    color: #0d9488; text-align: right; background: #f8fafc;
    transition: border-color 0.2s, background 0.2s;
}
.site-rate-input:focus { background: #fff; border-color: #0d9488; outline: none; }

.btn-primary:hover { background: #0f766e !important; transform: translateY(-2px); box-shadow: 0 12px 20px -5px rgba(13, 148, 136, 0.4) !important; }

.po-pg-btn {
    min-width: 30px; height: 30px; border: 1px solid #e2e8f0; background: white;
    border-radius: 7px; cursor: pointer; font-weight: 700; font-size: 0.78rem;
    transition: all 0.2s; color: #475569; display: flex; align-items: center; justify-content: center; padding: 0 6px;
}
.po-pg-btn:hover:not(:disabled) { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
.po-pg-btn.active { background: #0d9488; color: white; border-color: #0d9488; box-shadow: 0 3px 8px rgba(13,148,136,0.25); }
.po-pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.po-pg-dots { color: #94a3b8; font-weight: 800; padding: 0 2px; font-size: 0.8rem; }
</style>

<script>
const sitesData = <?php echo json_encode($sites); ?>;
const vendorsData = <?php echo json_encode($vendors); ?>;
const isEditMode = <?php echo $action === 'edit' ? 'true' : 'false'; ?>;
const initialRateData = <?php echo $rateData ? json_encode($rateData) : 'null'; ?>;
const selectedSitesData = <?php echo json_encode($selectedSitesData); ?>;
const baseUrl = "<?php echo BASE_URL; ?>";
let currentPage = 1;
const pageSize = 10;

function renderPagination() {
    const allRows = Array.from(document.querySelectorAll('tr.site-row'));
    const activeRows = allRows.filter(r => !r.classList.contains('search-hidden'));

    const total = activeRows.length;
    const start = (currentPage - 1) * pageSize;
    const end = Math.min(start + pageSize, total);

    // Hide all first
    allRows.forEach(r => r.style.display = 'none');

    // Renumber and show current page
    activeRows.forEach((r, i) => {
        const sno = r.querySelector('.sno-cell');
        if (sno) sno.innerText = i + 1;
    });
    activeRows.slice(start, end).forEach(r => r.style.display = '');

    // Update info
    const pgStart = document.getElementById('po-pg-start');
    const pgEnd   = document.getElementById('po-pg-end');
    const pgTotal = document.getElementById('po-pg-total');
    if (pgStart) pgStart.innerText = total === 0 ? 0 : start + 1;
    if (pgEnd)   pgEnd.innerText   = end;
    if (pgTotal) pgTotal.innerText = total;

    // Update "X matching" badge
    const countEl = document.getElementById('filtered_sites_count');
    if (countEl) countEl.innerText = total;

    updatePgControls(total);
}

function updatePgControls(total) {
    const totalPages = Math.ceil(total / pageSize);
    const container = document.getElementById('po-pg-numbers');
    if (!container) return;
    container.innerHTML = '';
    if (totalPages <= 1) return;

    const btn = (html, disabled, active, cb) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'po-pg-btn' + (active ? ' active' : '');
        b.innerHTML = html;
        b.disabled = disabled;
        if (!disabled) b.onclick = cb;
        return b;
    };
    const dots = () => { const s = document.createElement('span'); s.className = 'po-pg-dots'; s.innerText = '…'; return s; };

    container.appendChild(btn('<i class="fas fa-angle-double-left"></i>', currentPage === 1, false, () => { currentPage = 1; renderPagination(); }));
    container.appendChild(btn('<i class="fas fa-angle-left"></i>', currentPage === 1, false, () => { currentPage--; renderPagination(); }));

    let sp = Math.max(1, currentPage - 2);
    let ep = Math.min(totalPages, sp + 4);
    if (ep - sp < 4) sp = Math.max(1, ep - 4);

    if (sp > 1) { container.appendChild(btn('1', false, false, () => { currentPage = 1; renderPagination(); })); if (sp > 2) container.appendChild(dots()); }
    for (let i = sp; i <= ep; i++) { const pg = i; container.appendChild(btn(i, false, i === currentPage, () => { currentPage = pg; renderPagination(); })); }
    if (ep < totalPages) { if (ep < totalPages - 1) container.appendChild(dots()); container.appendChild(btn(totalPages, false, false, () => { currentPage = totalPages; renderPagination(); })); }

    container.appendChild(btn('<i class="fas fa-angle-right"></i>', currentPage === totalPages, false, () => { currentPage++; renderPagination(); }));
    container.appendChild(btn('<i class="fas fa-angle-double-right"></i>', currentPage === totalPages, false, () => { currentPage = totalPages; renderPagination(); }));
}

document.getElementById('f_vendor').addEventListener('change', filterSitesByVendor);
document.getElementById('f_site').addEventListener('change', calculateTotal);

const rateEl = document.getElementById('f_rate');
if (rateEl) {
    rateEl.addEventListener('input', function() {
        const val = this.value;
        document.querySelectorAll('.site-chk-input:checked').forEach(chk => {
            const row = chk.closest('tr.site-row');
            const rateInput = row.querySelector('.site-rate-input');
            if (rateInput && rateInput.dataset.touched !== 'true') {
                rateInput.value = val;
                updateRowTotal(row);
            }
        });
        calculateTotal();
        updateBucketUI();
    });
}

document.getElementById('selectAllSites').addEventListener('change', function() {
    const masterRate = document.getElementById('f_rate').value;
    document.querySelectorAll('tr.site-row').forEach(tr => {
        if (tr.classList.contains('search-hidden') || tr.style.display === 'none') return;
        const chk = tr.querySelector('.site-chk-input');
        const rateInput = tr.querySelector('.site-rate-input');
        if (!chk) return;
        chk.checked = this.checked;
        if (this.checked) {
            tr.classList.add('selected');
            if (masterRate && rateInput && rateInput.dataset.touched !== 'true') {
                rateInput.value = masterRate;
                updateRowTotal(tr);
            }
        } else {
            tr.classList.remove('selected');
        }
    });
    calculateTotal();
    updateBucketUI();
});

function setMedia(val) {
    const selectEl = document.getElementById('f_media');
    if (selectEl) selectEl.value = val;
}

function updateRowTotal(tr) {
    const chk = tr.querySelector('.site-chk-input');
    const rateInput = tr.querySelector('.site-rate-input');
    const totalCell = tr.querySelector('.row-total-cell');
    if (!chk || !rateInput || !totalCell) return;
    const sqft = parseFloat(chk.dataset.sqft) || 0;
    const rate = parseFloat(rateInput.value) || 0;
    const total = sqft * rate;
    totalCell.innerText = total > 0 ? '₹' + total.toLocaleString(undefined, {maximumFractionDigits: 0}) : '₹0';
}

function filterSitesByVendor() {
    const vendorId = document.getElementById('f_vendor').value;
    const siteSelect = document.getElementById('f_site');
    const tbody = document.getElementById('site-rows-body');

    siteSelect.innerHTML = '<option value="">Generic / All Sites</option>';
    tbody.innerHTML = '';

    if (sitesData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 4rem; color: #94a3b8; font-size: 0.9rem;">No sites found in system.</td></tr>';
        return;
    }

    sitesData.forEach((s, index) => {
        const siteVendor = vendorsData.find(v => v.id == s.vendor_id);
        const vName = s.vendor_name || (siteVendor ? siteVendor.name : '');
        const sqft = parseFloat(s.width) * parseFloat(s.height);
        const isOwnVendor = (vendorId && s.vendor_id == vendorId);

        // Dropdown option (for edit mode)
        const option = document.createElement('option');
        option.value = s.id;
        option.text = `${s.site_code} - ${s.name} (${s.width}x${s.height})`;
        siteSelect.add(option);

        // Thumbnail
        const allImgs = s.all_images || s.thumbnail || '';
        const thumbHtml = s.thumbnail
            ? `<div style="position: relative; width: 120px; height: 75px;">
                <img src="${baseUrl}uploads/sites/${s.thumbnail}"
                     onclick="openLightboxSlider('${allImgs}', '${s.id}')"
                     style="width: 100%; height: 100%; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; transition: transform 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
               </div>`
            : `<div style="width: 80px; height: 50px; border-radius: 8px; background: #f8fafc; border: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #94a3b8; font-weight: 700;">No Img</div>`;

        // Tags
        const typeBadge = s.type ? `<span style="background: #ecfdf5; color: #059669; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase;">${s.type}</span>` : '';
        const lightBadge = s.light_type ? `<span style="background: #f1f5f9; color: #475569; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase;">${s.light_type}</span>` : '';
        const ownerLabel = s.owner_type + (isOwnVendor && vName ? ' - ' + vName : '');
        const ownerBadge = s.owner_type ? `<span style="background: #f1f5f9; color: #475569; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase;">${ownerLabel}</span>` : '';
        const ownBadge = isOwnVendor ? `<span style="color:#0d9488; font-size:0.55rem; background:#ccfbf1; padding:2px 6px; border-radius:10px; margin-left:4px;">OWN</span>` : '';

        const tr = document.createElement('tr');
        tr.className = 'site-row';
        tr.dataset.id = s.id;
        tr.dataset.code = s.site_code || '';
        tr.dataset.name = s.name || '';
        tr.dataset.city = s.city || '';
        tr.dataset.state = s.state || '';
        tr.dataset.type = s.type || '';
        tr.dataset.illumination = s.light_type || '';
        tr.dataset.owner = s.owner_type || '';
        tr.dataset.status = s.status || '';
        tr.dataset.size = `${s.width}x${s.height}`;
        tr.dataset.vendorId = s.vendor_id || '';
        tr.dataset.thumbnail = s.thumbnail || '';
        tr.dataset.images = s.all_images || s.thumbnail || '';
        tr.dataset.location = s.location || '';
        tr.dataset.width = s.width || '';
        tr.dataset.height = s.height || '';

        tr.innerHTML = `
            <td class="sno-cell" style="padding: 0.6rem 1rem; font-weight: 700; color: #64748b; text-align: center; font-size: 0.85rem;">${index + 1}</td>
            <td style="padding: 0.6rem 1rem; text-align: center;">
                <input type="checkbox" name="site_ids[]" value="${s.id}" class="site-chk-input" data-sqft="${sqft}">
            </td>
            <td style="padding: 0.6rem 1rem;">${thumbHtml}</td>
            <td style="padding: 0.6rem 1rem;">
                <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 2px;">${s.city || ''}</div>
                <div style="color: #f97316; font-size: 0.7rem; font-weight: 800;">${s.site_code}</div>
            </td>
            <td style="padding: 0.6rem 1rem;">
                <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 2px;">${s.name}${ownBadge}</div>
                ${s.location ? `<div style="font-size: 0.65rem; color: #64748b; margin-bottom: 3px; line-height: 1.1;">${s.location}</div>` : ''}
                <div style="display: flex; gap: 0.3rem; flex-wrap: wrap; align-items: center; margin-top: 2px;">${typeBadge}${lightBadge}${ownerBadge}</div>
            </td>
            <td style="padding: 0.6rem 1rem;">
                <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem;">${s.width}' x ${s.height}'</div>
                <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;">${sqft.toLocaleString()} SQFT</div>
            </td>
            <td style="padding: 0.6rem 1rem;">
                <div style="font-size: 0.5rem; color: #0d9488; font-weight: 900; text-transform: uppercase; margin-bottom: 3px;">Offer Rate</div>
                <input type="number" step="0.01" name="individual_rates[${s.id}]" class="site-rate-input" placeholder="₹" data-touched="false">
            </td>
            <td style="padding: 0.6rem 1rem; text-align: right;">
                <div style="font-size: 0.55rem; color: #64748b; font-weight: 800; text-transform: uppercase;">Total</div>
                <div class="row-total-cell" style="font-weight: 900; color: #0d9488; font-size: 0.9rem;">₹0</div>
            </td>
        `;

        const chk = tr.querySelector('.site-chk-input');
        const rateInput = tr.querySelector('.site-rate-input');

        chk.onchange = function() {
            const masterRate = document.getElementById('f_rate').value;
            if (this.checked) {
                tr.classList.add('selected');
                if (masterRate && rateInput.dataset.touched !== 'true') {
                    rateInput.value = masterRate;
                    updateRowTotal(tr);
                }
            } else {
                tr.classList.remove('selected');
            }
            calculateTotal();
            updateBucketUI();
        };

        rateInput.oninput = function() {
            this.dataset.touched = 'true';
            if (this.value !== '') chk.checked = true;
            if (this.value !== '') tr.classList.add('selected');
            updateRowTotal(tr);
            calculateTotal();
            updateBucketUI();
        };

        if (isEditMode && selectedSitesData[s.id] !== undefined) {
            chk.checked = true;
            tr.classList.add('selected');
            rateInput.value = selectedSitesData[s.id];
            rateInput.dataset.touched = 'true';
            updateRowTotal(tr);
        }

        tbody.appendChild(tr);
    });

    filterSitesInModal();
    calculateTotal();
    updateBucketUI();
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

    const availabilityEl = document.querySelector('input[name="availability"]:checked');
    const availability = availabilityEl ? availabilityEl.value : 'all';

    const media = document.getElementById('filter_media').value;
    const state = document.getElementById('filter_state').value;
    const city = document.getElementById('filter_city').value;
    const light = document.getElementById('filter_light').value;
    const size = document.getElementById('filter_size').value;
    const vendorId = document.getElementById('filter_vendor') ? document.getElementById('filter_vendor').value : '';

    document.querySelectorAll('tr.site-row').forEach(row => {
        const itemCode = (row.dataset.code || '').toLowerCase();
        const itemName = (row.dataset.name || '').toLowerCase();
        const itemCity = (row.dataset.city || '').toLowerCase();
        const itemState = (row.dataset.state || '').toLowerCase();
        const itemType = row.dataset.type || '';
        const itemIllum = row.dataset.illumination || '';
        const itemOwner = row.dataset.owner || '';
        const itemStatus = row.dataset.status || '';
        const itemSize = row.dataset.size || '';
        const itemVendor = row.dataset.vendorId || '';

        const matchesSearch = !q || itemCode.includes(q) || itemName.includes(q) || itemCity.includes(q) || itemState.includes(q);
        const matchesOwnership = ownership === 'all' || itemOwner === ownership;
        const matchesAvailability = availability === 'all' || (availability === 'available' && itemStatus.toLowerCase() === 'available');
        const matchesMedia = !media || itemType === media;
        const matchesState = !state || itemState.toLowerCase() === state.toLowerCase();
        const matchesCity = !city || itemCity.toLowerCase() === city.toLowerCase();
        const matchesLight = !light || itemIllum === light;
        const matchesSize = !size || itemSize === size;
        const matchesVendor = !vendorId || itemVendor == vendorId;

        if (matchesSearch && matchesOwnership && matchesVendor && matchesAvailability && matchesMedia && matchesState && matchesCity && matchesLight && matchesSize) {
            row.classList.remove('search-hidden');
        } else {
            row.classList.add('search-hidden');
        }
    });

    currentPage = 1;
    renderPagination();
}

function resetFilters() {
    document.getElementById('siteSearch').value = '';

    const ownerAll = document.querySelector('input[name="ownership"][value="all"]');
    if (ownerAll) ownerAll.checked = true;

    const availAvail = document.querySelector('input[name="availability"][value="available"]');
    if (availAvail) availAvail.checked = true;

    document.getElementById('filter_media').value = '';
    document.getElementById('filter_state').value = '';
    document.getElementById('filter_city').value = '';
    document.getElementById('filter_light').value = '';
    document.getElementById('filter_size').value = '';
    if (document.getElementById('filter_vendor')) document.getElementById('filter_vendor').value = '';

    filterSitesInModal();
}

function calculateTotal() {
    const masterRate = parseFloat(document.getElementById('f_rate').value) || 0;
    let totalSqft = 0;
    let netAmount = 0;

    document.querySelectorAll('.site-chk-input:checked').forEach(chk => {
        const row = chk.closest('tr.site-row');
        const sqft = parseFloat(chk.dataset.sqft) || 0;
        const rateInput = row ? row.querySelector('.site-rate-input') : null;
        const indRate = (rateInput ? parseFloat(rateInput.value) : 0) || masterRate;
        totalSqft += sqft;
        netAmount += (sqft * indRate);
    });

    if (totalSqft > 0) {
        const sqftValEl = document.getElementById('sqft_value');
        const totalPriceEl = document.getElementById('total_price_value');
        const sqftDisplayEl = document.getElementById('sqft_display');
        if (sqftValEl) sqftValEl.innerText = totalSqft.toLocaleString() + ' SQFT';
        if (totalPriceEl) totalPriceEl.innerText = '₹' + netAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (sqftDisplayEl) sqftDisplayEl.style.display = 'block';
    } else {
        const sqftDisplayEl = document.getElementById('sqft_display');
        if (sqftDisplayEl) sqftDisplayEl.style.display = 'none';
    }
}

function toggleSearchCriteria() {
    const panel = document.getElementById('search_criteria_panel');
    const icon = document.getElementById('toggleSearchIcon');
    const text = document.getElementById('toggleSearchText');
    const btn = document.getElementById('toggleSearchBtn');

    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        icon.className = 'fas fa-eye-slash';
        text.innerText = 'HIDE SEARCH CRITERIA';
        btn.style.borderColor = '#0d9488';
        btn.style.color = '#0d9488';
    } else {
        panel.style.display = 'none';
        icon.className = 'fas fa-eye';
        text.innerText = 'SHOW SEARCH CRITERIA';
        btn.style.borderColor = '#94a3b8';
        btn.style.color = '#64748b';
    }
}

document.getElementById('rateForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.site-chk-input:checked');
    if (checked.length === 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Error',
            text: 'Please select at least one site to save this Printing PO.',
            icon: 'error',
            confirmButtonColor: '#0d9488'
        });
    }
});

function openBucket() {
    document.getElementById('selection-bucket-panel').style.right = '0';
    document.getElementById('bucket-backdrop').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeBucket() {
    document.getElementById('selection-bucket-panel').style.right = '-1400px';
    document.getElementById('bucket-backdrop').style.display = 'none';
    document.body.style.overflow = '';
}

function uncheckSiteInBucket(siteId) {
    const row = document.querySelector(`tr.site-row[data-id="${siteId}"]`);
    if (row) {
        const chk = row.querySelector('.site-chk-input');
        if (chk) {
            chk.checked = false;
            row.classList.remove('selected');
            calculateTotal();
            updateBucketUI();
        }
    }
}

function syncRateFromBucket(siteId, rate) {
    const row = document.querySelector(`tr.site-row[data-id="${siteId}"]`);
    if (row) {
        const rateInput = row.querySelector('.site-rate-input');
        const chk = row.querySelector('.site-chk-input');
        if (rateInput) {
            rateInput.value = rate;
            rateInput.dataset.touched = 'true';
            if (rate !== '' && chk) {
                chk.checked = true;
                row.classList.add('selected');
            }
            updateRowTotal(row);
            calculateTotal();
            updateBucketUI();
        }
    }
}

function updateBucketUI() {
    const bucketList = document.getElementById('bucket-list');
    const emptyMsg = document.getElementById('bucket-empty-msg');
    const bucketCount = document.getElementById('bucket-count');
    const selectedCountBtm = document.getElementById('selected-count-btm');

    const checkedCheckboxes = document.querySelectorAll('.site-chk-input:checked');
    const count = checkedCheckboxes.length;

    if (bucketCount) bucketCount.innerText = count;
    if (selectedCountBtm) selectedCountBtm.innerText = count;

    if (count === 0) {
        if (emptyMsg) emptyMsg.style.display = 'block';
        if (bucketList) bucketList.innerHTML = '';
        return;
    }

    if (emptyMsg) emptyMsg.style.display = 'none';

    let html = `
        <table style="width: 100%; border-collapse: collapse; font-family: inherit;">
            <thead style="background: white; position: sticky; top: 0; z-index: 10;">
                <tr style="border-bottom: 2px solid #f1f5f9;">
                    <th style="width: 40px; padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">#</th>
                    <th style="width: 50px; padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">ACT</th>
                    <th style="width: 110px; padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">PREVIEW</th>
                    <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">CITY / CODE</th>
                    <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">ASSET DETAILS</th>
                    <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase;">SIZE</th>
                    <th style="padding: 0.8rem 1rem; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase; width: 140px;">RATE ₹/SQFT</th>
                    <th style="padding: 0.8rem 1rem; text-align: right; font-size: 0.7rem; font-weight: 800; color: #475569; text-transform: uppercase; width: 130px;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
    `;

    checkedCheckboxes.forEach((chk, index) => {
        const row = chk.closest('tr.site-row');
        if (!row) return;
        const siteId    = row.dataset.id;
        const siteCode  = row.dataset.code;
        const siteName  = row.dataset.name;
        const siteCity  = row.dataset.city || '';
        const siteType  = row.dataset.type || '';
        const siteLight = row.dataset.illumination || '';
        const siteOwner = row.dataset.owner || '';
        const siteW     = row.dataset.width || '';
        const siteH     = row.dataset.height || '';
        const siteLoc   = row.dataset.location || '';
        const thumb     = row.dataset.thumbnail || '';
        const sqftVal   = parseFloat(chk.dataset.sqft) || 0;

        const rowImages = row.dataset.images || thumb;
        const thumbHtml = thumb
            ? `<div style="width: 90px; height: 58px; position: relative;">
                <img src="${baseUrl}uploads/sites/${thumb}"
                     onclick="openLightboxSlider('${rowImages}', '${siteId}')"
                     style="width: 100%; height: 100%; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.08); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
               </div>`
            : `<div style="width: 70px; height: 44px; border-radius: 8px; background: #f8fafc; border: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #94a3b8; font-weight: 700;">No Img</div>`;

        const typeBadge  = siteType  ? `<span style="background:#ecfdf5;color:#059669;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${siteType}</span>` : '';
        const lightBadge = siteLight ? `<span style="background:#f1f5f9;color:#475569;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${siteLight}</span>` : '';
        const ownerBadge = siteOwner ? `<span style="background:#f1f5f9;color:#475569;padding:0.1rem 0.4rem;border-radius:4px;font-size:0.55rem;font-weight:800;text-transform:uppercase;">${siteOwner}</span>` : '';

        const gridRateInput = row.querySelector('.site-rate-input');
        const masterRateEl  = document.getElementById('f_rate');
        const currentRate   = (gridRateInput ? gridRateInput.value : '') || (masterRateEl ? masterRateEl.value : '') || 0;
        const totalCost     = sqftVal * parseFloat(currentRate);

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                <td style="padding: 0.7rem 1rem; font-weight: 700; color: #64748b; font-size: 0.85rem;">${index + 1}</td>
                <td style="padding: 0.7rem 1rem;">
                    <button type="button" onclick="uncheckSiteInBucket('${siteId}')" style="background: #fee2e2; color: #ef4444; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                        <i class="fas fa-trash-alt" style="font-size: 0.7rem;"></i>
                    </button>
                </td>
                <td style="padding: 0.7rem 1rem;">${thumbHtml}</td>
                <td style="padding: 0.7rem 1rem;">
                    <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 2px;">${siteCity}</div>
                    <div style="color: #f97316; font-size: 0.7rem; font-weight: 800;">${siteCode}</div>
                </td>
                <td style="padding: 0.7rem 1rem; max-width: 220px;">
                    <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 2px;">${siteName}</div>
                    ${siteLoc ? `<div style="font-size: 0.65rem; color: #64748b; margin-bottom: 3px; line-height: 1.1;">${siteLoc}</div>` : ''}
                    <div style="display:flex; gap:0.25rem; flex-wrap:wrap; margin-top:2px;">${typeBadge}${lightBadge}${ownerBadge}</div>
                </td>
                <td style="padding: 0.7rem 1rem;">
                    <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem;">${siteW}' x ${siteH}'</div>
                    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;">${sqftVal.toLocaleString()} SQFT</div>
                </td>
                <td style="padding: 0.7rem 1rem;">
                    <div style="font-size: 0.5rem; color: #0d9488; font-weight: 900; text-transform: uppercase; margin-bottom: 3px;">Offer Rate</div>
                    <input type="number" step="0.01" value="${gridRateInput ? gridRateInput.value : ''}"
                           oninput="syncRateFromBucket('${siteId}', this.value)"
                           placeholder="₹"
                           style="width: 100px; height: 30px; font-size: 0.85rem; font-weight: 800; border-radius: 6px; border: 1.5px solid #e2e8f0; padding: 0 0.4rem; color: #0d9488; text-align: right; background: #f8fafc;">
                </td>
                <td style="padding: 0.7rem 1rem; text-align: right; font-weight: 900; color: #0d9488; font-size: 0.95rem;">
                    ₹${totalCost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </td>
            </tr>
        `;
    });

    html += `</tbody></table>`;
    if (bucketList) bucketList.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => {
    resetFilters();

    if (isEditMode && initialRateData) {
        document.getElementById('f_vendor').value = initialRateData.vendor_id;
        filterSitesByVendor();
        document.getElementById('f_site').value = initialRateData.site_id || '';
        const rateInput = document.getElementById('f_rate');
        if (rateInput) rateInput.value = initialRateData.rate_per_sqft;
        setMedia(initialRateData.media_type);
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        const vId = urlParams.get('vendor_id');
        if (vId) {
            document.getElementById('f_vendor').value = vId;
        }
        filterSitesByVendor();
    }
    calculateTotal();
    updateBucketUI();
});
</script>

<!-- Lightbox HTML -->
<div id="po-lightbox" onclick="closeLightbox()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
    <div style="position: relative; max-width: 90%; max-height: 90%; display: flex; align-items: center; justify-content: center;" onclick="event.stopPropagation()">

        <button class="lb-nav" id="lb-prev" onclick="prevSlide(event)" style="position: absolute; left: -75px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); width: 48px; height: 48px; border-radius: 50%; display: none; align-items: center; justify-content: center; cursor: pointer; font-size: 1.4rem; transition: all 0.3s;">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div style="position: relative;">
            <img id="lb-img" src="" style="max-width: 100%; max-height: 85vh; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.15); display: block;">

            <!-- Use as Primary Button -->
            <button id="lb-primary-btn" onclick="setPrimaryImagePO(event)" style="position: absolute; top: 16px; left: 16px; background: #0d9488; color: white; border: none; padding: 0.5rem 1rem; border-radius: 10px; font-weight: 800; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 8px 20px rgba(13,148,136,0.35); transition: all 0.2s;">
                <i class="fas fa-check-circle"></i> Use as Primary Photo
            </button>

            <!-- Image counter -->
            <div id="lb-badge" style="position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 5px 14px; border-radius: 50px; font-weight: 800; font-size: 0.8rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2); display: none;"></div>
        </div>

        <button class="lb-nav" id="lb-next" onclick="nextSlide(event)" style="position: absolute; right: -75px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); width: 48px; height: 48px; border-radius: 50%; display: none; align-items: center; justify-content: center; cursor: pointer; font-size: 1.4rem; transition: all 0.3s;">
            <i class="fas fa-chevron-right"></i>
        </button>

        <div onclick="closeLightbox()" style="position: absolute; top: -55px; right: -55px; color: white; font-size: 2.5rem; cursor: pointer; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</div>
    </div>
</div>

<script>
let lbImages = [];
let lbIndex = 0;
let lbSiteId = null;

function openLightboxSlider(imageString, siteId) {
    if (!imageString) return;
    lbSiteId = siteId;
    lbImages = imageString.split(',').filter(i => i.trim() !== '');
    lbIndex = 0;
    renderLbImage();
    document.getElementById('po-lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    const showNav = lbImages.length > 1;
    document.getElementById('lb-prev').style.display = showNav ? 'flex' : 'none';
    document.getElementById('lb-next').style.display = showNav ? 'flex' : 'none';
}

function renderLbImage() {
    const img = document.getElementById('lb-img');
    const badge = document.getElementById('lb-badge');
    const primaryBtn = document.getElementById('lb-primary-btn');
    if (!img) return;
    img.src = baseUrl + 'uploads/sites/' + lbImages[lbIndex];
    badge.style.display = lbImages.length > 1 ? 'block' : 'none';
    badge.innerText = (lbIndex + 1) + ' / ' + lbImages.length;

    // Check if current image is already primary for this site
    const row = document.querySelector(`tr.site-row[data-id="${lbSiteId}"]`);
    const currentThumb = row ? row.dataset.thumbnail : '';
    if (currentThumb && currentThumb === lbImages[lbIndex]) {
        primaryBtn.innerHTML = '<i class="fas fa-check-double"></i> Selected as Primary';
        primaryBtn.style.background = '#059669';
    } else {
        primaryBtn.innerHTML = '<i class="fas fa-check-circle"></i> Use as Primary Photo';
        primaryBtn.style.background = '#0d9488';
    }
}

function nextSlide(e) {
    if (e) e.stopPropagation();
    lbIndex = (lbIndex + 1) % lbImages.length;
    renderLbImage();
}

function prevSlide(e) {
    if (e) e.stopPropagation();
    lbIndex = (lbIndex - 1 + lbImages.length) % lbImages.length;
    renderLbImage();
}

function closeLightbox() {
    document.getElementById('po-lightbox').style.display = 'none';
    document.body.style.overflow = '';
}

function setPrimaryImagePO(e) {
    if (e) e.stopPropagation();
    const newThumb = lbImages[lbIndex];
    if (!lbSiteId || !newThumb) return;

    // Update data-thumbnail on the main table row
    const row = document.querySelector(`tr.site-row[data-id="${lbSiteId}"]`);
    if (row) {
        row.dataset.thumbnail = newThumb;
        // Update the visible thumbnail in the main table
        const img = row.querySelector('td:nth-child(3) img');
        if (img) img.src = baseUrl + 'uploads/sites/' + newThumb;
    }

    // Re-render bucket so it picks up the new thumbnail
    updateBucketUI();

    const btn = document.getElementById('lb-primary-btn');
    btn.innerHTML = '<i class="fas fa-check-double"></i> Selected as Primary';
    btn.style.background = '#059669';

    Swal.fire({ icon: 'success', title: 'Primary Image Set', timer: 1200, showConfirmButton: false, toast: true, position: 'top-end' });
}

document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('po-lightbox');
    if (lb && lb.style.display === 'flex') {
        if (e.key === 'ArrowRight') nextSlide();
        if (e.key === 'ArrowLeft') prevSlide();
        if (e.key === 'Escape') closeLightbox();
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
