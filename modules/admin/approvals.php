<?php
$activePage = 'approvals';
$pageTitle = 'Approval Queue';
include_once __DIR__ . '/../../includes/header.php';

// Enforce Admin Role
requireRole('admin');

// Fetch pending counts
$pendingProposals = $pdo->query("SELECT COUNT(*) FROM proposals WHERE approval_status = 'pending_approval'")->fetchColumn();
$pendingPOs       = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE approval_status = 'pending_approval'")->fetchColumn();
$pendingBookings  = $pdo->query("SELECT COUNT(*) FROM bookings WHERE approval_status = 'pending_approval'")->fetchColumn();
$pendingInvoices  = $pdo->query("SELECT COUNT(*) FROM invoices WHERE approval_status = 'pending_approval'")->fetchColumn();
$totalPending     = $pendingProposals + $pendingPOs + $pendingBookings + $pendingInvoices;

// Current tab
$tab = isset($_GET['tab']) ? clean($_GET['tab']) : 'proposals';

// Fetch pending proposals
$proposals = $pdo->query("
    SELECT p.*, c.name as client_name, u.full_name as created_by_name, u.username as created_by_username
    FROM proposals p
    LEFT JOIN partners c ON p.client_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.approval_status = 'pending_approval'
    ORDER BY p.created_at DESC
")->fetchAll();

// Fetch pending POs
$pos = $pdo->query("
    SELECT po.*, v.name as vendor_name, u.full_name as created_by_name, u.username as created_by_username
    FROM purchase_orders po
    LEFT JOIN partners v ON po.vendor_id = v.id
    LEFT JOIN users u ON po.employee_id = u.id
    WHERE po.approval_status = 'pending_approval'
    ORDER BY po.po_date DESC
")->fetchAll();

// Fetch pending bookings
$bookings = $pdo->query("
    SELECT b.*, c.name as client_name
    FROM bookings b
    LEFT JOIN partners c ON b.client_id = c.id
    WHERE b.approval_status = 'pending_approval'
    ORDER BY b.created_at DESC
")->fetchAll();

// Fetch pending invoices
$invoices = $pdo->query("
    SELECT i.*, b.campaign_name, b.customer_po_no, b.customer_po_file, b.customer_po_date, b.email_date, b.confirmation_type, c.name as client_name
    FROM invoices i
    LEFT JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN partners c ON b.client_id = c.id
    WHERE i.approval_status = 'pending_approval'
    ORDER BY i.created_at DESC
")->fetchAll();

// Fetch pending client printing invoices
$clientPrintings = $pdo->query("
    SELECT r.*, c.name as client_name, p.full_name as requested_by_name
    FROM client_printing_rates r
    LEFT JOIN partners c ON r.client_id = c.id
    LEFT JOIN approval_requests ar ON ar.entity_type = 'client_printing' AND ar.entity_ref = r.po_number
    LEFT JOIN users p ON ar.requested_by = p.id
    WHERE r.approval_status = 'pending_approval'
    GROUP BY r.po_number
    ORDER BY r.id DESC
")->fetchAll();

// Fetch pending payments
$payments = $pdo->query("
    SELECT p.*, c.name as partner_name, u.full_name as requested_by_name
    FROM payments p
    LEFT JOIN partners c ON p.partner_id = c.id
    LEFT JOIN approval_requests ar ON ar.entity_type = 'payment' AND ar.entity_id = p.id
    LEFT JOIN users u ON ar.requested_by = u.id
    WHERE p.approval_status = 'pending_approval'
    ORDER BY p.created_at DESC
")->fetchAll();

// Fetch recent actions (approved/rejected)
$recentActions = $pdo->query("
    SELECT ar.*, u1.full_name as requested_name, u2.full_name as reviewed_name
    FROM approval_requests ar
    LEFT JOIN users u1 ON ar.requested_by = u1.id
    LEFT JOIN users u2 ON ar.reviewed_by = u2.id
    WHERE ar.status IN ('approved','rejected')
    ORDER BY ar.reviewed_at DESC
    LIMIT 15
")->fetchAll();

$pendingPOs = count($pos);
$pendingBookings = count($bookings);
$pendingInvoices = count($invoices);
$pendingClientPrintings = count($clientPrintings);
$pendingPayments = count($payments);
?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Dashboard Cards -->
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); cursor: pointer; <?php echo $tab === 'proposals' ? 'border-left: 4px solid var(--primary);' : ''; ?>" onclick="window.location='?tab=proposals'">
        <div style="background: #fef3c7; color: #d97706; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
            <i class="fas fa-file-contract"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Proposals</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: <?php echo $pendingProposals > 0 ? '#d97706' : '#0f172a'; ?>;"><?php echo $pendingProposals; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); cursor: pointer; <?php echo $tab === 'pos' ? 'border-left: 4px solid var(--primary);' : ''; ?>" onclick="window.location='?tab=pos'">
        <div style="background: #fee2e2; color: #dc2626; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Purchase Orders</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: <?php echo $pendingPOs > 0 ? '#dc2626' : '#0f172a'; ?>;"><?php echo $pendingPOs; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); cursor: pointer; <?php echo $tab === 'bookings' ? 'border-left: 4px solid var(--primary);' : ''; ?>" onclick="window.location='?tab=bookings'">
        <div style="background: #e0f2fe; color: #0284c7; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Bookings</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: <?php echo $pendingBookings > 0 ? '#0284c7' : '#0f172a'; ?>;"><?php echo $pendingBookings; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); cursor: pointer; <?php echo $tab === 'invoices' ? 'border-left: 4px solid var(--primary);' : ''; ?>" onclick="window.location='?tab=invoices'">
        <div style="background: #ecfdf5; color: #059669; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Invoices</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: <?php echo $pendingInvoices > 0 ? '#059669' : '#0f172a'; ?>;"><?php echo $pendingInvoices; ?></div>
        </div>
    </div>
    
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); cursor: pointer; <?php echo $tab === 'client_printing' ? 'border-left: 4px solid var(--primary);' : ''; ?>" onclick="window.location='?tab=client_printing'">
        <div style="background: #f3e8ff; color: #7e22ce; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
            <i class="fas fa-print"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Client Printing</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: <?php echo $pendingClientPrintings > 0 ? '#7e22ce' : '#0f172a'; ?>;"><?php echo $pendingClientPrintings; ?></div>
        </div>
    </div>

    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); cursor: pointer; <?php echo $tab === 'payments' ? 'border-left: 4px solid var(--primary);' : ''; ?>" onclick="window.location='?tab=payments'">
        <div style="background: #fef08a; color: #ca8a04; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Payments</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: <?php echo $pendingPayments > 0 ? '#ca8a04' : '#0f172a'; ?>;"><?php echo $pendingPayments; ?></div>
        </div>
    </div>
</div>

<!-- Main Card -->
<div class="card" style="border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 2rem;">

    <!-- Tab Bar -->
    <div style="display: flex; gap: 0.25rem; border-bottom: 2px solid #f1f5f9; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <?php
        $tabs = [
            'proposals' => ['Proposals', 'fa-file-contract', $pendingProposals],
            'pos'       => ['Purchase Orders', 'fa-shopping-cart', $pendingPOs],
            'bookings'  => ['Bookings', 'fa-calendar-check', $pendingBookings],
            'invoices'  => ['Invoices', 'fa-file-invoice-dollar', $pendingInvoices],
            'client_printing' => ['Client Printing', 'fa-print', $pendingClientPrintings],
            'payments'  => ['Payments', 'fa-money-bill-wave', $pendingPayments],
            'history'   => ['Recent Activity', 'fa-history', 0],
        ];
        foreach ($tabs as $key => $info):
        ?>
            <a href="?tab=<?php echo $key; ?>" class="appr-tab <?php echo $tab === $key ? 'active' : ''; ?>">
                <i class="fas <?php echo $info[1]; ?>"></i> <?php echo $info[0]; ?>
                <?php if ($info[2] > 0): ?>
                    <span class="appr-badge"><?php echo $info[2]; ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ====== PROPOSALS TAB ====== -->
    <?php if ($tab === 'proposals'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>Proposal #</th>
                    <th>Campaign</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Created By</th>
                    <th>Date</th>
                    <th style="width: 200px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proposals as $p): ?>
                <tr id="row-proposal-<?php echo $p['id']; ?>">
                    <td style="font-weight: 700; color: var(--primary);">
                        <a href="../proposals/view.php?id=<?php echo $p['id']; ?>" style="text-decoration: none; color: var(--primary);"><?php echo htmlspecialchars($p['proposal_number']); ?></a>
                    </td>
                    <td><?php echo htmlspecialchars($p['campaign_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($p['client_name'] ?: '—'); ?></td>
                    <td style="font-weight: 700;"><?php echo formatCurrency($p['grand_total']); ?></td>
                    <td><?php echo htmlspecialchars($p['created_by_name'] ?: $p['created_by_username'] ?: '—'); ?></td>
                    <td style="font-size: 0.8rem; color: #64748b;"><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn-approve" onclick="approveEntity('proposal', <?php echo $p['id']; ?>)">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn-reject" onclick="rejectEntity('proposal', <?php echo $p['id']; ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($proposals)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All proposals have been reviewed!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ====== PURCHASE ORDERS TAB ====== -->
    <?php if ($tab === 'pos'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Type</th>
                    <th>Vendor</th>
                    <th>Campaign</th>
                    <th>Amount</th>
                    <th>Created By</th>
                    <th>Date</th>
                    <th style="width: 200px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pos as $po): ?>
                <tr id="row-purchase_order-<?php echo $po['id']; ?>">
                    <td style="font-weight: 700; color: var(--primary);">
                        <a href="../financials/po_view.php?id=<?php echo $po['id']; ?>" style="text-decoration: none; color: var(--primary);"><?php echo htmlspecialchars($po['po_number']); ?></a>
                    </td>
                    <td>
                        <?php
                        $poType = $po['type'] ?? 'site';
                        if ($poType === 'printing') echo '<span style="background: #fdf2f8; color: #db2777; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">PRINTING</span>';
                        else echo '<span style="background: #e0f2fe; color: #0284c7; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">SITE</span>';
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($po['vendor_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($po['campaign_name'] ?: '—'); ?></td>
                    <td style="font-weight: 700;"><?php echo formatCurrency($po['total_amount']); ?></td>
                    <td><?php echo htmlspecialchars($po['created_by_name'] ?: $po['created_by_username'] ?: '—'); ?></td>
                    <td style="font-size: 0.8rem; color: #64748b;"><?php echo date('d M Y', strtotime($po['po_date'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn-approve" onclick="approveEntity('purchase_order', <?php echo $po['id']; ?>)">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn-reject" onclick="rejectEntity('purchase_order', <?php echo $po['id']; ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pos)): ?>
                <tr><td colspan="8" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All purchase orders have been reviewed!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ====== BOOKINGS TAB ====== -->
    <?php if ($tab === 'bookings'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Campaign</th>
                    <th>Client</th>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th style="width: 200px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr id="row-booking-<?php echo $b['id']; ?>">
                    <td style="font-weight: 700; color: var(--primary);">
                        <a href="../operations/view_booking.php?id=<?php echo $b['id']; ?>" style="text-decoration: none; color: var(--primary);">#<?php echo $b['id']; ?></a>
                    </td>
                    <td><?php echo htmlspecialchars($b['campaign_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($b['client_name'] ?: '—'); ?></td>
                    <td style="font-size: 0.8rem;"><?php echo ($b['start_date'] ? date('d M', strtotime($b['start_date'])) : '—') . ' → ' . ($b['end_date'] ? date('d M Y', strtotime($b['end_date'])) : '—'); ?></td>
                    <td style="font-weight: 700;"><?php echo formatCurrency($b['grand_total']); ?></td>
                    <td style="font-size: 0.8rem; color: #64748b;"><?php echo date('d M Y', strtotime($b['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn-approve" onclick="approveEntity('booking', <?php echo $b['id']; ?>)">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn-reject" onclick="rejectEntity('booking', <?php echo $b['id']; ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($bookings)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All bookings have been reviewed!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ====== INVOICES TAB ====== -->
    <?php if ($tab === 'invoices'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Type</th>
                    <th>Client</th>
                    <th>Customer Approval (PO/Email)</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th style="width: 200px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr id="row-invoice-<?php echo $inv['id']; ?>">
                    <td style="font-weight: 700; color: var(--primary);">
                        <a href="../operations/generate_invoice.php?booking_id=<?php echo $inv['booking_id']; ?>" target="_blank" style="text-decoration: none; color: var(--primary);">
                            <?php echo htmlspecialchars($inv['invoice_number']); ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        $invType = $inv['type'] ?? 'tax';
                        if ($invType === 'proforma') echo '<span style="background: #fef3c7; color: #d97706; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">PROFORMA</span>';
                        elseif ($invType === 'estimate') echo '<span style="background: #e0f2fe; color: #0284c7; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">ESTIMATE</span>';
                        else echo '<span style="background: #ecfdf5; color: #059669; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">TAX</span>';
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($inv['client_name'] ?: '—'); ?></td>
                    <td>
                        <?php if ($invType === 'tax'): ?>
                            <div style="font-size: 0.8rem;">
                                <?php if ($inv['confirmation_type'] === 'email'): ?>
                                    <strong>Email Conf:</strong> <?php echo $inv['email_date'] ? date('d M Y', strtotime($inv['email_date'])) : 'N/A'; ?>
                                <?php else: ?>
                                    <strong>PO:</strong> <?php echo htmlspecialchars($inv['customer_po_no'] ?: 'N/A'); ?><br>
                                    <strong>Date:</strong> <?php echo $inv['customer_po_date'] ? date('d M Y', strtotime($inv['customer_po_date'])) : 'N/A'; ?>
                                <?php endif; ?>
                                
                                <?php if (!empty($inv['customer_po_file'])): ?>
                                    <br><a href="<?php echo BASE_URL . $inv['customer_po_file']; ?>" target="_blank" style="color: #3b82f6; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 4px;">
                                        <i class="fas fa-paperclip"></i> View Document
                                    </a>
                                <?php else: ?>
                                    <br><span style="color: #ef4444; font-size: 0.7rem;">No File Attached</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #94a3b8;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight: 700;"><?php echo formatCurrency($inv['total_amount']); ?></td>
                    <td style="font-size: 0.8rem; color: #64748b;"><?php echo date('d M Y', strtotime($inv['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn-approve" onclick="approveEntity('invoice', <?php echo $inv['id']; ?>)">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn-reject" onclick="rejectEntity('invoice', <?php echo $inv['id']; ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All invoices have been reviewed!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ====== CLIENT PRINTING TAB ====== -->
    <?php if ($tab === 'client_printing'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>PO Ref</th>
                    <th>Client</th>
                    <th>Requested By</th>
                    <th>Document</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientPrintings as $cp): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($cp['po_number']); ?></strong>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                            Req: <?php echo date('d M, h:i A'); // Simplified for now since we don't have created_at on this table ?>
                        </div>
                    </td>
                    <td><strong><?php echo htmlspecialchars($cp['client_name'] ?: 'Unknown'); ?></strong></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #475569;">
                                <?php echo substr($cp['requested_by_name'] ?: '?', 0, 1); ?>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($cp['requested_by_name'] ?: 'System'); ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($cp['customer_po_file']): ?>
                            <a href="../../uploads/customer_pos/<?php echo rawurlencode($cp['customer_po_file']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; color: #0284c7; background: #f0f9ff; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-decoration: none;">
                                <i class="fas fa-file-pdf"></i> View Attachment
                            </a>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 0.8rem;"><i class="fas fa-minus-circle"></i> No Doc</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-primary" onclick="approveEntity('client_printing', '<?php echo $cp['po_number']; ?>')" style="padding: 6px 12px; font-size: 0.8rem; background: #10b981; border: none;"><i class="fas fa-check"></i> Approve</button>
                            <button class="btn btn-primary" onclick="rejectEntity('client_printing', '<?php echo $cp['po_number']; ?>')" style="padding: 6px 12px; font-size: 0.8rem; background: #ef4444; border: none;"><i class="fas fa-times"></i> Reject</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($clientPrintings)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All client printing requests have been reviewed!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ====== PAYMENTS TAB ====== -->
    <?php if ($tab === 'payments'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>Payment Info</th>
                    <th>Partner</th>
                    <th>Requested By</th>
                    <th>Amount & Mode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $pay): ?>
                <tr>
                    <td>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 4px;">
                            <?php if ($pay['type'] === 'receivable'): ?>
                                <span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Customer Pmt</span>
                            <?php else: ?>
                                <span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Vendor Pmt</span>
                            <?php endif; ?>
                        </div>
                        <strong><?php echo htmlspecialchars($pay['transaction_id'] ?: 'No Txn ID'); ?></strong>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                            Date: <?php echo date('d M Y', strtotime($pay['payment_date'])); ?>
                        </div>
                    </td>
                    <td><strong><?php echo htmlspecialchars($pay['partner_name'] ?: 'Unknown'); ?></strong></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #475569;">
                                <?php echo substr($pay['requested_by_name'] ?: '?', 0, 1); ?>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($pay['requested_by_name'] ?: 'System'); ?></span>
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #0f172a;">
                            ₹<?php echo number_format($pay['amount'], 2); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; margin-top: 2px;">
                            Mode: <?php echo htmlspecialchars($pay['payment_mode']); ?>
                        </div>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-primary" onclick="approveEntity('payment', <?php echo $pay['id']; ?>)" style="padding: 6px 12px; font-size: 0.8rem; background: #10b981; border: none;"><i class="fas fa-check"></i> Approve</button>
                            <button class="btn btn-primary" onclick="rejectEntity('payment', <?php echo $pay['id']; ?>)" style="padding: 6px 12px; font-size: 0.8rem; background: #ef4444; border: none;"><i class="fas fa-times"></i> Reject</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All payment requests have been reviewed!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ====== HISTORY TAB ====== -->
    <?php if ($tab === 'history'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>Entity</th>
                    <th>Reference</th>
                    <th>Requested By</th>
                    <th>Decision</th>
                    <th>Reviewed By</th>
                    <th>Remarks</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentActions as $ra): ?>
                <tr>
                    <td><span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;"><?php echo str_replace('_', ' ', $ra['entity_type']); ?></span></td>
                    <td style="font-weight: 700;"><?php echo htmlspecialchars($ra['entity_ref'] ?: '#' . $ra['entity_id']); ?></td>
                    <td><?php echo htmlspecialchars($ra['requested_name'] ?: '—'); ?></td>
                    <td>
                        <?php if ($ra['status'] === 'approved'): ?>
                            <span style="background: #dcfce7; color: #15803d; padding: 0.25rem 0.625rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">APPROVED</span>
                        <?php else: ?>
                            <span style="background: #fee2e2; color: #b91c1c; padding: 0.25rem 0.625rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">REJECTED</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($ra['reviewed_name'] ?: '—'); ?></td>
                    <td style="font-size: 0.8rem; color: #64748b; max-width: 200px; word-break: break-word;"><?php echo htmlspecialchars($ra['remarks'] ?: '—'); ?></td>
                    <td style="font-size: 0.8rem; color: #64748b;"><?php echo $ra['reviewed_at'] ? date('d M Y, h:i A', strtotime($ra['reviewed_at'])) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentActions)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;">No approval history yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<style>
/* Approval Tab Buttons */
.appr-tab {
    padding: 0.75rem 1.25rem; text-decoration: none; color: #64748b; font-weight: 700; font-size: 0.85rem;
    border-bottom: 3px solid transparent; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s;
}
.appr-tab:hover { color: var(--primary); }
.appr-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.appr-badge {
    background: #ef4444; color: #fff; font-size: 0.65rem; font-weight: 800;
    padding: 0.15rem 0.45rem; border-radius: 50px; min-width: 18px; text-align: center;
    animation: pulse-badge 2s infinite;
}
@keyframes pulse-badge { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

/* Action Buttons */
.btn-approve {
    background: #dcfce7; color: #15803d; border: 1px solid #86efac; border-radius: 8px;
    padding: 0.4rem 0.75rem; font-size: 0.75rem; font-weight: 800; cursor: pointer;
    display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;
}
.btn-approve:hover { background: #15803d; color: #fff; }

.btn-reject {
    background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 8px;
    padding: 0.4rem 0.75rem; font-size: 0.75rem; font-weight: 800; cursor: pointer;
    display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;
}
.btn-reject:hover { background: #b91c1c; color: #fff; }

/* Table */
.matrix-table { width: 100%; border-collapse: collapse; }
.matrix-table th { background: #f8fafc; color: #475569; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.875rem 1rem; border-bottom: 1.5px solid #e2e8f0; text-align: left; }
.matrix-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; background: #fff; font-size: 0.85rem; }
.matrix-table tr:hover td { background: #f8fafc; }
</style>

<script>
const BASE = '<?php echo BASE_URL; ?>';

function approveEntity(entityType, entityId) {
    Swal.fire({
        title: 'Approve this ' + entityType.replace('_', ' ') + '?',
        text: 'This will activate the record and make it official.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#15803d',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-check"></i> Yes, Approve',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            sendApproval(entityType, entityId, 'approve', '');
        }
    });
}

function rejectEntity(entityType, entityId) {
    Swal.fire({
        title: 'Reject this ' + entityType.replace('_', ' ') + '?',
        input: 'textarea',
        inputPlaceholder: 'Rejection reason (optional)...',
        inputAttributes: { 'aria-label': 'Rejection reason' },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-times"></i> Yes, Reject',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            sendApproval(entityType, entityId, 'reject', result.value || '');
        }
    });
}

function sendApproval(entityType, entityId, action, reason) {
    fetch(BASE + 'ajax/approve_entity.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            entity_type: entityType,
            entity_id: entityId,
            action: action,
            reason: reason
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: action === 'approve' ? 'Approved!' : 'Rejected!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            });
            // Fade out row
            const row = document.getElementById(`row-${entityType}-${entityId}`);
            if (row) {
                row.style.transition = 'opacity 0.4s, transform 0.4s';
                row.style.opacity = '0';
                row.style.transform = 'translateX(30px)';
                setTimeout(() => row.remove(), 400);
            }
            // Update badge counts after a small delay
            setTimeout(() => updateBadgeCounts(), 500);
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error', 'Network error: ' + err.message, 'error');
    });
}

function updateBadgeCounts() {
    // Reload page to refresh counts
    window.location.reload();
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
