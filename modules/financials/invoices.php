<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    include_once __DIR__ . '/../../config/db.php';
    include_once __DIR__ . '/../../includes/functions.php';
    include_once __DIR__ . '/../../includes/trash_helper.php';
    checkAuth();
    requirePermission('financials', 'delete');
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    try {
        $pdo->beginTransaction();
        
        // Find the booking ID and invoice number associated with this invoice
        $stmt = $pdo->prepare("SELECT booking_id, invoice_number FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $invoiceData = $stmt->fetch();
        
        if ($invoiceData) {
            $bookingId = $invoiceData['booking_id'];
            $invoiceNumber = $invoiceData['invoice_number'];

            // Move invoice items to trash first so they can be restored with the invoice
            $itemStmt = $pdo->prepare("SELECT id FROM invoice_items WHERE invoice_id = ?");
            $itemStmt->execute([$id]);
            while ($item = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
                move_row_to_trash($pdo, 'invoice_items', 'id', $item['id'], $_SESSION['user_id'] ?? null, 'Invoice deleted - item moved to trash');
            }
            
            // Move the invoice itself to trash
            move_row_to_trash($pdo, 'invoices', 'id', $id, $_SESSION['user_id'] ?? null, 'Invoice deleted via invoice UI');
            
            logActivity('deleted invoice', 'financials', $id, "Invoice $invoiceNumber was deleted. Booking #$bookingId reverted to editable state.");
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$activePage = 'invoices';
$pageTitle = 'Financials - Invoices';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

// Pagination Logic
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$clientFilter = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');
$periodFilter = $_GET['period'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

$where = 'WHERE 1=1';
$params = [];

$activeEntityId = $_SESSION['active_entity_id'] ?? null;
if ($activeEntityId) {
    $where .= ' AND i.entity_id = ?';
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

// Period filtering
if ($periodFilter === 'today') {
    $where .= " AND DATE(i.created_at) = CURDATE()";
} elseif ($periodFilter === 'this_month') {
    $where .= " AND i.created_at >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY)";
} elseif ($periodFilter === 'last_month') {
    $where .= " AND i.created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY), INTERVAL 1 MONTH) 
                AND i.created_at < DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY)";
} elseif ($periodFilter === 'this_quarter') {
    $where .= " AND QUARTER(i.created_at) = QUARTER(CURDATE()) AND YEAR(i.created_at) = YEAR(CURDATE())";
} elseif ($periodFilter === 'this_year') {
    // Financial year starts April 1st
    $currentYear = intval(date('Y'));
    $currentMonth = intval(date('n'));
    if ($currentMonth >= 4) {
        $fyStart = "$currentYear-04-01";
        $fyEnd = ($currentYear + 1) . "-03-31";
    } else {
        $fyStart = ($currentYear - 1) . "-04-01";
        $fyEnd = "$currentYear-03-31";
    }
    $where .= " AND i.created_at >= ? AND i.created_at <= ?";
    $params[] = $fyStart . ' 00:00:00';
    $params[] = $fyEnd . ' 23:59:59';
} elseif ($periodFilter === 'custom' && !empty($fromDate) && !empty($toDate)) {
    $where .= " AND i.created_at >= ? AND i.created_at <= ?";
    $params[] = $fromDate . ' 00:00:00';
    $params[] = $toDate . ' 23:59:59';
}

$totalInvoicesQuery = $pdo->prepare("
    SELECT COUNT(*) 
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    $where
");
$totalInvoicesQuery->execute($params);
$totalInvoices = $totalInvoicesQuery->fetchColumn();
$totalPages = ceil($totalInvoices / $limit);

$invoices = $pdo->prepare("
    SELECT i.*, b.id as booking_id, b.booking_number, b.campaign_name, b.brand_name, c.name as client_name, e.name as entity_name,
           (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.invoice_id = i.id AND p.approval_status = 'approved') as paid_amount
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN proposals p ON b.proposal_id = p.id
    JOIN partners c ON b.client_id = c.id
    LEFT JOIN entities e ON i.entity_id = e.id
    $where
    ORDER BY i.id DESC
    LIMIT ? OFFSET ?
");
$invoicesParams = $params;
$invoicesParams[] = $limit;
$invoicesParams[] = $offset;
$invoices->execute($invoicesParams);
$invoices = $invoices->fetchAll();

$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;"><i class="fas fa-file-invoice-dollar"></i> Tax Invoices & Receivables</h2>
        <?php if (hasRole('admin')): ?>
        <a href="../admin/trash.php" class="btn btn-warning" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; text-decoration: none;">Trash</a>
        <?php endif; ?>
    </div>

    <form method="get" action="invoices.php" style="display:flex; flex-wrap: wrap; gap:1rem; align-items:flex-end; margin-bottom:1.5rem; background: #f8fafc; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; box-sizing: border-box; width: 100%;">
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Client</label>
            <select name="client_id" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; min-width:220px; background: white; font-family: inherit; font-size: 0.9rem;">
                <option value="">All Clients</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>" <?php echo $clientFilter === intval($client['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Campaign Name</label>
            <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..." style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; min-width:220px; background: white; font-family: inherit; font-size: 0.9rem;">
        </div>

        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Period</label>
            <select name="period" id="filter_period" onchange="toggleCustomDates(this.value)" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; min-width:160px; background: white; font-family: inherit; font-size: 0.9rem;">
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
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; background: white; font-family: inherit; font-size: 0.9rem;">
            </div>
            <div style="display:flex; flex-direction:column; gap:0.35rem;">
                <label style="font-size:0.85rem; color:#475569; font-weight:600;">To</label>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" style="padding:0.625rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1; background: white; font-family: inherit; font-size: 0.9rem;">
            </div>
        </div>

        <div style="display:flex; gap:0.5rem; margin-bottom: 2px;">
            <button type="submit" class="btn btn-primary" style="padding:0.625rem 1.25rem; height: 38px; display: inline-flex; align-items: center; background: #0f172a; border-color: #0f172a; font-family: inherit; font-size: 0.9rem; font-weight: 600;">
                <i class="fas fa-filter" style="margin-right: 4px; font-size: 0.85rem;"></i> Filter
            </button>
            <a href="invoices.php" class="btn" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:0.625rem 1.25rem; height: 38px; display: inline-flex; align-items: center; text-decoration:none; box-sizing: border-box; justify-content: center; font-family: inherit; font-size: 0.9rem; font-weight: 600;">Reset</a>
        </div>
    </form>

    <script>
    function toggleCustomDates(val) {
        document.getElementById('custom_date_range').style.display = val === 'custom' ? 'flex' : 'none';
    }
    </script>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 120px;">Invoice #</th>
                <th>Client / Booking</th>
                <th>Campaign / Brand</th>
                <th>Amount</th>
                <th>Status</th>
                <th style="text-align: center; width: 100px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--secondary); padding: 2rem;">No invoices generated yet.</td>
                </tr>
            <?php else: ?>
                <?php 
                $sn = $offset + 1;
                foreach ($invoices as $i): ?>
                <tr>
                    <td>
                        <div style="font-weight: 700; color: var(--primary);">
                            <?php echo $i['invoice_number']; ?>
                            <?php if (($i['type'] ?? '') === 'ro'): ?>
                                <span style="background: #e2e8f0; color: #475569; padding: 0.1rem 0.3rem; border-radius: 4px; font-size: 0.6rem; font-weight: 800; margin-left: 0.25rem;">RO</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo date('d M Y', strtotime($i['created_at'] ?? date('Y-m-d'))); ?></div>
                        <?php if (!empty($i['entity_name'])): ?>
                            <div style="font-size: 0.7rem; color: #0d9488; font-weight: 800; margin-top: 3px;">
                                <i class="fas fa-building" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($i['entity_name']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #334155;"><?php echo $i['client_name']; ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">Booking: <?php echo htmlspecialchars(!empty($i['booking_number']) ? $i['booking_number'] : '#BK-' . str_pad($i['booking_id'], 4, '0', STR_PAD_LEFT)); ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($i['campaign_name'] ?? ''); ?></div>
                        <?php if (!empty($i['brand_name'])): ?>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($i['brand_name']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 0.85rem; line-height: 1.4; white-space: nowrap; text-align: left; vertical-align: middle;">
                        <div style="font-weight: 500; color: #64748b;">Base: <span style="font-weight: 700; color: #334155;"><?php echo formatCurrency($i['sub_total']); ?></span></div>
                        <div style="font-weight: 500; color: #64748b;">Tax: <span style="font-weight: 700; color: #334155;"><?php echo formatCurrency($i['cgst'] + $i['sgst'] + $i['igst']); ?></span></div>
                        <div style="font-weight: 700; color: #0f172a; border-top: 1px dashed #cbd5e1; margin-top: 2px; padding-top: 2px;">Total: <span><?php echo formatCurrency($i['total_amount']); ?></span></div>
                        
                        <div style="font-weight: 600; color: #0369a1; margin-top: 4px; border-top: 1px solid #e2e8f0; padding-top: 2px;">Paid (Adv): <span style="font-weight: 700;"><?php echo formatCurrency($i['paid_amount']); ?></span></div>
                        <div style="font-weight: 600; color: <?php echo ($i['total_amount'] - $i['paid_amount'] > 0) ? '#b91c1c' : '#059669'; ?>;">Balance: <span style="font-weight: 700;"><?php echo formatCurrency($i['total_amount'] - $i['paid_amount']); ?></span></div>
                    </td>
                    <td>
                        <span class="pay-status pay-<?php echo $i['payment_status']; ?>" style="padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 800; display: inline-block;">
                            <?php echo str_replace('_', ' ', ucfirst($i['payment_status'])); ?>
                        </span>
                        <div style="margin-top: 6px;">
                            <?php if ($i['approval_status'] === 'approved'): ?>
                                <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-check-circle"></i> Approved
                                </span>
                            <?php elseif ($i['approval_status'] === 'rejected'): ?>
                                <span class="badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Rejection Reason: <?php echo htmlspecialchars($i['rejection_reason'] ?? ''); ?>">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                                <?php if (!empty($i['rejection_reason'])): ?>
                                    <div style="font-size: 0.65rem; color: #b91c1c; margin-top: 4px; font-weight: 600; max-width: 150px; line-height: 1.2;">
                                        Reason: <?php echo htmlspecialchars($i['rejection_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge" style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Pending Approval">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="text-align: center; width: 100px; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: center;">
                            <?php if ($i['approval_status'] !== 'rejected'): ?>
                                <a href="../operations/<?php echo $i['type'] === 'ro' ? 'generate_ro_invoice.php' : 'generate_invoice.php'; ?>?booking_id=<?php echo $i['booking_id']; ?>" target="_blank" class="btn-icon btn-view" title="View & Print" style="margin: 0;"><i class="fas fa-file-invoice"></i></a>
                            <?php else: ?>
                                <span class="btn-icon" title="Invoice Rejected (Cannot View/Print)" style="margin: 0; color: #cbd5e1; background: #f1f5f9; border-color: #e2e8f0; cursor: not-allowed; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px;"><i class="fas fa-ban"></i></span>
                            <?php endif; ?>
                            <?php if (canDelete('financials')): ?>
                            <button class="btn-icon btn-delete" title="Delete Invoice" onclick="deleteInvoice(<?php echo $i['id']; ?>)" style="margin: 0;"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php echo renderPagination($page, $totalPages, 'invoices.php', 'page', [
        'client_id' => $clientFilter,
        'campaign_name' => $campaignFilter,
        'period' => $periodFilter,
        'from_date' => $fromDate,
        'to_date' => $toDate
    ]); ?>
</div>

<style>
.pay-status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.pay-unpaid { background: #fee2e2; color: #991b1b; }
.pay-partially_paid { background: #fef9c3; color: #854d0e; }
.pay-paid { background: #dcfce7; color: #166534; }
</style>

<script>
function deleteInvoice(id) {
    Swal.fire({
        title: 'Delete Invoice?',
        text: "This will remove the invoice and its ledger entry, but the Booking will remain and can be edited/re-invoiced.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('invoices.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    Swal.fire('Deleted!', 'Invoice has been deleted and Booking reverted.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
