<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('bookings', 'view');

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    include_once __DIR__ . '/../../includes/trash_helper.php';
    requirePermission('bookings', 'delete');
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    try {
        $pdo->beginTransaction();
        $opStmt = $pdo->prepare("SELECT id FROM operations WHERE booking_id = ?");
        $opStmt->execute([$id]);
        while ($op = $opStmt->fetch(PDO::FETCH_ASSOC)) {
            move_row_to_trash($pdo, 'operations', 'id', $op['id'], $_SESSION['user_id'] ?? null, 'Booking deleted - operation moved to trash');
        }

        // Delete associated booking items
        $itemStmt = $pdo->prepare("SELECT id FROM booking_items WHERE booking_id = ?");
        $itemStmt->execute([$id]);
        while ($item = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
            move_row_to_trash($pdo, 'booking_items', 'id', $item['id'], $_SESSION['user_id'] ?? null, 'Booking deleted');
        }

        // Delete associated client printing records
        $printStmt = $pdo->prepare("SELECT id FROM client_printing_rates WHERE booking_id = ?");
        $printStmt->execute([$id]);
        while ($pr = $printStmt->fetch(PDO::FETCH_ASSOC)) {
            move_row_to_trash($pdo, 'client_printing_rates', 'id', $pr['id'], $_SESSION['user_id'] ?? null, 'Booking deleted');
        }

        // Delete associated client mounting records
        $mountStmt = $pdo->prepare("SELECT id FROM client_mounting_rates WHERE booking_id = ?");
        $mountStmt->execute([$id]);
        while ($mr = $mountStmt->fetch(PDO::FETCH_ASSOC)) {
            move_row_to_trash($pdo, 'client_mounting_rates', 'id', $mr['id'], $_SESSION['user_id'] ?? null, 'Booking deleted');
        }

        // Delete associated invoices
        $invStmt = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ?");
        $invStmt->execute([$id]);
        while ($inv = $invStmt->fetch(PDO::FETCH_ASSOC)) {
            move_row_to_trash($pdo, 'invoices', 'id', $inv['id'], $_SESSION['user_id'] ?? null, 'Booking deleted');
        }
        $trashId = move_row_to_trash($pdo, 'bookings', 'id', $id, $_SESSION['user_id'] ?? null, 'Booking deleted via UI');
        if (!$trashId) {
            throw new Exception('Failed to move booking to trash');
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Duplicate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'duplicate') {
    requirePermission('bookings', 'add');
    header('Content-Type: application/json');
    $id = intval($_POST['id']);

    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    $originalBooking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$originalBooking) {
        echo json_encode(['success' => false, 'message' => 'Original booking not found.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $bookingNum = generateSequenceNumber($pdo, 'booking');

        $entityId = $originalBooking['entity_id'] ?? $_SESSION['active_entity_id'] ?? null;
        if (!$entityId) {
            $stmtEntity = $pdo->query("SELECT id FROM entities LIMIT 1");
            $entityId = $stmtEntity->fetchColumn() ?: null;
        }

        $userId = $_SESSION['user_id'] ?? null;

        $insertBooking = $pdo->prepare(
            "INSERT INTO bookings (booking_number, proposal_id, client_id, entity_id, campaign_name, start_date, end_date, total_amount, tax_amount, grand_total, printing_cost, mounting_cost, status, approval_status, confirmation_type, customer_po_no, customer_po_date, email_date, customer_po_file, mounting_date, brand_name, external_po, contact_person, billing_gstin, tax_type, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'approved', ?, NULL, NULL, NULL, NULL, NULL, ?, '', ?, ?, ?, ?)"
        );

        $insertBooking->execute([
            $bookingNum,
            $originalBooking['proposal_id'],
            $originalBooking['client_id'],
            $entityId,
            $originalBooking['campaign_name'],
            $originalBooking['start_date'],
            $originalBooking['end_date'],
            $originalBooking['total_amount'],
            $originalBooking['tax_amount'],
            $originalBooking['grand_total'],
            $originalBooking['printing_cost'],
            $originalBooking['mounting_cost'],
            $originalBooking['confirmation_type'],
            $originalBooking['brand_name'],
            $originalBooking['contact_person'],
            $originalBooking['billing_gstin'],
            $originalBooking['tax_type'],
            $userId
        ]);

        $newBookingId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("SELECT proposal_item_id, site_id, purchase_rate, sale_rate, start_date, end_date, days, purchase_amount, amount, selected_image, printing_vendor_id, printing_rate, printing_amount, custom_location, custom_site_name FROM booking_items WHERE booking_id = ?");
        $itemStmt->execute([$id]);
        $insertItem = $pdo->prepare(
            "INSERT INTO booking_items (booking_id, proposal_item_id, site_id, purchase_rate, sale_rate, start_date, end_date, days, purchase_amount, amount, selected_image, printing_vendor_id, printing_rate, printing_amount, custom_location, custom_site_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        while ($item = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
            $insertItem->execute([
                $newBookingId,
                $item['proposal_item_id'],
                $item['site_id'],
                $item['purchase_rate'],
                $item['sale_rate'],
                $item['start_date'],
                $item['end_date'],
                $item['days'],
                $item['purchase_amount'],
                $item['amount'],
                $item['selected_image'],
                $item['printing_vendor_id'],
                $item['printing_rate'],
                $item['printing_amount'],
                $item['custom_location'],
                $item['custom_site_name']
            ]);
        }

        $operationStmt = $pdo->prepare("SELECT site_id, assigned_mounter_id FROM operations WHERE booking_id = ?");
        $operationStmt->execute([$id]);
        $insertOperation = $pdo->prepare(
            "INSERT INTO operations (booking_id, site_id, assigned_mounter_id, mounting_date, status, field_team_notes, proof_image)
             VALUES (?, ?, ?, NULL, 'pending', NULL, NULL)"
        );

        while ($operation = $operationStmt->fetch(PDO::FETCH_ASSOC)) {
            $insertOperation->execute([
                $newBookingId,
                $operation['site_id'],
                $operation['assigned_mounter_id']
            ]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'booking_id' => $newBookingId]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$activePage = 'bookings';
$pageTitle = 'Campaign Execution';
include_once __DIR__ . '/../../includes/header.php';

$isAdmin = hasRole('admin') || (($_SESSION['user_role'] ?? '') === 'admin');

$clientFilter = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');
$invoiceFilter = trim($_GET['invoice_status'] ?? '');
$periodFilter = trim($_GET['period'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

$where = 'WHERE 1=1';
$params = [];

$activeEntityId = $_SESSION['active_entity_id'] ?? null;
if ($activeEntityId) {
    $where .= ' AND b.entity_id = ?';
    $params[] = $activeEntityId;
}

if ($clientFilter) {
    $where .= ' AND b.client_id = ?';
    $params[] = $clientFilter;
}
if ($campaignFilter !== '') {
    $where .= ' AND b.campaign_name LIKE ?';
    $params[] = '%' . $campaignFilter . '%';
}
if ($invoiceFilter === 'draft') {
    $where .= " AND i.invoice_number IS NULL";
} elseif ($invoiceFilter === 'finalized') {
    $where .= " AND i.invoice_number IS NOT NULL";
}

// Period overlap filtering
$periodStart = null;
$periodEnd = null;
if ($periodFilter === 'today') {
    $periodStart = date('Y-m-d');
    $periodEnd = date('Y-m-d');
} elseif ($periodFilter === 'this_month') {
    $periodStart = date('Y-m-01');
    $periodEnd = date('Y-m-t');
} elseif ($periodFilter === 'last_month') {
    $periodStart = date('Y-m-01', strtotime('last month'));
    $periodEnd = date('Y-m-t', strtotime('last month'));
} elseif ($periodFilter === 'this_quarter') {
    $current_month = date('m');
    $current_year = date('Y');
    if ($current_month >= 1 && $current_month <= 3) {
        $periodStart = "$current_year-01-01"; $periodEnd = "$current_year-03-31";
    } elseif ($current_month >= 4 && $current_month <= 6) {
        $periodStart = "$current_year-04-01"; $periodEnd = "$current_year-06-30";
    } elseif ($current_month >= 7 && $current_month <= 9) {
        $periodStart = "$current_year-07-01"; $periodEnd = "$current_year-09-30";
    } else {
        $periodStart = "$current_year-10-01"; $periodEnd = "$current_year-12-31";
    }
} elseif ($periodFilter === 'this_year') {
    $currentYear = intval(date('Y'));
    $currentMonth = intval(date('n'));
    if ($currentMonth >= 4) {
        $periodStart = "$currentYear-04-01";
        $periodEnd = ($currentYear + 1) . "-03-31";
    } else {
        $periodStart = ($currentYear - 1) . "-04-01";
        $periodEnd = "$currentYear-03-31";
    }
} elseif ($periodFilter === 'custom' && !empty($fromDate) && !empty($toDate)) {
    $periodStart = $fromDate;
    $periodEnd = $toDate;
}

if ($periodStart && $periodEnd) {
    $where .= " AND b.start_date <= ? AND b.end_date >= ?";
    $params[] = $periodEnd;
    $params[] = $periodStart;
}

$bookings = $pdo->prepare("
    SELECT b.*, p.proposal_number, c.name as client_name,
           i.invoice_number, i.approval_status AS invoice_approval_status,
           i.rejection_reason AS invoice_rejection_reason,
           (SELECT SUM(sale_rate + purchase_rate) FROM booking_items WHERE booking_id = b.id) as total_rental_rate,
           (SELECT SUM(client_print_rate) FROM booking_items WHERE booking_id = b.id) as total_print_rate,
           (SELECT SUM(client_mount_rate) FROM booking_items WHERE booking_id = b.id) as total_mount_rate,
           (SELECT SUM(amount) FROM booking_items WHERE booking_id = b.id) as total_rental_amount,
           (SELECT SUM(bi.client_print_rate * COALESCE(s.width, 0) * COALESCE(s.height, 0) * COALESCE(bi.unit, 1)) FROM booking_items bi JOIN sites s ON bi.site_id = s.id WHERE bi.booking_id = b.id) as total_print_amount,
           (SELECT SUM(bi.client_mount_rate * COALESCE(s.width, 0) * COALESCE(s.height, 0) * COALESCE(bi.unit, 1)) FROM booking_items bi JOIN sites s ON bi.site_id = s.id WHERE bi.booking_id = b.id) as total_mount_amount,
           COALESCE(u_b.name, u_b.username, u_p.name, u_p.username, u_po.name, u_po.username, '') as creator_name
    FROM bookings b 
    LEFT JOIN proposals p ON b.proposal_id = p.id 
    LEFT JOIN partners c ON b.client_id = c.id 
    LEFT JOIN invoices i ON b.id = i.booking_id AND i.type = 'tax'
    LEFT JOIN users u_b ON b.created_by = u_b.id
    LEFT JOIN users u_p ON p.created_by = u_p.id
    LEFT JOIN (SELECT campaign_id, MIN(COALESCE(created_by, employee_id)) as creator_id FROM purchase_orders WHERE campaign_id IS NOT NULL GROUP BY campaign_id) po_c ON po_c.campaign_id = b.id
    LEFT JOIN users u_po ON po_c.creator_id = u_po.id
    $where
    ORDER BY b.id DESC
");
$bookings->execute($params);
$bookings = $bookings->fetchAll();

if ($activeEntityId) {
    $clients = $pdo->prepare("
        SELECT DISTINCT c.id, c.name 
        FROM partners c
        JOIN bookings b ON b.client_id = c.id
        WHERE c.type = 'client' AND b.entity_id = ?
        ORDER BY c.name ASC
    ");
    $clients->execute([$activeEntityId]);
    $clients = $clients->fetchAll();
} else {
    $clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
}
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Bookings</h2>
        <?php if (canAdd('bookings')): ?>
        <a href="direct_booking.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Direct Booking
        </a>
        <?php endif; ?>
    </div>

    <form method="get" action="bookings.php" style="display:flex; flex-wrap: wrap; gap:1rem; align-items:flex-end; margin-bottom:1.5rem;">
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Client</label>
            <select name="client_id" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:220px;">
                <option value="">All Clients</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>" <?php echo $clientFilter === intval($client['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.35rem; min-width:280px;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Campaign Name</label>
            <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..." style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:280px;">
        </div>
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Invoice Status</label>
            <select name="invoice_status" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:150px;">
                <option value="">All</option>
                <option value="draft" <?php echo $invoiceFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="finalized" <?php echo $invoiceFilter === 'finalized' ? 'selected' : ''; ?>>Finalized</option>
            </select>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Period</label>
            <select name="period" id="filter_period" onchange="toggleCustomDates(this.value)" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:160px; background: white; font-family: inherit; font-size: 0.9rem;">
                <option value="">All Time</option>
                <option value="today" <?php echo $periodFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="this_month" <?php echo $periodFilter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                <option value="last_month" <?php echo $periodFilter === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="this_quarter" <?php echo $periodFilter === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                <option value="this_year" <?php echo $periodFilter === 'this_year' ? 'selected' : ''; ?>>This Financial Year</option>
                <option value="custom" <?php echo $periodFilter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
            </select>
        </div>
        <div id="custom_date_range" style="display: <?php echo $periodFilter === 'custom' ? 'flex' : 'none'; ?>; gap:0.5rem; align-items:center;">
            <div style="display:flex; flex-direction:column; gap:0.35rem;">
                <label style="font-size:0.85rem; color:#475569; font-weight:600;">From</label>
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; background: white; font-family: inherit; font-size: 0.9rem;">
            </div>
            <div style="display:flex; flex-direction:column; gap:0.35rem;">
                <label style="font-size:0.85rem; color:#475569; font-weight:600;">To</label>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; background: white; font-family: inherit; font-size: 0.9rem;">
            </div>
        </div>
        <div style="display:flex; gap:0.75rem;">
            <button type="submit" class="btn btn-primary" style="padding:0.85rem 1.25rem;">Filter</button>
            <a href="bookings.php" class="btn" style="background:#f8fafc; color:#475569; border:1px solid #cbd5e1; padding:0.85rem 1.25rem; text-decoration:none;">Reset</a>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Booking ID</th>
                <th style="width: 100px;">Type</th>
                <th>Proposal #</th>
                <th>Campaign / Brand</th>
                <th>Client</th>
                <th>Period</th>
                <th>Amount</th>
                <th>Execution Status</th>
                <th style="text-align: center; width: 100px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--secondary); padding: 2rem;">No active bookings found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td>
                        <a href="view_booking.php?id=<?php echo $b['id']; ?>" style="text-decoration: none; color: inherit; transition: color 0.2s;" onmouseover="this.style.color='var(--primary)';" onmouseout="this.style.color='inherit';">
                            <strong><?php echo htmlspecialchars(!empty($b['booking_number']) ? $b['booking_number'] : '#BK-' . str_pad($b['id'], 4, '0', STR_PAD_LEFT)); ?></strong>
                        <?php if (!empty($b['creator_name'])): ?>
                        <div style="font-size: 0.65rem; color: #475569; font-weight: 700; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; padding: 1px 6px; border-radius: 4px;" title="Created By">
                            <i class="fas fa-user-circle" style="font-size: 0.65rem; color: #64748b;"></i> <?php echo htmlspecialchars($b['creator_name']); ?>
                        </div>
                        <?php endif; ?>
                        </a>
                    </td>
                    <td>
                        <?php if (empty($b['proposal_id'])): ?>
                            <span class="badge" style="background: #fff1f2; color: #be123c; font-size: 0.65rem; font-weight: 800; border: 1px solid #fecdd3; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase;">Direct</span>
                        <?php else: ?>
                            <span class="badge" style="background: #f0fdf4; color: #15803d; font-size: 0.65rem; font-weight: 800; border: 1px solid #dcfce7; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase;">System</span>
                        <?php endif; ?>
                        
                        <div style="display: flex; flex-direction: column; gap: 0.3rem; margin-top: 0.6rem;">
                            <?php if (floatval($b['total_rental_rate'] ?? 0) > 0 || (floatval($b['total_print_rate'] ?? 0) == 0 && floatval($b['total_mount_rate'] ?? 0) == 0)): ?>
                                <span style="font-size: 0.6rem; font-weight: 800; color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 0.15rem 0.4rem; border-radius: 4px; display: inline-flex; align-items: center; width: max-content; text-transform: uppercase;">
                                    <i class="fas fa-building" style="margin-right: 0.3rem;"></i> Rental
                                </span>
                            <?php endif; ?>
                            
                            <?php if (floatval($b['total_print_rate'] ?? 0) > 0): ?>
                                <span style="font-size: 0.6rem; font-weight: 800; color: #0d9488; background: #f0fdfa; border: 1px solid #ccfbf1; padding: 0.15rem 0.4rem; border-radius: 4px; display: inline-flex; align-items: center; width: max-content; text-transform: uppercase;">
                                    <i class="fas fa-print" style="margin-right: 0.3rem;"></i> Print
                                </span>
                            <?php endif; ?>
                            
                            <?php if (floatval($b['total_mount_rate'] ?? 0) > 0): ?>
                                <span style="font-size: 0.6rem; font-weight: 800; color: #ea580c; background: #fff7ed; border: 1px solid #ffedd5; padding: 0.15rem 0.4rem; border-radius: 4px; display: inline-flex; align-items: center; width: max-content; text-transform: uppercase;">
                                    <i class="fas fa-tools" style="margin-right: 0.3rem;"></i> Mount
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="font-weight: 600; color: #64748b;">
                        <?php if (!empty($b['proposal_number'])): ?>
                            <?php echo $b['proposal_number']; ?>
                        <?php elseif (!empty($b['external_po'])): ?>
                            <span style="color: #0d9488; font-size: 0.75rem;"><i class="fas fa-tag"></i> <?php echo $b['external_po']; ?></span>
                        <?php else: ?>
                            <span style="color: #cbd5e1; font-weight: 400;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo $b['campaign_name']; ?></div>
                        <?php if (!empty($b['brand_name'])): ?>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php echo $b['brand_name']; ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $b['client_name']; ?></td>
                    <td style="font-size: 0.875rem; white-space: nowrap;">
                        <?php 
                        if (!empty($b['start_date']) && $b['start_date'] !== '0000-00-00' && !empty($b['end_date']) && $b['end_date'] !== '0000-00-00') {
                            echo date('d M', strtotime($b['start_date'])) . ' - ' . date('d M Y', strtotime($b['end_date']));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                    <td style="font-size: 0.85rem; line-height: 1.4; white-space: nowrap; text-align: left; vertical-align: middle;">
                        <?php if (floatval($b['total_rental_amount'] ?? 0) > 0 || floatval($b['total_print_amount'] ?? 0) > 0 || floatval($b['total_mount_amount'] ?? 0) > 0): ?>
                            <?php if (floatval($b['total_rental_amount'] ?? 0) > 0): ?>
                                <div style="font-size: 0.75rem; color: #475569; display: flex; justify-content: space-between; gap: 8px;">
                                    <span>Rent:</span> <span style="font-weight: 600;"><?php echo formatCurrency($b['total_rental_amount']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (floatval($b['total_print_amount'] ?? 0) > 0): ?>
                                <div style="font-size: 0.75rem; color: #0d9488; display: flex; justify-content: space-between; gap: 8px;">
                                    <span>Print:</span> <span style="font-weight: 600;"><?php echo formatCurrency($b['total_print_amount']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (floatval($b['total_mount_amount'] ?? 0) > 0): ?>
                                <div style="font-size: 0.75rem; color: #ea580c; display: flex; justify-content: space-between; gap: 8px;">
                                    <span>Mount:</span> <span style="font-weight: 600;"><?php echo formatCurrency($b['total_mount_amount']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div style="border-top: 1px dashed #cbd5e1; margin: 4px 0;"></div>
                        <?php endif; ?>
                        <div style="font-weight: 500; color: #64748b;">Base: <span style="font-weight: 700; color: #334155;"><?php echo formatCurrency($b['total_amount']); ?></span></div>
                        <div style="font-weight: 500; color: #64748b;">Tax: <span style="font-weight: 700; color: #334155;"><?php echo formatCurrency($b['tax_amount']); ?></span></div>
                        <div style="font-weight: 700; color: #059669; border-top: 1px dashed #cbd5e1; margin-top: 2px; padding-top: 2px;">Total: <span><?php echo formatCurrency($b['grand_total']); ?></span></div>
                    </td>
                    <td>
                        <span class="exec-status status-<?php echo $b['status']; ?>">
                            <?php echo ucfirst($b['status']); ?>
                        </span>
                        <?php if (($b['approval_status'] ?? '') === 'pending_approval'): ?>
                            <div style="margin-top: 4px;">
                                <span style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;">
                                    <i class="fas fa-clock"></i> Awaiting Approval
                                </span>
                            </div>
                        <?php elseif (($b['approval_status'] ?? '') === 'rejected'): ?>
                            <div style="margin-top: 4px;">
                                <span style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;" title="<?php echo htmlspecialchars($b['rejection_reason'] ?? ''); ?>">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 6px; display: inline-flex; align-items: center; gap: 4px;">
                            <?php if (!empty($b['invoice_number'])): ?>
                                <?php if ($b['invoice_approval_status'] === 'approved'): ?>
                                    <a href="generate_invoice.php?booking_id=<?php echo $b['id']; ?>" target="_blank" style="text-decoration: none; display: inline-flex;">
                                        <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#d1fae5';" onmouseout="this.style.background='#ecfdf5';" title="Invoice Approved - Click to View">
                                            <i class="fas fa-file-invoice-dollar"></i> <?php echo htmlspecialchars($b['invoice_number']); ?>
                                        </span>
                                    </a>
                                <?php elseif ($b['invoice_approval_status'] === 'rejected'): ?>
                                    <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 4px;">
                                        <span class="badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Invoice Rejected (Cannot View)">
                                            <i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($b['invoice_number']); ?> (Rejected)
                                        </span>
                                        <?php if (!empty($b['invoice_rejection_reason'])): ?>
                                            <span style="font-size: 0.65rem; color: #b91c1c; font-weight: 600; max-width: 150px; line-height: 1.2;">Reason: <?php echo htmlspecialchars($b['invoice_rejection_reason']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <a href="generate_invoice.php?booking_id=<?php echo $b['id']; ?>" target="_blank" style="text-decoration: none; display: inline-flex;">
                                        <span class="badge" style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#ffedd5';" onmouseout="this.style.background='#fff7ed';" title="Invoice Pending Approval - Click to View">
                                            <i class="fas fa-clock"></i> <?php echo htmlspecialchars($b['invoice_number']); ?> (Pending)
                                        </span>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasRole('admin')): ?>
                                    <button type="button" onclick="syncBookingInvoice(<?php echo $b['id']; ?>)" class="btn-icon" style="color: #0d9488; background: #f0fdfa; border: 1px solid #ccfbf1; padding: 2px 5px; border-radius: 4px; font-size: 0.65rem; margin: 0; cursor: pointer;" title="Sync Invoice & Ledger Amount (Admin Only)">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Tax Invoice Not Generated Yet">
                                    <i class="fas fa-file-invoice"></i> Draft
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="text-align: center; width: 120px; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: center;">
                            <?php if ($isAdmin): ?>
                            <button type="button" class="btn-icon" onclick="openInvoicePopup(<?php echo $b['id']; ?>)" title="Campaign Confirmation & Attachments (Admin Only)" style="margin: 0; color: #2563eb; background: #eff6ff; border-color: #bfdbfe;"><i class="fas fa-paperclip"></i></button>
                            <?php endif; ?>
                            <?php if (canEdit('bookings')): ?>
                            <a href="view_booking.php?id=<?php echo $b['id']; ?>" class="btn-icon btn-edit" title="Edit Booking" style="margin: 0; color: #0284c7; background: #f0f9ff; border-color: #e0f2fe;"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <?php if (canAdd('bookings')): ?>
                            <button class="btn-icon btn-copy" onclick="openCloneBookingModal(<?php echo $b['id']; ?>)" title="Clone / Renew Campaign" style="margin: 0;"><i class="fas fa-copy"></i></button>
                            <?php endif; ?>
                            <?php if (canDelete('bookings')): ?>
                            <button class="btn-icon btn-delete" onclick="deleteBooking(<?php echo $b['id']; ?>)" title="Delete Booking" style="margin: 0;"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ========================================================================= -->
<!-- SMART CLONE / CAMPAIGN RENEWAL MODAL                                      -->
<!-- ========================================================================= -->
<div id="smartCloneModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 820px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #e2e8f0; overflow: hidden; animation: cloneModalIn 0.25s ease-out;">
        <!-- Header -->
        <div style="padding: 1.25rem 1.5rem; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(14, 165, 233, 0.2); border: 1px solid rgba(14, 165, 233, 0.4); display: flex; align-items: center; justify-content: center; color: #38bdf8; font-size: 1.1rem;">
                    <i class="fas fa-copy"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #ffffff;">Clone / Renew Campaign</h3>
                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">
                        Source: <span id="clone_src_booking_no" style="color: #38bdf8; font-weight: 700;">-</span> &bull; Client: <span id="clone_src_client" style="color: #f1f5f9; font-weight: 600;">-</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeCloneBookingModal()" style="background: rgba(255,255,255,0.1); border: none; color: #94a3b8; font-size: 1.2rem; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.color='#ffffff';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.color='#94a3b8';">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body (Scrollable) -->
        <div id="cloneModalContent" style="padding: 1.5rem; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 1.25rem;">
            <!-- Loading State -->
            <div id="cloneModalLoading" style="text-align: center; padding: 3rem 1rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #0284c7; margin-bottom: 0.75rem;"></i>
                <div style="font-size: 0.9rem; color: #64748b; font-weight: 600;">Loading campaign assets and rate details...</div>
            </div>

            <!-- Form Content -->
            <div id="cloneModalForm" style="display: none; flex-direction: column; gap: 1.25rem;">
                <!-- 1. Campaign Name -->
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-bullhorn" style="color: #0284c7; margin-right: 4px;"></i> New Campaign Title <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="clone_campaign_name" class="form-control" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 0.95rem; font-weight: 600; color: #0f172a; box-sizing: border-box;" placeholder="Enter new campaign title..." />
                </div>

                <!-- 2. Campaign Duration & Dates -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">
                            <i class="fas fa-calendar-alt" style="color: #0d9488; margin-right: 4px;"></i> New Campaign Period <span style="color: #ef4444;">*</span>
                        </label>
                        <span id="clone_duration_badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700;">
                            <i class="fas fa-clock"></i> 30 Days
                        </span>
                    </div>

                    <!-- Quick Preset Buttons -->
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
                        <button type="button" class="clone-preset-btn active" id="btnPresetConsecutive" onclick="applyCloneDatePreset('consecutive')" style="flex: 1; min-width: 140px; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1.5px solid #0284c7; background: #f0f9ff; color: #0369a1; text-align: center; transition: all 0.2s;">
                            <i class="fas fa-forward"></i> Next Consecutive Month
                        </button>
                        <button type="button" class="clone-preset-btn" id="btnPresetToday" onclick="applyCloneDatePreset('today')" style="flex: 1; min-width: 140px; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1.5px solid #e2e8f0; background: #ffffff; color: #475569; text-align: center; transition: all 0.2s;">
                            <i class="fas fa-calendar-day"></i> Next 30 Days From Today
                        </button>
                        <button type="button" class="clone-preset-btn" id="btnPresetCustom" onclick="applyCloneDatePreset('custom')" style="flex: 1; min-width: 140px; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border: 1.5px solid #e2e8f0; background: #ffffff; color: #475569; text-align: center; transition: all 0.2s;">
                            <i class="fas fa-sliders-h"></i> Custom Dates
                        </button>
                    </div>

                    <!-- Date Inputs -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <span style="font-size: 0.75rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">Start Date:</span>
                            <input type="date" id="clone_start_date" onchange="onCloneDateChanged()" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 0.55rem 0.75rem; font-size: 0.875rem; font-weight: 600; color: #0f172a; box-sizing: border-box; background: #ffffff;" />
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">End Date:</span>
                            <input type="date" id="clone_end_date" onchange="onCloneDateChanged()" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 0.55rem 0.75rem; font-size: 0.875rem; font-weight: 600; color: #0f172a; box-sizing: border-box; background: #ffffff;" />
                        </div>
                    </div>
                </div>

                <!-- 3. Sites to Clone / Renew -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">
                            <i class="fas fa-map-marker-alt" style="color: #e11d48; margin-right: 4px;"></i> Select Sites to Renew / Clone (<span id="clone_selected_sites_count">0</span> Selected)
                        </label>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #0284c7; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; user-select: none;">
                            <input type="checkbox" id="clone_select_all_sites" onchange="toggleAllCloneSites(this.checked)" style="width: 16px; height: 16px; accent-color: #0284c7; cursor: pointer;" checked /> Select All Sites
                        </label>
                    </div>

                    <!-- Scrollable Site List -->
                    <div id="clone_sites_list_container" style="max-height: 230px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; padding: 0.5rem; display: flex; flex-direction: column; gap: 0.4rem;">
                        <!-- Injected via JS -->
                    </div>
                </div>

                <!-- 4. Additional Options -->
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 1rem;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 600; color: #334155; cursor: pointer; user-select: none; margin: 0;">
                        <input type="checkbox" id="clone_copy_print_mount" checked style="width: 16px; height: 16px; accent-color: #0d9488; cursor: pointer;" />
                        <span>Copy Client Printing & Mounting Rates</span>
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 600; color: #334155; cursor: pointer; user-select: none; margin: 0;">
                        <input type="checkbox" id="clone_copy_mounters" checked style="width: 16px; height: 16px; accent-color: #0284c7; cursor: pointer;" />
                        <span>Assign Same Field Mounters</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Footer / Action Bar -->
        <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div id="cloneModalFooterStats" style="display: none; align-items: center; gap: 1.25rem;">
                <div style="font-size: 0.8rem; color: #64748b;">
                    Est. Base: <strong id="clone_est_base" style="color: #0f172a;">₹0.00</strong>
                </div>
                <div style="font-size: 0.8rem; color: #64748b;">
                    GST (18%): <strong id="clone_est_tax" style="color: #0f172a;">₹0.00</strong>
                </div>
                <div style="font-size: 0.95rem; font-weight: 800; color: #059669; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.25rem 0.65rem; border-radius: 6px;">
                    Grand Total: <span id="clone_est_grand">₹0.00</span>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-left: auto;">
                <button type="button" onclick="closeCloneBookingModal()" style="padding: 0.6rem 1.2rem; border-radius: 8px; border: 1.5px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='#ffffff';">
                    Cancel
                </button>
                <button type="button" id="btnSubmitClone" onclick="submitCloneBooking()" style="padding: 0.6rem 1.4rem; border-radius: 8px; border: none; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.3); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-1px)';" onmouseout="this.style.transform='translateY(0)';">
                    <i class="fas fa-rocket"></i> Create Cloned Booking
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes cloneModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.exec-status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-pending { background: #fef9c3; color: #854d0e; }
.status-mounting { background: #dcfce7; color: #166534; }
.status-active { background: #e0f2fe; color: #0369a1; }
.status-completed { background: #f1f5f9; color: #475569; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
@keyframes pulse-approval { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.btn-icon.btn-copy { color: #0284c7; background: #f0f9ff; border-color: #e0f2fe; }
.btn-icon.btn-copy:hover { background: #e0f2fe; color: #0369a1; }
.clone-site-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
    background: #ffffff;
    transition: all 0.15s ease;
}
.clone-site-card:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.clone-site-card.selected {
    background: #f0f9ff;
    border-color: #bae6fd;
}
</style>

<script>
function deleteBooking(id) {
    Swal.fire({
        title: 'Cancel Booking?',
        text: "This will delete the booking and all related operational tasks. Proceed?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete booking'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('bookings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Booking has been cancelled and removed.', 'success').then(() => location.reload());
            });
        }
    });
}

// Global Clone State
let currentCloneData = null;

function openCloneBookingModal(bookingId) {
    const modal = document.getElementById('smartCloneModal');
    const loading = document.getElementById('cloneModalLoading');
    const form = document.getElementById('cloneModalForm');
    const footerStats = document.getElementById('cloneModalFooterStats');
    const btnSubmit = document.getElementById('btnSubmitClone');

    modal.style.display = 'flex';
    loading.style.display = 'block';
    form.style.display = 'none';
    footerStats.style.display = 'none';
    btnSubmit.disabled = true;

    fetch(`../../ajax/get_booking_clone_info.php?id=${bookingId}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                Swal.fire('Error', res.message || 'Could not load booking details.', 'error');
                closeCloneBookingModal();
                return;
            }

            currentCloneData = res;
            populateCloneModal(res);

            loading.style.display = 'none';
            form.style.display = 'flex';
            footerStats.style.display = 'flex';
            btnSubmit.disabled = false;
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Failed to communicate with server.', 'error');
            closeCloneBookingModal();
        });
}

function closeCloneBookingModal() {
    document.getElementById('smartCloneModal').style.display = 'none';
    currentCloneData = null;
}

function populateCloneModal(data) {
    const b = data.booking;
    document.getElementById('clone_src_booking_no').innerText = b.booking_number || `#BK-${b.id}`;
    document.getElementById('clone_src_client').innerText = b.client_name || 'Direct';
    document.getElementById('clone_campaign_name').value = b.campaign_name || b.suggested_name || '';

    // Apply consecutive preset by default
    applyCloneDatePreset('consecutive');

    // Populate Sites
    const listContainer = document.getElementById('clone_sites_list_container');
    listContainer.innerHTML = '';

    if (!data.items || data.items.length === 0) {
        listContainer.innerHTML = '<div style="text-align:center; padding: 1rem; color: #94a3b8; font-size: 0.85rem;">No sites found in this booking.</div>';
        return;
    }

    data.items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'clone-site-card selected';
        div.id = `clone_site_card_${item.site_id}`;

        const unitLabel = (item.unit > 1) ? ` &bull; <span style="color:#0284c7; font-weight:700;">${item.unit} Units</span>` : '';
        const mounterLabel = item.mounter_name ? `<span style="font-size: 0.7rem; background:#f1f5f9; color:#475569; padding:1px 5px; border-radius:4px; margin-left:4px;"><i class="fas fa-user-check"></i> ${escapeHtml(item.mounter_name)}</span>` : '';

        const origAmount = parseFloat(item.amount || 0);
        const origDays = parseInt(item.days || 30) || 30;
        const monthlyRate = parseFloat(item.monthly_rate || (origDays === 30 || origDays === 31 ? origAmount : (origAmount / origDays) * 30) || (item.sale_rate * (item.unit || 1)) || 0);

        div.innerHTML = `
            <input type="checkbox" class="clone-site-checkbox" value="${item.site_id}" onchange="onCloneSiteToggle(${item.site_id})" checked style="width: 18px; height: 18px; accent-color: #0284c7; cursor: pointer; margin-top: 4px;" />
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <span style="background: #0f172a; color: #ffffff; font-size: 0.65rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;">${escapeHtml(item.site_code || 'SITE')}</span>
                    <strong style="font-size: 0.85rem; color: #1e293b;">${escapeHtml(item.site_name)}</strong>
                    <span style="font-size: 0.75rem; color: #64748b;">(${escapeHtml(item.location || item.city || '')})</span>
                    ${mounterLabel}
                </div>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                    ${item.width} x ${item.height} ft (${item.sqft} sqft)${unitLabel} &bull; Booking Rate: <strong style="color: #0284c7;">₹${monthlyRate.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong> / mo
                </div>
            </div>
            <div style="text-align: right; white-space: nowrap; display: flex; align-items: center; gap: 4px;">
                <span style="font-size: 0.8rem; font-weight: 700; color: #64748b;">₹</span>
                <input type="number" step="0.01" class="clone-site-amount-input" id="clone_site_amt_${item.site_id}" 
                       data-site-id="${item.site_id}" 
                       data-orig-amount="${origAmount}" 
                       data-orig-days="${origDays}" 
                       value="${origAmount.toFixed(2)}" 
                       oninput="onCloneAmountManualEdit(${item.site_id})"
                       style="width: 110px; text-align: right; font-weight: 800; color: #059669; font-size: 0.9rem; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 4px 6px; outline: none; background: #ffffff;"
                       title="Rental amount for this site (exact from booking, editable)" />
            </div>
        `;
        listContainer.appendChild(div);
    });

    recalculateCloneTotals(false);
}

function onCloneAmountManualEdit(siteId) {
    const input = document.getElementById(`clone_site_amt_${siteId}`);
    if (input) {
        input.dataset.manualEdit = '1';
        input.style.borderColor = '#0284c7';
    }
    recalculateCloneTotals(false);
}

function applyCloneDatePreset(preset) {
    document.querySelectorAll('.clone-preset-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.borderColor = '#e2e8f0';
        btn.style.background = '#ffffff';
        btn.style.color = '#475569';
    });

    if (!currentCloneData || !currentCloneData.booking) return;
    const b = currentCloneData.booking;

    const btn = document.getElementById(`btnPreset${preset.charAt(0).toUpperCase() + preset.slice(1)}`);
    if (btn) {
        btn.classList.add('active');
        btn.style.borderColor = '#0284c7';
        btn.style.background = '#f0f9ff';
        btn.style.color = '#0369a1';
    }

    if (preset === 'consecutive') {
        document.getElementById('clone_start_date').value = b.consecutive_start;
        document.getElementById('clone_end_date').value = b.consecutive_end;
    } else if (preset === 'today') {
        document.getElementById('clone_start_date').value = b.today_start;
        document.getElementById('clone_end_date').value = b.today_end;
    }

    onCloneDateChanged();
}

function onCloneDateChanged() {
    const startDate = document.getElementById('clone_start_date').value;
    const endDate = document.getElementById('clone_end_date').value;

    let days = 30;
    if (startDate && endDate) {
        const s = new Date(startDate);
        const e = new Date(endDate);
        const diffTime = e - s;
        if (diffTime >= 0) {
            days = Math.round(diffTime / (1000 * 60 * 60 * 24)) + 1;
        } else {
            days = 0;
        }
    }

    const badge = document.getElementById('clone_duration_badge');
    badge.innerHTML = `<i class="fas fa-clock"></i> ${days} Days`;
    if (days <= 0) {
        badge.style.background = '#fee2e2';
        badge.style.color = '#b91c1c';
        badge.style.borderColor = '#fca5a5';
    } else {
        badge.style.background = '#e0f2fe';
        badge.style.color = '#0369a1';
        badge.style.borderColor = '#bae6fd';
    }

    recalculateCloneTotals(true);
}

function onCloneSiteToggle(siteId) {
    const card = document.getElementById(`clone_site_card_${siteId}`);
    const chk = card ? card.querySelector('.clone-site-checkbox') : null;
    if (chk && card) {
        if (chk.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
    recalculateCloneTotals(false);
}

function toggleAllCloneSites(checked) {
    document.querySelectorAll('.clone-site-checkbox').forEach(chk => {
        chk.checked = checked;
        const card = document.getElementById(`clone_site_card_${chk.value}`);
        if (card) {
            if (checked) card.classList.add('selected');
            else card.classList.remove('selected');
        }
    });
    recalculateCloneTotals(false);
}

function recalculateCloneTotals(isDateTriggered = false) {
    if (!currentCloneData || !currentCloneData.items) return;

    const startDate = document.getElementById('clone_start_date').value;
    const endDate = document.getElementById('clone_end_date').value;

    let days = 30;
    if (startDate && endDate) {
        const s = new Date(startDate);
        const e = new Date(endDate);
        const diffTime = e - s;
        if (diffTime >= 0) {
            days = Math.round(diffTime / (1000 * 60 * 60 * 24)) + 1;
        } else {
            days = 0;
        }
    }

    let selectedCount = 0;
    let totalBase = 0;

    const checkboxes = document.querySelectorAll('.clone-site-checkbox');
    checkboxes.forEach(chk => {
        const siteId = parseInt(chk.value);
        const item = currentCloneData.items.find(i => i.site_id === siteId);
        if (!item) return;

        const amtInput = document.getElementById(`clone_site_amt_${siteId}`);
        const origAmount = parseFloat(item.amount || 0);
        const origDays = parseInt(item.days || 30) || 30;
        const unit = item.unit || 1;

        if (isDateTriggered && amtInput && !amtInput.dataset.manualEdit) {
            let calculatedSiteAmount = origAmount;
            if (origAmount > 0) {
                // If standard 1-month renewal or exact duration match: keep exact same booking amount
                if (days === origDays || (origDays >= 28 && origDays <= 31 && days >= 28 && days <= 31)) {
                    calculatedSiteAmount = origAmount;
                } else if (days > 0) {
                    calculatedSiteAmount = (origAmount / (origDays >= 28 && origDays <= 31 ? 30 : origDays)) * days;
                }
            } else {
                const saleRate = parseFloat(item.sale_rate || 0);
                calculatedSiteAmount = (days === 30) ? (saleRate * unit) : ((saleRate / 30.0) * days * unit);
            }
            amtInput.value = calculatedSiteAmount.toFixed(2);
        }

        const currentAmt = amtInput ? parseFloat(amtInput.value || 0) : origAmount;

        if (chk.checked && days > 0) {
            selectedCount++;
            totalBase += currentAmt;
        }
    });

    const taxAmount = totalBase * 0.18;
    const grandTotal = totalBase + taxAmount;

    document.getElementById('clone_selected_sites_count').innerText = selectedCount;
    document.getElementById('clone_est_base').innerText = `₹${totalBase.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    document.getElementById('clone_est_tax').innerText = `₹${taxAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    document.getElementById('clone_est_grand').innerText = `₹${grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const selectAllChk = document.getElementById('clone_select_all_sites');
    if (selectAllChk) {
        selectAllChk.checked = (selectedCount === checkboxes.length && checkboxes.length > 0);
    }
}

function submitCloneBooking() {
    if (!currentCloneData || !currentCloneData.booking) return;

    const campaignName = document.getElementById('clone_campaign_name').value.trim();
    const startDate = document.getElementById('clone_start_date').value;
    const endDate = document.getElementById('clone_end_date').value;
    const copyPrintMount = document.getElementById('clone_copy_print_mount').checked;
    const copyMounters = document.getElementById('clone_copy_mounters').checked;

    if (!campaignName) {
        Swal.fire('Required Field', 'Please enter a campaign title for the cloned booking.', 'warning');
        return;
    }
    if (!startDate || !endDate) {
        Swal.fire('Required Field', 'Please select both start date and end date.', 'warning');
        return;
    }
    if (new Date(endDate) < new Date(startDate)) {
        Swal.fire('Invalid Date Range', 'End date cannot be earlier than start date.', 'warning');
        return;
    }

    const selectedSiteIds = [];
    const siteAmounts = {};
    document.querySelectorAll('.clone-site-checkbox:checked').forEach(chk => {
        const siteId = parseInt(chk.value);
        selectedSiteIds.push(siteId);
        const amtInput = document.getElementById(`clone_site_amt_${siteId}`);
        if (amtInput) {
            siteAmounts[siteId] = parseFloat(amtInput.value || 0);
        }
    });

    if (selectedSiteIds.length === 0) {
        Swal.fire('No Sites Selected', 'Please select at least one site to include in the cloned booking.', 'warning');
        return;
    }

    const payload = {
        original_booking_id: currentCloneData.booking.id,
        campaign_name: campaignName,
        start_date: startDate,
        end_date: endDate,
        selected_site_ids: selectedSiteIds,
        site_amounts: siteAmounts,
        copy_printing_mounting: copyPrintMount ? 1 : 0,
        copy_mounters: copyMounters ? 1 : 0
    };

    const btnSubmit = document.getElementById('btnSubmitClone');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cloned Campaign Generating...';

    fetch('../../ajax/clone_booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-rocket"></i> Create Cloned Booking';

        if (res.success) {
            closeCloneBookingModal();
            Swal.fire({
                title: 'Campaign Cloned!',
                html: `<strong>${escapeHtml(res.booking_number || 'New Booking')}</strong> has been created successfully.<br><small style="color: #64748b;">Opening campaign execution details...</small>`,
                icon: 'success',
                timer: 1800,
                showConfirmButton: false
            }).then(() => {
                window.location.href = `view_booking.php?id=${res.booking_id}`;
            });
        } else {
            Swal.fire('Error', res.message || 'Could not clone campaign.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-rocket"></i> Create Cloned Booking';
        Swal.fire('Error', 'Network or server error during campaign cloning.', 'error');
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function toggleCustomDates(val) {
    document.getElementById('custom_date_range').style.display = val === 'custom' ? 'flex' : 'none';
}

function syncBookingInvoice(id) {
    Swal.fire({
        title: 'Sync Invoice & Ledger?',
        text: 'This will recalculate all booking item amounts and update the Tax Invoice and Client Ledger.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Sync Now'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Syncing...',
                text: 'Recalculating booking financials, invoice, and ledger...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            const fd = new FormData();
            fd.append('booking_id', id);
            fetch('../../ajax/sync_booking_invoice.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('Synced!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Sync failed', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network or server error during sync', 'error');
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

