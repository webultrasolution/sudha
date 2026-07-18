<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('vendors', 'view');

// Handle Form Submissions
// Sync Printing PO helper function
function syncPrintingPO($pdo, $po_number, $vendor_id, $media_type, $campaign_name, $brand_name) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Fetch all sites of this PO from vendor_printing_rates
    $stmtFinal = $pdo->prepare("
        SELECT r.site_id, r.rate_per_sqft, s.name as site_name, s.site_code, s.width, s.height 
        FROM vendor_printing_rates r
        LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.po_number = ? AND r.vendor_id = ?
    ");
    $stmtFinal->execute([$po_number, $vendor_id]);
    $finalRates = $stmtFinal->fetchAll(PDO::FETCH_ASSOC);
    
    $subtotal = 0;
    $items = [];
    foreach ($finalRates as $fr) {
        $width = floatval($fr['width'] ?? 0);
        $height = floatval($fr['height'] ?? 0);
        $sqft = $width * $height;
        $cost = $sqft * floatval($fr['rate_per_sqft']);
        $subtotal += $cost;
        $items[] = [
            'site_id' => $fr['site_id'],
            'site_name' => $fr['site_name'] ?? 'Generic',
            'site_code' => $fr['site_code'] ?? '',
            'sqft' => $sqft,
            'rate' => floatval($fr['rate_per_sqft']),
            'cost' => $cost
        ];
    }
    
    // Calculate GST
    $stmtGst = $pdo->prepare("SELECT gstin, state FROM partners WHERE id = ?");
    $stmtGst->execute([$vendor_id]);
    $vendorRow = $stmtGst->fetch(PDO::FETCH_ASSOC);
    $db_vendor_gst = trim($vendorRow['gstin'] ?? '');
    $vendor_state = trim($vendorRow['state'] ?? '');
    $vendor_has_gst = vendorHasGST($db_vendor_gst);

    $cgst = 0; $sgst = 0; $igst = 0;
    if ($vendor_has_gst) {
        $isVendorInterstate = (strcasecmp($vendor_state, 'West Bengal') !== 0 && substr($db_vendor_gst, 0, 2) !== '19');
        if ($isVendorInterstate) {
            $igst = $subtotal * 0.18;
        } else {
            $cgst = $subtotal * 0.09;
            $sgst = $subtotal * 0.09;
        }
    }
    $grandTotal = $subtotal + $cgst + $sgst + $igst;

    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    $poStatus = $isAdmin ? 'approved' : 'pending';
    $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';

    // Check if purchase_orders entry exists for this po_number
    $stmtPOId = $pdo->prepare("SELECT id FROM purchase_orders WHERE po_number = ?");
    $stmtPOId->execute([$po_number]);
    $poId = $stmtPOId->fetchColumn();

    if ($poId) {
        // Update PO
        $stmtPOUpdate = $pdo->prepare("
            UPDATE purchase_orders 
            SET po_amount = ?, cgst_amount = ?, sgst_amount = ?, igst_amount = ?, total_amount = ?, status = ?, approval_status = ?, campaign_name = ?, brand_name = ?, vendor_id = ?
            WHERE id = ?
        ");
        $stmtPOUpdate->execute([
            $subtotal, $cgst, $sgst, $igst, $grandTotal,
            $poStatus, $approvalStatus,
            $campaign_name ?: 'Printing PO',
            $brand_name,
            $vendor_id,
            $poId
        ]);
    } else {
        // Insert PO
        $stmtPOInsert = $pdo->prepare("
            INSERT INTO purchase_orders (vendor_id, employee_id, campaign_name, brand_name, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, approval_status, remarks, type) 
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'printing')
        ");
        $stmtPOInsert->execute([
            $vendor_id,
            $_SESSION['user_id'] ?? 0,
            $campaign_name ?: 'Printing PO',
            $brand_name,
            $po_number,
            $subtotal, $cgst, $sgst, $igst, $grandTotal,
            $poStatus, $approvalStatus,
            "Printing PO" . ($campaign_name ? " - " . $campaign_name : ""),
        ]);
        $poId = $pdo->lastInsertId();
    }

    // Sync po_items (delete old and insert new)
    $pdo->prepare("DELETE FROM po_items WHERE po_id = ?")->execute([$poId]);

    $stmtItem = $pdo->prepare("INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost, description) VALUES (?, ?, CURDATE(), CURDATE(), 1, ?, ?, ?)");
    foreach ($items as $item) {
        $desc = "Printing: $media_type - {$item['site_code']} {$item['site_name']} ({$item['sqft']} SQFT × ₹{$item['rate']}/sqft)";
        $stmtItem->execute([$poId, $item['site_id'], $item['rate'], $item['cost'], $desc]);
    }

    // Update or insert approval request
    if (!$isAdmin) {
        $stmtARCheck = $pdo->prepare("SELECT id FROM approval_requests WHERE entity_type = 'purchase_order' AND entity_id = ?");
        $stmtARCheck->execute([$poId]);
        $arId = $stmtARCheck->fetchColumn();
        if ($arId) {
            $pdo->prepare("UPDATE approval_requests SET status = 'pending', reviewed_by = NULL, reviewed_at = NULL, remarks = NULL WHERE id = ?")
                ->execute([$arId]);
        } else {
            $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('purchase_order', ?, ?, ?, 'pending')")
                ->execute([$poId, $po_number, $_SESSION['user_id'] ?? 0]);
        }
    } else {
        // Auto-approved by admin
        $stmtARCheck = $pdo->prepare("SELECT id FROM approval_requests WHERE entity_type = 'purchase_order' AND entity_id = ?");
        $stmtARCheck->execute([$poId]);
        $arId = $stmtARCheck->fetchColumn();
        if ($arId) {
            $pdo->prepare("UPDATE approval_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), remarks = 'Auto-approved by admin' WHERE id = ?")
                ->execute([$_SESSION['user_id'] ?? 0, $arId]);
        }
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $vendor_id = intval($_POST['vendor_id']);
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);
        $campaign_name = isset($_POST['campaign_name']) ? clean($_POST['campaign_name']) : null;
        $brand_name = isset($_POST['brand_name']) ? clean($_POST['brand_name']) : null;

        if ($_POST['action'] === 'add') {
            requirePermission('vendors', 'add');
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [null];
            $individual_rates = $_POST['individual_rates'] ?? [];
            $po_number = generateSequenceNumber($pdo, 'vendor_printing_po');
            
            foreach ($site_ids as $site_id) {
                $site_id = !empty($site_id) ? intval($site_id) : null;
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                // Insert new rate with PO number to preserve historical PO data
                $insertStmt = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$vendor_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name]);
            }
            
            syncPrintingPO($pdo, $po_number, $vendor_id, $media_type, $campaign_name, $brand_name);
            
            header("Location: printing_rates.php?msg=added"); exit;
        } else {
            requirePermission('vendors', 'edit');
            
            $po_number = !empty($_POST['po_number']) ? clean($_POST['po_number']) : null;
            $rate_ids_post = isset($_POST['rate_ids']) ? $_POST['rate_ids'] : [];
            
            if (!$po_number) {
                $po_number = generateSequenceNumber($pdo, 'vendor_printing_po');
                // Assign this new PO number to legacy records first so they are grouped
                if (!empty($rate_ids_post)) {
                    $in = str_repeat('?,', count($rate_ids_post) - 1) . '?';
                    $upd_legacy = $pdo->prepare("UPDATE vendor_printing_rates SET po_number = ?, campaign_name = ?, brand_name = ? WHERE id IN ($in)");
                    $upd_legacy->execute(array_merge([$po_number, $campaign_name, $brand_name], $rate_ids_post));
                }
            }
            
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [];
            $individual_rates = $_POST['individual_rates'] ?? [];
            
            $stmt = $pdo->prepare("SELECT id, site_id FROM vendor_printing_rates WHERE po_number = ? AND vendor_id = ?");
            $stmt->execute([$po_number, $vendor_id]);
            $existing = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [id => site_id]
            $existing_site_to_id = array_flip($existing); // [site_id => id]
            
            $posted_sites = [];
            foreach ($site_ids as $site_id) {
                if (empty($site_id)) continue;
                $site_id = intval($site_id);
                $posted_sites[] = $site_id;
                
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                if (isset($existing_site_to_id[$site_id])) {
                    // Update
                    $upd = $pdo->prepare("UPDATE vendor_printing_rates SET media_type=?, rate_per_sqft=?, campaign_name=?, brand_name=? WHERE id=?");
                    $upd->execute([$media_type, $this_rate, $campaign_name, $brand_name, $existing_site_to_id[$site_id]]);
                } else {
                    // Insert
                    $meta = $pdo->prepare("SELECT client_tax_order, attachments FROM vendor_printing_rates WHERE po_number = ? AND vendor_id = ? LIMIT 1");
                    $meta->execute([$po_number, $vendor_id]);
                    $m = $meta->fetch();
                    
                    if ($m) {
                        $ins = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, client_tax_order, attachments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$vendor_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name, $m['client_tax_order'], $m['attachments']]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$vendor_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name]);
                    }
                }
            }
            
            // Delete removed
            foreach ($existing_site_to_id as $es_site => $es_id) {
                if (!in_array($es_site, $posted_sites)) {
                    $pdo->prepare("DELETE FROM vendor_printing_rates WHERE id = ?")->execute([$es_id]);
                }
            }

            // Sync campaign/brand across all rows of this PO
            $syncStmt = $pdo->prepare("UPDATE vendor_printing_rates SET campaign_name = ?, brand_name = ? WHERE po_number = ?");
            $syncStmt->execute([$campaign_name, $brand_name, $po_number]);
            
            syncPrintingPO($pdo, $po_number, $vendor_id, $media_type, $campaign_name, $brand_name);
            
            header("Location: printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
        requirePermission('vendors', 'delete');
        header('Content-Type: application/json');
        include_once __DIR__ . '/../../includes/trash_helper.php';
        
        $po_number = $_POST['po_number'] ?? '';
        $rate_ids_str = $_POST['rate_ids'] ?? '';
        
        if (!empty($po_number)) {
            $stmt = $pdo->prepare("SELECT id FROM vendor_printing_rates WHERE po_number = ?");
            $stmt->execute([$po_number]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ids)) {
                move_multiple_rows_to_trash($pdo, 'vendor_printing_rates', 'id', $ids, $_SESSION['user_id'] ?? null, "Printing PO #$po_number deleted");
            }
            
            // Move purchase_orders matching record to trash if exists
            $stmtPO = $pdo->prepare("SELECT id FROM purchase_orders WHERE po_number = ?");
            $stmtPO->execute([$po_number]);
            $poId = $stmtPO->fetchColumn();
            if ($poId) {
                // Move related po_items to trash
                $itemStmt = $pdo->prepare("SELECT id FROM po_items WHERE po_id = ?");
                $itemStmt->execute([$poId]);
                while ($item = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
                    move_row_to_trash($pdo, 'po_items', 'id', $item['id'], $_SESSION['user_id'] ?? null, 'PO deleted - item moved to trash');
                }
                move_row_to_trash($pdo, 'purchase_orders', 'id', $poId, $_SESSION['user_id'] ?? null, "Purchase order #$po_number deleted (Printing PO delete)");
            }
        } elseif (!empty($rate_ids_str)) {
            $ids = explode('||', $rate_ids_str);
            $ids = array_map('intval', $ids);
            if (!empty($ids)) {
                move_multiple_rows_to_trash($pdo, 'vendor_printing_rates', 'id', $ids, $_SESSION['user_id'] ?? null, "Printing rates deleted");
            }
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                move_multiple_rows_to_trash($pdo, 'vendor_printing_rates', 'id', [$id], $_SESSION['user_id'] ?? null, "Printing rate ID $id deleted");
            }
        }
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'printing_rates';
$pageTitle = 'Printing PO';
include_once __DIR__ . '/../../includes/header.php';

$selectedVendorId = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');

$queryWhere = "WHERE 1=1";
$params = [];
if ($selectedVendorId) {
    $queryWhere .= " AND r.vendor_id = ?";
    $params[] = $selectedVendorId;
}
if ($campaignFilter !== '') {
    $queryWhere .= " AND r.campaign_name LIKE ?";
    $params[] = '%' . $campaignFilter . '%';
}

// Fetch Rates Grouped by PO Number
$ratesStmt = $pdo->prepare("
    SELECT 
        r.po_number,
        r.vendor_id,
        v.name as vendor_name,
        GROUP_CONCAT(r.id SEPARATOR '||') as rate_ids,
        GROUP_CONCAT(COALESCE(s.name, 'Generic') SEPARATOR '||') as site_names,
        GROUP_CONCAT(COALESCE(s.site_code, '-') SEPARATOR '||') as site_codes,
        GROUP_CONCAT(COALESCE(s.width, 0) SEPARATOR '||') as widths,
        GROUP_CONCAT(COALESCE(s.height, 0) SEPARATOR '||') as heights,
        GROUP_CONCAT(r.media_type SEPARATOR '||') as media_types,
        GROUP_CONCAT(r.rate_per_sqft SEPARATOR '||') as rates,
        MIN(r.created_at) as created_at,
        SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as total_amount,
        MAX(r.attachments) as attachments,
        MAX(r.client_tax_order) as client_tax_order,
        MAX(r.campaign_name) as campaign_name,
        MAX(r.brand_name) as brand_name,
        MAX(po.approval_status) as approval_status,
        MAX(po.id) as po_id
    FROM vendor_printing_rates r
    JOIN partners v ON r.vendor_id = v.id
    LEFT JOIN sites s ON r.site_id = s.id
    LEFT JOIN purchase_orders po ON r.po_number = po.po_number
    $queryWhere
    GROUP BY r.po_number, r.vendor_id, v.name, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY r.id DESC
");
$ratesStmt->execute($params);
$rates = $ratesStmt->fetchAll();

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$sites = $pdo->query("SELECT id, name, site_code, width, height, vendor_id, city, state, type, light_type, owner_type, status FROM sites ORDER BY site_code ASC")->fetchAll();

// Fetch filter values for advanced search criteria in the modal
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL AND width != '' AND height != '' ORDER BY width, height")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Printing PO</h2>
        <div style="display: flex; gap: 0.75rem;">
            <?php if (canAdd('vendors')): ?>
            <a href="create_printing_po.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <i class="fas fa-plus"></i> Add New Printing PO 
            </a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" action="printing_rates.php" style="display:flex; flex-wrap: wrap; gap:1rem; align-items:flex-end; margin-bottom:1.5rem;">
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Vendor</label>
            <select name="vendor_id" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:220px;">
                <option value="">All Vendors</option>
                <?php foreach ($vendors as $vendor): ?>
                    <option value="<?php echo $vendor['id']; ?>" <?php echo $selectedVendorId === intval($vendor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($vendor['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.35rem; min-width:280px;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Campaign Name</label>
            <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..." style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:280px;">
        </div>
        <div style="display:flex; gap:0.75rem;">
            <button type="submit" class="btn btn-primary" style="padding:0.85rem 1.25rem;">Filter</button>
            <a href="printing_rates.php" class="btn" style="background:#f8fafc; color:#475569; border:1px solid #cbd5e1; padding:0.85rem 1.25rem; text-decoration:none;">Reset</a>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>PO Number</th>
                <th>Vendor</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th style="text-align: center; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">Invoice Attachments</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rates)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--secondary); padding: 2rem;">No Vendor Printing POs found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rates as $r): ?>
                <?php 
                    $ids = explode('||', $r['rate_ids']);
                    
                    // Re-calculate Total Amount
                    $widths = explode('||', $r['widths']);
                    $heights = explode('||', $r['heights']);
                    $unitRates = explode('||', $r['rates']);
                    
                    $totalGroupAmount = 0;
                    foreach($ids as $i => $id) {
                        $sqft = floatval($widths[$i]) * floatval($heights[$i]);
                        $totalGroupAmount += ($sqft * floatval($unitRates[$i]));
                    }
                    $gst = $totalGroupAmount * 0.18;
                    $grandTotal = $totalGroupAmount + $gst;
                ?>
                <tr>
                    <td>
                        <?php if($r['po_number']): ?>
                            <strong>#<?php echo $r['po_number']; ?></strong>
                        <?php else: ?>
                            <span style="color: #cbd5e1; font-weight: 400;">N/A</span>
                        <?php endif; ?>
                        <?php 
                        $camp_brand = [];
                        if (!empty($r['campaign_name'])) $camp_brand[] = trim($r['campaign_name']);
                        if (!empty($r['brand_name'])) $camp_brand[] = trim($r['brand_name']);
                        $display_camp_brand = implode(' / ', $camp_brand);
                        if (!empty($display_camp_brand)): ?>
                            <div style="font-size: 0.72rem; color: #2563eb; font-weight: 700; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; padding: 2px 6px; border-radius: 4px;">
                                <i class="fas fa-bullhorn" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($r['vendor_name']); ?></div>
                    </td>
                    <td>
                        <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td>
                        <div style="font-weight: 800; color: #059669;">₹<?php echo number_format($totalGroupAmount, 2); ?></div>
                        <div style="font-size: 0.65rem; color: #94a3b8;">+GST: ₹<?php echo number_format($gst, 2); ?></div>
                        <div style="font-size: 0.7rem; font-weight: 800; color: #0f172a;">Total: ₹<?php echo number_format($grandTotal, 2); ?></div>
                    </td>
                    <td>
                        <?php if ($r['approval_status'] === 'pending_approval'): ?>
                            <span style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;" title="Pending Admin Approval">
                                <i class="fas fa-clock"></i> Awaiting Approval
                            </span>
                        <?php elseif ($r['approval_status'] === 'approved'): ?>
                            <span style="background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-check-circle"></i> Approved
                            </span>
                        <?php elseif ($r['approval_status'] === 'rejected'): ?>
                            <span style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-times-circle"></i> Rejected
                            </span>
                        <?php else: ?>
                            <span style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-file"></i> Draft
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <!-- Invoice Attachments Section -->
                            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 80px; text-align: right; display: inline-block;">Invoice:</span>
                                <?php
                                if (!empty($r['attachments'])):
                                    $atts = explode('||', $r['attachments']);
                                    if (count($atts) > 0): 
                                        $file = $atts[0];
                                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                        $icon = 'fa-file';
                                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                        if ($ext === 'pdf') $icon = 'fa-file-pdf';
                                        ?>
                                        <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" target="_blank"
                                            class="attachment-badge" title="View Attachment">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </a>
                                        <?php
                                    endif;
                                endif;
                                ?>
                                <?php if (canEdit('vendors') && $r['po_number']): ?>
                                    <button class="btn-upload-row" onclick="triggerUpload('<?php echo htmlspecialchars($r['po_number'], ENT_QUOTES); ?>')"
                                        title="Upload Invoice/Scan">
                                        <i class="fas fa-cloud-upload-alt"></i> Upload
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Tax Invoice Section -->
                            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-right: 4px; width: 80px; text-align: right; display: inline-block;">Tax Invoice:</span>
                                <?php if (!empty($r['client_tax_order'])):
                                    $file = $r['client_tax_order'];
                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    $icon = 'fa-file';
                                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                    if ($ext === 'pdf') $icon = 'fa-file-pdf';
                                    ?>
                                    <a href="../../uploads/pos/tax_orders/<?php echo rawurlencode($file); ?>"
                                        target="_blank" class="attachment-badge" style="background: #e0e7ff; color: #4f46e5;"
                                        title="View Tax Invoice">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (canEdit('vendors') && $r['po_number']): ?>
                                    <button class="btn-upload-row"
                                        style="background: #eef2ff; color: #4f46e5; border-color: #c7d2fe;"
                                        onclick="triggerTaxOrderUpload('<?php echo htmlspecialchars($r['po_number'], ENT_QUOTES); ?>')" title="Upload Tax Invoice">
                                        <i class="fas fa-cloud-upload-alt"></i> Upload
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php 
                            $viewUrl = "view_vendor_printing_po.php?vendor_id=" . $r['vendor_id'];
                            if ($r['po_number']) {
                                $viewUrl .= "&po_number=" . urlencode($r['po_number']);
                            } else {
                                foreach($ids as $id) $viewUrl .= "&rate_ids[]=" . $id;
                            }
                        ?>
                        <?php if (hasRole('admin') || $r['approval_status'] === 'approved'): ?>
                        <a href="<?php echo $viewUrl; ?>" class="btn-icon" style="color: #0d9488;" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasRole('admin') || ($r['approval_status'] !== 'approved')): ?>
                        <?php 
                            $editUrl = "create_printing_po.php?action=edit&vendor_id=" . $r['vendor_id'];
                            if ($r['po_number']) {
                                $editUrl .= "&po_number=" . urlencode($r['po_number']);
                            } else {
                                foreach($ids as $id) $editUrl .= "&rate_ids[]=" . $id;
                            }
                        ?>
                        <a href="<?php echo $editUrl; ?>" class="btn-icon" style="color: #0284c7;" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (canDelete('vendors')): ?>
                        <button class="btn-icon btn-delete" onclick="deleteRate('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($r['rate_ids'], ENT_QUOTES); ?>')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<input type="file" id="po-list-upload-input" style="display: none;" onchange="handlePOUpload(this)" accept=".pdf,.png,.jpg,.jpeg">

<style>
.btn-upload-row {
    background: #f0fdf4;
    color: #166534;
    border: 1px dashed #bbf7d0;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}
.btn-upload-row:hover {
    background: #dcfce7;
    border-color: #86efac;
    transform: translateY(-1px);
}
.attachment-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.85rem;
    transition: all 0.2s;
}
.attachment-badge:hover {
    background: #e2e8f0;
    color: #0d9488;
    transform: translateY(-1px);
}
</style>

<script>
let activeUploadPoNumber = null;
let activeUploadType = 'invoice';

function triggerUpload(poNumber) {
    if (!poNumber) return Swal.fire('Error', 'Invalid PO number for attachment.', 'error');
    activeUploadPoNumber = poNumber;
    activeUploadType = 'invoice';
    document.getElementById('po-list-upload-input').click();
}

function triggerTaxOrderUpload(poNumber) {
    if (!poNumber) return Swal.fire('Error', 'Invalid PO number for attachment.', 'error');
    activeUploadPoNumber = poNumber;
    activeUploadType = 'tax_order';
    document.getElementById('po-list-upload-input').click();
}

function handlePOUpload(input) {
    if (!input.files || input.files.length === 0 || !activeUploadPoNumber) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('po_number', activeUploadPoNumber);
    formData.append('file', file);
    
    Swal.fire({
        title: 'Uploading Document...',
        text: 'Please wait while the file is being processed.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const url = activeUploadType === 'tax_order' 
        ? '../../ajax/upload_printing_tax_order.php' 
        : '../../ajax/upload_printing_attachment.php';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Uploaded Successfully!',
                text: 'Attachment added successfully.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Upload Failed', res.message || 'Error occurred.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Network error. Please try again.', 'error');
    });
}
// Check for parameters on load
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('vendor_id')) {
        const vId = urlParams.get('vendor_id');
        window.location.href = `create_printing_po.php?vendor_id=${vId}`;
    }
    
    // Show success message if present in URL
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        let title = 'Success';
        let text = '';
        if (msg === 'added') text = 'Printing PO created successfully.';
        if (msg === 'updated') text = 'Printing PO updated successfully.';
        
        if (text) {
            Swal.fire({
                title: title,
                text: text,
                icon: 'success',
                confirmButtonColor: '#0d9488',
                timer: 2500,
                showConfirmButton: false
            });
            // Clean URL without reloading
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});

function deleteRate(poNumber, rateIds) {
    Swal.fire({
        title: 'Delete PO?',
        text: "Are you sure you want to remove this Printing PO and all its sites?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('printing_rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&po_number=${encodeURIComponent(poNumber)}&rate_ids=${encodeURIComponent(rateIds)}`
            }).then(() => {
                Swal.fire('Deleted!', 'Printing PO has been removed.', 'success').then(() => location.reload());
            });
        }
    });
}

function togglePODetails(groupId) {
    const elements = document.querySelectorAll('.collapsible-po-' + groupId);
    const btn = document.getElementById('toggle-btn-' + groupId);
    if (elements.length > 0) {
        const isHidden = elements[0].style.display === 'none';
        elements.forEach(el => {
            el.style.display = isHidden ? 'block' : 'none';
        });
        const count = btn.getAttribute('data-count');
        if (isHidden) {
            btn.innerHTML = `<i class="fas fa-chevron-up"></i> Show less`;
        } else {
            btn.innerHTML = `<i class="fas fa-chevron-down"></i> + ${count} more site(s)`;
        }
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
