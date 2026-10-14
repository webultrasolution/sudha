<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('vendors', 'view');

// Handle Form Submissions
// Sync Printing PO helper function
function syncPrintingPO($pdo, $po_number, $vendor_id, $media_type, $campaign_name, $brand_name, $printing_date = null, $entity_id = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!$printing_date) {
        $printing_date = date('Y-m-d');
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
            SET po_amount = ?, cgst_amount = ?, sgst_amount = ?, igst_amount = ?, total_amount = ?, status = ?, approval_status = ?, campaign_name = ?, brand_name = ?, vendor_id = ?, po_date = ?" . ($entity_id !== null ? ", entity_id = ?" : "") . "
            WHERE id = ?
        ");
        $updateParams = [
            $subtotal, $cgst, $sgst, $igst, $grandTotal,
            $poStatus, $approvalStatus,
            $campaign_name ?: 'Printing PO',
            $brand_name,
            $vendor_id,
            $printing_date
        ];
        if ($entity_id !== null) {
            $updateParams[] = $entity_id;
        }
        $updateParams[] = $poId;
        $stmtPOUpdate->execute($updateParams);
    } else {
        // Insert PO
        $stmtPOInsert = $pdo->prepare("
            INSERT INTO purchase_orders (vendor_id, employee_id, campaign_name, brand_name, po_number, po_date, po_amount, cgst_amount, sgst_amount, igst_amount, total_amount, status, approval_status, remarks, type" . ($entity_id !== null ? ", entity_id" : "") . ") 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'printing'" . ($entity_id !== null ? ", ?" : "") . ")
        ");
        $insertParams = [
            $vendor_id,
            $_SESSION['user_id'] ?? 0,
            $campaign_name ?: 'Printing PO',
            $brand_name,
            $po_number,
            $printing_date,
            $subtotal, $cgst, $sgst, $igst, $grandTotal,
            $poStatus, $approvalStatus,
            "Printing PO" . ($campaign_name ? " - " . $campaign_name : ""),
        ];
        if ($entity_id !== null) {
            $insertParams[] = $entity_id;
        }
        $stmtPOInsert->execute($insertParams);
        $poId = $pdo->lastInsertId();
    }

    // Sync po_items (delete old and insert new)
    $pdo->prepare("DELETE FROM po_items WHERE po_id = ?")->execute([$poId]);

    $stmtItem = $pdo->prepare("INSERT INTO po_items (po_id, site_id, start_date, end_date, days, monthly_rate, cost, description) VALUES (?, ?, ?, ?, 1, ?, ?, ?)");
    foreach ($items as $item) {
        $desc = "Printing: $media_type - {$item['site_code']} {$item['site_name']} ({$item['sqft']} SQFT × ₹{$item['rate']}/sqft)";
        $stmtItem->execute([$poId, $item['site_id'], $printing_date, $printing_date, $item['rate'], $item['cost'], $desc]);
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
        $entity_id = isset($_POST['entity_id']) && $_POST['entity_id'] !== '' ? intval($_POST['entity_id']) : ($_SESSION['active_entity_id'] ?? null);
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);
        $campaign_name = isset($_POST['campaign_name']) ? clean($_POST['campaign_name']) : null;
        $brand_name = isset($_POST['brand_name']) ? clean($_POST['brand_name']) : null;
        $printing_date = isset($_POST['printing_date']) && $_POST['printing_date'] !== '' ? clean($_POST['printing_date']) : date('Y-m-d');

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
            
            syncPrintingPO($pdo, $po_number, $vendor_id, $media_type, $campaign_name, $brand_name, $printing_date, $entity_id);
            
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
            
            syncPrintingPO($pdo, $po_number, $vendor_id, $media_type, $campaign_name, $brand_name, $printing_date, $entity_id);
            
            header("Location: printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'update_printing_date') {
        requirePermission('vendors', 'edit');
        header('Content-Type: application/json');
        
        $po_number = $_POST['po_number'] ?? '';
        $printing_date = $_POST['printing_date'] ?? '';
        
        if (empty($po_number) || empty($printing_date)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }
        
        // Update purchase_orders po_date
        $stmtPO = $pdo->prepare("UPDATE purchase_orders SET po_date = ? WHERE po_number = ?");
        $stmtPO->execute([$printing_date, $po_number]);
        
        // Update po_items start_date and end_date
        $stmtItems = $pdo->prepare("UPDATE po_items SET start_date = ?, end_date = ? WHERE po_id = (SELECT id FROM purchase_orders WHERE po_number = ? LIMIT 1)");
        $stmtItems->execute([$printing_date, $printing_date, $po_number]);
        
        echo json_encode(['success' => true]);
        exit;
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
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

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
if ($statusFilter !== '') {
    if ($statusFilter === 'approved') {
        $queryWhere .= " AND po.approval_status = 'approved'";
    } elseif ($statusFilter === 'pending_draft') {
        $queryWhere .= " AND (po.approval_status IS NULL OR po.approval_status IN ('pending_approval', 'draft'))";
    } elseif ($statusFilter === 'rejected') {
        $queryWhere .= " AND po.approval_status = 'rejected'";
    }
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
        MAX(po.id) as po_id,
        (SELECT MIN(start_date) FROM po_items WHERE po_id = po.id) as po_start_date,
        (SELECT MAX(end_date) FROM po_items WHERE po_id = po.id) as po_end_date,
        (SELECT MIN(start_date) FROM bookings WHERE campaign_name COLLATE utf8mb4_unicode_ci = r.campaign_name COLLATE utf8mb4_unicode_ci) as booking_start_date,
        (SELECT MAX(end_date) FROM bookings WHERE campaign_name COLLATE utf8mb4_unicode_ci = r.campaign_name COLLATE utf8mb4_unicode_ci) as booking_end_date,
        MAX(po.vendor_invoice_no) as vendor_invoice_no,
        MAX(po.vendor_invoice_date) as vendor_invoice_date,
        MAX(po.vendor_invoice_amount) as vendor_invoice_amount,
        MAX(u.username) as creator_name
    FROM vendor_printing_rates r
    JOIN partners v ON r.vendor_id = v.id
    LEFT JOIN sites s ON r.site_id = s.id
    LEFT JOIN purchase_orders po ON r.po_number = po.po_number
    LEFT JOIN users u ON COALESCE(r.created_by, po.created_by, po.employee_id) = u.id
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
<div class="card" style="box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; border-radius: 12px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-print" style="color: #0d9488;"></i> Vendor Printing PO
            </h2>
            <p style="margin: 3px 0 0 0; font-size: 0.82rem; color: #64748b;">Manage vendor printing purchase orders, rates, and invoice attachments</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <?php if (canAdd('vendors')): ?>
            <a href="create_printing_po.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none; padding: 0.6rem 1.15rem; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 6px rgba(13, 148, 136, 0.25);">
                <i class="fas fa-plus"></i> Add New Printing PO 
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem;">
        <form method="get" action="printing_rates.php" style="display: flex; align-items: flex-end; gap: 0.75rem; flex-wrap: nowrap; width: 100%;">
            <div style="flex: 1; min-width: 160px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.35rem; white-space: nowrap;">
                    <i class="fas fa-truck text-slate-400" style="margin-right: 4px;"></i> Vendor
                </label>
                <select name="vendor_id" class="form-control" style="width: 100%; height: 38px; border-radius: 7px; border: 1px solid #cbd5e1; font-size: 0.85rem; padding: 0 0.75rem; background-color: #fff; box-sizing: border-box;">
                    <option value="">All Vendors</option>
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?php echo $vendor['id']; ?>" <?php echo $selectedVendorId === intval($vendor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($vendor['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1.4; min-width: 180px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.35rem; white-space: nowrap;">
                    <i class="fas fa-bullhorn text-slate-400" style="margin-right: 4px;"></i> Campaign Name
                </label>
                <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..." class="form-control" style="width: 100%; height: 38px; border-radius: 7px; border: 1px solid #cbd5e1; font-size: 0.85rem; padding: 0 0.75rem; background-color: #fff; box-sizing: border-box;">
            </div>
            <div style="flex: 0.9; min-width: 140px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.35rem; white-space: nowrap;">
                    <i class="fas fa-tag text-slate-400" style="margin-right: 4px;"></i> Status
                </label>
                <select name="status" class="form-control" style="width: 100%; height: 38px; border-radius: 7px; border: 1px solid #cbd5e1; font-size: 0.85rem; padding: 0 0.75rem; background-color: #fff; box-sizing: border-box;">
                    <option value="">All Statuses</option>
                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending_draft" <?php echo $statusFilter === 'pending_draft' ? 'selected' : ''; ?>>Pending / Draft</option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div style="display: flex; gap: 0.4rem; flex-shrink: 0;">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 1.15rem; border-radius: 7px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(13, 148, 136, 0.15); white-space: nowrap;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="printing_rates.php" class="btn" style="height: 38px; padding: 0 0.85rem; border-radius: 7px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: #fff; color: #64748b; border: 1px solid #cbd5e1; text-decoration: none; white-space: nowrap;">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-responsive" style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff;">
        <table class="table" style="margin-bottom: 0; width: 100%; border-collapse: collapse;">
            <thead style="background: #f8fafc;">
                <tr>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">PO Number</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0;">Vendor</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Printing Date</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">PO Date</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Amount</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; text-align: center; white-space: nowrap;">Status</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; min-width: 250px;">Invoice & Attachments</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Created By</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; text-align: center; white-space: nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rates)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #64748b; padding: 3rem 1rem;">
                            <div style="font-size: 2rem; color: #cbd5e1; margin-bottom: 0.5rem;"><i class="fas fa-folder-open"></i></div>
                            <div style="font-weight: 600; font-size: 0.95rem;">No Vendor Printing POs found</div>
                            <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 4px;">Try adjusting your filter options or add a new Printing PO.</div>
                        </td>
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
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                        <td style="vertical-align: middle; padding: 12px 14px;">
                            <div style="font-weight: 800; font-size: 0.9rem; color: #0f172a; letter-spacing: -0.01em;">
                                <?php if($r['po_number']): ?>
                                    #<?php echo htmlspecialchars($r['po_number']); ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-weight: 500;">N/A</span>
                                <?php endif; ?>
                            </div>
                            <?php 
                            $camp_brand = [];
                            if (!empty($r['campaign_name'])) $camp_brand[] = trim($r['campaign_name']);
                            if (!empty($r['brand_name'])) $camp_brand[] = trim($r['brand_name']);
                            $display_camp_brand = implode(' / ', $camp_brand);
                            if (!empty($display_camp_brand)): ?>
                                <div style="font-size: 0.72rem; color: #2563eb; font-weight: 700; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; border: 1px solid #dbeafe; padding: 2px 7px; border-radius: 4px; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($display_camp_brand); ?>">
                                    <i class="fas fa-bullhorn" style="font-size: 0.65rem; flex-shrink: 0;"></i> <span><?php echo htmlspecialchars($display_camp_brand); ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px;">
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($r['vendor_name']); ?></div>
                        </td>
                        <?php $printDate = !empty($r['po_start_date']) ? $r['po_start_date'] : $r['created_at']; ?>
                        <td style="vertical-align: middle; padding: 12px 14px; white-space: nowrap;">
                            <?php if (!empty($printDate) && $printDate !== '0000-00-00'): ?>
                                <button type="button" class="btn-date-pill" onclick="changePrintingDate('<?php echo htmlspecialchars($r['po_number'], ENT_QUOTES); ?>', '<?php echo date('Y-m-d', strtotime($printDate)); ?>')" title="Click to change Printing Date">
                                    <i class="fas fa-print" style="color: #0284c7;"></i>
                                    <span><?php echo date('d M Y', strtotime($printDate)); ?></span>
                                    <i class="fas fa-pencil-alt" style="font-size: 0.65rem; opacity: 0.6;"></i>
                                </button>
                            <?php else: ?>
                                <span style="color: #cbd5e1;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px; white-space: nowrap; font-size: 0.82rem; color: #475569; font-weight: 500;">
                            <i class="far fa-calendar-alt" style="color: #94a3b8; margin-right: 4px;"></i>
                            <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px; white-space: nowrap;">
                            <div style="font-weight: 800; color: #059669; font-size: 0.92rem;">₹<?php echo number_format($totalGroupAmount, 2); ?></div>
                            <div style="font-size: 0.7rem; color: #64748b; margin-top: 1px;">+GST: ₹<?php echo number_format($gst, 2); ?></div>
                            <div style="font-size: 0.75rem; font-weight: 800; color: #0f172a; margin-top: 1px;">Total: ₹<?php echo number_format($grandTotal, 2); ?></div>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px; text-align: center; white-space: nowrap;">
                            <?php if ($r['approval_status'] === 'pending_approval'): ?>
                                <span class="status-badge status-pending" title="Pending Admin Approval">
                                    <i class="fas fa-clock"></i> Awaiting Approval
                                </span>
                            <?php elseif ($r['approval_status'] === 'approved'): ?>
                                <span class="status-badge status-approved">
                                    <i class="fas fa-check-circle"></i> Approved
                                </span>
                            <?php elseif ($r['approval_status'] === 'rejected'): ?>
                                <span class="status-badge status-rejected">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-draft">
                                    <i class="fas fa-file"></i> Draft
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: middle; padding: 10px 14px;">
                            <div style="display: flex; flex-direction: column; gap: 6px; min-width: 240px;">
                                <!-- Vendor Invoice Section -->
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; display: flex; flex-direction: column; gap: 4px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em;">Vendor Inv</span>
                                            <?php
                                            if (!empty($r['attachments'])):
                                                $atts = explode('||', $r['attachments']);
                                                if (count($atts) > 0 && !empty($atts[0])): 
                                                    $file = $atts[0];
                                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                                    $icon = 'fa-file';
                                                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                                    if ($ext === 'pdf') $icon = 'fa-file-pdf';
                                                    ?>
                                                    <a href="../../uploads/pos/<?php echo rawurlencode($file); ?>" target="_blank"
                                                        class="btn-file-preview" title="View Vendor Invoice (<?php echo htmlspecialchars($file); ?>)">
                                                        <i class="fas <?php echo $icon; ?>" style="color: #0284c7;"></i> View
                                                    </a>
                                                    <?php
                                                endif;
                                            endif;
                                            ?>
                                        </div>
                                        <?php if (canEdit('vendors') && $r['po_number']): ?>
                                            <button type="button" class="btn-upload-pill" onclick="triggerUpload('<?php echo htmlspecialchars($r['po_number'], ENT_QUOTES); ?>')"
                                                title="Upload / Edit Vendor Invoice">
                                                <i class="fas fa-cloud-upload-alt"></i> <?php echo (!empty($r['vendor_invoice_no']) || !empty($r['attachments'])) ? 'Edit' : 'Upload'; ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($r['vendor_invoice_no'])): ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px 8px; font-size: 0.7rem; color: #475569; background: #fff; border: 1px solid #f1f5f9; padding: 3px 6px; border-radius: 4px; font-weight: 600;">
                                            <span style="color: #0284c7;" title="Invoice Number"><i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($r['vendor_invoice_no']); ?></span>
                                            <span style="color: #059669;" title="Invoice Amount"><i class="fas fa-rupee-sign"></i> <?php echo number_format($r['vendor_invoice_amount'] ?? 0, 2); ?></span>
                                            <span style="color: #64748b;" title="Invoice Date"><i class="far fa-calendar"></i> <?php echo date('d M Y', strtotime($r['vendor_invoice_date'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Tax Invoice Section -->
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em;">Tax Invoice</span>
                                        <?php if (!empty($r['client_tax_order'])):
                                            $file = $r['client_tax_order'];
                                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                            $icon = 'fa-file';
                                            if (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'fa-file-image';
                                            if ($ext === 'pdf') $icon = 'fa-file-pdf';
                                            ?>
                                            <a href="../../uploads/pos/tax_orders/<?php echo rawurlencode($file); ?>"
                                                target="_blank" class="btn-file-preview" style="background: #eef2ff; border-color: #c7d2fe; color: #4f46e5;"
                                                title="View Tax Invoice (<?php echo htmlspecialchars($file); ?>)">
                                                <i class="fas <?php echo $icon; ?>" style="color: #4f46e5;"></i> View
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (canEdit('vendors') && $r['po_number']): ?>
                                        <button type="button" class="btn-upload-pill"
                                            style="background: #eef2ff; color: #4f46e5; border-color: #c7d2fe;"
                                            onclick="triggerTaxOrderUpload('<?php echo htmlspecialchars($r['po_number'], ENT_QUOTES); ?>')" title="Upload Tax Invoice">
                                            <i class="fas fa-cloud-upload-alt"></i> <?php echo !empty($r['client_tax_order']) ? 'Replace' : 'Upload'; ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px; white-space: nowrap;">
                            <span style="font-weight: 600; color: #475569; font-size: 0.8rem; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-user" style="color: #94a3b8; font-size: 0.75rem;"></i>
                                <?php echo htmlspecialchars($r['creator_name'] ?: '—'); ?>
                            </span>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px; text-align: center; white-space: nowrap;">
                            <div style="display: inline-flex; align-items: center; gap: 4px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 3px 4px; border-radius: 8px;">
                                <?php 
                                    $viewUrl = "view_vendor_printing_po.php?vendor_id=" . $r['vendor_id'];
                                    if ($r['po_number']) {
                                        $viewUrl .= "&po_number=" . urlencode($r['po_number']);
                                    } else {
                                        foreach($ids as $id) $viewUrl .= "&rate_ids[]=" . $id;
                                    }
                                ?>
                                <?php if (hasRole('admin') || $r['approval_status'] === 'approved'): ?>
                                <a href="<?php echo $viewUrl; ?>" class="table-action-btn action-view" title="View Details">
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
                                <a href="<?php echo $editUrl; ?>" class="table-action-btn action-edit" title="Edit PO">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($r['po_number'])): ?>
                                <a href="../operations/generate_printing_po.php?vendor_id=<?php echo $r['vendor_id']; ?>&preview=1&po_number=<?php echo urlencode($r['po_number']); ?>" target="_blank" class="table-action-btn action-pdf" title="Download / Print PDF PO">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                <?php endif; ?>

                                <?php if (canDelete('vendors')): ?>
                                <button type="button" class="table-action-btn action-delete" onclick="deleteRate('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($r['rate_ids'], ENT_QUOTES); ?>')" title="Delete PO">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<input type="file" id="po-list-upload-input" style="display: none;" onchange="handlePOUpload(this)" accept=".pdf,.png,.jpg,.jpeg">

<style>
/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 50px;
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
}
.status-approved {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.status-pending {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
    animation: pulse-approval 2s infinite;
}
.status-rejected {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.status-draft {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

/* Printing Date Pill Button */
.btn-date-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f0f9ff;
    color: #0284c7;
    border: 1px dashed #7dd3fc;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-date-pill:hover {
    background: #e0f2fe;
    border-color: #0284c7;
    transform: translateY(-1px);
}

/* Upload Pill Button */
.btn-upload-pill {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 0.68rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-upload-pill:hover {
    background: #dcfce7;
    border-color: #86efac;
    transform: translateY(-1px);
}

/* File Preview Button */
.btn-file-preview {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f0f9ff;
    color: #0369a1;
    border: 1px solid #bae6fd;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 0.68rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-file-preview:hover {
    background: #e0f2fe;
    border-color: #7dd3fc;
    color: #0284c7;
}

/* Table Action Buttons */
.table-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    font-size: 0.8rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    text-decoration: none;
}
.table-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}
.table-action-btn.action-view:hover {
    background: #f0fdfa;
    border-color: #99f6e4;
    color: #0d9488;
}
.table-action-btn.action-edit:hover {
    background: #f0f9ff;
    border-color: #bae6fd;
    color: #0284c7;
}
.table-action-btn.action-pdf:hover {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #e11d48;
}
.table-action-btn.action-delete:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

@keyframes pulse-approval {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.85; transform: scale(0.98); }
}
</style>

<script>
let activeUploadPoNumber = null;
let activeUploadType = 'invoice';

function triggerUpload(poNumber) {
    if (!poNumber) return Swal.fire('Error', 'Invalid PO number for attachment.', 'error');
    
    Swal.fire({
        title: 'Upload Invoice Details',
        html: `
            <div style="text-align: left; display: flex; flex-direction: column; gap: 0.75rem;">
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Invoice Number</label>
                    <input type="text" id="swal-inv-no" class="swal2-input" placeholder="e.g. INV-1234" style="margin: 0; width: 100%; box-sizing: border-box;">
                </div>
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Invoice Amount (Base/Total)</label>
                    <input type="number" id="swal-inv-amount" class="swal2-input" placeholder="e.g. 10220" style="margin: 0; width: 100%; box-sizing: border-box;">
                </div>
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Invoice Date</label>
                    <input type="date" id="swal-inv-date" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box;">
                </div>
                <div>
                    <label style="font-weight: 700; font-size: 0.85rem; color: #475569;">Select File</label>
                    <input type="file" id="swal-inv-file" class="swal2-file" accept=".pdf,.png,.jpg,.jpeg" style="margin: 0; width: 100%; box-sizing: border-box; padding: 0.5rem 0;">
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Submit & Upload',
        confirmButtonColor: '#0d9488',
        preConfirm: () => {
            const no = document.getElementById('swal-inv-no').value;
            const amount = document.getElementById('swal-inv-amount').value;
            const date = document.getElementById('swal-inv-date').value;
            const fileInput = document.getElementById('swal-inv-file');
            
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.showValidationMessage('Please select an invoice file');
                return false;
            }
            if (!no) {
                Swal.showValidationMessage('Please enter invoice number');
                return false;
            }
            if (!amount) {
                Swal.showValidationMessage('Please enter invoice amount');
                return false;
            }
            if (!date) {
                Swal.showValidationMessage('Please select invoice date');
                return false;
            }
            
            return {
                no: no,
                amount: amount,
                date: date,
                file: fileInput.files[0]
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const data = result.value;
            const formData = new FormData();
            formData.append('po_number', poNumber);
            formData.append('invoice_no', data.no);
            formData.append('invoice_amount', data.amount);
            formData.append('invoice_date', data.date);
            formData.append('file', data.file);
            
            Swal.fire({
                title: 'Uploading...',
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../../ajax/upload_printing_attachment.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('Uploaded!', 'Invoice uploaded and synced successfully.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Upload failed.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        }
    });
}

function triggerTaxOrderUpload(poNumber) {
    if (!poNumber) return Swal.fire('Error', 'Invalid PO number for attachment.', 'error');
    activeUploadPoNumber = poNumber;
    activeUploadType = 'tax_order';
    document.getElementById('po-list-upload-input').click();
}

function handlePOUpload(input) {
    if (!input.files || input.files.length === 0 || !activeUploadPoNumber || activeUploadType !== 'tax_order') return;
    
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

    const url = '../../ajax/upload_printing_tax_order.php';

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
function changePrintingDate(poNumber, currentDate) {
    if (!poNumber) return Swal.fire('Error', 'Legacy rates without PO number cannot have printing date updated directly.', 'error');
    
    Swal.fire({
        title: 'Change Printing Date',
        html: `
            <div style="text-align: left;">
                <label style="font-weight: 700; font-size: 0.85rem; color: #475569; display: block; margin-bottom: 0.5rem;">Select Printing Date</label>
                <input type="date" id="swal-print-date" class="swal2-input" value="${currentDate}" style="margin: 0; width: 100%; box-sizing: border-box;">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Update Date',
        confirmButtonColor: '#0d9488',
        preConfirm: () => {
            const date = document.getElementById('swal-print-date').value;
            if (!date) {
                Swal.showValidationMessage('Please select a date');
                return false;
            }
            return date;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const newDate = result.value;
            
            Swal.fire({
                title: 'Updating...',
                didOpen: () => { Swal.showLoading(); }
            });
            
            fetch('printing_rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_printing_date&po_number=${encodeURIComponent(poNumber)}&printing_date=${encodeURIComponent(newDate)}`
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('Updated!', 'Printing date updated successfully.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Update failed.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
