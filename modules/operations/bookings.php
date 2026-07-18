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

        $insertBooking = $pdo->prepare(
            "INSERT INTO bookings (booking_number, proposal_id, client_id, entity_id, campaign_name, start_date, end_date, total_amount, tax_amount, grand_total, printing_cost, mounting_cost, status, approval_status, confirmation_type, customer_po_no, customer_po_date, email_date, customer_po_file, mounting_date, brand_name, external_po, contact_person, billing_gstin, tax_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'approved', ?, NULL, NULL, NULL, NULL, NULL, ?, '', ?, ?, ?)"
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
            $originalBooking['tax_type']
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

$clientFilter = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');

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

$bookings = $pdo->prepare("
    SELECT b.*, p.proposal_number, c.name as client_name,
           i.invoice_number, i.approval_status AS invoice_approval_status,
           i.rejection_reason AS invoice_rejection_reason,
           (SELECT COALESCE(SUM(amount), 0) FROM booking_items WHERE booking_id = b.id) as space_cost,
           (SELECT COALESCE(SUM(printing_amount), 0) FROM booking_items WHERE booking_id = b.id) as print_cost,
           (SELECT COALESCE(SUM(mounting_amount), 0) FROM booking_items WHERE booking_id = b.id) as mount_cost
    FROM bookings b 
    LEFT JOIN proposals p ON b.proposal_id = p.id 
    LEFT JOIN partners c ON b.client_id = c.id 
    LEFT JOIN invoices i ON b.id = i.booking_id AND i.type = 'tax'
    $where
    ORDER BY b.id DESC
");
$bookings->execute($params);
$bookings = $bookings->fetchAll();

$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
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
                        </a>
                    </td>
                    <td>
                        <?php if (empty($b['proposal_id'])): ?>
                            <span class="badge" style="background: #fff1f2; color: #be123c; font-size: 0.65rem; font-weight: 800; border: 1px solid #fecdd3; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase;">Direct</span>
                        <?php else: ?>
                            <span class="badge" style="background: #f0fdf4; color: #15803d; font-size: 0.65rem; font-weight: 800; border: 1px solid #dcfce7; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase;">System</span>
                        <?php endif; ?>
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
                        <?php if (floatval($b['space_cost']) > 0): ?>
                            <div style="font-size: 0.75rem; font-weight: 500; color: #64748b;">Rental: <span style="font-weight: 700; color: #475569;"><?php echo formatCurrency($b['space_cost']); ?></span></div>
                        <?php endif; ?>
                        <?php if (floatval($b['print_cost']) > 0): ?>
                            <div style="font-size: 0.75rem; font-weight: 500; color: #64748b;">Print: <span style="font-weight: 700; color: #475569;"><?php echo formatCurrency($b['print_cost']); ?></span></div>
                        <?php endif; ?>
                        <?php if (floatval($b['mount_cost']) > 0): ?>
                            <div style="font-size: 0.75rem; font-weight: 500; color: #64748b;">Mount: <span style="font-weight: 700; color: #475569;"><?php echo formatCurrency($b['mount_cost']); ?></span></div>
                        <?php endif; ?>
                        <div style="font-weight: 500; color: #64748b; border-top: 1px dashed #cbd5e1; margin-top: 2px; padding-top: 2px;">Base: <span style="font-weight: 700; color: #334155;"><?php echo formatCurrency($b['total_amount']); ?></span></div>
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
                        
                        <div style="margin-top: 6px;">
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
                            <?php else: ?>
                                <span class="badge" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;" title="No Invoice Generated Yet">
                                    <i class="fas fa-file-excel"></i> Invoice: No
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="text-align: center; width: 100px; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: center;">
                            <?php if (canEdit('bookings')): ?>
                            <a href="direct_booking.php?action=edit&id=<?php echo $b['id']; ?>" class="btn-icon btn-edit" title="Edit Booking" style="margin: 0; color: #0284c7; background: #f0f9ff; border-color: #e0f2fe;"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <?php if (canAdd('bookings')): ?>
                            <button class="btn-icon btn-copy" onclick="duplicateBooking(<?php echo $b['id']; ?>)" title="Copy Booking" style="margin: 0;"><i class="fas fa-copy"></i></button>
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

<style>
.exec-status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-pending { background: #fef9c3; color: #854d0e; }
.status-mounting { background: #dcfce7; color: #166534; }
.status-active { background: #e0f2fe; color: #0369a1; }
.status-completed { background: #f1f5f9; color: #475569; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
@keyframes pulse-approval { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.btn-icon.btn-copy { color: #0284c7; background: #f0f9ff; border-color: #e0f2fe; }
.btn-icon.btn-copy:hover { background: #e0f2fe; color: #0369a1; }
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

function duplicateBooking(id) {
    Swal.fire({
        title: 'Copy Booking?',
        text: 'This will create a new booking copy for editing and publishing later.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0f172a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Create Copy'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('bookings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=duplicate&id=${id}`
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('Copied!', 'A new booking draft has been created.', 'success').then(() => {
                        window.location.href = `view_booking.php?id=${res.booking_id}`;
                    });
                } else {
                    Swal.fire('Error', res.message || 'Could not duplicate booking.', 'error');
                }
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
