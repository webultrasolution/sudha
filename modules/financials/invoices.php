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

// Period overlap filtering (based on campaign start_date / end_date)
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
    $where .= " AND COALESCE(i.invoice_date, DATE(i.created_at)) >= ? AND COALESCE(i.invoice_date, DATE(i.created_at)) <= ?";
    $params[] = $periodStart;
    $params[] = $periodEnd;
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
    SELECT i.*, b.id as booking_id, b.booking_number, b.campaign_name, b.brand_name, b.customer_po_file, c.name as client_name, e.name as entity_name,
           b.start_date, b.end_date,
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
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px;">
        <h2 style="font-size: 1.25rem; margin: 0;"><i class="fas fa-file-invoice-dollar"></i> Tax Invoices & Receivables <span style="font-size: 0.9rem; color: #64748b; font-weight: 500; margin-left: 0.5rem;">(<?php echo $totalInvoices; ?> found)</span></h2>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <button onclick="downloadBulkInvoices('zip')" class="btn" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.9rem; transition: all 0.2s;" onmouseover="this.style.background='#d1fae5';" onmouseout="this.style.background='#ecfdf5';">
                <i class="fas fa-file-archive"></i> ZIP Invoices
            </button>
            <button onclick="downloadBulkInvoices('merge')" class="btn" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.9rem; transition: all 0.2s;" onmouseover="this.style.background='#dbeafe';" onmouseout="this.style.background='#eff6ff';">
                <i class="fas fa-file-pdf"></i> Merged PDF
            </button>
            <?php if (hasRole('admin')): ?>
            <a href="../admin/trash.php" class="btn btn-warning" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">Trash</a>
            <?php endif; ?>
        </div>
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
                <th>Period</th>
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
                    <td style="font-size: 0.875rem; white-space: nowrap; vertical-align: middle;">
                        <?php 
                        if (!empty($i['start_date']) && $i['start_date'] !== '0000-00-00' && !empty($i['end_date']) && $i['end_date'] !== '0000-00-00') {
                            echo '<span style="font-weight: 600; color: #475569;"><i class="fas fa-calendar-alt" style="color: #94a3b8; margin-right: 4px;"></i>' . date('d M', strtotime($i['start_date'])) . ' - ' . date('d M Y', strtotime($i['end_date'])) . '</span>';
                        } else {
                            echo '<span style="color: #cbd5e1;">N/A</span>';
                        }
                        ?>
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
                    <td style="text-align: center; width: 120px; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: center;">
                            <?php 
                            $attFiles = getAttachmentUrls($i['customer_po_file'] ?? '');
                            $bookingId = intval($i['booking_id'] ?? 0);
                            $invNumSafe = htmlspecialchars($i['invoice_number'] ?? '', ENT_QUOTES, 'UTF-8');
                            $jsonFiles = !empty($attFiles) ? htmlspecialchars(json_encode($attFiles), ENT_QUOTES, 'UTF-8') : '[]';

                            if (!empty($attFiles)): 
                                if (count($attFiles) > 1 || hasRole('admin')): ?>
                                    <button type="button" onclick="showInvoiceAttachments(<?php echo $jsonFiles; ?>, '<?php echo $invNumSafe; ?>', <?php echo $bookingId; ?>)" class="btn-icon" title="View <?php echo count($attFiles); ?> Attachment(s)<?php echo hasRole('admin') ? ' / Manage' : ''; ?>" style="margin: 0; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; position: relative;">
                                        <i class="fas fa-paperclip"></i>
                                        <?php if (count($attFiles) > 1): ?>
                                            <span style="position: absolute; top: -4px; right: -4px; background: #2563eb; color: white; font-size: 0.6rem; font-weight: 800; border-radius: 9999px; min-width: 15px; height: 15px; display: flex; align-items: center; justify-content: center; padding: 0 2px;"><?php echo count($attFiles); ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php else: 
                                    $firstAtt = $attFiles[0];
                                    $fullAttUrl = (strpos($firstAtt, 'http') === 0) ? $firstAtt : (BASE_URL . $firstAtt);
                                ?>
                                    <a href="<?php echo htmlspecialchars($fullAttUrl); ?>" target="_blank" class="btn-icon" title="View Customer PO / Attachment" style="margin: 0; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; text-decoration: none;"><i class="fas fa-paperclip"></i></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (hasRole('admin')): ?>
                                    <button type="button" onclick="openAttachmentsModal(<?php echo $bookingId; ?>)" class="btn-icon" title="Add Attachments (Admin)" style="margin: 0; color: #94a3b8; background: #f8fafc; border: 1px dashed #cbd5e1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px;"><i class="fas fa-paperclip"></i></button>
                                <?php else: ?>
                                    <span class="btn-icon" title="No Attachment Uploaded" style="margin: 0; color: #cbd5e1; background: #f8fafc; border: 1px solid #e2e8f0; cursor: not-allowed; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px;"><i class="fas fa-paperclip"></i></span>
                                <?php endif; ?>
                            <?php endif; ?>
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

<!-- External libraries for bulk PDF / ZIP generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- Hidden iframe for rendering invoice pages in isolation -->
<iframe id="invoice-pdf-iframe" style="position: fixed; left: -9999px; top: 0; width: 794px; height: 1200px; border: none; opacity: 0; pointer-events: none; z-index: -1;"></iframe>

<script>
function getJsPdfConstructor() {
    if (window.jspdf && window.jspdf.jsPDF) return window.jspdf.jsPDF;
    if (window.jsPDF) return window.jsPDF;
    if (typeof jsPDF !== 'undefined') return jsPDF;
    throw new Error('jsPDF library could not be initialized');
}

async function renderInvoiceToCanvas(htmlContent) {
    const iframe = document.getElementById('invoice-pdf-iframe');
    const iframeDoc = iframe.contentWindow.document;

    iframeDoc.open();
    iframeDoc.write(htmlContent);
    iframeDoc.close();

    // Inject high-precision styling override to guarantee perfect 1-page A4 proportion
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
        .btn-print { display: none !important; }
        .invoice-wrapper {
            width: 794px !important;
            max-width: 794px !important;
            min-height: auto !important;
            height: auto !important;
            margin: 0 auto !important;
            padding: 6mm 10mm 4mm !important;
            box-shadow: none !important;
            border: none !important;
            box-sizing: border-box !important;
            background: #ffffff !important;
            display: block !important;
            page-break-after: avoid !important;
            break-after: avoid !important;
        }
        .avoid-break {
            margin-top: 8px !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        table { width: 100% !important; border-collapse: collapse !important; }
        img { max-width: 100% !important; }
    `;
    iframeDoc.head.appendChild(styleElem);

    // Remove any interactive print buttons
    iframeDoc.querySelectorAll('.btn-print').forEach(btn => btn.remove());

    // Wait for all images inside iframe to be fully loaded
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

    // Brief stabilization pause for web fonts & DOM layout
    await new Promise(resolve => setTimeout(resolve, 250));

    const targetElem = iframeDoc.querySelector('.invoice-wrapper') || iframeDoc.body;

    // Render using html2canvas at 2x scale for sharp text and graphics
    return await html2canvas(targetElem, {
        scale: 2,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff',
        width: 794,
        windowWidth: 794
    });
}

function addCanvasToPdf(pdf, canvas, isFirstPage) {
    const pageWidth = 210;  // mm (A4)
    const pageHeight = 297; // mm (A4)

    const canvasWidth = canvas.width;
    const canvasHeight = canvas.height;
    const canvasRatio = canvasWidth / canvasHeight;

    let finalWidth = pageWidth;
    let finalHeight = pageWidth / canvasRatio;
    let x = 0;
    let y = 0;

    // Strict 1-page fit constraint: scale down only if height exceeds 297mm
    if (finalHeight > pageHeight) {
        finalHeight = pageHeight;
        finalWidth = pageHeight * canvasRatio;
        x = (pageWidth - finalWidth) / 2; // Center horizontally
        y = 0;
    } else {
        // Standard top-aligned invoice
        x = 0;
        y = 0;
    }

    if (!isFirstPage) {
        pdf.addPage('a4', 'portrait');
    }

    const imgData = canvas.toDataURL('image/jpeg', 0.96);
    pdf.addImage(imgData, 'JPEG', x, y, finalWidth, finalHeight, undefined, 'FAST');
}

async function downloadBulkInvoices(mode) {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Show fetching dialog
    Swal.fire({
        title: 'Checking invoices...',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch(`../../ajax/get_filtered_invoices.php?${urlParams.toString()}`);
        const data = await response.json();
        
        if (!data.success) {
            Swal.fire('Error', data.message || 'Failed to fetch invoices', 'error');
            return;
        }
        
        const invoices = data.invoices;
        if (invoices.length === 0) {
            Swal.fire('No Invoices', 'No invoices found matching the current filters.', 'info');
            return;
        }
        
        let progressHtml = `
            <div style="text-align: left; margin-top: 10px;">
                <div style="font-weight: 600; margin-bottom: 8px;" id="bulk-progress-text">Preparing...</div>
                <div style="width: 100%; background: #e2e8f0; height: 10px; border-radius: 5px; overflow: hidden;">
                    <div id="bulk-progress-bar" style="width: 0%; background: #3b82f6; height: 100%; transition: width 0.2s;"></div>
                </div>
            </div>
        `;
        
        Swal.fire({
            title: mode === 'zip' ? 'Generating ZIP Archive...' : 'Generating Merged PDF...',
            html: progressHtml,
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const updateProgress = (text, percent) => {
            const textEl = document.getElementById('bulk-progress-text');
            const barEl = document.getElementById('bulk-progress-bar');
            if (textEl) textEl.textContent = text;
            if (barEl) barEl.style.width = `${percent}%`;
        };

        if (mode === 'zip') {
            const zip = new JSZip();
            
            for (let idx = 0; idx < invoices.length; idx++) {
                const inv = invoices[idx];
                const pct = Math.round((idx / invoices.length) * 90);
                updateProgress(`Generating ${inv.invoice_number} (${idx + 1}/${invoices.length})...`, pct);
                
                const pageUrl = `../operations/${inv.type === 'ro' ? 'generate_ro_invoice.php' : 'generate_invoice.php'}?booking_id=${inv.booking_id}`;
                const pageResp = await fetch(pageUrl);
                const htmlContent = await pageResp.text();
                
                const pdfBlob = await renderHtmlToPdfBlob(htmlContent, inv.invoice_number);
                const safeName = inv.invoice_number.replace(/\//g, '_') + '.pdf';
                zip.file(safeName, pdfBlob);
            }
            
            updateProgress('Building ZIP archive...', 95);
            const content = await zip.generateAsync({ type: 'blob' });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(content);
            link.download = `invoices_${new Date().toISOString().slice(0,10)}.zip`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            Swal.close();
            Swal.fire('Success', 'ZIP archive downloaded successfully!', 'success');
            
        } else {
            const JsPDF = getJsPdfConstructor();
            const pdf = new JsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4',
                compress: true
            });
            
            for (let idx = 0; idx < invoices.length; idx++) {
                const inv = invoices[idx];
                const pct = Math.round(((idx + 1) / invoices.length) * 92);
                updateProgress(`Rendering ${inv.invoice_number} (${idx + 1}/${invoices.length})...`, pct);
                
                const pageUrl = `../operations/${inv.type === 'ro' ? 'generate_ro_invoice.php' : 'generate_invoice.php'}?booking_id=${inv.booking_id}`;
                const pageResp = await fetch(pageUrl);
                const htmlContent = await pageResp.text();
                
                const canvas = await renderInvoiceToCanvas(htmlContent);
                addCanvasToPdf(pdf, canvas, idx === 0);
            }
            
            updateProgress('Finalizing Merged PDF...', 98);
            await new Promise(resolve => setTimeout(resolve, 200));

            pdf.save(`merged_invoices_${new Date().toISOString().slice(0,10)}.pdf`);
            
            Swal.close();
            Swal.fire('Success', 'Merged PDF downloaded successfully!', 'success');
        }
    } catch (err) {
        console.error(err);
        Swal.fire('Failed', 'Failed to generate files: ' + err.message, 'error');
    }
}

async function renderHtmlToPdfBlob(htmlContent, filename) {
    const JsPDF = getJsPdfConstructor();
    const pdf = new JsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4',
        compress: true
    });

    const canvas = await renderInvoiceToCanvas(htmlContent);
    addCanvasToPdf(pdf, canvas, true);

    return pdf.output('blob');
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
