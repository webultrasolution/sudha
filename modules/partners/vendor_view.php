<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('vendors', 'view');

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: vendors.php"); exit; }

$vendor = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$vendor->execute([$id]);
$vendor = $vendor->fetch();

if (!$vendor) { echo "Vendor not found."; exit; }

$activePage = 'vendors';
$pageTitle = 'Vendor Profile: ' . $vendor['name'];
include_once __DIR__ . '/../../includes/header.php';

// Fetch Owned Sites with rich details, thumbnail, and current active booking status
$sitesStmt = $pdo->prepare("SELECT s.*, p.name as vendor_name,
    (SELECT filename FROM site_images WHERE site_id = s.id ORDER BY id DESC LIMIT 1) as thumbnail_photo,
    (SELECT b.campaign_name FROM booking_items bi JOIN bookings b ON bi.booking_id = b.id WHERE bi.site_id = s.id AND CURDATE() BETWEEN b.start_date AND b.end_date LIMIT 1) as current_campaign,
    (SELECT c.name FROM booking_items bi JOIN bookings b ON bi.booking_id = b.id JOIN partners c ON b.client_id = c.id WHERE bi.site_id = s.id AND CURDATE() BETWEEN b.start_date AND b.end_date LIMIT 1) as current_client,
    (SELECT b.end_date FROM booking_items bi JOIN bookings b ON bi.booking_id = b.id WHERE bi.site_id = s.id AND CURDATE() BETWEEN b.start_date AND b.end_date LIMIT 1) as current_end_date
FROM sites s 
LEFT JOIN partners p ON s.vendor_id = p.id 
WHERE s.vendor_id = ? 
ORDER BY s.id DESC");
$sitesStmt->execute([$id]);
$sites = $sitesStmt->fetchAll();

// Extract filter options for Owned Sites
$cities = array_values(array_unique(array_filter(array_column($sites, 'city'))));
sort($cities);
$locations = array_values(array_unique(array_filter(array_column($sites, 'location'))));
sort($locations);
$states = array_values(array_unique(array_filter(array_column($sites, 'state'))));
sort($states);
$districts = array_values(array_unique(array_filter(array_column($sites, 'district'))));
sort($districts);
$mediaTypes = array_values(array_unique(array_filter(array_column($sites, 'type'))));
sort($mediaTypes);
$illuminations = array_values(array_unique(array_filter(array_column($sites, 'light_type'))));
sort($illuminations);
$sizes = array_values(array_unique(array_filter(array_map(function($s) {
    return (!empty($s['width']) && !empty($s['height'])) ? $s['width'] . 'x' . $s['height'] : '';
}, $sites))));
sort($sizes);

$activeEntityId = $_SESSION['active_entity_id'] ?? null;

// Fetch PO History
$poSql = "SELECT po.*,
                 (SELECT MIN(start_date) FROM po_items WHERE po_id = po.id) as item_start_date,
                 (SELECT MAX(end_date) FROM po_items WHERE po_id = po.id) as item_end_date
          FROM purchase_orders po 
          WHERE po.vendor_id = ?" . ($activeEntityId ? " AND po.entity_id = ?" : "") . " ORDER BY po.id DESC";
$poParams = [$id];
if ($activeEntityId) $poParams[] = $activeEntityId;
$posStmt = $pdo->prepare($poSql);
$posStmt->execute($poParams);
$pos = $posStmt->fetchAll();

// Fetch Payment Summary
$paySql = "SELECT SUM(amount) FROM payments WHERE partner_id = ? AND type = 'payable'" . ($activeEntityId ? " AND entity_id = ?" : "");
$payParams = [$id];
if ($activeEntityId) $payParams[] = $activeEntityId;
$payments = $pdo->prepare($paySql);
$payments->execute($payParams);
$totalPaid = $payments->fetchColumn() ?: 0;

$totalPOSql = "SELECT SUM(COALESCE(NULLIF(total_amount, 0), po_amount + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0))) FROM purchase_orders WHERE vendor_id = ?" . ($activeEntityId ? " AND entity_id = ?" : "");
$totalPOParams = [$id];
if ($activeEntityId) $totalPOParams[] = $activeEntityId;
$totalPO = $pdo->prepare($totalPOSql);
$totalPO->execute($totalPOParams);
$totalLiability = $totalPO->fetchColumn() ?: 0;
?>

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 1.5rem; align-items: start;">
    <!-- Sidebar Profile -->
    <div>
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--primary); font-size: 2rem;">
                    <i class="fas fa-truck"></i>
                </div>
                <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($vendor['name']); ?></h2>
                <span class="badge" style="background: #dcfce7; color: #166534; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">Active Partner</span>
            </div>
            
            <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block; font-size: 0.75rem; font-weight: 600;">Contact Person</small>
                    <strong><?php echo htmlspecialchars($vendor['contact_person'] ?: 'N/A'); ?></strong>
                </div>
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block; font-size: 0.75rem; font-weight: 600;">Email / Phone</small>
                    <div><?php echo htmlspecialchars($vendor['email'] ?: 'N/A'); ?></div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($vendor['phone'] ?: 'N/A'); ?></div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block; font-size: 0.75rem; font-weight: 600;">GSTIN / PAN</small>
                    <code><?php echo htmlspecialchars($vendor['gstin'] ?: 'N/A'); ?></code><br>
                    <code><?php echo htmlspecialchars($vendor['pan'] ?: 'N/A'); ?></code>
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <small style="color: var(--secondary); display: block; font-size: 0.75rem; font-weight: 600;">Payment Terms</small>
                    <strong><?php echo htmlspecialchars($vendor['payment_terms'] ?: 'N/A'); ?></strong>
                </div>
            </div>
        </div>
        
        <div class="card" style="background: var(--primary); color: white;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem; font-weight: 700;"><i class="fas fa-wallet" style="margin-right: 0.5rem;"></i> Financial Summary</h3>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem;">
                <span>Total POs</span>
                <strong><?php echo formatCurrency($totalLiability); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem;">
                <span>Total Paid</span>
                <strong><?php echo formatCurrency($totalPaid); ?></strong>
            </div>
            <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 0.75rem; margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <span>Outstanding</span>
                <strong style="font-size: 1.25rem; font-weight: 800;"><?php echo formatCurrency($totalLiability - $totalPaid); ?></strong>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div>
        <div class="card" style="padding: 0;">
            <div style="display: flex; border-bottom: 1px solid #f1f5f9; background: #fafafa; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <button class="tab-btn active" onclick="showTab('sites-tab')">
                    <i class="fas fa-map-marker-alt" style="margin-right: 0.4rem;"></i> Owned Sites (<span id="vendorOwnedSitesCountBadge"><?php echo count($sites); ?></span>)
                </button>
                <button class="tab-btn" onclick="showTab('pos-tab')">
                    <i class="fas fa-file-invoice" style="margin-right: 0.4rem;"></i> Purchase Orders (<?php echo count($pos); ?>)
                </button>
            </div>
            
            <div id="sites-tab" class="tab-content" style="padding: 1.25rem;">
                <!-- INVENTORY FILTERS BAR -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.1rem 1.25rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.6rem; flex-wrap: wrap; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; color: #0d9488; font-weight: 800; font-size: 0.85rem; letter-spacing: 0.5px;">
                                <i class="fas fa-filter"></i> FILTERS
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.8rem; font-weight: 700; color: #475569;">
                                <span style="text-transform: uppercase; font-size: 0.75rem;">Availability:</span>
                                <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; color: #0f172a;">
                                    <input type="radio" name="vendor_site_availability" value="available" onchange="filterVendorSites()" style="accent-color: #0d9488; cursor: pointer;"> Available
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; color: #0f172a;">
                                    <input type="radio" name="vendor_site_availability" value="all" checked onchange="filterVendorSites()" style="accent-color: #0d9488; cursor: pointer;"> All
                                </label>
                            </div>
                        </div>

                        <div>
                            <button type="button" onclick="resetVendorSiteFilters()" style="background: none; border: none; color: #ef4444; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; text-transform: uppercase;">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr; gap: 0.6rem; align-items: center;">
                        <div style="position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem;"></i>
                            <input type="text" id="vfilter-search" placeholder="Search by name, code, city..." oninput="filterVendorSites()" style="width: 100%; height: 32px; padding: 0 0.5rem 0 30px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.8rem; box-sizing: border-box; background: #ffffff;">
                        </div>
                        <div>
                            <select id="vfilter-media" onchange="filterVendorSites()" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.78rem; padding: 0 6px; background: #ffffff; color: #1e293b; box-sizing: border-box;">
                                <option value="">All Media</option>
                                <?php foreach($mediaTypes as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select id="vfilter-district" onchange="filterVendorSites()" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.78rem; padding: 0 6px; background: #ffffff; color: #1e293b; box-sizing: border-box;">
                                <option value="">All Districts</option>
                                <?php foreach($districts as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select id="vfilter-state" onchange="filterVendorSites()" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.78rem; padding: 0 6px; background: #ffffff; color: #1e293b; box-sizing: border-box;">
                                <option value="">All States</option>
                                <?php foreach($states as $st): ?>
                                    <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select id="vfilter-city" onchange="filterVendorSites()" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.78rem; padding: 0 6px; background: #ffffff; color: #1e293b; box-sizing: border-box;">
                                <option value="">All Cities</option>
                                <?php foreach($cities as $ct): ?>
                                    <option value="<?php echo htmlspecialchars($ct); ?>"><?php echo htmlspecialchars($ct); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select id="vfilter-location" onchange="filterVendorSites()" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.78rem; padding: 0 6px; background: #ffffff; color: #1e293b; box-sizing: border-box;">
                                <option value="">All Locations</option>
                                <?php foreach($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select id="vfilter-light" onchange="filterVendorSites()" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.78rem; padding: 0 6px; background: #ffffff; color: #1e293b; box-sizing: border-box;">
                                <option value="">All Lights</option>
                                <?php foreach($illuminations as $il): ?>
                                    <option value="<?php echo htmlspecialchars($il); ?>"><?php echo htmlspecialchars($il); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select id="vfilter-size" onchange="filterVendorSites()" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.78rem; padding: 0 6px; background: #ffffff; color: #1e293b; box-sizing: border-box;">
                                <option value="">All Sizes</option>
                                <?php foreach($sizes as $sz): ?>
                                    <option value="<?php echo htmlspecialchars($sz); ?>"><?php echo htmlspecialchars($sz); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;">
                    <button type="button" class="btn" onclick="sendSelectedSitesViaEmail()" style="background: white; border: 1px solid #ea4335; color: #ea4335; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 1px 3px rgba(234,67,53,0.12); transition: all 0.2s;" onmouseover="this.style.background='#ea4335'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#ea4335';" title="Send stamped photos directly to vendor via SMTP Email">
                        <i class="fas fa-paper-plane" style="color: inherit;"></i> Send via Email (SMTP)
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="downloadSelectedPhotosZip(true)" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; font-size: 0.85rem;">
                        <i class="fas fa-id-card"></i> Download Stamped JPEGs (ZIP)
                    </button>
                    <button type="button" class="btn" onclick="shareWithImagesWebShare()" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; padding: 0.5rem 1.15rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 2px 6px rgba(37,211,102,0.3);" title="Share selected sites as stamped JPEGs directly to WhatsApp / OS">
                        <i class="fab fa-whatsapp" style="font-size: 1.05rem;"></i> Share Stamped JPEGs (WhatsApp / OS)
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table responsive-table" id="vendorSitesTable" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="width: 45px; text-align: center; padding: 0.9rem 0.5rem;">
                                    <input type="checkbox" id="selectAllSites" onchange="toggleSelectAllSites(this)" style="cursor: pointer; width: 18px; height: 18px; accent-color: var(--primary);" title="Select All">
                                </th>
                                <th style="width: 220px; padding: 0.9rem 0.75rem;">Preview</th>
                                <th style="width: 24%; padding: 0.9rem 0.75rem;">City / Code</th>
                                <th style="width: 44%; padding: 0.9rem 0.75rem;">Asset Details</th>
                                <th style="width: 18%; text-align: right; padding: 0.9rem 1.25rem;">Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sn = 1;
                            foreach ($sites as $s): 
                            ?>
                                <tr class="vendor-site-row"
                                    data-code="<?php echo strtolower(htmlspecialchars($s['site_code'] ?? '')); ?>"
                                    data-name="<?php echo strtolower(htmlspecialchars($s['name'] ?? '')); ?>"
                                    data-city="<?php echo htmlspecialchars($s['city'] ?? ''); ?>"
                                    data-district="<?php echo htmlspecialchars($s['district'] ?? ''); ?>"
                                    data-state="<?php echo htmlspecialchars($s['state'] ?? ''); ?>"
                                    data-location="<?php echo htmlspecialchars($s['location'] ?? ''); ?>"
                                    data-area="<?php echo strtolower(htmlspecialchars($s['area'] ?? '')); ?>"
                                    data-type="<?php echo htmlspecialchars($s['type'] ?? ''); ?>"
                                    data-light="<?php echo htmlspecialchars($s['light_type'] ?? ''); ?>"
                                    data-size="<?php echo (!empty($s['width']) && !empty($s['height'])) ? ($s['width'] . 'x' . $s['height']) : ''; ?>"
                                    data-status="<?php echo htmlspecialchars($s['status'] ?? 'available'); ?>"
                                    data-occupied="<?php echo !empty($s['current_campaign']) ? '1' : '0'; ?>"
                                    style="border-bottom: 1px solid #f1f5f9;">
                                    <td data-label="Select" style="text-align: center; vertical-align: middle; padding: 1.1rem 0.5rem;">
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
                                                    'owner_type' => $s['owner_type'] ?? 'TA',
                                                    'vendor_name' => $s['vendor_name'] ?? ($vendor['name'] ?? ''),
                                                    'width' => $s['width'],
                                                    'height' => $s['height'],
                                                    'sqft' => $s['sqft'],
                                                    'card_rate' => $s['card_rate'] ?? 0,
                                                    'photo' => !empty($s['thumbnail_photo']) ? $s['thumbnail_photo'] : ''
                                                ]), ENT_QUOTES, 'UTF-8'); ?>'
                                                onchange="updateSelectionBar()" style="cursor: pointer; width: 18px; height: 18px; accent-color: var(--primary);">
                                            <span class="vendor-site-sn" style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;"><?php echo $sn++; ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Preview" style="vertical-align: middle; padding: 1.1rem 0.75rem;">
                                        <?php if (!empty($s['thumbnail_photo'])): ?>
                                            <div style="width: 190px; height: 120px; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.08); background: #000;">
                                                <img src="../../uploads/sites/<?php echo htmlspecialchars($s['thumbnail_photo']); ?>"
                                                    style="width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: transform 0.25s;"
                                                    onmouseover="this.style.transform='scale(1.05)'"
                                                    onmouseout="this.style.transform='scale(1)'"
                                                    onclick="viewPhotos(<?php echo $s['id']; ?>, '<?php echo addslashes($s['light_type'] ?? ''); ?>')"
                                                    title="Click to view full photo">
                                            </div>
                                        <?php else: ?>
                                            <div style="width: 190px; height: 120px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8;">
                                                <i class="fas fa-image" style="font-size: 2rem; margin-bottom: 4px;"></i>
                                                <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">No Photo</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="City / Code" style="vertical-align: middle; padding: 1.1rem 0.75rem;">
                                        <div style="font-weight: 800; font-size: 1.1rem; color: #0f172a; line-height: 1.3;"><?php echo htmlspecialchars($s['city']); ?></div>
                                        <?php if (!empty($s['area'])): ?>
                                            <div style="color: #dc2626; font-weight: 600; font-size: 0.85rem; margin-top: 2px;"><?php echo htmlspecialchars($s['area']); ?></div>
                                        <?php endif; ?>
                                        <div style="margin-top: 6px;">
                                            <span style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-family: monospace; font-size: 0.8rem; font-weight: 700; color: #334155; border: 1px solid #e2e8f0; display: inline-block;">
                                                <?php echo htmlspecialchars($s['site_code']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="Asset Details" style="vertical-align: middle; padding: 1.1rem 0.75rem;">
                                        <div style="font-weight: 800; font-size: 1.1rem; color: #0f172a; margin-bottom: 4px; line-height: 1.3;"><?php echo htmlspecialchars($s['name']); ?></div>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px; font-weight: 500;"><?php echo htmlspecialchars($s['location']); ?></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                            <span class="media-badge <?php echo strtolower($s['type'] ?? ''); ?>"><?php echo htmlspecialchars($s['type'] ?? 'OTHER'); ?></span>
                                            <span style="font-size: 0.75rem; color: #cbd5e1;">•</span>
                                            <span style="font-size: 0.8rem; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?php echo htmlspecialchars($s['light_type'] ?? 'NL'); ?></span>
                                            <span style="font-size: 0.75rem; color: #cbd5e1;">•</span>
                                            <span style="font-size: 0.85rem; color: var(--primary); font-weight: 700;">
                                                <?php echo htmlspecialchars($s['owner_type'] ?? 'TA'); ?>
                                                <?php if (($s['owner_type'] ?? '') === 'TA' && !empty($s['vendor_name']))
                                                    echo " - " . htmlspecialchars($s['vendor_name']); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($s['current_campaign'])): ?>
                                            <div style="margin-top: 8px; font-size: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #b45309; padding: 5px 12px; border-radius: 6px; font-weight: 700; display: inline-block;">
                                                <i class="fas fa-business-time"></i> Occupied by: <strong><?php echo htmlspecialchars($s['current_client'] ?? ''); ?></strong>
                                                <span style="font-weight: 600; color: #d97706; margin-left: 6px;">(Campaign: <?php echo htmlspecialchars($s['current_campaign']); ?>)</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Size" style="vertical-align: middle; text-align: right; padding: 1.1rem 1.25rem;">
                                        <div style="font-weight: 900; font-size: 1.2rem; color: #0f172a; white-space: nowrap;">
                                            <?php echo $s['width'] . "' x " . $s['height'] . "'"; ?>
                                        </div>
                                        <div style="margin-top: 5px;">
                                            <span style="display: inline-block; background: #f8fafc; border: 1px solid #e2e8f0; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; color: #64748b;">
                                                <?php echo number_format($s['sqft']); ?> SQFT
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="vendorSitesNoResultsRow" style="display: none;">
                                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 3rem;">
                                    <i class="fas fa-search" style="font-size: 1.8rem; margin-bottom: 8px; color: #cbd5e1; display: block;"></i>
                                    No sites match your selected filter criteria.
                                </td>
                            </tr>
                            <?php if (empty($sites)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 3rem;">No owned sites registered under this vendor.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="pos-tab" class="tab-content" style="padding: 1.25rem; display: none;">
                <div style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
                    <select id="poTypeFilter" onchange="filterPOsByType()" class="form-input" style="width: auto; display: inline-block;">
                        <option value="all">All PO Types</option>
                        <option value="rental">Rental</option>
                        <option value="printing">Printing</option>
                        <option value="adhoc">Adhoc</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table" id="posTable">
                        <thead>
                            <tr>
                                <th>PO #</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th style="text-align: right;">Amount (Base)</th>
                                <th style="text-align: right;">Grand Total (Inc GST)</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pos as $p): 
                                $baseAmt = floatval($p['po_amount'] ?: 0);
                                $totalAmt = floatval($p['total_amount'] ?: ($p['po_amount'] + $p['cgst_amount'] + $p['sgst_amount'] + $p['igst_amount']));
                                $poType = strtolower($p['type'] ?? 'direct');
                            ?>
                            <tr class="po-row" data-type="<?php echo htmlspecialchars($poType); ?>">
                                <td><strong><?php echo htmlspecialchars($p['po_number']); ?></strong></td>
                                <td>
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 2px 6px; text-transform: uppercase;">
                                        <?php echo htmlspecialchars($p['type'] ?? 'direct'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
                                <td style="text-align: right; font-weight: 700; color: #475569;">
                                    <?php if (canEdit('vendors')): ?>
                                    <button onclick="promptEditPoAmount(<?php echo $p['id']; ?>, <?php echo $baseAmt; ?>, '<?php echo htmlspecialchars($p['po_number']); ?>')" 
                                            style="background: #f8fafc; border: 1px dashed #cbd5e1; color: #475569; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                                        ₹<?php echo number_format($baseAmt, 2); ?> <i class="fas fa-edit" style="font-size: 0.7rem; color: #94a3b8;"></i>
                                    </button>
                                    <?php else: ?>
                                    ₹<?php echo number_format($baseAmt, 2); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 800; color: #0f172a;">
                                    ₹<?php echo number_format($totalAmt, 2); ?>
                                </td>
                                <td><span class="status-pill status-<?php echo htmlspecialchars($p['status']); ?>"><?php echo ucfirst($p['status']); ?></span></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php 
                                    $poStartDate = !empty($p['item_start_date']) ? date('d M Y', strtotime($p['item_start_date'])) : '';
                                    $poEndDate = !empty($p['item_end_date']) ? date('d M Y', strtotime($p['item_end_date'])) : '';
                                    $poCreatedDate = date('d M Y', strtotime($p['created_at']));
                                    ?>
                                    <button type="button" onclick="sendPoEmail(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['po_number']); ?>', '<?php echo number_format($totalAmt, 2); ?>', '<?php echo $poCreatedDate; ?>', '<?php echo $poStartDate; ?>', '<?php echo $poEndDate; ?>')" class="btn-icon" style="color: #ea4335; border: none; background: none; cursor: pointer; padding: 4px;" title="Send PO via Email (SMTP)">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <button type="button" onclick="sendPoWhatsApp(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['po_number']); ?>', '<?php echo number_format($totalAmt, 2); ?>', '<?php echo $poCreatedDate; ?>', '<?php echo $poStartDate; ?>', '<?php echo $poEndDate; ?>')" class="btn-icon" style="color: #25D366; border: none; background: none; cursor: pointer; padding: 4px;" title="Send PO via WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </button>
                                    <a href="../financials/po_view.php?id=<?php echo $p['id']; ?>" class="btn-icon" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="../operations/generate_po.php?po_id=<?php echo $p['id']; ?>" target="_blank" class="btn-icon" style="color: var(--primary);" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pos)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 2rem;">No purchase orders found for this vendor.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Selection Bar -->
<div id="selectionFloatingBar" style="display: none; position: fixed; bottom: 25px; left: 50%; transform: translateX(-50%); background: #0f172a; color: white; padding: 0.75rem 1.5rem; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.35); z-index: 2000; align-items: center; gap: 1rem; border: 1px solid rgba(255,255,255,0.15); backdrop-filter: blur(10px); animation: fadeInUp 0.3s ease;">
    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem;">
        <span id="selectedCountBadge" style="background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800;">0</span>
        <span>Sites Selected</span>
    </div>
    
    <div style="height: 24px; width: 1px; background: rgba(255,255,255,0.2);"></div>
    
    <button type="button" onclick="sendSelectedSitesViaEmail()" style="background: #ea4335; color: white; border: none; padding: 0.5rem 1.15rem; border-radius: 30px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(234,67,53,0.35);" onmouseover="this.style.background='#dc2626'; this.style.transform='scale(1.03)';" onmouseout="this.style.background='#ea4335'; this.style.transform='scale(1)';" title="Send stamped photos directly to vendor via SMTP Email">
        <i class="fas fa-paper-plane"></i> Send via Email (SMTP)
    </button>

    <button type="button" onclick="downloadSelectedPhotosZip(true)" style="background: #0284c7; color: white; border: none; padding: 0.5rem 1.15rem; border-radius: 30px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(2,132,199,0.35);" onmouseover="this.style.background='#0369a1'; this.style.transform='scale(1.03)';" onmouseout="this.style.background='#0284c7'; this.style.transform='scale(1)';">
        <i class="fas fa-id-card"></i> Download Stamped JPEGs (ZIP)
    </button>

    <button type="button" onclick="shareWithImagesWebShare()" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; padding: 0.5rem 1.25rem; border-radius: 30px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(37,211,102,0.35);" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" title="Share selected sites as stamped JPEGs directly to WhatsApp / OS">
        <i class="fab fa-whatsapp" style="font-size: 1.1rem;"></i> Share Stamped JPEGs (WhatsApp / OS)
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
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">Share Vendor Sites via WhatsApp</h2>
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
                <button type="button" onclick="sendSelectedSitesViaEmail()" class="btn btn-secondary" style="background: white; color: #ea4335; border: 1px solid #ea4335; font-weight: 700; font-size: 0.82rem;" title="Send stamped photos directly to vendor via SMTP Email">
                    <i class="fas fa-paper-plane"></i> Send via Email (SMTP)
                </button>
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

<style>
.tab-btn { background: none; border: none; padding: 1rem 1.5rem; font-weight: 600; color: var(--secondary); cursor: pointer; border-bottom: 2px solid transparent; }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: #ffffff; }
.tab-content { animation: fadeIn 0.3s ease; }

.media-badge {
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    background: #f1f5f9;
    color: #475569;
}
.media-badge.billboard { background: #dcfce7; color: #166534; }
.media-badge.unipole { background: #e0f2fe; color: #0369a1; }
.media-badge.bqs { background: #fef9c3; color: #854d0e; }
.media-badge.dcp { background: #fdf2f8; color: #9d174d; }

.status-pill.available { background: #dcfce7; color: #166534; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.status-pill.booked { background: #fee2e2; color: #991b1b; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.status-pill.occupied { background: #ffedd5; color: #c2410c; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }

/* WhatsApp Modal Styling */
#whatsappShareModal.modal {
    display: none;
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.75);
    z-index: 4000;
    overflow-y: auto;
    backdrop-filter: blur(6px);
    padding: 30px 15px;
    box-sizing: border-box;
}
#whatsappShareModal .modal-content {
    background: white;
    margin: 0 auto;
    max-width: 880px;
    width: 100%;
    border-radius: 16px;
    padding: 1.75rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
    box-sizing: border-box;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
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
.lb-prev { left: 40px; }
.lb-next { right: 40px; }
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
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<iframe id="po-pdf-render-iframe" style="position: fixed; left: -9999px; top: 0; width: 794px; height: 1200px; border: none; opacity: 0; pointer-events: none; z-index: -1;"></iframe>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    event.currentTarget.classList.add('active');
}

function filterPOsByType() {
    const filterValue = document.getElementById('poTypeFilter').value.toLowerCase();
    const rows = document.querySelectorAll('.po-row');
    
    rows.forEach(row => {
        const type = row.getAttribute('data-type');
        if (filterValue === 'all' || type === filterValue) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function promptEditPoAmount(poId, currentBase, poNumber) {
    Swal.fire({
        title: 'Edit PO Amount',
        html: `
            <div style="text-align: left;">
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
                    Updating amount for <strong>${poNumber}</strong>.<br>
                    GST (9% CGST + 9% SGST) will be recalculated automatically.
                </p>
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">NEW BASE AMOUNT (₹)</label>
                <input id="new_po_amount" type="number" step="0.01" class="swal2-input" value="${currentBase}" style="margin: 0; width: 100%; box-sizing: border-box;">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Update PO',
        focusConfirm: false,
        preConfirm: () => {
            const amount = document.getElementById('new_po_amount').value;
            if (!amount || amount <= 0) {
                Swal.showValidationMessage('Please enter a valid amount');
                return false;
            }
            
            const formData = new FormData();
            formData.append('po_id', poId);
            formData.append('amount', amount);
            
            return fetch('../../ajax/update_po_amount.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    throw new Error(res.message || 'Failed to update PO');
                }
                return res;
            })
            .catch(err => {
                Swal.showValidationMessage(err.message);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: 'PO amount and taxes recalculated successfully.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Helper function to render official Purchase Order to PDF Blob
async function generatePoPdfBlob(poId) {
    try {
        const poResp = await fetch(`../../modules/operations/generate_po.php?po_id=${poId}`);
        if (!poResp.ok) return null;
        const poHtml = await poResp.text();

        let iframe = document.getElementById('po-pdf-render-iframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'po-pdf-render-iframe';
            iframe.style.cssText = 'position: fixed; left: -9999px; top: 0; width: 794px; height: 1200px; border: none; opacity: 0; pointer-events: none; z-index: -1;';
            document.body.appendChild(iframe);
        }

        const iframeDoc = iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(poHtml);
        iframeDoc.close();

        // Inject overrides for perfect A4 rendering
        const styleElem = iframeDoc.createElement('style');
        styleElem.innerHTML = `
            * { box-sizing: border-box !important; }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                font-family: Arial, sans-serif !important;
                width: 794px !important;
            }
            .po-wrapper {
                width: 794px !important;
                max-width: 794px !important;
                min-height: auto !important;
                margin: 0 auto !important;
                padding: 6mm 8mm !important;
                box-shadow: none !important;
                border: none !important;
                box-sizing: border-box !important;
                background: #ffffff !important;
            }
            .btn-print, .no-print { display: none !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            img { max-width: 100% !important; }
        `;
        iframeDoc.head.appendChild(styleElem);

        // Wait for all images inside iframe to load (letterhead logo, signature, stamps)
        const images = Array.from(iframeDoc.images);
        if (images.length > 0) {
            await Promise.all(images.map(img => {
                if (img.complete && img.naturalHeight !== 0) return Promise.resolve();
                return new Promise(resolve => {
                    img.onload = resolve;
                    img.onerror = resolve;
                    setTimeout(resolve, 2000);
                });
            }));
        }

        // Stabilization pause
        await new Promise(resolve => setTimeout(resolve, 300));

        const targetElem = iframeDoc.querySelector('.po-wrapper') || iframeDoc.body;
        const canvas = await html2canvas(targetElem, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: 794,
            windowWidth: 794
        });

        const JsPdfConstructor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : ((typeof jsPDF !== 'undefined') ? jsPDF : (window.jsPDF || null));
        if (!JsPdfConstructor) {
            console.error('jsPDF not loaded');
            return null;
        }

        const pdf = new JsPdfConstructor({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4',
            compress: true
        });

        const pageWidth = 210; // mm
        const pageHeight = 297; // mm
        const imgWidth = pageWidth;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const imgData = canvas.toDataURL('image/jpeg', 0.95);

        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
        heightLeft -= pageHeight;

        while (heightLeft > 5) {
            position = heightLeft - imgHeight;
            pdf.addPage('a4', 'portrait');
            pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
            heightLeft -= pageHeight;
        }

        return pdf.output('blob');
    } catch (e) {
        console.error('Error generating PO PDF blob:', e);
        return null;
    }
}

// Helper function to render official Purchase Order to Base64 PDF
async function generatePoPdfBase64(poId) {
    const blob = await generatePoPdfBlob(poId);
    if (!blob) return null;
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            const dataUrl = reader.result;
            resolve(dataUrl.split(',')[1] || null);
        };
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(blob);
    });
}

// Send Single PO via Email (SMTP)
async function sendPoEmail(poId, poNumber, totalAmt, poDate, poStartDate = '', poEndDate = '') {
    const vendorEmail = '<?php echo addslashes($vendor['email'] ?? ''); ?>';
    const vendorName = '<?php echo addslashes($vendor['name'] ?? 'Vendor'); ?>';
    const defaultSubject = `Purchase Order: ${poNumber} - Sudha Creative`;
    
    const periodText = (poStartDate && poEndDate) ? `for the period from **${poStartDate} to ${poEndDate}** ` : '';
    const defaultTemplateText = `Dear Sir/Madam,

Please be informed that the Purchase Order **${poNumber}** has been issued ${periodText}at a rate of **₹${totalAmt}**.

Please find the official Purchase Order PDF and booking details attached for your reference and records.

Thank you.

Regards,

Sudha Creative`;

    const { value: formValues } = await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#ea4335;"><i class="fas fa-paper-plane"></i> Send Purchase Order via Email</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Recipient Email Address (Vendor) <span style="color:#ef4444;">*</span></label>
                    <input id="smtp_po_recipient_email" type="text" value="${vendorEmail}" placeholder="vendor@example.com" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Email Subject</label>
                    <input id="smtp_po_subject" type="text" value="${defaultSubject}" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Message Body</label>
                    <textarea id="smtp_po_message" style="width: 100%; box-sizing: border-box; height: 130px; font-size: 0.86rem; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; resize: vertical; line-height: 1.45; font-family: inherit;">${defaultTemplateText}</textarea>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; color: #166534; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.3rem;"></i>
                    <span>Official <b>${poNumber}.pdf</b> document will be rendered and attached directly to the email.</span>
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
            const emailVal = document.getElementById('smtp_po_recipient_email').value.trim();
            const subjectVal = document.getElementById('smtp_po_subject').value.trim();
            const msgVal = document.getElementById('smtp_po_message').value.trim();
            if (!emailVal) {
                Swal.showValidationMessage('Please enter a valid recipient email address.');
                return false;
            }
            return { email: emailVal, subject: subjectVal, message: msgVal };
        }
    });

    if (formValues) {
        Swal.fire({
            title: 'Preparing & Sending PO...',
            html: `
                <div style="padding: 10px 0; text-align: center;">
                    <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #0d9488; margin-bottom: 12px;"></i><br>
                    Rendering official <b>${poNumber}.pdf</b> & dispatching via SMTP to <b>${formValues.email}</b>...
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            // Generate official PO PDF as base64
            const poPdfBase64 = await generatePoPdfBase64(poId);

            const formData = new FormData();
            formData.append('po_id', poId);
            formData.append('email', formValues.email);
            formData.append('subject', formValues.subject);
            formData.append('message', formValues.message);
            if (poPdfBase64) {
                const cleanPoNum = poNumber.replace(/[\/\\?%*:|"<>]/g, '_');
                formData.append('po_pdf_base64', poPdfBase64);
                formData.append('po_pdf_name', `Purchase_Order_${cleanPoNum}.pdf`);
            }

            const resp = await fetch('../../ajax/send_po_email.php', {
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
                    title: 'PO Sent Successfully!',
                    html: `<b>${res.message || 'Purchase Order sent successfully!'}</b><br><br><span style="color:#059669;font-size:0.88rem;"><i class="fas fa-paperclip"></i> Official PDF Attached (${poNumber}.pdf)</span><br><span style="color:#64748b;font-size:0.82rem;">Sender: sudhacreativemalda@gmail.com</span>`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Sending Failed',
                    text: res.message || 'Could not send PO email via SMTP server.'
                });
            }
        } catch (err) {
            Swal.fire('SMTP Error', err.message || 'Network error occurred while communicating with SMTP server.', 'error');
        }
    } else if (Swal.getDismissReason() === Swal.DismissReason.cancel) {
        const emailInput = document.getElementById('smtp_po_recipient_email')?.value.trim() || vendorEmail;
        const subjectInput = document.getElementById('smtp_po_subject')?.value.trim() || defaultSubject;
        const msgInput = document.getElementById('smtp_po_message')?.value.trim() || defaultTemplateText;
        const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailInput)}&su=${encodeURIComponent(subjectInput)}&body=${encodeURIComponent(msgInput)}`;
        window.open(gmailUrl, '_blank');
    }
}

// Send Single PO via WhatsApp with Attached Official PDF
async function sendPoWhatsApp(poId, poNumber, totalAmt, poDate, poStartDate = '', poEndDate = '') {
    const vendorPhone = '<?php echo addslashes($vendor['phone'] ?? ''); ?>';
    const cleanPoNum = poNumber.replace(/[\/\\?%*:|"<>]/g, '_');
    const pdfFileName = `Purchase_Order_${cleanPoNum}.pdf`;

    await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#25D366;"><i class="fab fa-whatsapp"></i> Share PO PDF via WhatsApp</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Vendor WhatsApp Number</label>
                    <div style="position: relative;">
                        <i class="fab fa-whatsapp" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #25D366; font-size: 1.1rem;"></i>
                        <input id="wa_po_phone" type="text" value="${vendorPhone}" placeholder="e.g. 9876543210 (or leave blank for chat selection)" style="width: 100%; box-sizing: border-box; font-size: 0.95rem; font-weight: 600; padding: 0.6rem 0.75rem 0.6rem 2.4rem; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none;">
                    </div>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.8rem; flex-shrink: 0;"></i>
                    <div style="flex: 1; overflow: hidden;">
                        <div style="font-weight: 800; color: #0f172a; font-size: 0.9rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${pdfFileName}</div>
                        <div style="font-size: 0.75rem; color: #15803d; margin-top: 2px;">PO #${poNumber} ${totalAmt ? '• ₹' + totalAmt : ''} • Ready for WhatsApp</div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                    <button type="button" id="btnShareWaWeb" style="width: 100%; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.92rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(37,211,102,0.35); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                        <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Open & Share in WhatsApp Web
                    </button>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" id="btnShareWaApp" style="flex: 1; background: #0284c7; color: white; border: none; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fas fa-share-alt"></i> WhatsApp App / Mobile
                        </button>
                        <button type="button" id="btnDownloadPoPdf" style="flex: 1; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fas fa-download"></i> Download PDF Only
                        </button>
                    </div>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times"></i> Close',
        cancelButtonColor: '#94a3b8',
        showCloseButton: true,
        didOpen: () => {
            document.getElementById('btnShareWaWeb')?.addEventListener('click', () => {
                executePoShare('web');
            });
            document.getElementById('btnShareWaApp')?.addEventListener('click', () => {
                executePoShare('app');
            });
            document.getElementById('btnDownloadPoPdf')?.addEventListener('click', () => {
                executePoShare('download');
            });
        }
    });

    async function executePoShare(mode) {
        const phoneInput = document.getElementById('wa_po_phone')?.value.trim().replace(/[^0-9]/g, '') || '';
        let phoneFormatted = phoneInput;
        if (phoneFormatted && phoneFormatted.length === 10) phoneFormatted = '91' + phoneFormatted;

        if (mode === 'download') {
            Swal.fire({
                title: 'Generating PDF...',
                text: 'Please wait...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });
            const pdfBlob = await generatePoPdfBlob(poId);
            if (pdfBlob) {
                const blobUrl = URL.createObjectURL(pdfBlob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = pdfFileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);
                Swal.fire({
                    icon: 'success',
                    title: 'PO PDF Downloaded!',
                    text: `${pdfFileName} has been saved to your downloads.`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire('Error', 'Failed to generate PDF.', 'error');
            }
            return;
        }

        Swal.fire({
            title: 'Preparing PO PDF...',
            html: `
                <div style="padding: 10px 0; text-align: center;">
                    <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #25D366; margin-bottom: 12px;"></i><br>
                    Rendering official <b>${pdfFileName}</b> for WhatsApp...
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const pdfBlob = await generatePoPdfBlob(poId);
            if (!pdfBlob) throw new Error('Could not generate PO PDF document.');

            const pdfFile = new File([pdfBlob], pdfFileName, { type: 'application/pdf' });

            if (mode === 'app') {
                if (navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
                    Swal.close();
                    try {
                        await navigator.share({
                            files: [pdfFile]
                        });
                    } catch(shareErr) {
                        if (shareErr.name !== 'AbortError') {
                            triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                        }
                    }
                } else {
                    triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                }
            } else {
                // WhatsApp Web mode
                triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'web');
            }
        } catch(err) {
            Swal.fire('Error', err.message || 'Failed to prepare PO PDF for WhatsApp.', 'error');
        }
    }
}

function triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, target = 'web') {
    // 1. Download PDF to user's device
    const blobUrl = URL.createObjectURL(pdfBlob);
    const a = document.createElement('a');
    a.href = blobUrl;
    a.download = pdfFileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(blobUrl);

    // 2. Open WhatsApp Web or Desktop App (no prefilled message)
    let waUrl = '';
    if (target === 'app') {
        waUrl = phoneFormatted ? `whatsapp://send?phone=${phoneFormatted}` : `whatsapp://send`;
    } else {
        waUrl = phoneFormatted 
            ? `https://web.whatsapp.com/send?phone=${phoneFormatted}`
            : `https://web.whatsapp.com/`;
    }
    window.open(waUrl, '_blank');

    Swal.fire({
        icon: 'success',
        title: 'PDF Downloaded & WhatsApp Web Opened!',
        html: `
            <div style="text-align: left; font-size: 0.9rem; line-height: 1.5;">
                <p style="margin: 0 0 8px 0;">📄 <b>${pdfFileName}</b> has been downloaded to your computer.</p>
                <p style="margin: 0 0 8px 0;">💬 <b>${target === 'app' ? 'WhatsApp App' : 'WhatsApp Web'}</b> has been opened in a new window/tab.</p>
                <div style="color: #065f46; font-weight: 600; background: #ecfdf5; padding: 10px 14px; border-radius: 8px; border: 1px solid #a7f3d0; margin-top: 10px;">
                    👉 Simply drag & drop the downloaded PDF into WhatsApp chat or click 📎 to attach and send!
                </div>
            </div>
        `,
        confirmButtonText: 'Got It!',
        confirmButtonColor: '#25D366'
    });
}

// Send via Email (SMTP)
async function sendSelectedSitesViaEmail() {
    const selected = getSelectedSitesData();
    if (!selected.length) {
        Swal.fire('Warning', 'Please select at least one site from the list first.', 'warning');
        return;
    }

    const validSites = selected.filter(s => s && s.id);
    if (!validSites.length) {
        Swal.fire('No Valid Sites', 'None of the selected items have valid site records.', 'warning');
        return;
    }

    const initialEmail = '<?php echo addslashes($vendor['email'] ?? ''); ?>' || (validSites.find(s => s.vendor_email)?.vendor_email || '');
    const vendorName = '<?php echo addslashes($vendor['name'] ?? 'Vendor'); ?>';
    const defaultSubject = `Booking Confirmation & Stamped Site Photos - ${vendorName} (${validSites.length} Site${validSites.length > 1 ? 's' : ''})`;

    let totalAmount = 0;
    validSites.forEach(s => {
        if (s.card_rate && !isNaN(s.card_rate)) totalAmount += Number(s.card_rate);
    });
    const amountText = totalAmount > 0 ? totalAmount.toLocaleString('en-IN') : '{{Amount}}';

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
        const emailInput = document.getElementById('smtp_inv_recipient_email')?.value.trim() || initialEmail;
        const subjectInput = document.getElementById('smtp_inv_subject')?.value.trim() || defaultSubject;
        const msgInput = document.getElementById('smtp_inv_message')?.value.trim() || defaultTemplateText;
        const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailInput)}&su=${encodeURIComponent(subjectInput)}&body=${encodeURIComponent(msgInput)}`;
        window.open(gmailUrl, '_blank');
    }
}

// Client-Side Live Filter Logic for Vendor Owned Sites
function filterVendorSites() {
    const search = document.getElementById('vfilter-search') ? document.getElementById('vfilter-search').value.toLowerCase().trim() : '';
    const media = document.getElementById('vfilter-media') ? document.getElementById('vfilter-media').value : '';
    const district = document.getElementById('vfilter-district') ? document.getElementById('vfilter-district').value : '';
    const state = document.getElementById('vfilter-state') ? document.getElementById('vfilter-state').value : '';
    const city = document.getElementById('vfilter-city') ? document.getElementById('vfilter-city').value : '';
    const loc = document.getElementById('vfilter-location') ? document.getElementById('vfilter-location').value : '';
    const light = document.getElementById('vfilter-light') ? document.getElementById('vfilter-light').value : '';
    const size = document.getElementById('vfilter-size') ? document.getElementById('vfilter-size').value : '';
    const availRadio = document.querySelector('input[name="vendor_site_availability"]:checked');
    const availability = availRadio ? availRadio.value : 'all';

    const rows = document.querySelectorAll('#vendorSitesTable tbody tr.vendor-site-row');
    let visibleCount = 0;
    let sn = 1;

    rows.forEach(row => {
        let show = true;

        if (availability === 'available' && (row.dataset.status !== 'available' || row.dataset.occupied === '1')) {
            show = false;
        }

        if (media && row.dataset.type !== media) show = false;
        if (district && row.dataset.district !== district) show = false;
        if (state && row.dataset.state !== state) show = false;
        if (city && row.dataset.city !== city) show = false;
        if (loc && row.dataset.location !== loc) show = false;
        if (light && row.dataset.light !== light) show = false;
        if (size && row.dataset.size !== size) show = false;

        if (search) {
            const code = row.dataset.code || '';
            const name = row.dataset.name || '';
            const rCity = (row.dataset.city || '').toLowerCase();
            const rLoc = (row.dataset.location || '').toLowerCase();
            const rArea = row.dataset.area || '';
            if (!code.includes(search) && !name.includes(search) && !rCity.includes(search) && !rLoc.includes(search) && !rArea.includes(search)) {
                show = false;
            }
        }

        if (show) {
            row.style.display = '';
            visibleCount++;
            const snSpan = row.querySelector('.vendor-site-sn');
            if (snSpan) snSpan.innerText = sn++;
        } else {
            row.style.display = 'none';
        }
    });

    const emptyRow = document.getElementById('vendorSitesNoResultsRow');
    if (emptyRow) {
        emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
    }

    const countBadge = document.getElementById('vendorOwnedSitesCountBadge');
    if (countBadge) {
        countBadge.innerText = visibleCount;
    }

    updateSelectionBar();
}

function resetVendorSiteFilters() {
    if (document.getElementById('vfilter-search')) document.getElementById('vfilter-search').value = '';
    if (document.getElementById('vfilter-media')) document.getElementById('vfilter-media').value = '';
    if (document.getElementById('vfilter-district')) document.getElementById('vfilter-district').value = '';
    if (document.getElementById('vfilter-state')) document.getElementById('vfilter-state').value = '';
    if (document.getElementById('vfilter-city')) document.getElementById('vfilter-city').value = '';
    if (document.getElementById('vfilter-location')) document.getElementById('vfilter-location').value = '';
    if (document.getElementById('vfilter-light')) document.getElementById('vfilter-light').value = '';
    if (document.getElementById('vfilter-size')) document.getElementById('vfilter-size').value = '';
    const allRadio = document.querySelector('input[name="vendor_site_availability"][value="all"]');
    if (allRadio) allRadio.checked = true;
    filterVendorSites();
}

// Multi-Selection and WhatsApp Share Logic
function toggleSelectAllSites(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.site-select-chk');
    checkboxes.forEach(chk => {
        const row = chk.closest('tr');
        if (!row || row.style.display !== 'none') {
            chk.checked = masterCheckbox.checked;
        }
    });
    updateSelectionBar();
}

function updateSelectionBar() {
    const selected = getSelectedSitesData();
    const bar = document.getElementById('selectionFloatingBar');
    const badge = document.getElementById('selectedCountBadge');
    const masterChk = document.getElementById('selectAllSites');
    
    // Count only visible checkboxes for master check status
    const visibleCheckboxes = Array.from(document.querySelectorAll('.site-select-chk')).filter(chk => {
        const row = chk.closest('tr');
        return !row || row.style.display !== 'none';
    });
    const totalVisible = visibleCheckboxes.length;
    const selectedVisible = visibleCheckboxes.filter(chk => chk.checked).length;
    
    if (badge) badge.innerText = selected.length;
    if (masterChk) {
        masterChk.checked = totalVisible > 0 && selectedVisible === totalVisible;
        masterChk.indeterminate = selectedVisible > 0 && selectedVisible < totalVisible;
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
    if (!selected.length) {
        Swal.fire('Warning', 'Please select at least one site from the list first.', 'warning');
        return;
    }
    
    const validSites = selected.filter(s => s && s.photo);
    if (validSites.length === 0) {
        openWhatsAppShareModal();
        return;
    }

    const includePricing = document.getElementById('waIncludePricing') ? document.getElementById('waIncludePricing').checked : true;
    
    Swal.fire({
        title: 'Preparing Stamped Photos...',
        html: `Generating high-resolution stamped cards for <b>${validSites.length}</b> site(s)...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    try {
        const filesArray = [];
        const batchSize = 6;
        for (let i = 0; i < validSites.length; i += batchSize) {
            const batch = validSites.slice(i, i + batchSize);
            const promises = batch.map(async (s) => {
                try {
                    const cardUrl = `../../ajax/get_site_card_jpeg.php?id=${s.id}&price=${includePricing ? 1 : 0}`;
                    const resp = await fetch(cardUrl);
                    if (resp.ok) {
                        const blob = await resp.blob();
                        const safeName = `${s.site_code || 'SITE'}_${(s.city || '').replace(/[^a-zA-Z0-9]/g, '')}_Card.jpg`;
                        return new File([blob], safeName, { type: 'image/jpeg' });
                    }
                } catch(e) {}
                return null;
            });
            const results = await Promise.all(promises);
            results.forEach(f => { if (f) filesArray.push(f); });
        }
        
        if (filesArray.length === 0) {
            throw new Error('Could not generate stamped photo cards.');
        }

        if (navigator.canShare && navigator.canShare({ files: filesArray })) {
            Swal.close();
            try {
                await navigator.share({
                    files: filesArray,
                    title: `Stamped Site Photos (${validSites.length} Sites)`
                });
            } catch(shareErr) {
                if (shareErr.name !== 'AbortError') {
                    openWhatsAppShareModal();
                }
            }
        } else {
            // Desktop / non-file share browser fallback
            Swal.fire({
                icon: 'success',
                title: 'Stamped Photos Ready!',
                html: `Prepared <strong>${filesArray.length}</strong> stamped photo cards with City, Size & Details stamped directly.<br><br>Choose how you would like to share:`,
                confirmButtonText: '<i class="fab fa-whatsapp"></i> Open WhatsApp Web & Copy Info',
                confirmButtonColor: '#25D366',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-id-card"></i> Download Stamped ZIP',
                cancelButtonColor: '#0284c7',
                showCloseButton: true
            }).then((result) => {
                if (result.isConfirmed) {
                    openWhatsAppShareModal();
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    downloadSelectedPhotosZip(true);
                }
            });
        }
    } catch(e) {
        console.error('Error generating stamped photos for share:', e);
        openWhatsAppShareModal();
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

// Lightbox Logic
let currentLbIndex = 0;
let lbImages = [];

function viewPhotos(siteId, lightType) {
    fetch(`../../ajax/get_site_images.php?id=${siteId}`)
        .then(r => r.json())
        .then(imgs => {
            if (!imgs || !imgs.length) {
                Swal.fire('Info', 'No photos available for this site.', 'info');
                return;
            }
            openLightbox(imgs, 0);
        })
        .catch(err => {
            Swal.fire('Error', 'Unable to fetch site photos.', 'error');
        });
}

function openLightbox(images, index) {
    const slider = document.getElementById('lbSlider');
    lbImages = images;
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

    const prevBtn = document.querySelector('.lb-prev');
    const nextBtn = document.querySelector('.lb-next');
    if (prevBtn) prevBtn.style.display = currentLbIndex === 0 ? 'none' : 'flex';
    if (nextBtn) nextBtn.style.display = currentLbIndex === lbImages.length - 1 ? 'none' : 'flex';
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

document.addEventListener('keydown', (e) => {
    const lb = document.getElementById('lbOverlay');
    if (lb && lb.style.display === 'flex') {
        if (e.key === 'ArrowLeft') lbPrev();
        if (e.key === 'ArrowRight') lbNext();
        if (e.key === 'Escape') closeLightbox();
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
