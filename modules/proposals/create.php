<?php
$activePage = 'proposals';
$pageTitle = 'Create New Proposal';
$hideSidebar = true;
include_once __DIR__ . '/../../includes/header.php';

requirePermission('proposals', 'add');

// Fetch Data
$clients = $pdo->query("SELECT id, name, city, contact_person FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$sitesQuery = "
    SELECT 
        s.*, 
        p.name as vendor_name,
        (SELECT GROUP_CONCAT(filename) FROM site_images WHERE site_id = s.id) as all_images,
        (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail 
    FROM sites s 
    LEFT JOIN partners p ON s.vendor_id = p.id
    ORDER BY s.site_code ASC";
$sites = $pdo->query($sitesQuery)->fetchAll();

// Fetch filter values
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM sites WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$genres = $pdo->query("SELECT DISTINCT genre FROM sites WHERE genre IS NOT NULL AND genre != '' ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL AND width != '' AND height != '' ORDER BY width, height")->fetchAll(PDO::FETCH_COLUMN);
$printingVendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$printingRates = $pdo->query("SELECT * FROM vendor_printing_rates")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="proposal-full-wrapper">
    <!-- Wizard Progress Tracker (Condensed) -->
    <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 1.5rem; background: white; padding: 0.6rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); max-width: 400px; margin-left: auto; margin-right: auto;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div id="tab-indicator-1" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem;">
                <div style="width: 24px; height: 24px; background: #059669; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid white; box-shadow: 0 0 0 2px #059669;"><i class="fas fa-check"></i></div>
                <span style="font-size: 0.55rem; font-weight: 800; color: #059669; text-transform: uppercase;">Details</span>
            </div>
            <div style="width: 30px; height: 2px; background: #e2e8f0;"></div>
            <div id="tab-indicator-2" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem; opacity: 0.4;">
                <div style="width: 24px; height: 24px; background: #cbd5e1; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800;">2</div>
                <span style="font-size: 0.55rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Assets</span>
            </div>
            <div style="width: 30px; height: 2px; background: #e2e8f0;"></div>
            <div id="tab-indicator-3" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem; opacity: 0.4;">
                <div style="width: 24px; height: 24px; background: #cbd5e1; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800;">3</div>
                <span style="font-size: 0.55rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Finish</span>
            </div>
        </div>
    </div>

<!-- STEP 1 -->
<div id="step-1">
    <div class="p-panel" style="max-width: 1100px; margin: 0 auto 1.5rem auto;">
        <div class="p-header"> Basic Details & Duration</div>
        
        <!-- Row 1: Campaign, Client, Contact -->
        <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom: 1rem;">
            <div class="form-group">
                <label>Campaign Name <span style="color:red;">*</span></label>
                <input type="text" id="campaign_name" class="p-input" placeholder="Enter campaign name (e.g. Summer Sale 2024)" style="height: 38px;">
            </div>
            <div class="form-group">
                <label style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Company / Client <span style="color:red;">*</span></span>
                    <button type="button" class="btn-text" onclick="openClientModal()" style="font-size: 0.7rem; color: var(--primary); background: none; border: none; cursor: pointer; padding: 0;">
                        <i class="fas fa-plus-circle"></i> New
                    </button>
                </label>
                <select id="client_id" class="p-input" style="height: 38px;" onchange="handleClientChange()">
                    <option value="">-- Choose Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>" data-contact="<?php echo htmlspecialchars($c['contact_person'] ?? ''); ?>">
                            <?php echo $c['name']; ?> <?php echo $c['city'] ? "({$c['city']})" : ""; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- GST Selection for Group Companies -->
            <div id="gst_selection_container" style="display: none; grid-column: span 3; margin-top: 0.5rem; background: #f0fdfa; padding: 1rem; border-radius: 12px; border: 1px solid #ccfbf1; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <label style="color: var(--primary); font-weight: 800; font-size: 0.7rem; margin-bottom: 0; display: block; text-transform: uppercase;">
                        <i class="fas fa-id-card"></i> Billing GSTIN / State Selection
                    </label>
                    <span id="gst_count_badge" style="background: var(--primary); color: white; font-size: 0.65rem; padding: 2px 8px; border-radius: 50px; font-weight: 700;"></span>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <select id="selected_gstin" class="p-input" style="flex: 1; height: 38px; border-color: #5eead4; background: white;" onchange="handleGstSelectionChange()">
                        <!-- Dynamic Options -->
                    </select>
                    <div id="gst_details_preview" style="flex: 2; background: white; border: 1px solid #ccfbf1; border-radius: 6px; padding: 0.5rem; font-size: 0.75rem; color: #0f766e; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="gst_preview_text">Select a GSTIN to see location details</span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" id="contact_person" class="p-input" placeholder="Contact person name" style="height: 38px;">
            </div>
        </div>

        <!-- Row 2: Dates -->
        <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="form-group">
                <label>From Date</label>
                <input type="date" id="start_date" class="p-input" style="height: 38px;" onchange="calculateEndDate()">
            </div>
            <div class="form-group">
                <label>To Date</label>
                <input type="date" id="end_date" class="p-input" style="height: 38px;" onchange="calculateTotalDays()">
            </div>
            <div class="form-group">
                <label>Total Days</label>
                <input type="number" id="total_days" class="p-input" placeholder="Days" style="height: 38px;" oninput="calculateEndDate()">
            </div>
        </div>
    </div>

    <!-- Single Next Step Button for Step 1 -->
    <div style="display: flex; justify-content: flex-end; margin: 2rem auto; max-width: 1100px;">
        <button class="btn btn-primary" onclick="goToStep2()" style="width: 250px; height: 48px; border-radius: 12px; font-weight: 800; font-size: 0.95rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            Next Step: Search & Select <i class="fas fa-arrow-right" style="margin-left: 0.75rem;"></i>
        </button>
    </div>
</div>


    <!-- STEP 2: Media Selection & Search -->
    <div id="step-2" style="display: none;">
        <!-- Category Filter Tabs -->
        <div class="inventory-tabs" id="proposal-create-tabs" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            <button type="button" class="tab active" onclick="selectProposalMediaTab('all', this)">All</button>
            <?php foreach ($mediaTypes as $mtype): ?>
                <button type="button" class="tab" onclick="selectProposalMediaTab('<?php echo htmlspecialchars($mtype); ?>', this)">
                    <?php echo htmlspecialchars($mtype); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="p-panel" style="margin-bottom: 1rem; padding: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button onclick="goToStep1()" class="btn btn-secondary" style="height: 28px; padding: 0 0.6rem; font-size: 0.7rem; border-radius: 6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <span style="font-weight: 900; color: var(--primary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Search Criteria</span>
                </div>
                <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;">STEP 2: ASSETS & STRATEGY</div>
            </div>
            
            <div class="media-search-grid">
            <!-- Ownership & Availability -->
            <div class="search-row" style="margin-bottom: 1rem; display: flex; gap: 2rem; align-items: flex-end;">
                <div class="search-group">
                    <label style="font-size: 0.6rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Ownership</label>
                    <div class="radio-group" style="gap: 1rem;">
                        <label style="font-size: 0.75rem;"><input type="radio" name="ownership" value="all" checked onchange="filterSites()"> All</label>
                        <label style="font-size: 0.75rem;"><input type="radio" name="ownership" value="HA" onchange="filterSites()"> Self</label>
                        <label style="font-size: 0.75rem;"><input type="radio" name="ownership" value="TA" onchange="filterSites()"> Vendor</label>
                    </div>
                </div>
                <div class="search-group">
                    <label style="font-size: 0.6rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Availability</label>
                    <div class="radio-group" style="gap: 1rem;">
                        <label style="font-size: 0.75rem;"><input type="radio" name="availability" value="available" checked onchange="filterSites()"> Available</label>
                        <label style="font-size: 0.75rem;"><input type="radio" name="availability" value="all" onchange="filterSites()"> All</label>
                    </div>
                </div>
                <div id="vendor-filter-group" class="search-group" style="display: none; min-width: 180px;">
                    <label style="font-size: 0.6rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.4rem; display: block; text-transform: uppercase;">Vendor</label>
                    <select id="filter-vendor" class="p-input" onchange="filterSites()" style="height: 30px; font-size: 0.75rem;">
                        <option value="">All Vendors</option>
                        <?php foreach($vendors as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.5rem; align-items: flex-end; width: 100%;">
                <div class="form-group" style="flex: 2 1 200px; min-width: 150px; margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.2rem; text-transform: uppercase;">Search Site / Code / Area</label>
                    <input type="text" id="site-search" class="p-input" placeholder="Search by Site Name, Code, Location, City, State, Media..." oninput="filterSites()" style="height: 30px; font-size: 0.75rem; padding: 0 10px; box-sizing: border-box;">
                </div>
                <div class="form-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.2rem; text-transform: uppercase;">Media</label>
                    <select id="media_type" class="p-input" onchange="filterSites()" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                        <option value="">All</option>
                        <?php foreach($mediaTypes as $mt): ?> <option value="<?php echo $mt; ?>"><?php echo $mt; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.2rem; text-transform: uppercase;">State</label>
                    <select id="filter-state" class="p-input" onchange="filterSites()" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                        <option value="">All</option>
                        <?php foreach($states as $s): ?> <option value="<?php echo $s; ?>"><?php echo $s; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.2rem; text-transform: uppercase;">City</label>
                    <select id="filter-city" class="p-input" onchange="filterSites()" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                        <option value="">All</option>
                        <?php foreach($cities as $c): ?> <option value="<?php echo $c; ?>"><?php echo $c; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.2rem; text-transform: uppercase;">Location</label>
                    <select id="filter-location" class="p-input" onchange="filterSites()" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                        <option value="">All</option>
                        <?php foreach($locations as $loc): ?> <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.2rem; text-transform: uppercase;">Light</label>
                    <select id="light_type" class="p-input" onchange="filterSites()" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                        <option value="">All</option>
                        <?php foreach($illuminations as $il): ?> <option value="<?php echo $il; ?>"><?php echo $il; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1 1 110px; min-width: 90px; margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.2rem; text-transform: uppercase;">Size</label>
                    <select id="filter-size" class="p-input" onchange="filterSites()" style="height: 30px; font-size: 0.75rem; padding: 0 8px; box-sizing: border-box;">
                        <option value="">All</option>
                        <?php foreach($sizes as $sz): ?> <option value="<?php echo $sz; ?>"><?php echo $sz; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 0 0 auto; margin-bottom: 0; display: flex; align-items: flex-end;">
                    <button class="btn btn-secondary" onclick="clearFilters()" style="height: 30px; font-size: 0.75rem; padding: 0 0.75rem; border-radius: 8px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; box-sizing: border-box;"><i class="fas fa-times-circle"></i> Clear</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Asset Selection Table -->
    <div class="p-panel" id="asset-plan-panel" style="margin-bottom: 2rem;">
        <div class="p-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span>Select Assets Based on Your Criteria</span>
            </div>
            <div class="header-right" style="display: flex; gap: 1rem; align-items: center;">
                <button onclick="openBucket()" id="bucket-toggle-btn" style="background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; padding: 0.5rem 1rem; border-radius: 10px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 0.6rem; transition: all 0.2s;">
                    <i class="fas fa-shopping-basket"></i>
                    Selected: <span id="selected-count" style="background: #059669; color: white; padding: 0.1rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">0</span>
                </button>
                <a href="index.php" class="btn btn-secondary" style="border-radius: 10px; height: 38px; display: flex; align-items: center;"><i class="fas fa-times"></i></a>
            </div>
        </div>

        <div class="site-list-container" style="max-height: 700px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 0 0 12px 12px;">
            <table class="crs-table selection-table" id="asset-table" style="width: 100%; border-collapse: separate; border-spacing: 0 0.5rem;">
                <thead style="background: white; position: sticky; top: 0; z-index: 10;">
                    <tr style="border-bottom: 2px solid #f1f5f9;">
                        <th style="width: 40px; padding: 0.8rem 1rem;">#</th>
                        <th style="width: 50px; padding: 0.8rem 1rem;"><i class="far fa-check-square"></i></th>
                        <th style="width: 100px; padding: 0.8rem 1rem;">PREVIEW</th>
                        <th style="padding: 0.8rem 1rem;">CITY / CODE</th>
                        <th style="padding: 0.8rem 1rem;">ASSET DETAILS</th>
                        <th style="padding: 0.8rem 1rem;">SIZE</th>
                        <th style="padding: 0.8rem 1rem;">PRICING</th>
                        <th style="padding: 0.8rem 1rem;">OFFER RATE</th>
                        <th style="padding: 0.8rem 1rem; text-align: right;">TOTAL</th>
                    </tr>
                </thead>
                <tbody id="asset-body">
                    <?php $sno = 1; foreach ($sites as $s): 
                        $sqft = $s['width'] * $s['height'];
                    ?>
                    <tr class="site-row" 
                        id="row-<?php echo $s['id']; ?>"
                        style="background: white; transition: all 0.2s;"
                        data-id="<?php echo $s['id']; ?>" 
                        data-name="<?php echo $s['name']; ?>" 
                        data-code="<?php echo $s['site_code']; ?>"
                        data-location="<?php echo $s['location']; ?>"
                        data-city="<?php echo $s['city']; ?>"
                        data-state="<?php echo $s['state']; ?>"
                        data-type="<?php echo $s['type']; ?>"
                        data-illumination="<?php echo $s['light_type']; ?>"
                        data-status="<?php echo $s['status']; ?>"
                        data-rate="<?php echo $s['card_rate']; ?>" 
                        data-prate="<?php echo $s['purchase_rate']; ?>" 
                        data-owner="<?php echo $s['owner_type']; ?>"
                        data-vendor="<?php echo $s['vendor_id']; ?>"
                        data-vendor-name="<?php echo htmlspecialchars($s['vendor_name'] ?? ''); ?>"
                        data-width="<?php echo $s['width']; ?>"
                        data-height="<?php echo $s['height']; ?>"
                        data-size="<?php echo $s['width'] . 'x' . $s['height']; ?>"
                        data-thumbnail="<?php echo $s['thumbnail'] ?? ''; ?>"
                        data-images="<?php echo $s['all_images'] ?? ''; ?>"
                        data-sqft="<?php echo $sqft; ?>">
                        
                        <td class="sno-cell" style="padding: 0.6rem 1rem; font-weight: 700; color: #64748b;"><?php echo $sno++; ?></td>
                        
                        <td style="padding: 0.6rem 1rem; text-align: center;">
                            <input type="checkbox" class="asset-chk" onclick="toggleSite('<?php echo $s['id']; ?>')" style="width: 18px; height: 18px; border-radius: 4px; cursor: pointer; accent-color: var(--primary);">
                        </td>

                        <td style="padding: 0.6rem 1rem;">
                            <?php if (!empty($s['thumbnail'])): 
                                $imgList = explode(',', $s['all_images'] ?? '');
                                $imgCount = count($imgList);
                            ?>
                                <div style="position: relative; width: 150px; height: 95px;">
                                    <img src="<?php echo BASE_URL; ?>uploads/sites/<?php echo $s['thumbnail']; ?>" 
                                         onclick="openLightboxSlider('<?php echo htmlspecialchars($s['all_images']); ?>', '<?php echo $s['id']; ?>')" 
                                         class="site-thumb" 
                                         style="width: 100%; height: 100%; border-radius: 12px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; transition: transform 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                    <?php if ($imgCount > 1): ?>
                                        <div style="position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,0.7); color: white; font-size: 0.55rem; padding: 2px 5px; border-radius: 4px; font-weight: 800; backdrop-filter: blur(2px);">
                                            <i class="fas fa-images"></i> <?php echo $imgCount; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="width: 80px; height: 50px; border-radius: 8px; background: #f8fafc; border: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #94a3b8; font-weight: 700;">No Img</div>
                            <?php endif; ?>
                        </td>

                        <td style="padding: 0.6rem 1rem;">
                            <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 1px;"><?php echo $s['city']; ?>, <?php echo $s['state']; ?></div>
                            <div style="color: #f97316; font-size: 0.65rem; font-weight: 800;"><?php echo $s['site_code']; ?></div>
                        </td>

                        <td style="padding: 0.6rem 1rem;">
                            <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 1px;"><?php echo $s['name']; ?></div>
                            <div style="font-size: 0.65rem; color: #64748b; margin-bottom: 3px; line-height: 1.1;"><?php echo $s['location']; ?></div>
                            <div style="display: flex; gap: 0.3rem; align-items: center;">
                                <span style="background: #ecfdf5; color: #059669; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase;"><?php echo $s['type']; ?></span>
                                <span style="background: #f1f5f9; color: #475569; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase;"><?php echo $s['light_type']; ?></span>
                                <span style="background: #f1f5f9; color: #475569; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase;">
                                    <?php echo $s['owner_type']; ?>
                                    <?php if ($s['owner_type'] === 'TA' && $s['vendor_name']) echo " - " . htmlspecialchars($s['vendor_name']); ?>
                                </span>
                            </div>
                        </td>

                        <td style="padding: 0.6rem 1rem;">
                            <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 1px;"><?php echo $s['width']; ?>' x <?php echo $s['height']; ?>'</div>
                            <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;"><?php echo number_format($sqft); ?> SQFT</div>
                        </td>

                        <td style="padding: 0.6rem 1rem;">
                            <div style="font-size: 0.55rem; color: #94a3b8; font-weight: 800; text-transform: uppercase;">Card Rate</div>
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.8rem;">₹<?php echo number_format($s['card_rate']); ?></div>
                        </td>

                        <td style="padding: 0.6rem 1rem;">
                            <div style="font-size: 0.55rem; color: var(--primary); font-weight: 900; text-transform: uppercase; margin-bottom: 2px;">Offer Rate</div>
                            <input type="number" class="p-input sale-rate-input" 
                                   value="0" 
                                   oninput="updateSitePrice('<?php echo $s['id']; ?>', this.value)"
                                   style="width: 80px; height: 24px; font-size: 0.75rem; font-weight: 800; border-radius: 5px; padding: 0 0.3rem;">
                        </td>

                        <td style="padding: 0.6rem 1rem; text-align: right;">
                            <div style="font-size: 0.55rem; color: #64748b; font-weight: 800; text-transform: uppercase;">Total</div>
                            <div class="total-cell" style="font-weight: 900; color: var(--primary); font-size: 0.9rem;">₹0</div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap" style="padding: 1rem; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-top: 1px solid #e2e8f0;">
            <div class="pg-info" style="font-size: 0.75rem; font-weight: 700; color: #64748b;">
                Showing <span id="pg-start">1</span> to <span id="pg-end">10</span> of <span id="pg-total"><?php echo count($sites); ?></span> assets matching criteria
            </div>
            <div class="pg-controls" id="pg-numbers"></div>
        </div>
    </div>

    <!-- SELECTION BUCKET (Slide-out Drawer) -->
    <div id="bucket-backdrop" onclick="closeBucket()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 2000; display: none;"></div>
    <div id="selection-bucket-panel" style="position: fixed; top: 0; right: -1400px; width: 1400px; max-width: 95vw; height: 100%; background: white; z-index: 2001; box-shadow: -10px 0 30px rgba(0,0,0,0.1); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column;">
        <div class="p-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: #059669; color: white; border: none; margin: 0;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-shopping-basket" style="font-size: 1.2rem;"></i>
                <span style="font-size: 1.1rem; color: white;">Selection Review</span>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <div class="selection-stats" style="background: rgba(255,255,255,0.2); color: white; padding: 0.3rem 0.8rem; border-radius: 6px; font-weight: 800; font-size: 0.75rem;">
                    <span id="bucket-count">0</span> Assets Selected
                </div>
                <button onclick="closeBucket()" style="background: rgba(0,0,0,0.1); border: none; color: white; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 1.2rem;">&times;</button>
            </div>
        </div>
        <div style="display: flex; background: #f1f5f9; padding: 0.5rem 1rem; gap: 0.5rem; border-bottom: 1px solid #e2e8f0;">
            <button type="button" class="btn bucket-tab active" id="btn-tab-rental" onclick="switchBucketTab('rental')" style="flex: 1; font-weight: 800; font-size: 0.8rem; height: 38px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s;">
                <i class="fas fa-ad"></i> Space Rental
            </button>
            <button type="button" class="btn bucket-tab" id="btn-tab-printing" onclick="switchBucketTab('printing')" style="flex: 1; font-weight: 800; font-size: 0.8rem; height: 38px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s;">
                <i class="fas fa-print"></i> Printing Service
            </button>
            <button type="button" class="btn bucket-tab" id="btn-tab-mounting" onclick="switchBucketTab('mounting')" style="flex: 1; font-weight: 800; font-size: 0.8rem; height: 38px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s;">
                <i class="fas fa-tools"></i> Mounting Service
            </button>
        </div>
        <div id="bucket-empty-msg" style="padding: 4rem 2rem; text-align: center; color: #94a3b8; font-weight: 700;">
            <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.3;"></i>
            Your bucket is empty. Select assets from the main list.
        </div>
        <div id="bucket-list" style="flex: 1; overflow-y: auto; padding: 1rem;">
            <!-- Selected items injected here -->
        </div>
        <div style="padding: 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc;">
            <button onclick="closeBucket()" class="btn btn-primary" style="width: 100%; height: 45px; border-radius: 10px; font-weight: 800;">CONTINUE SELECTION</button>
        </div>
    </div>

    <!-- Bottom: Configuration Grid -->
    <!-- Sleek Horizontal Action Bar -->
    <div class="proposal-action-bar" style="position: sticky; bottom: 0; background: white; border-top: 2px solid var(--primary); padding: 0.5rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; margin-top: 1rem; box-shadow: 0 -10px 25px rgba(0,0,0,0.05); z-index: 1000; border-radius: 12px 12px 0 0;">
        <!-- Inputs Group -->
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Disc %</label>
                <input type="number" id="global_discount" value="0" class="p-input" oninput="recalcAll()" style="width: 60px; height: 28px; font-size: 0.8rem; padding: 0 0.4rem;">
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Mark %</label>
                <input type="number" id="global_markup" value="0" class="p-input" oninput="recalcAll()" style="width: 60px; height: 28px; font-size: 0.8rem; padding: 0 0.4rem;">
            </div>
            <div style="width: 1px; height: 20px; background: #e2e8f0;"></div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Print ₹</label>
                <input type="number" id="print_cost" value="0" class="p-input" oninput="recalcAll()" style="width: 80px; height: 28px; font-size: 0.8rem; padding: 0 0.4rem;">
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Mount ₹</label>
                <input type="number" id="mount_cost" value="0" class="p-input" oninput="recalcAll()" style="width: 80px; height: 28px; font-size: 0.8rem; padding: 0 0.4rem;">
            </div>
            <div style="width: 1px; height: 20px; background: #e2e8f0;"></div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-size: 0.6rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Tax</label>
                <select id="tax-type" class="p-input" onchange="recalcAll()" style="width: 90px; height: 28px; font-size: 0.65rem; padding: 0 0.25rem;">
                    <option value="igst">IGST 18%</option>
                    <option value="cgst_sgst">CGST/SGST</option>
                </select>
            </div>
        </div>

        <!-- Totals & CTA Group -->
        <div style="display: flex; align-items: center; gap: 2rem;">
            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                <div style="font-size: 0.55rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Total Display</div>
                <div id="sum-display-btm" style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">₹0</div>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end;" id="tax-breakdown">
                <div style="font-size: 0.55rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">GST (18%)</div>
                <div id="sum-tax-btm" style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">₹0</div>
            </div>
            <div style="background: #f0fdfa; padding: 0.4rem 1rem; border-radius: 8px; border: 1px solid #ccfbf1; text-align: right;">
                <div style="font-size: 0.6rem; color: var(--primary); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: -2px;">Grand Total</div>
                <div id="sum-grand-btm" style="font-size: 1.3rem; font-weight: 900; color: var(--primary);">₹0</div>
            </div>
            <button class="btn btn-primary" onclick="saveProposal()" style="height: 42px; padding: 0 1.5rem; border-radius: 8px; font-weight: 900; font-size: 0.85rem; text-transform: uppercase; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);">
                <i class="fas fa-file-invoice" style="margin-right: 0.5rem;"></i> Generate Proposal
            </button>
        </div>
    </div>
</div>
</div>

<div id="clientModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>New Client</h3>
            <button class="close-modal" onclick="closeClientModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-grid" style="margin-bottom: 1rem;">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="new_client_city" class="p-input">
                </div>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" id="new_client_name" class="p-input">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Contact Person</label>
                <input type="text" id="new_client_contact" class="p-input">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="new_client_phone" class="p-input">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="new_client_email" class="p-input">
                </div>
            </div>
            <div class="form-group" style="margin-top: 1rem;">
                <label>Address / Location</label>
                <input type="text" id="new_client_address" class="p-input">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeClientModal()">Cancel</button>
            <button class="btn btn-primary" onclick="submitQuickClient()">Save Client</button>
        </div>
    </div>
</div>

<style>
.bucket-tab { background: #e2e8f0; color: #475569; }
.bucket-tab.active { background: #059669 !important; color: white !important; }
/* Search Panel Styles */
.media-search-grid { padding: 0.5rem 0; }
.search-row { display: flex; align-items: center; }
.radio-group { display: flex; gap: 1.5rem; }
.radio-group label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; }
.radio-group input[type="radio"] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }

/* Layout Structure */
.proposal-full-wrapper { max-width: 100%; margin: 0 auto; padding: 1.5rem; background: #f8fafc; min-height: 100vh; }

.proposal-bottom-grid { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 1.5rem; 
    margin-top: 2rem; 
}

/* Step Indicators */
.step-indicator { 
    background: var(--primary); 
    color: white; 
    padding: 0.35rem 1rem; 
    border-radius: 50px; 
    font-size: 0.75rem; 
    font-weight: 800; 
    text-transform: uppercase; 
    letter-spacing: 0.05em;
}

/* Panel Styling */
.p-panel { 
    background: white; 
    border-radius: 12px; 
    padding: 1rem; 
    border: 1px solid #e2e8f0; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
}

.p-header { 
    font-weight: 800; 
    font-size: 0.95rem; 
    color: var(--primary); 
    margin-bottom: 1rem; 
    border-bottom: 2px solid #f1f5f9; 
    padding-bottom: 0.5rem; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
}

/* Form Elements */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.form-group { margin-bottom: 0.75rem; }
.form-group label { display: block; font-size: 0.75rem; font-weight: 800; color: var(--secondary); margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.025em; }

.p-input { 
    width: 100%; padding: 0.5rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px; 
    font-family: inherit; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease; 
    background: #fcfcfc;
}
.p-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); outline: none; background: white; }

/* Table Styling */
.site-list-container { overflow-x: auto; border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; background: white; }
.crs-table { width: 100%; border-collapse: collapse; }
.crs-table th { background: transparent; padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; font-weight: 800; border-bottom: 1px solid #f1f5f9; text-align: left; }
.crs-table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f8fafc; color: #64748b; font-size: 0.85rem; }

.site-row { transition: all 0.2s; }
.site-row:hover { background: #f8fafc; }
.site-row.selected { background: #f0fdfa !important; }
.site-row.selected td { color: var(--primary); }

/* Previously associated with this client (proposal/booking) */
.site-row.already-associated { box-shadow: inset 0 0 0 3px rgba(250,204,21,0.06); }
.site-row .assoc-badge { background: #fef3c7; color: #92400e; padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; margin-left: 0.5rem; display: inline-block; }

.asset-chk {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    accent-color: var(--primary);
}

/* Summary Items */
.stat-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 700; color: #475569; }
.summary-box { background: linear-gradient(to bottom right, #ffffff, #f8fafc); }
.grand-total { border-top: 2px solid #e2e8f0; padding-top: 1rem; margin-top: 0.5rem; }

/* Badges */
.badge-media { background: #eff6ff; color: #1e40af; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800; border: 1px solid #dbeafe; }
.badge-ha { background: #dcfce7; color: #166534; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; }
.badge-ta { background: #fef9c3; color: #854d0e; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; }

.status-available { background: #ecfdf5; color: #059669; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; border: 1px solid #d1fae5; }
.site-thumb { width: 140px; height: 90px; border-radius: 8px; object-fit: cover; cursor: zoom-in; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.no-img { font-size: 0.7rem; color: #94a3b8; font-style: italic; }

.sno-cell { font-weight: 800; color: #94a3b8; text-align: center; font-size: 0.85rem; }

/* Pagination Styles */
.pagination-wrap { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2rem; background: #fafafa; border-top: 1px solid #f1f5f9; border-radius: 0 0 20px 20px; }
.pg-info { font-size: 0.85rem; color: #64748b; font-weight: 600; }
.pg-controls { display: flex; gap: 0.4rem; align-items: center; }
.pg-btn { min-width: 38px; height: 38px; border: 1px solid #e2e8f0; background: white; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 0.85rem; transition: all 0.2s; color: #475569; display: flex; align-items: center; justify-content: center; }
.pg-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); background: #f0fdfa; transform: translateY(-1px); }
.pg-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2); }
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; background: #f8fafc; }
.pg-dots { color: #94a3b8; font-weight: 800; padding: 0 0.5rem; }

/* Responsive */
@media (max-width: 1400px) {
    .proposal-bottom-grid { grid-template-columns: repeat(3, 1fr); gap: 1rem; }
}

@media (max-width: 1200px) {
    .proposal-bottom-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .proposal-bottom-grid { grid-template-columns: 1fr; }
    .p-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
}

/* Modal Styles */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 5000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
.modal-content { background: white; width: 95%; max-width: 550px; border-radius: 30px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
.modal-header { padding: 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 2.5rem; }
.modal-footer { padding: 1.5rem 2.5rem; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 1rem; }
</style>

<script>
let selectedSites = [];
let currentPage = 1;
let pageSize = 6;

let selectedMediaTab = 'all';
function selectProposalMediaTab(mtype, btn) {
    selectedMediaTab = mtype;
    
    // Update active class on tabs
    const tabs = document.querySelectorAll('#proposal-create-tabs .tab');
    tabs.forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    
    // Sync the media_type select
    const select = document.getElementById('media_type');
    if (select) {
        select.value = mtype === 'all' ? '' : mtype;
    }
    
    currentPage = 1;
    filterSites();
}

document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('media_type');
    if (select) {
        select.addEventListener('change', function() {
            const val = this.value || 'all';
            const tabs = document.querySelectorAll('#proposal-create-tabs .tab');
            tabs.forEach(t => {
                const onclickAttr = t.getAttribute('onclick');
                if (onclickAttr && (onclickAttr.includes(`'${val}'`) || (val === 'all' && onclickAttr.includes("'all'")))) {
                    tabs.forEach(x => x.classList.remove('active'));
                    t.classList.add('active');
                }
            });
        });
    }
});
const baseUrl = "<?php echo BASE_URL; ?>";
const imgBaseUrl = "<?php echo BASE_URL; ?>uploads/sites/";
const printingVendors = <?php echo json_encode($printingVendors); ?>;
const printingRates = <?php echo json_encode($printingRates); ?>;

function handleClientChange() {
    const select = document.getElementById('client_id');
    const clientId = select.value;
    const contact = select.options[select.selectedIndex].dataset.contact;
    document.getElementById('contact_person').value = contact || '';

    const gstContainer = document.getElementById('gst_selection_container');
    const gstSelect = document.getElementById('selected_gstin');
    const gstBadge = document.getElementById('gst_count_badge');
    const gstPreview = document.getElementById('gst_preview_text');

    if (!clientId) {
        gstContainer.style.display = 'none';
        return;
    }

    // Fetch Full Client Details via AJAX
    fetch(`../../ajax/get_partner_details.php?id=${clientId}`)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const p = res.data;
            gstSelect.innerHTML = '';
            
            // User requested to ONLY show this for Group of Companies
            if (p.business_type !== 'Group of Companies') {
                gstContainer.style.display = 'none';
                return;
            }

            let gsts = [];
            // Add primary GST if exists
            if (p.gstin) {
                gsts.push({ gstin: p.gstin, state: 'Primary', city: '', district: '', address: 'Main Address' });
            }

            // Parse additional GSTs
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
                    gstSelect.add(opt);
                });
                
                handleGstSelectionChange(); // Update preview for first option
            } else {
                gstContainer.style.display = 'none';
            }
        } else {
            gstContainer.style.display = 'none';
        }
    });

    // Fetch sites already associated with this client and mark them in the list
    fetch(`../../ajax/get_client_sites.php?id=${clientId}`)
    .then(r => r.json())
    .then(res => {
        if (!res.success) return;

        // Clear previous markers
        document.querySelectorAll('#asset-body tr.site-row.already-associated').forEach(row => {
            row.classList.remove('already-associated');
            const b = row.querySelector('.assoc-badge'); if (b) b.remove();
        });

        res.sites.forEach(s => {
            const row = document.getElementById('row-' + s.site_id);
            if (!row) return;
            row.classList.add('already-associated');

            // Add a small badge into the asset details column (5th td)
            const assetCell = row.querySelector('td:nth-child(5)');
            if (assetCell && !assetCell.querySelector('.assoc-badge')) {
                const span = document.createElement('span');
                span.className = 'assoc-badge';
                span.innerText = s.type === 'booking' ? 'Booked' : ('In Proposal ' + (s.ref || ''));
                assetCell.appendChild(span);
            }
        });
    })
    .catch(e => console.error('Client sites fetch error', e));
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

function calculateEndDate() {
    const startStr = document.getElementById('start_date').value;
    const days = parseInt(document.getElementById('total_days').value);
    
    if (startStr && !isNaN(days) && days > 0) {
        const startDate = new Date(startStr);
        const endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + days - 1);
        document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
    }
}

function calculateTotalDays() {
    const startStr = document.getElementById('start_date').value;
    const endStr = document.getElementById('end_date').value;
    
    if (startStr && endStr) {
        const start = new Date(startStr);
        const end = new Date(endStr);
        const diffTime = end - start;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('total_days').value = diffDays > 0 ? diffDays : 0;
    }
}

function renderPagination() {
    const allRows = Array.from(document.querySelectorAll('#asset-body tr.site-row'));
    const activeRows = allRows.filter(row => !row.classList.contains('search-hidden'));
    
    const total = activeRows.length;
    const start = (currentPage - 1) * pageSize;
    const end = Math.min(start + pageSize, total);
    
    // Hide everything first
    allRows.forEach(row => row.style.display = 'none');

    // Update S.No for all active rows (sequential 1 to N)
    activeRows.forEach((row, index) => {
        const snoCell = row.querySelector('.sno-cell');
        if(snoCell) snoCell.innerText = index + 1;
    });

    // Show only current page slice
    const visibleRows = activeRows.slice(start, end);
    visibleRows.forEach(row => row.style.display = '');

    // Update info
    document.getElementById('pg-start').innerText = total === 0 ? 0 : start + 1;
    document.getElementById('pg-end').innerText = end;
    document.getElementById('pg-total').innerText = total;

    updatePgControls(total);
}

function updatePgControls(total) {
    const totalPages = Math.ceil(total / pageSize);
    const container = document.getElementById('pg-numbers');
    container.innerHTML = '';

    if (totalPages <= 1) return;

    const createBtn = (content, disabled, active, onClick) => {
        const btn = document.createElement('button');
        btn.className = 'pg-btn' + (active ? ' active' : '');
        btn.innerHTML = content;
        btn.disabled = disabled;
        if (!disabled) btn.onclick = onClick;
        return btn;
    };

    // First & Prev
    container.appendChild(createBtn('<i class="fas fa-angle-double-left"></i>', currentPage === 1, false, () => { currentPage = 1; renderPagination(); }));
    container.appendChild(createBtn('<i class="fas fa-angle-left"></i>', currentPage === 1, false, () => { currentPage--; renderPagination(); }));

    // Page Numbers
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    if (startPage > 1) {
        container.appendChild(createBtn('1', false, false, () => { currentPage = 1; renderPagination(); }));
        if (startPage > 2) {
            const dots = document.createElement('span');
            dots.className = 'pg-dots';
            dots.innerText = '...';
            container.appendChild(dots);
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createBtn(i, false, i === currentPage, () => { currentPage = i; renderPagination(); }));
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const dots = document.createElement('span');
            dots.className = 'pg-dots';
            dots.innerText = '...';
            container.appendChild(dots);
        }
        container.appendChild(createBtn(totalPages, false, false, () => { currentPage = totalPages; renderPagination(); }));
    }

    // Next & Last
    container.appendChild(createBtn('<i class="fas fa-angle-right"></i>', currentPage === totalPages, false, () => { currentPage++; renderPagination(); }));
    container.appendChild(createBtn('<i class="fas fa-angle-double-right"></i>', currentPage === totalPages, false, () => { currentPage = totalPages; renderPagination(); }));
}

function changePageSize() {
    pageSize = parseInt(document.getElementById('pg-limit').value);
    currentPage = 1;
    renderPagination();
}

function openClientModal() { document.getElementById('clientModal').style.display = 'flex'; }
function closeClientModal() { document.getElementById('clientModal').style.display = 'none'; }

function submitQuickClient() {
    const name = document.getElementById('new_client_name').value;
    const contact = document.getElementById('new_client_contact').value;
    const phone = document.getElementById('new_client_phone').value;
    const email = document.getElementById('new_client_email').value;
    const city = document.getElementById('new_client_city').value;
    const address = document.getElementById('new_client_address').value;

    if (!name) {
        Swal.fire('Error', 'Company Name is required', 'error');
        return;
    }

    const data = { type: 'client', name, contact, phone, email, city, address };

    fetch('../../ajax/quick_save_partner.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const select = document.getElementById('client_id');
            const opt = document.createElement('option');
            opt.value = res.id;
            opt.text = name;
            opt.selected = true;
            select.add(opt);
            
            if (select.refreshSearchable) {
                select.refreshSearchable();
            }
            handleClientChange();
            closeClientModal();
            Swal.fire('Success', 'Client created and selected!', 'success');
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}

function getBestPrintingRate(siteId, mediaType) {
    // 1. Try to find rate for THIS SITE
    let rate = printingRates.find(r => r.site_id == siteId);
    if (rate) return rate;
    
    // 2. Try to find rate for THIS MEDIA
    rate = printingRates.find(r => !r.site_id && r.media_type === mediaType);
    return rate || null;
}

function toggleSite(id) {
    const row = document.getElementById('row-' + id);
    const chk = row.querySelector('.asset-chk');
    const input = row.querySelector('.sale-rate-input');
    
    const name = row.dataset.name;
    const rate = parseFloat(row.dataset.rate);
    const prate = parseFloat(row.dataset.prate);
    const owner = row.dataset.owner;
    const sqft = parseFloat(row.dataset.sqft);
    const type = row.dataset.type;

    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx === -1) {
        const city = row.dataset.city;
        const state = row.dataset.state;
        const illumination = row.dataset.illumination;
        const thumbnail = row.dataset.thumbnail;
        const width = row.dataset.width;
        const height = row.dataset.height;
        const vendorName = row.dataset.vendorName;
        const siteCode = row.dataset.code;
        const location = row.dataset.location;
        const area = row.querySelector('.location-area') ? row.querySelector('.location-area').innerText : '';
        const allImages = row.dataset.images;

        // Find default Printing PO
        const bestRate = getBestPrintingRate(id, type);
        const pVendor = bestRate ? bestRate.vendor_id : null;
        const pRate = bestRate ? parseFloat(bestRate.rate_per_sqft) : 0;
        const pTotal = pRate * sqft;

        const mRate = 0;
        const mType = 'Standard';
        const mTotal = mRate * sqft;

        selectedSites.push({ 
            id, name, cardRate: rate, purchaseRate: prate, saleRate: 0, owner, sqft, city, state, type, illumination, thumbnail, allImages, width, height, vendorName, siteCode, area, location,
            printing_vendor_id: pVendor,
            printing_rate: pRate,
            printing_total: pTotal,
            mounting_type: mType,
            mounting_rate: mRate,
            mounting_total: mTotal
        });
        if(row) row.classList.add('selected');
        if(chk) chk.checked = true;
        if(input) input.disabled = false;
    } else {
        selectedSites.splice(idx, 1);
        if(row) {
            row.classList.remove('selected');
            if(chk) chk.checked = false;
            if(input) input.disabled = true;
            row.querySelector('.total-cell').innerText = '₹0';
        }
    }
    
    const count = selectedSites.length;
    if(document.getElementById('selected-count')) document.getElementById('selected-count').innerText = count;
    if(document.getElementById('selected-count-btm')) document.getElementById('selected-count-btm').innerText = count;
    
    updateBucketUI();
    recalcAll();
    
    // Auto open drawer on first selection if closed
    if (selectedSites.length === 1 && idx === -1) {
        openBucket();
    }
}

let activeBucketTab = 'rental';
function switchBucketTab(tab) {
    activeBucketTab = tab;
    document.querySelectorAll('.bucket-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('btn-tab-' + tab).classList.add('active');
    updateBucketUI();
}

function openBucket() {
    document.getElementById('selection-bucket-panel').style.right = '0';
    document.getElementById('bucket-backdrop').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scroll
}

function closeBucket() {
    document.getElementById('selection-bucket-panel').style.right = '-1400px';
    document.getElementById('bucket-backdrop').style.display = 'none';
    document.body.style.overflow = '';
}

function updatePrintingInfo(id, vendorId, rateVal) {
    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx !== -1) {
        selectedSites[idx].printing_vendor_id = vendorId;
        selectedSites[idx].printing_rate = parseFloat(rateVal) || 0;
        selectedSites[idx].printing_total = selectedSites[idx].printing_rate * selectedSites[idx].sqft;
        
        recalcAll();
        updateBucketUI();
    }
}

function updateMountingInfo(id, typeVal, rateVal) {
    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx !== -1) {
        selectedSites[idx].mounting_type = typeVal;
        selectedSites[idx].mounting_rate = parseFloat(rateVal) || 0;
        selectedSites[idx].mounting_total = selectedSites[idx].mounting_rate * selectedSites[idx].sqft;
        
        recalcAll();
        updateBucketUI();
    }
}

function updateBucketUI() {
    const bucketPanel = document.getElementById('selection-bucket-panel');
    const bucketList = document.getElementById('bucket-list');
    const emptyMsg = document.getElementById('bucket-empty-msg');
    const bucketCount = document.getElementById('bucket-count');
    
    if (selectedSites.length === 0) {
        document.getElementById('bucket-empty-msg').style.display = 'block';
        bucketList.innerHTML = '';
        bucketCount.innerText = '0';
        return;
    }
    
    document.getElementById('bucket-empty-msg').style.display = 'none';
    bucketCount.innerText = selectedSites.length;
    
    let headers = '';
    if (activeBucketTab === 'rental') {
        headers = `
            <th style="width: 40px; padding: 0.8rem 1rem;">#</th>
            <th style="width: 50px; padding: 0.8rem 1rem;">ACT</th>
            <th style="width: 80px; padding: 0.8rem 1rem;">PREVIEW</th>
            <th style="padding: 0.8rem 1rem;">CITY / CODE</th>
            <th style="padding: 0.8rem 1rem;">ASSET DETAILS</th>
            <th style="padding: 0.8rem 1rem;">SIZE</th>
            <th style="padding: 0.8rem 1rem;">PRICING</th>
            <th style="padding: 0.8rem 1rem; text-align: right; width: 120px;">OFFER RATE</th>
            <th style="padding: 0.8rem 1rem; text-align: right; width: 100px;">TOTAL</th>
        `;
    } else if (activeBucketTab === 'printing') {
        headers = `
            <th style="width: 40px; padding: 0.8rem 1rem;">#</th>
            <th style="width: 50px; padding: 0.8rem 1rem;">ACT</th>
            <th style="width: 80px; padding: 0.8rem 1rem;">PREVIEW</th>
            <th style="padding: 0.8rem 1rem;">CITY / CODE</th>
            <th style="padding: 0.8rem 1rem;">ASSET DETAILS</th>
            <th style="padding: 0.8rem 1rem;">SIZE / SQFT</th>
            <th style="padding: 0.8rem 1rem;">PRINTING VENDOR</th>
            <th style="padding: 0.8rem 1rem; text-align: right; width: 120px;">RATE / SQFT</th>
            <th style="padding: 0.8rem 1rem; text-align: right; width: 100px;">TOTAL</th>
        `;
    } else if (activeBucketTab === 'mounting') {
        headers = `
            <th style="width: 40px; padding: 0.8rem 1rem;">#</th>
            <th style="width: 50px; padding: 0.8rem 1rem;">ACT</th>
            <th style="width: 80px; padding: 0.8rem 1rem;">PREVIEW</th>
            <th style="padding: 0.8rem 1rem;">CITY / CODE</th>
            <th style="padding: 0.8rem 1rem;">ASSET DETAILS</th>
            <th style="padding: 0.8rem 1rem;">SIZE / SQFT</th>
            <th style="padding: 0.8rem 1rem;">MOUNTING TYPE</th>
            <th style="padding: 0.8rem 1rem; text-align: right; width: 120px;">RATE / SQFT</th>
            <th style="padding: 0.8rem 1rem; text-align: right; width: 100px;">TOTAL</th>
        `;
    }

    let html = `
        <table class="crs-table selection-table" style="width: 100%; border-collapse: separate; border-spacing: 0 0.5rem;">
            <thead>
                <tr style="border-bottom: 2px solid #f1f5f9;">
                    ${headers}
                </tr>
            </thead>
            <tbody>
    `;

    selectedSites.forEach((site, index) => {
        const thumb = site.thumbnail ? '<?php echo BASE_URL; ?>uploads/sites/' + site.thumbnail : 'https://via.placeholder.com/150x95?text=No+Img';
        const imgList = (site.allImages || "").split(',').filter(img => img.trim() !== "");
        const imgCount = imgList.length;

        let cells = '';

        if (activeBucketTab === 'rental') {
            cells = `
                <td style="padding: 0.6rem 1rem;">
                    <div style="font-weight: 800; color: #64748b; font-size: 0.7rem;">CARD: ₹${site.cardRate.toLocaleString()}</div>
                </td>
                <td style="padding: 0.6rem 1rem;">
                    <input type="number" class="p-input bucket-rate-input" 
                           value="${site.saleRate}" 
                           oninput="updateSitePrice('${site.id}', this.value)"
                           style="width: 100px; height: 32px; font-size: 0.8rem; font-weight: 800; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 0.4rem; color: #1e293b; text-align: right;">
                </td>
                <td style="padding: 0.6rem 1rem; text-align: right; font-weight: 900; color: var(--primary);">
                    ₹${site.saleRate.toLocaleString()}
                </td>
            `;
        } else if (activeBucketTab === 'printing') {
            let pOptions = '<option value="">Select Vendor</option>';
            if (typeof printingVendors !== 'undefined') {
                printingVendors.forEach(v => {
                    const selected = v.id == site.printing_vendor_id ? 'selected' : '';
                    pOptions += `<option value="${v.id}" ${selected}>${v.name}</option>`;
                });
            }

            cells = `
                <td style="padding: 0.6rem 1rem;">
                    <select onchange="updatePrintingInfo('${site.id}', this.value, document.getElementById('p_rate_${site.id}').value)" 
                            style="width: 150px; font-size: 0.75rem; padding: 0.4rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                        ${pOptions}
                    </select>
                </td>
                <td style="padding: 0.6rem 1rem; text-align: right;">
                    <input type="number" id="p_rate_${site.id}" value="${site.printing_rate || 0}" 
                           oninput="updatePrintingInfo('${site.id}', this.closest('tr').querySelector('select').value, this.value)"
                           style="width: 100px; height: 32px; font-size: 0.8rem; font-weight: 800; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 0.4rem; color: #1e293b; text-align: right;">
                </td>
                <td style="padding: 0.6rem 1rem; text-align: right; font-weight: 900; color: var(--primary);">
                    ₹${(site.printing_total || 0).toLocaleString()}
                </td>
            `;
        } else if (activeBucketTab === 'mounting') {
            const mTypes = ['Standard', 'Premium', 'Non-Lit Flex', 'Back-Lit Flex', 'Vinyl'];
            let mOptions = '';
            mTypes.forEach(t => {
                const selected = t === site.mounting_type ? 'selected' : '';
                mOptions += `<option value="${t}" ${selected}>${t}</option>`;
            });

            cells = `
                <td style="padding: 0.6rem 1rem;">
                    <select onchange="updateMountingInfo('${site.id}', this.value, document.getElementById('m_rate_${site.id}').value)" 
                            style="width: 150px; font-size: 0.75rem; padding: 0.4rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                        ${mOptions}
                    </select>
                </td>
                <td style="padding: 0.6rem 1rem; text-align: right;">
                    <input type="number" id="m_rate_${site.id}" value="${site.mounting_rate || 0}" 
                           oninput="updateMountingInfo('${site.id}', this.closest('tr').querySelector('select').value, this.value)"
                           style="width: 100px; height: 32px; font-size: 0.8rem; font-weight: 800; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 0.4rem; color: #1e293b; text-align: right;">
                </td>
                <td style="padding: 0.6rem 1rem; text-align: right; font-weight: 900; color: var(--primary);">
                    ₹${(site.mounting_total || 0).toLocaleString()}
                </td>
            `;
        }

        html += `
            <tr id="bucket-row-${site.id}" class="site-row selected" style="background: white;">
                <td style="font-weight: 700; color: #64748b; padding: 0.6rem 1rem;">${index + 1}</td>
                <td style="padding: 0.6rem 1rem;">
                    <button onclick="toggleSite('${site.id}')" style="background: #fee2e2; color: #ef4444; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-trash-alt" style="font-size: 0.75rem;"></i>
                    </button>
                </td>
                <td style="padding: 0.6rem 1rem;">
                    <div style="position: relative; width: 80px; height: 50px;">
                        <img src="${thumb}" onclick="openLightboxSlider('${site.allImages}', '${site.id}')" 
                             class="site-thumb"
                             style="width: 100%; height: 100%; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; transition: transform 0.2s;">
                        ${imgCount > 1 ? `<div style="position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,0.8); color: white; font-size: 0.55rem; padding: 2px 5px; border-radius: 4px; font-weight: 800;"><i class="fas fa-images"></i> ${imgCount}</div>` : ''}
                    </div>
                </td>
                <td style="padding: 0.6rem 1rem;">
                    <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 1px;">${site.city}</div>
                    <div style="color: #f97316; font-size: 0.65rem; font-weight: 800;">${site.siteCode}</div>
                </td>
                <td style="padding: 0.6rem 1rem;">
                    <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 1px;">${site.name}</div>
                    <div style="font-size: 0.65rem; color: #64748b; margin-bottom: 4px; line-height: 1.1;">${site.location}</div>
                    <div style="display: flex; gap: 0.3rem; align-items: center;">
                        <span style="background: #ecfdf5; color: #059669; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase;">${site.type}</span>
                    </div>
                </td>
                <td style="padding: 0.6rem 1rem;">
                    <div style="font-weight: 800; color: #1e293b; font-size: 0.8rem; margin-bottom: 1px;">${site.width}' x ${site.height}'</div>
                    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;">${site.sqft.toLocaleString()} SQFT</div>
                </td>
                ${cells}
            </tr>
        `;
    });

    html += `</tbody></table>`;
    bucketList.innerHTML = html;
}

function updateSitePrice(id, val) {
    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx !== -1) {
        const rate = parseFloat(val) || 0;
        selectedSites[idx].saleRate = rate;
        
        // 1. Update main table input if it exists
        const mainRow = document.getElementById('row-' + id);
        if (mainRow) {
            const mainInput = mainRow.querySelector('.sale-rate-input');
            if (mainInput && mainInput.value !== val) mainInput.value = val;
            const mainTotal = mainRow.querySelector('.total-cell');
            if (mainTotal) mainTotal.innerText = '₹' + rate.toLocaleString();
        }

        // 2. Update bucket row total
        const bucketRow = document.getElementById('bucket-row-' + id);
        if (bucketRow) {
            const bucketInput = bucketRow.querySelector('.bucket-rate-input');
            if (bucketInput && bucketInput.value !== val) bucketInput.value = val;
            const bucketTotal = bucketRow.querySelector('.total-cell');
            if (bucketTotal) bucketTotal.innerText = '₹' + rate.toLocaleString();
        }

        recalcAll();
    }
}

function recalcAll() {
    const globalDisc = parseFloat(document.getElementById('global_discount').value) || 0;
    const globalMark = parseFloat(document.getElementById('global_markup').value) || 0;
    
    // Auto-calculate printing and mounting totals from selected sites
    const totalPrinting = selectedSites.reduce((acc, s) => acc + (s.printing_total || 0), 0);
    const totalMounting = selectedSites.reduce((acc, s) => acc + (s.mounting_total || 0), 0);
    
    // Update inputs
    const printCostInput = document.getElementById('print_cost');
    if (printCostInput) printCostInput.value = totalPrinting;
    const mountCostInput = document.getElementById('mount_cost');
    if (mountCostInput) mountCostInput.value = totalMounting;

    const print = totalPrinting;
    const mount = totalMounting;
    const taxType = document.getElementById('tax-type').value;

    let totalDisplay = 0;

    selectedSites.forEach((site) => {
        const row = document.getElementById('row-' + site.id);
        const currentTotal = site.saleRate;
        
        // Margin Analysis: (Sale - Purchase)
        const markupVal = site.saleRate - site.purchaseRate;
        const markupPct = site.purchaseRate > 0 ? ((markupVal / site.purchaseRate) * 100).toFixed(1) : '0';

        totalDisplay += currentTotal;
        
        // Update row visual
        if(row) {
            row.querySelector('.total-cell').innerText = '₹' + currentTotal.toLocaleString();
            const markupCell = row.querySelector('.markup-cell');
            if(markupCell) {
                markupCell.innerText = '₹' + markupVal.toLocaleString() + ' (' + markupPct + '%)';
                markupCell.style.color = markupVal >= 0 ? '#059669' : '#ef4444';
            }
        }
    });

    // Apply global discount/markup to the total display cost
    let adjustedDisplay = totalDisplay;
    if (globalMark > 0) adjustedDisplay += (totalDisplay * (globalMark / 100));
    if (globalDisc > 0) adjustedDisplay -= (totalDisplay * (globalDisc / 100));

    const subtotal = adjustedDisplay + print + mount;
    const totalTax = subtotal * 0.18;
    const grand = subtotal + totalTax;

    // Update Summary Panel
    document.getElementById('sum-display-btm').innerText = '₹' + adjustedDisplay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Tax Breakdown Logic
    const taxContainer = document.getElementById('tax-breakdown');
    if (taxType === 'cgst_sgst') {
        const halfTax = totalTax / 2;
        taxContainer.innerHTML = `
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.4rem; font-size:0.85rem;">
                <span style="color:#64748b; font-weight:600;">CGST (9%):</span>
                <span style="font-weight:800; color:#1e293b;">₹${halfTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.4rem; font-size:0.85rem;">
                <span style="color:#64748b; font-weight:600;">SGST (9%):</span>
                <span style="font-weight:800; color:#1e293b;">₹${halfTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
        `;
    } else {
        taxContainer.innerHTML = `
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.4rem; font-size:0.85rem;">
                <span style="color:#64748b; font-weight:600;">IGST (18%):</span>
                <span style="font-weight:800; color:#1e293b;">₹${totalTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
        `;
    }

    document.getElementById('sum-grand-btm').innerText = '₹' + grand.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function clearFilters() {
    document.getElementById('site-search').value = '';
    document.getElementById('media_type').value = '';
    document.getElementById('filter-state').value = '';
    document.getElementById('filter-city').value = '';
    document.getElementById('filter-location').value = '';
    document.getElementById('light_type').value = '';
    document.getElementById('filter-size').value = '';
    if(document.getElementById('filter-vendor')) document.getElementById('filter-vendor').value = '';
    
    document.querySelector('input[name="ownership"][value="all"]').checked = true;
    document.querySelector('input[name="availability"][value="available"]').checked = true;
    document.getElementById('vendor-filter-group').style.display = 'none';

    // Reset tabs
    const tabs = document.querySelectorAll('#proposal-create-tabs .tab');
    tabs.forEach(t => t.classList.remove('active'));
    const allTab = Array.from(tabs).find(t => t.getAttribute('onclick') && t.getAttribute('onclick').includes("'all'"));
    if (allTab) allTab.classList.add('active');
    selectedProposalMediaTab = 'all';

    filterSites();
}

function filterSites() {
    const q = document.getElementById('site-search') ? document.getElementById('site-search').value.toLowerCase() : '';
    const mediaType = document.getElementById('media_type').value;
    const lightType = document.getElementById('light_type').value;
    const ownershipRadio = document.querySelector('input[name="ownership"]:checked').value;
    const availability = document.querySelector('input[name="availability"]:checked').value;
    const vendorId = document.getElementById('filter-vendor').value;
    
    // Show/Hide Vendor Dropdown based on ownership radio
    const vendorGroup = document.getElementById('vendor-filter-group');
    if (ownershipRadio === 'TA') {
        vendorGroup.style.display = 'block';
    } else {
        vendorGroup.style.display = 'none';
        document.getElementById('filter-vendor').value = ''; // Reset vendor if not TA
    }

    const state = document.getElementById('filter-state').value;
    const city = document.getElementById('filter-city').value;
    const locationFilter = document.getElementById('filter-location') ? document.getElementById('filter-location').value : '';
    const size = document.getElementById('filter-size').value;

    const rows = document.querySelectorAll('#asset-body tr.site-row');
    
    rows.forEach(row => {
        let show = true;
        
        // Availability Filter
        if (availability === 'available' && row.dataset.status !== 'available') show = false;
        
        // Mandatory Filters & Radios
        if (mediaType && row.dataset.type !== mediaType) show = false;
        if (lightType && row.dataset.illumination !== lightType) show = false;
        if (ownershipRadio !== 'all' && row.dataset.owner !== ownershipRadio) show = false;
        
        // Vendor Filter (only if TA selected)
        if (ownershipRadio === 'TA' && vendorId && row.dataset.vendor !== vendorId) show = false;
        
        // Location & Size Filters
        if (state && row.dataset.state !== state) show = false;
        if (city && row.dataset.city !== city) show = false;
        if (locationFilter && row.dataset.location !== locationFilter) show = false;
        if (size && row.dataset.size !== size) show = false;
        
        // Comprehensive Search (All Fields)
        if (q) {
            const rowText = (
                row.dataset.name + ' ' + 
                row.dataset.code + ' ' + 
                row.dataset.location + ' ' + 
                row.dataset.city + ' ' + 
                row.dataset.state + ' ' + 
                row.dataset.type + ' ' + 
                row.dataset.illumination + ' ' + 
                row.dataset.vendorName + ' ' + 
                row.dataset.size
            ).toLowerCase();
            if (!rowText.includes(q)) show = false;
        }

        if (show) {
            row.classList.remove('search-hidden');
        } else {
            row.classList.add('search-hidden');
        }
    });

    currentPage = 1;
    renderPagination();
}

document.addEventListener('DOMContentLoaded', () => {
    renderPagination();
});

(function() {
    function tryInit() {
        if (typeof initSearchableSelect === 'function') {
            initSearchableSelect('client_id', 'Search Company / Client...');
            console.log("Searchable selects initialized successfully on create.php (proposals)");
        } else {
            console.warn("initSearchableSelect function not available yet, retrying on window load...");
            window.addEventListener('load', () => {
                if (typeof initSearchableSelect === 'function') {
                    initSearchableSelect('client_id', 'Search Company / Client...');
                    console.log("Searchable selects initialized successfully on window load");
                } else {
                    console.error("initSearchableSelect function could not be loaded!");
                }
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();

function saveProposal() {
    const clientId = document.getElementById('client_id').value;
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    const campaignName = document.getElementById('campaign_name').value;
    const mediaType = document.getElementById('media_type').value;
    const ownership = document.querySelector('input[name="ownership"]:checked').value;
    const totalDays = document.getElementById('total_days').value;
    const contactPerson = document.getElementById('contact_person').value;
    const lightType = document.getElementById('light_type').value;
    const selectedGstin = document.getElementById('selected_gstin').value;
    
    if (!clientId || !campaignName || selectedSites.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please fill the mandatory fields (Client, Campaign Name) and select at least one asset.',
            confirmButtonColor: 'var(--primary)'
        });
        return;
    }

    const data = {
        clientId,
        selectedGstin,
        taxType: document.getElementById('tax-type').value,
        startDate: start,
        endDate: end,
        campaignName,
        mediaType,
        inventoryType: ownership, // Use ownership radio value
        lightType,
        totalDays,
        contactPerson,
        printCost: parseFloat(document.getElementById('print_cost').value) || 0,
        mountCost: parseFloat(document.getElementById('mount_cost').value) || 0,
        selectedSites
    };

    fetch('../../ajax/save_proposal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('Success', 'Proposal generated successfully!', 'success')
            .then(() => window.location.href = '<?php echo BASE_URL; ?>modules/proposals/proposals.php');
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}

function goToStep1() {
    document.getElementById('step-2').style.display = 'none';
    document.getElementById('step-1').style.display = 'block';
    if(document.getElementById('step-3')) document.getElementById('step-3').style.display = 'none';
    updateStepUI(1);
    if(document.getElementById('wizard-progress-line')) document.getElementById('wizard-progress-line').style.width = '0%';
}

function goToStep2() {
    const campaignName = document.getElementById('campaign_name').value;
    const clientId = document.getElementById('client_id').value;
    
    if (!clientId || !campaignName) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Details',
            text: 'Please enter a Campaign Name and select a Client before proceeding.',
            confirmButtonColor: 'var(--primary)'
        });
        return;
    }
    
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'block';
    if(document.getElementById('step-3')) document.getElementById('step-3').style.display = 'none';
    window.scrollTo(0, 0);
    filterSites();
    updateStepUI(2);
    if(document.getElementById('wizard-progress-line')) document.getElementById('wizard-progress-line').style.width = '50%';
}

function goToStep3() {
    if(selectedSites.length === 0) {
        Swal.fire('Error', 'Please select at least one site to review', 'error');
        return;
    }
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'none';
    if(document.getElementById('step-3')) document.getElementById('step-3').style.display = 'block';
    updateStepUI(3);
    if(document.getElementById('wizard-progress-line')) document.getElementById('wizard-progress-line').style.width = '100%';
}

function updateStepUI(step) {
    if(document.getElementById('tab-indicator-1')) document.getElementById('tab-indicator-1').style.opacity = step >= 1 ? '1' : '0.4';
    if(document.getElementById('tab-indicator-2')) document.getElementById('tab-indicator-2').style.opacity = step >= 2 ? '1' : '0.4';
    if(document.getElementById('tab-indicator-3')) document.getElementById('tab-indicator-3').style.opacity = step >= 3 ? '1' : '0.4';
}

// Lightbox & Slider Logic
let currentImages = [];
let currentImgIndex = 0;

function openLightboxSlider(imageString, siteId) {
    if (!imageString) return;
    window.currentLightboxSiteId = siteId;
    currentImages = imageString.split(',');
    currentImgIndex = 0;
    
    updateSliderImage();
    
    const lb = document.getElementById('simple-lightbox');
    if(lb) {
        lb.style.display = 'flex';
        // Show/Hide Nav Buttons
        const navs = document.querySelectorAll('.slider-nav');
        navs.forEach(n => n.style.display = currentImages.length > 1 ? 'flex' : 'none');
    }
}
function updateSliderImage() {
    const lbImg = document.getElementById('lightbox-img');
    const lbBadge = document.getElementById('lightbox-badge');
    if(lbImg) {
        lbImg.src = imgBaseUrl + currentImages[currentImgIndex];
        if(lbBadge) {
            lbBadge.innerText = (currentImgIndex + 1) + " / " + currentImages.length;
            lbBadge.style.display = currentImages.length > 1 ? 'block' : 'none';
        }

        // Update Button State
        const btn = document.getElementById('primary-btn');
        if(btn && window.currentLightboxSiteId) {
            const idx = selectedSites.findIndex(s => s.id === window.currentLightboxSiteId);
            const isPrimary = (idx !== -1 && selectedSites[idx].thumbnail === currentImages[currentImgIndex]);
            
            if(isPrimary) {
                btn.innerHTML = '<i class="fas fa-check-double"></i> Selected as Primary';
                btn.style.background = '#059669';
            } else {
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Use as Primary Photo';
                btn.style.background = 'var(--primary)';
            }
        }
    }
}

function setPrimaryImage(e) {
    if(e) e.stopPropagation();
    const newThumb = currentImages[currentImgIndex];
    if(window.currentLightboxSiteId) {
        const id = window.currentLightboxSiteId;
        const idx = selectedSites.findIndex(s => s.id === id);
        if(idx !== -1) {
            selectedSites[idx].thumbnail = newThumb;
            selectedSites[idx].isCustomized = true;
            
            updateBucketUI();
            
            const row = document.getElementById('row-' + id);
            if(row) {
                const img = row.querySelector('.site-thumb');
                if(img) {
                    img.src = baseUrl + newThumb;
                    img.style.border = '2px solid #059669';
                }
            }
            
            // Update button text in lightbox
            const btn = document.getElementById('primary-btn');
            if(btn) {
                btn.innerHTML = '<i class="fas fa-check-double"></i> Selected as Primary';
                btn.style.background = '#059669';
            }

            Swal.fire({
                icon: 'success',
                title: 'Primary Image Set',
                text: 'This image will be used in your proposal.',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            Swal.fire('Note', 'Please select this asset first to set its primary image.', 'info');
        }
    }
}

function nextSlide(e) {
    if(e) e.stopPropagation();
    currentImgIndex = (currentImgIndex + 1) % currentImages.length;
    updateSliderImage();
}

function prevSlide(e) {
    if(e) e.stopPropagation();
    currentImgIndex = (currentImgIndex - 1 + currentImages.length) % currentImages.length;
    updateSliderImage();
}

function closeLightbox() {
    const lb = document.getElementById('simple-lightbox');
    if(lb) lb.style.display = 'none';
}

// Keyboard Support
document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('simple-lightbox');
    if(lb && lb.style.display === 'flex') {
        if(e.key === 'ArrowRight') nextSlide();
        if(e.key === 'ArrowLeft') prevSlide();
        if(e.key === 'Escape') closeLightbox();
    }
});

</script>

<!-- Simple Lightbox HTML -->
<div id="simple-lightbox" onclick="closeLightbox()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
    <div style="position: relative; max-width: 90%; max-height: 90%; display: flex; align-items: center; justify-content: center;" onclick="event.stopPropagation()">
        
        <!-- Prev Button -->
        <button class="slider-nav" onclick="prevSlide(event)" style="position: absolute; left: -80px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.5rem; transition: all 0.3s; z-index: 10001;">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div style="position: relative;">
            <img id="lightbox-img" src="" style="max-width: 100%; max-height: 85vh; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.15);">
            
            <!-- Select as Primary Button -->
            <button id="primary-btn" onclick="setPrimaryImage(event)" style="position: absolute; top: 20px; left: 20px; background: var(--primary); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 800; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 10px 20px rgba(13, 148, 136, 0.3); transition: all 0.2s;">
                <i class="fas fa-check-circle"></i> Use as Primary Photo
            </button>

            <!-- Image Counter Badge -->
            <div id="lightbox-badge" style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 6px 16px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2);"></div>
        </div>

        <!-- Next Button -->
        <button class="slider-nav" onclick="nextSlide(event)" style="position: absolute; right: -80px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.5rem; transition: all 0.3s; z-index: 10001;">
            <i class="fas fa-chevron-right"></i>
        </button>

        <!-- Close Button -->
        <div onclick="closeLightbox()" style="position: absolute; top: -60px; right: -60px; color: white; font-size: 2.5rem; cursor: pointer; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</div>
    </div>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
