<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$activePage = 'printing_rates';
$pageTitle = 'Create Printing PO';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? clean($_GET['action']) : 'add';

$rateData = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM vendor_printing_rates WHERE id = ?");
    $stmt->execute([$id]);
    $rateData = $stmt->fetch();
    if (!$rateData) {
        die("Rate record not found.");
    }
    $pageTitle = 'Edit Printing PO';
}

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$sites = $pdo->query("SELECT id, name, site_code, width, height, vendor_id, city, state, type, light_type, owner_type, status FROM sites ORDER BY site_code ASC")->fetchAll();

// Fetch filter values
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL AND width != '' AND height != '' ORDER BY width, height")->fetchAll(PDO::FETCH_COLUMN);

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 0; overflow: hidden; background: #fff;">

    <form method="POST" action="printing_rates.php" id="rateForm" style="display: flex; flex-direction: column; margin: 0;">
        <input type="hidden" name="action" id="formAction" value="<?php echo htmlspecialchars($action); ?>">
        <input type="hidden" name="id" id="rateId" value="<?php echo htmlspecialchars($id); ?>">
        
        <?php if ($action === 'edit'): ?>
            <!-- Sleek Single-Column Card for Edit Mode -->
            <div style="max-width: 650px; margin: 3rem auto; padding: 2.5rem; background: #f8fafc; border-radius: 20px; border: 1px solid #e2e8f0; width: 100%; box-sizing: border-box;">
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-weight: 800; color: #1e293b; font-size: 0.75rem; margin-bottom: 0.75rem; display: block; text-transform: uppercase; letter-spacing: 0.05em;">1. Select Vendor</label>
                    <select name="vendor_id" id="f_vendor" required style="background: #fff; border: 2px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 1rem; width: 100%; font-weight: 600; font-size: 0.95rem;">
                        <option value="">Choose Printing Partner...</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo ($rateData && $rateData['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-weight: 800; color: #1e293b; font-size: 0.75rem; margin-bottom: 0.75rem; display: block; text-transform: uppercase; letter-spacing: 0.05em;">2. Select Site</label>
                    <select name="site_id" id="f_site" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 1rem; width: 100%; font-weight: 600; font-size: 0.95rem;">
                        <option value="">Generic / All Sites</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-weight: 800; color: #1e293b; font-size: 0.75rem; margin-bottom: 0.75rem; display: block; text-transform: uppercase; letter-spacing: 0.05em;">3. Media Type</label>
                    <select name="media_type" id="f_media" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 1rem; width: 100%; font-weight: 600; font-size: 0.95rem; height: 50px;">
                        <option value="Flex" <?php echo (!$rateData || $rateData['media_type'] === 'Flex') ? 'selected' : ''; ?>>Flex</option>
                        <option value="Vinyl" <?php echo ($rateData && $rateData['media_type'] === 'Vinyl') ? 'selected' : ''; ?>>Vinyl</option>
                        <option value="Star Flex" <?php echo ($rateData && $rateData['media_type'] === 'Star Flex') ? 'selected' : ''; ?>>Star Flex</option>
                        <option value="Backlit Flex" <?php echo ($rateData && ($rateData['media_type'] === 'Backlit Flex' || $rateData['media_type'] === 'Backlit')) ? 'selected' : ''; ?>>Backlit</option>
                        <option value="One Way Vision" <?php echo ($rateData && ($rateData['media_type'] === 'One Way Vision' || $rateData['media_type'] === 'OWV')) ? 'selected' : ''; ?>>OWV</option>
                        <option value="Canvas" <?php echo ($rateData && $rateData['media_type'] === 'Canvas') ? 'selected' : ''; ?>>Canvas</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-weight: 800; color: #1e293b; font-size: 0.75rem; margin-bottom: 0.75rem; display: block; text-transform: uppercase; letter-spacing: 0.05em;">4. Rate (₹ per SQFT)</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-weight: 800; color: #94a3b8;">₹</span>
                        <input type="number" step="0.01" name="rate_per_sqft" id="f_rate" min="0" placeholder="0.00" value="<?php echo $rateData ? htmlspecialchars($rateData['rate_per_sqft']) : ''; ?>" style="padding-left: 35px; font-size: 1.2rem; font-weight: 900; color: #0d9488; border: 2px solid #e2e8f0; border-radius: 10px; width: 100%; height: 50px;">
                    </div>
                </div>

                <div id="sqft_display" style="display: none; background: #0f172a; padding: 1.5rem; border-radius: 16px; color: white; box-shadow: 0 10px 20px rgba(0,0,0,0.15); margin-top: 2rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.5rem;">
                        <span style="color: #94a3b8; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">TOTAL AREA</span>
                        <strong id="sqft_value" style="color: #fff; font-size: 1.2rem; font-weight: 800;">0 SQFT</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                        <span style="color: #94a3b8; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">ESTIMATED PRICE</span>
                        <strong id="total_price_value" style="color: #2dd4bf; font-size: 1.6rem; font-weight: 900;">₹0.00</strong>
                    </div>
                </div>
            </div>
        <?php else: ?>
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
                        
                        <!-- Hidden / unused in add mode but kept for js bindings -->
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

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                <label style="display: flex; align-items: center; cursor: pointer; font-size: 0.75rem; font-weight: 800; color: #0d9488; margin: 0; background: #fff; padding: 6px 12px; border-radius: 20px; border: 1.5px solid #0d9488; transition: all 0.2s;">
                                    <input type="checkbox" id="selectAllSites" style="width: 16px; height: 16px; margin-right: 8px; accent-color: #0d9488; cursor: pointer;"> SELECT ALL (<span id="filtered_sites_count">0</span> matching)
                                </label>
                            </div>

                            <div id="site_checkbox_list" class="site-grid" style="flex: 1; max-height: 480px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 12px; background: #fff;">
                                <div style="grid-column: 1 / -1; color: #94a3b8; font-size: 0.9rem; padding: 4rem; text-align: center; font-style: italic;">Select a vendor to see sites...</div>
                            </div>
                        </div>
                    </div>
                </div>
        <?php endif; ?>

        <div style="background: #f8fafc; padding: 1.5rem 2.5rem; border-top: 1px solid #f1f5f9; text-align: right;">
            <a href="printing_rates.php" class="btn" style="font-weight: 700; color: #64748b; margin-right: 1.5rem; font-size: 1rem; border: none; background: transparent; padding: 0.5rem 1.5rem; text-decoration: none;">Discard Changes</a>
            <button type="submit" class="btn btn-primary" style="background: #0d9488; color: white; border: none; padding: 1rem 3rem; border-radius: 12px; font-weight: 800; font-size: 1.1rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);">
                <i class="fas fa-save" style="margin-right: 0.5rem;"></i> Save Printing PO
            </button>
        </div>
    </form>
</div>

<style>
.media-pills { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.media-pill { 
    padding: 10px 5px; text-align: center; background: #fff; border: 1.5px solid #e2e8f0; 
    border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.2s; color: #64748b;
}
.media-pill:hover { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
.media-pill.active { background: #0d9488; color: #fff; border-color: #0d9488; box-shadow: 0 4px 6px -1px rgba(13, 148, 136, 0.3); }

.site-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.site-item { 
    display: flex; align-items: center; padding: 15px; border-radius: 12px; cursor: pointer; 
    border: 2px solid #f1f5f9; transition: all 0.2s; background: #fff; position: relative;
    box-sizing: border-box;
}
.site-item:hover { border-color: #0d9488; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); transform: translateY(-2px); }
.site-item.selected { border-color: #0d9488; background: #f0fdfa; }
.site-item input[type="checkbox"] { width: 20px !important; height: 20px !important; accent-color: #0d9488; margin: 0; cursor: pointer; }
.site-item .site-info { flex: 1; min-width: 0; padding: 0 12px; }
.site-item .site-info label { margin: 0; color: #0f172a; font-size: 0.9rem; font-weight: 800; text-transform: none; letter-spacing: normal; cursor: pointer; line-height: 1.3; }
.site-item .site-info small { color: #64748b; font-size: 0.75rem; display: block; margin-top: 5px; font-weight: 500; }
.site-item .rate-input-wrap { width: 100px; }
.site-item .rate-input-wrap input { 
    padding: 8px 10px; font-size: 0.95rem; border-radius: 8px; border: 2px solid #f1f5f9; 
    text-align: right; font-weight: 900; color: #0d9488; background: #f8fafc; width: 100%;
}
.site-item .rate-input-wrap input:focus { background: #fff; border-color: #0d9488; outline: none; }

.btn-primary:hover { background: #0f766e !important; transform: translateY(-2px); box-shadow: 0 12px 20px -5px rgba(13, 148, 136, 0.4) !important; }
</style>

<script>
const sitesData = <?php echo json_encode($sites); ?>;
const vendorsData = <?php echo json_encode($vendors); ?>;
const isEditMode = <?php echo $action === 'edit' ? 'true' : 'false'; ?>;
const initialRateData = <?php echo $rateData ? json_encode($rateData) : 'null'; ?>;

document.getElementById('f_vendor').addEventListener('change', filterSitesByVendor);
document.getElementById('f_site').addEventListener('change', calculateTotal);
const rateEl = document.getElementById('f_rate');
if (rateEl) {
    rateEl.addEventListener('input', function() {
        const val = this.value;
        document.querySelectorAll('.site-chk-input:checked').forEach(chk => {
            const rateInput = chk.closest('.site-item').querySelector('.site-individual-rate');
            if (rateInput.dataset.touched !== 'true') {
                rateInput.value = val;
            }
        });
        calculateTotal();
    });
}

document.getElementById('selectAllSites').addEventListener('change', function() {
    const rateEl = document.getElementById('f_rate');
    const masterRate = rateEl ? rateEl.value : '';
    document.querySelectorAll('.site-chk-input').forEach(chk => {
        if (chk.closest('.site-item').style.display !== 'none') {
            chk.checked = this.checked;
            const rateInput = chk.closest('.site-item').querySelector('.site-individual-rate');
            const item = chk.closest('.site-item');
            if (this.checked) {
                item.classList.add('selected');
                if (masterRate && rateInput.dataset.touched !== 'true') {
                    rateInput.value = masterRate;
                }
            } else {
                item.classList.remove('selected');
            }
        }
    });
    calculateTotal();
});

function setMedia(val) {
    const selectEl = document.getElementById('f_media');
    if (selectEl) {
        selectEl.value = val;
    }
}

function filterSitesByVendor() {
    const vendorId = document.getElementById('f_vendor').value;
    const siteSelect = document.getElementById('f_site');
    const siteList = document.getElementById('site_checkbox_list');
    
    // Clear current UI
    siteSelect.innerHTML = '<option value="">Generic / All Sites</option>';
    siteList.innerHTML = '';
    
    const displaySites = isEditMode && vendorId ? sitesData.filter(s => s.vendor_id == vendorId) : sitesData;

    if (displaySites.length === 0) {
        siteList.innerHTML = '<div style="color: #94a3b8; font-size: 0.8rem; padding: 10px; text-align: center; grid-column: 1 / -1;">No sites found in system.</div>';
    }

    displaySites.forEach(s => {
        const siteVendor = vendorsData.find(v => v.id == s.vendor_id);
        const vName = siteVendor ? siteVendor.name : '';

        // Add to dropdown (for Edit)
        const option = document.createElement('option');
        option.value = s.id;
        option.text = `${s.site_code} - ${s.name} (${s.width}x${s.height})`;
        siteSelect.add(option);

        // Add to Checkbox List (for Add)
        const item = document.createElement('div');
        item.className = 'site-item';
        item.dataset.id = s.id;
        item.dataset.code = s.site_code || '';
        item.dataset.name = s.name || '';
        item.dataset.city = s.city || '';
        item.dataset.state = s.state || '';
        item.dataset.type = s.type || '';
        item.dataset.illumination = s.light_type || '';
        item.dataset.owner = s.owner_type || '';
        item.dataset.status = s.status || '';
        item.dataset.size = `${s.width}x${s.height}`;
        item.dataset.vendorId = s.vendor_id || '';
        
        const isSelectedVendor = (vendorId && s.vendor_id == vendorId);
        if (isSelectedVendor) item.style.background = '#f0fdfa';

        item.innerHTML = `
            <input type="checkbox" name="site_ids[]" value="${s.id}" class="site-chk-input" data-sqft="${parseFloat(s.width) * parseFloat(s.height)}">
            <div class="site-info">
                <label>${s.site_code} - ${s.name} ${isSelectedVendor ? '<span style="color:#0d9488; font-size:0.55rem; background:#ccfbf1; padding:2px 6px; border-radius:10px; margin-left:4px;">OWN</span>' : ''}</label>
                <small>${s.width}x${s.height} = <strong>${parseFloat(s.width) * parseFloat(s.height)} SQFT</strong> • ${vName}</small>
            </div>
            <div class="rate-input-wrap">
                <input type="number" step="0.01" name="individual_rates[${s.id}]" class="site-individual-rate" placeholder="₹" data-touched="false">
            </div>
        `;
        
        const chk = item.querySelector('.site-chk-input');
        const rateInput = item.querySelector('.site-individual-rate');
        
        chk.onchange = function() {
            const rateEl = document.getElementById('f_rate');
            const masterRate = rateEl ? rateEl.value : '';
            if (this.checked) {
                item.classList.add('selected');
                if (masterRate && rateInput.dataset.touched !== 'true') {
                    rateInput.value = masterRate;
                }
            } else {
                item.classList.remove('selected');
            }
            calculateTotal();
        };

        rateInput.oninput = function() {
            this.dataset.touched = 'true';
            if (this.value !== '') chk.checked = true;
            calculateTotal();
        };

        siteList.appendChild(item);
    });
    
    filterSitesInModal();
    calculateTotal();
}

function filterSitesInModal() {
    if (isEditMode) return;
    
    const q = document.getElementById('siteSearch').value.toLowerCase();
    
    // Get radio values
    const ownershipEl = document.querySelector('input[name="ownership"]:checked');
    const ownership = ownershipEl ? ownershipEl.value : 'all';
    
    const vendorGroup = document.getElementById('vendor_filter_group');
    if (vendorGroup) {
        vendorGroup.style.display = (ownership === 'TA') ? 'flex' : 'none';
        if (ownership !== 'TA') document.getElementById('filter_vendor').value = '';
    }
    
    const availabilityEl = document.querySelector('input[name="availability"]:checked');
    const availability = availabilityEl ? availabilityEl.value : 'all';
    
    // Get selects
    const media = document.getElementById('filter_media').value;
    const state = document.getElementById('filter_state').value;
    const city = document.getElementById('filter_city').value;
    const light = document.getElementById('filter_light').value;
    const size = document.getElementById('filter_size').value;
    const vendorId = document.getElementById('filter_vendor') ? document.getElementById('filter_vendor').value : '';
    
    let visibleCount = 0;
    
    document.querySelectorAll('.site-item').forEach(item => {
        const itemCode = (item.dataset.code || '').toLowerCase();
        const itemName = (item.dataset.name || '').toLowerCase();
        const itemCity = (item.dataset.city || '').toLowerCase();
        const itemState = (item.dataset.state || '').toLowerCase();
        const itemType = item.dataset.type || '';
        const itemIllum = item.dataset.illumination || '';
        const itemOwner = item.dataset.owner || '';
        const itemStatus = item.dataset.status || '';
        const itemSize = item.dataset.size || '';
        const itemVendor = item.dataset.vendorId || '';
        
        const matchesSearch = !q || 
            itemCode.includes(q) || 
            itemName.includes(q) || 
            itemCity.includes(q) || 
            itemState.includes(q);
            
        const matchesOwnership = ownership === 'all' || itemOwner === ownership;
        const matchesAvailability = availability === 'all' || 
            (availability === 'available' && itemStatus.toLowerCase() === 'available');
            
        const matchesMedia = !media || itemType === media;
        const matchesState = !state || itemState.toLowerCase() === state.toLowerCase();
        const matchesCity = !city || itemCity.toLowerCase() === city.toLowerCase();
        const matchesLight = !light || itemIllum === light;
        const matchesSize = !size || itemSize === size;
        const matchesVendor = !vendorId || itemVendor == vendorId;
        
        if (matchesSearch && matchesOwnership && matchesVendor && matchesAvailability && matchesMedia && matchesState && matchesCity && matchesLight && matchesSize) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    const countEl = document.getElementById('filtered_sites_count');
    if (countEl) {
        countEl.innerText = visibleCount;
    }
}

function resetFilters() {
    if (isEditMode) return;
    
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
    const rate = parseFloat(document.getElementById('f_rate').value) || 0;
    let totalSqft = 0;
    let netAmount = 0;
    
    if (isEditMode) {
        const siteId = document.getElementById('f_site').value;
        if (siteId) {
            const site = sitesData.find(s => s.id == siteId);
            if (site && site.width && site.height) {
                totalSqft = parseFloat(site.width) * parseFloat(site.height);
                netAmount = totalSqft * rate;
            }
        }
    } else {
        document.querySelectorAll('.site-chk-input:checked').forEach(chk => {
            const sqft = parseFloat(chk.dataset.sqft) || 0;
            const indRate = parseFloat(chk.closest('.site-item').querySelector('.site-individual-rate').value) || rate;
            totalSqft += sqft;
            netAmount += (sqft * indRate);
        });
    }
    
    if (totalSqft > 0) {
        document.getElementById('sqft_value').innerText = totalSqft.toLocaleString() + ' SQFT';
        document.getElementById('total_price_value').innerText = '₹' + netAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('sqft_display').style.display = 'block';
    } else {
        document.getElementById('sqft_display').style.display = 'none';
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
    if (isEditMode) return;
    
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

document.addEventListener('DOMContentLoaded', () => {
    // Initial Setup
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
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
