<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('clients', 'view');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $client_id = intval($_POST['client_id']);
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);
        $campaign_name = isset($_POST['campaign_name']) ? clean($_POST['campaign_name']) : null;
        $brand_name = isset($_POST['brand_name']) ? clean($_POST['brand_name']) : null;
        $billing_gstin = !empty($_POST['billing_gstin']) ? clean($_POST['billing_gstin']) : null;

        if ($_POST['action'] === 'add') {
            requirePermission('clients', 'add');
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [null];
            $individual_rates = $_POST['individual_rates'] ?? [];
            $po_number = generateSequenceNumber($pdo, 'client_printing_draft');
            
            foreach ($site_ids as $site_id) {
                $site_id = !empty($site_id) ? intval($site_id) : null;
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                // Insert new rate with PO number to preserve historical PO data
                $insertStmt = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$client_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name, $billing_gstin]);
            }
            header("Location: client_printing_rates.php?msg=added"); exit;
        } else {
            requirePermission('clients', 'edit');
            
            $po_number = !empty($_POST['po_number']) ? clean($_POST['po_number']) : null;
            $rate_ids_post = isset($_POST['rate_ids']) ? $_POST['rate_ids'] : [];
            
            if (!$po_number) {
                $po_number = generateSequenceNumber($pdo, 'client_printing_draft');
                // Assign this new PO number to legacy records first so they are grouped
                if (!empty($rate_ids_post)) {
                    $in = str_repeat('?,', count($rate_ids_post) - 1) . '?';
                    $upd_legacy = $pdo->prepare("UPDATE client_printing_rates SET po_number = ?, campaign_name = ?, brand_name = ?, billing_gstin = ? WHERE id IN ($in)");
                    $upd_legacy->execute(array_merge([$po_number, $campaign_name, $brand_name, $billing_gstin], $rate_ids_post));
                }
            }
            
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [];
            $individual_rates = $_POST['individual_rates'] ?? [];
            
            $stmt = $pdo->prepare("SELECT id, site_id FROM client_printing_rates WHERE po_number = ? AND client_id = ?");
            $stmt->execute([$po_number, $client_id]);
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
                    $upd = $pdo->prepare("UPDATE client_printing_rates SET media_type=?, rate_per_sqft=?, campaign_name=?, brand_name=?, billing_gstin=? WHERE id=?");
                    $upd->execute([$media_type, $this_rate, $campaign_name, $brand_name, $billing_gstin, $existing_site_to_id[$site_id]]);
                } else {
                    // Insert
                    $meta = $pdo->prepare("SELECT customer_po_no, customer_po_date, email_date, is_final_invoice, approval_status, custom_invoice_number, custom_invoice_date FROM client_printing_rates WHERE po_number = ? AND client_id = ? LIMIT 1");
                    $meta->execute([$po_number, $client_id]);
                    $m = $meta->fetch();
                    
                    if ($m) {
                        $ins = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, customer_po_no, customer_po_date, email_date, is_final_invoice, approval_status, custom_invoice_number, custom_invoice_date, billing_gstin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$client_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name, $m['customer_po_no'], $m['customer_po_date'], $m['email_date'], $m['is_final_invoice'], $m['approval_status'], $m['custom_invoice_number'], $m['custom_invoice_date'], $billing_gstin]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO client_printing_rates (client_id, site_id, media_type, rate_per_sqft, po_number, campaign_name, brand_name, billing_gstin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$client_id, $site_id, $media_type, $this_rate, $po_number, $campaign_name, $brand_name, $billing_gstin]);
                    }
                }
            }
            
            // Delete removed
            foreach ($existing_site_to_id as $es_site => $es_id) {
                if (!in_array($es_site, $posted_sites)) {
                    $pdo->prepare("DELETE FROM client_printing_rates WHERE id = ?")->execute([$es_id]);
                }
            }

            // Sync campaign/brand and billing_gstin across all rows of this PO
            // Also reset approval_status to pending_approval if it was rejected so admin can review again
            $syncStmt = $pdo->prepare("UPDATE client_printing_rates SET campaign_name = ?, brand_name = ?, billing_gstin = ?, approval_status = CASE WHEN approval_status = 'rejected' THEN 'pending_approval' ELSE approval_status END WHERE po_number = ?");
            $syncStmt->execute([$campaign_name, $brand_name, $billing_gstin, $po_number]);
            
            // Delete old rejection request if any and insert a new pending request
            $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'client_printing' AND entity_ref = ? AND status = 'pending'")->execute([$po_number]);
            $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('client_printing', 0, ?, ?, 'pending')");
            $stmtAR->execute([$po_number, $_SESSION['user_id'] ?? 0]);
            
            header("Location: client_printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
        requirePermission('clients', 'delete');
        header('Content-Type: application/json');
        include_once __DIR__ . '/../../includes/trash_helper.php';
        
        $po_number = $_POST['po_number'] ?? '';
        $rate_ids_str = $_POST['rate_ids'] ?? '';
        
        if (!empty($po_number)) {
            $stmt = $pdo->prepare("SELECT id FROM client_printing_rates WHERE po_number = ?");
            $stmt->execute([$po_number]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ids)) {
                move_multiple_rows_to_trash($pdo, 'client_printing_rates', 'id', $ids, $_SESSION['user_id'] ?? null, "Client Printing PO #$po_number deleted");
            }
        } elseif (!empty($rate_ids_str)) {
            $ids = explode('||', $rate_ids_str);
            $ids = array_map('intval', $ids);
            if (!empty($ids)) {
                move_multiple_rows_to_trash($pdo, 'client_printing_rates', 'id', $ids, $_SESSION['user_id'] ?? null, "Client printing rates deleted");
            }
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                move_multiple_rows_to_trash($pdo, 'client_printing_rates', 'id', [$id], $_SESSION['user_id'] ?? null, "Client printing rate ID $id deleted");
            }
        }
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'client_printing_rates';
$pageTitle = 'Client Printing Invoice';
include_once __DIR__ . '/../../includes/header.php';

$selectedClientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');

$queryWhere = "WHERE 1=1";
$params = [];
if ($selectedClientId) {
    $queryWhere .= " AND r.client_id = ?";
    $params[] = $selectedClientId;
}
if ($campaignFilter !== '') {
    $queryWhere .= " AND r.campaign_name LIKE ?";
    $params[] = '%' . $campaignFilter . '%';
}

// Fetch Rates Grouped by PO Number
$ratesStmt = $pdo->prepare("
    SELECT 
        r.po_number,
        r.client_id,
        c.name as client_name,
        GROUP_CONCAT(r.id SEPARATOR '||') as rate_ids,
        GROUP_CONCAT(COALESCE(s.name, 'Generic') SEPARATOR '||') as site_names,
        GROUP_CONCAT(COALESCE(s.site_code, '-') SEPARATOR '||') as site_codes,
        GROUP_CONCAT(COALESCE(s.width, 0) SEPARATOR '||') as widths,
        GROUP_CONCAT(COALESCE(s.height, 0) SEPARATOR '||') as heights,
        GROUP_CONCAT(COALESCE(r.media_type, s.type, 'Billboard') SEPARATOR '||') as media_types,
        GROUP_CONCAT(r.rate_per_sqft SEPARATOR '||') as rates,
        MIN(r.created_at) as created_at,
        SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as total_amount,
        MAX(r.attachments) as attachments,
        MAX(r.client_tax_order) as client_tax_order,
        MAX(r.is_final_invoice) as is_final_invoice,
        MAX(r.approval_status) as approval_status,
        MAX(r.campaign_name) as campaign_name,
        MAX(r.brand_name) as brand_name,
        MAX(r.custom_invoice_number) as custom_invoice_number,
        MAX(r.custom_invoice_date) as invoice_date,
        MAX(r.billing_gstin) as billing_gstin,
        MAX(r.booking_id) as booking_id,
        (SELECT invoice_number FROM invoices WHERE booking_id = MAX(r.booking_id) AND type = 'tax' LIMIT 1) as tax_invoice_number,
        MAX(c.state) as client_state,
        MAX(c.gstin) as client_gstin,
        MAX(c.additional_gst) as additional_gst,
        (SELECT remarks FROM approval_requests WHERE entity_type = 'client_printing' AND entity_ref COLLATE utf8mb4_unicode_ci = r.po_number COLLATE utf8mb4_unicode_ci ORDER BY id DESC LIMIT 1) as rejection_reason,
        (SELECT COUNT(*) FROM bookings WHERE booking_number COLLATE utf8mb4_unicode_ci = r.po_number COLLATE utf8mb4_unicode_ci) as is_unified
    FROM client_printing_rates r
    JOIN partners c ON r.client_id = c.id
    LEFT JOIN sites s ON r.site_id = s.id
    $queryWhere
    GROUP BY r.po_number, r.client_id, c.name, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY MIN(r.id) DESC
");
$ratesStmt->execute($params);
$rates = $ratesStmt->fetchAll();

$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
?>

<div class="card" style="box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; border-radius: 12px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-file-invoice-dollar" style="color: #0d9488;"></i> Client Printing Invoice
            </h2>
            <p style="margin: 3px 0 0 0; font-size: 0.82rem; color: #64748b;">Manage client printing estimates, invoices, rates, and tax orders</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <?php if (canAdd('clients')): ?>
            <a href="create_client_printing_po.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none; padding: 0.6rem 1.15rem; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 6px rgba(13, 148, 136, 0.25);">
                <i class="fas fa-plus"></i> Add New Client Printing Invoice 
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem;">
        <form method="get" action="client_printing_rates.php" style="display: flex; align-items: flex-end; gap: 0.75rem; flex-wrap: nowrap; width: 100%;">
            <div style="flex: 1; min-width: 180px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.35rem; white-space: nowrap;">
                    <i class="fas fa-user-tie text-slate-400" style="margin-right: 4px;"></i> Client
                </label>
                <select name="client_id" class="form-control" style="width: 100%; height: 38px; border-radius: 7px; border: 1px solid #cbd5e1; font-size: 0.85rem; padding: 0 0.75rem; background-color: #fff; box-sizing: border-box;">
                    <option value="">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>" <?php echo $selectedClientId === intval($client['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1.5; min-width: 200px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.35rem; white-space: nowrap;">
                    <i class="fas fa-bullhorn text-slate-400" style="margin-right: 4px;"></i> Campaign Name
                </label>
                <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..." class="form-control" style="width: 100%; height: 38px; border-radius: 7px; border: 1px solid #cbd5e1; font-size: 0.85rem; padding: 0 0.75rem; background-color: #fff; box-sizing: border-box;">
            </div>
            <div style="display: flex; gap: 0.4rem; flex-shrink: 0;">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 1.15rem; border-radius: 7px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(13, 148, 136, 0.15); white-space: nowrap;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="client_printing_rates.php" class="btn" style="height: 38px; padding: 0 0.85rem; border-radius: 7px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: #fff; color: #64748b; border: 1px solid #cbd5e1; text-decoration: none; white-space: nowrap;">
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
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Invoice / PO #</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0;">Campaign / Brand</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0;">Client</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Sites</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Media Type</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Date</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Amount</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; text-align: center; white-space: nowrap;">Status</th>
                    <th style="padding: 12px 14px; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; text-align: center; white-space: nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rates)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #64748b; padding: 3rem 1rem;">
                            <div style="font-size: 2rem; color: #cbd5e1; margin-bottom: 0.5rem;"><i class="fas fa-folder-open"></i></div>
                            <div style="font-weight: 600; font-size: 0.95rem;">No client printing invoices found</div>
                            <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 4px;">Try adjusting your filter options or add a new invoice.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rates as $r): ?>
                    <?php 
                        $ids = explode('||', $r['rate_ids']);
                        $widths = explode('||', $r['widths']);
                        $heights = explode('||', $r['heights']);
                        $unitRates = explode('||', $r['rates']);
                        $siteCodes = explode('||', $r['site_codes']);
                        $mtypes = array_filter(array_unique(explode('||', $r['media_types'])));
                        if (empty($mtypes)) $mtypes = ['-'];
                        
                        $totalGroupAmount = 0;
                        foreach($ids as $i => $id) {
                            $sqft = floatval($widths[$i] ?? 0) * floatval($heights[$i] ?? 0);
                            $totalGroupAmount += ($sqft * floatval($unitRates[$i] ?? 0));
                        }
                        $gst = $totalGroupAmount * 0.18;
                        $grandTotal = $totalGroupAmount + $gst;
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                        <td style="vertical-align: middle; padding: 12px 14px; white-space: nowrap;">
                            <?php if (!empty($r['custom_invoice_number'])): ?>
                                <strong style="color: #0d9488; font-weight: 800; font-size: 0.9rem;"><?php echo htmlspecialchars($r['custom_invoice_number']); ?></strong><br>
                                <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 600;">#<?php echo htmlspecialchars($r['po_number'] ?? ''); ?></span>
                            <?php elseif ($r['po_number']): ?>
                                <strong style="color: #0d9488; font-weight: 800; font-size: 0.9rem;">#<?php echo htmlspecialchars($r['po_number']); ?></strong>
                            <?php else: ?>
                                <span style="color: #cbd5e1; font-weight: 500;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px;">
                            <?php 
                            $camp_brand = [];
                            if (!empty($r['campaign_name'])) $camp_brand[] = trim($r['campaign_name']);
                            if (!empty($r['brand_name'])) $camp_brand[] = trim($r['brand_name']);
                            $display_camp_brand = implode(' / ', $camp_brand);
                            if (!empty($display_camp_brand)): ?>
                                <div style="font-size: 0.72rem; color: #2563eb; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; border: 1px solid #dbeafe; padding: 2px 7px; border-radius: 4px; max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($display_camp_brand); ?>">
                                    <i class="fas fa-bullhorn" style="font-size: 0.65rem; flex-shrink: 0;"></i> <span><?php echo htmlspecialchars($display_camp_brand); ?></span>
                                </div>
                            <?php else: ?>
                                <span style="color: #cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px;">
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($r['client_name'], ENT_QUOTES, 'UTF-8', false); ?></div>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px; white-space: nowrap;">
                            <div style="font-size: 0.8rem; color: #475569; font-weight: 600;"><?php echo count($ids); ?> site<?php echo count($ids) > 1 ? 's' : ''; ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8;">
                                <?php echo htmlspecialchars(implode(', ', array_slice($siteCodes, 0, 3))); ?>
                                <?php if (count($siteCodes) > 3) echo ' +' . (count($siteCodes) - 3) . ' more'; ?>
                            </div>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px;">
                            <?php foreach ($mtypes as $mt): ?>
                                <span style="background: #f0fdf4; color: #0d9488; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 800; display: inline-block; margin: 1px; white-space: nowrap;"><?php echo htmlspecialchars($mt); ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px; white-space: nowrap; font-size: 0.82rem; color: #475569; font-weight: 500;">
                            <i class="far fa-calendar-alt" style="color: #94a3b8; margin-right: 4px;"></i>
                            <?php echo !empty($r['invoice_date']) ? date('d M Y', strtotime($r['invoice_date'])) : date('d M Y', strtotime($r['created_at'])); ?>
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
                            <?php elseif ($r['approval_status'] === 'rejected'): ?>
                                <span class="status-badge status-rejected" title="Rejected by Admin">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                                <?php if (!empty($r['rejection_reason'])): ?>
                                    <div style="font-size: 0.68rem; color: #dc2626; margin-top: 3px; font-weight: 600;" title="<?php echo htmlspecialchars($r['rejection_reason']); ?>">
                                        Reason: <?php echo htmlspecialchars($r['rejection_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($r['is_final_invoice'] || (!empty($r['booking_id']) && !empty($r['tax_invoice_number']))): ?>
                                <span class="status-badge status-approved">
                                    <i class="fas fa-check-circle"></i> Final Invoice
                                </span>
                                <div style="margin-top: 4px;">
                                    <?php if (!empty($r['tax_invoice_number'])): ?>
                                        <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Tax Invoice Number">
                                            <i class="fas fa-file-invoice-dollar"></i> <?php echo htmlspecialchars($r['tax_invoice_number']); ?>
                                        </span>
                                    <?php elseif (!empty($r['custom_invoice_number'])): ?>
                                        <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Custom Invoice Number">
                                            <i class="fas fa-file-invoice-dollar"></i> <?php echo htmlspecialchars($r['custom_invoice_number']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="status-badge status-draft">
                                    <i class="fas fa-file-invoice"></i> Draft
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($r['is_unified'])): ?>
                                <div style="margin-top: 3px;">
                                    <span style="background: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; padding: 2px 7px; border-radius: 50px; font-size: 0.65rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-link"></i> Unified Booking
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: middle; padding: 12px 14px; text-align: center; white-space: nowrap;">
                            <div style="display: inline-flex; align-items: center; gap: 4px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 3px 4px; border-radius: 8px;">
                                <?php 
                                    $viewUrl = "view_client_printing_po.php?client_id=" . $r['client_id'];
                                    if ($r['po_number']) {
                                        $viewUrl .= "&po_number=" . urlencode($r['po_number']);
                                    } else {
                                        foreach($ids as $id) $viewUrl .= "&rate_ids[]=" . $id;
                                    }
                                ?>
                                <?php if (hasRole('admin') || $r['approval_status'] === 'approved' || $r['approval_status'] === 'rejected'): ?>
                                <a href="<?php echo $viewUrl; ?>" target="_blank" class="table-action-btn action-view" title="View Details / Print Estimate">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!$r['is_final_invoice'] && $r['approval_status'] !== 'pending_approval' && empty($r['booking_id'])): ?>
                                <button type="button" class="table-action-btn action-invoice" onclick="openPrintingInvoicePopup('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', <?php echo $r['client_id']; ?>, '<?php echo implode(',', $ids); ?>')" title="Generate Final Tax Invoice">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </button>
                                <?php endif; ?>

                                <?php if ($r['is_final_invoice'] || (!empty($r['booking_id']) && !empty($r['tax_invoice_number']))): ?>
                                    <?php if (!empty($r['booking_id'])): ?>
                                        <a href="../operations/generate_invoice.php?booking_id=<?php echo $r['booking_id']; ?>" target="_blank" class="table-action-btn action-pdf" title="View Booking Tax Invoice">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    <?php else: ?>
                                        <?php 
                                            $taxInvUrl = "../operations/client_printing.php?client_id=" . $r['client_id'] . "&preview=1&is_final=1";
                                            foreach($ids as $id) $taxInvUrl .= "&rate_ids[]=" . $id;
                                        ?>
                                        <a href="<?php echo $taxInvUrl; ?>" target="_blank" class="table-action-btn action-pdf" title="View Final Tax Invoice">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ((hasRole('admin') || ($r['approval_status'] !== 'approved')) && empty($r['booking_id'])): ?>
                                <?php 
                                    $editUrl = "create_client_printing_po.php?action=edit&client_id=" . $r['client_id'];
                                    if ($r['po_number']) {
                                        $editUrl .= "&po_number=" . urlencode($r['po_number']);
                                    } else {
                                        foreach($ids as $id) $editUrl .= "&rate_ids[]=" . $id;
                                    }
                                ?>
                                <a href="<?php echo $editUrl; ?>" class="table-action-btn action-edit" title="Edit Invoice">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (canDelete('clients') && empty($r['booking_id'])): ?>
                                <button type="button" class="table-action-btn action-delete" onclick="deleteRate('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($r['rate_ids'], ENT_QUOTES); ?>')" title="Delete Invoice">
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
.table-action-btn.action-invoice:hover {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #059669;
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
        ? '../../ajax/upload_client_printing_tax_order.php' 
        : '../../ajax/upload_client_printing_attachment.php';

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

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('client_id')) {
        const cId = urlParams.get('client_id');
        window.location.href = `create_client_printing_po.php?client_id=${cId}`;
    }
    
    // Show success message if present in URL
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        let text = '';
        if (msg === 'added') text = 'Client Printing Invoice created successfully.';
        if (msg === 'updated') text = 'Client Printing Invoice updated successfully.';
        
        if (text) {
            Swal.fire({
                title: 'Success',
                text: text,
                icon: 'success',
                confirmButtonColor: '#0d9488',
                timer: 2500,
                showConfirmButton: false
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});

function deleteRate(poNumber, rateIds) {
    Swal.fire({
        title: 'Delete Client Invoice / PO?',
        text: "Are you sure you want to remove this Client Printing Invoice and all its sites?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('client_printing_rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&po_number=${encodeURIComponent(poNumber)}&rate_ids=${encodeURIComponent(rateIds)}`
            }).then(() => {
                Swal.fire('Deleted!', 'Client Printing Invoice has been removed.', 'success').then(() => location.reload());
            });
        }
    });
}

function openPrintingInvoicePopup(poNumber, clientId, rateIdsStr) {
    Swal.fire({
        title: 'Printing PO Confirmation',
        html: `
            <div style="text-align: left;">
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">INVOICE NUMBER (Last Used: <?php echo getLastSequenceNumber($pdo, 'client_printing_po'); ?>) <span style="color:red;">*</span></label>
                <input id="custom_invoice_number" class="swal2-input" value="<?php echo getPreviewSequenceNumber($pdo, 'client_printing_po'); ?>" placeholder="e.g. SCRP/26-27/0001" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">

                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">INVOICE DATE <span style="color:red;">*</span></label>
                <input id="custom_invoice_date" type="date" class="swal2-input" value="<?php echo date('Y-m-d'); ?>" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">

                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CONFIRMATION TYPE <span style="color:red;">*</span></label>
                <select id="confirmation_type" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;" onchange="toggleConfFields()">
                    <option value="po">Customer Purchase Order (PO)</option>
                    <option value="email">Email Confirmation</option>
                </select>

                <div id="po_fields">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CUSTOMER PO NUMBER <span style="color:red;">*</span></label>
                    <input id="customer_po_no" class="swal2-input" placeholder="Enter PO ID" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                    
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">PO DATE <span style="color:red;">*</span></label>
                    <input id="customer_po_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>

                <div id="email_fields" style="display:none;">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">EMAIL CONFIRMATION DATE <span style="color:red;">*</span></label>
                    <input id="email_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>
                
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">UPLOAD ATTACHMENT (PDF/IMAGE) <span style="color:red;">*</span></label>
                <input id="customer_po_file" type="file" accept=".pdf,image/*" class="swal2-file" style="margin: 0; width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px;">
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Save & Generate Invoice',
        preConfirm: () => {
            const type = document.getElementById('confirmation_type').value;
            const po_no = document.getElementById('customer_po_no').value;
            const po_date = document.getElementById('customer_po_date').value;
            const email_date = document.getElementById('email_date').value;
            const po_file = document.getElementById('customer_po_file').files[0];
            const custom_invoice_number = document.getElementById('custom_invoice_number').value;
            const custom_invoice_date = document.getElementById('custom_invoice_date').value;
            
            if (!custom_invoice_number) { Swal.showValidationMessage(`Invoice Number is mandatory`); return false; }
            if (!custom_invoice_date) { Swal.showValidationMessage(`Invoice Date is mandatory`); return false; }
            
            if (type === 'po') {
                if (!po_no) { Swal.showValidationMessage(`Customer PO Number is mandatory`); return false; }
                if (!po_date) { Swal.showValidationMessage(`PO Date is mandatory`); return false; }
            }
            if (type === 'email') {
                if (!email_date) { Swal.showValidationMessage(`Email Confirmation Date is mandatory`); return false; }
            }
            if (!po_file) {
                Swal.showValidationMessage(`Please upload the PO/Email attachment (PDF/Image)`);
                return false;
            }
            
            let formData = new FormData();
            formData.append('po_number', poNumber);
            formData.append('client_id', clientId);
            formData.append('rate_ids', rateIdsStr);
            formData.append('confirmation_type', type);
            formData.append('customer_po_no', po_no);
            formData.append('customer_po_date', po_date);
            formData.append('email_date', email_date);
            formData.append('custom_invoice_number', custom_invoice_number);
            formData.append('custom_invoice_date', custom_invoice_date);
            formData.append('customer_po_file', po_file);
            
            return fetch('../../ajax/upload_printing_po.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Upload failed');
                }
                return data;
            }).catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let resData = result.value;
            if (resData && resData.approval_status === 'pending_approval') {
                Swal.fire('Approval Sent!', 'The Client Printing Invoice has been sent for admin approval.', 'success').then(() => {
                    window.location.reload();
                });
            } else {
                let rateIds = rateIdsStr.split(',');
                let taxInvUrl = `../operations/client_printing.php?client_id=${clientId}&preview=1&is_final=1`;
                rateIds.forEach(id => taxInvUrl += `&rate_ids[]=${id}`);
                window.open(taxInvUrl, '_blank');
                window.location.reload();
            }
        }
    });
}

function toggleConfFields() {
    const type = document.getElementById('confirmation_type').value;
    const poFields = document.getElementById('po_fields');
    const emailFields = document.getElementById('email_fields');
    if (poFields) poFields.style.display = type === 'po' ? 'block' : 'none';
    if (emailFields) emailFields.style.display = type === 'email' ? 'block' : 'none';
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
