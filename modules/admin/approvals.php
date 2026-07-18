<?php
$activePage = 'approvals';
$pageTitle = 'Approval Queue';
include_once __DIR__ . '/../../includes/header.php';

// Enforce Admin Role
requireRole('admin');

// Fetch pending counts
$pendingProposals = $pdo->query("SELECT COUNT(*) FROM proposals WHERE approval_status = 'pending_approval'")->fetchColumn();
$pendingPOs       = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE approval_status = 'pending_approval'")->fetchColumn();
$pendingBookings  = 0;
$pendingInvoices  = $pdo->query("SELECT COUNT(*) FROM invoices WHERE approval_status = 'pending_approval'")->fetchColumn();
$pendingClientPrintings = $pdo->query("SELECT COUNT(DISTINCT po_number) FROM client_printing_rates WHERE approval_status = 'pending_approval'")->fetchColumn();
$pendingClientMountings = $pdo->query("SELECT COUNT(DISTINCT po_number) FROM client_mounting_rates WHERE approval_status = 'pending_approval'")->fetchColumn();
$pendingPayments  = $pdo->query("SELECT COUNT(*) FROM payments WHERE approval_status = 'pending_approval'")->fetchColumn();
$totalPending     = $pendingProposals + $pendingPOs + $pendingBookings + $pendingInvoices + $pendingClientPrintings + $pendingClientMountings + $pendingPayments;

// Current tab
$tab = isset($_GET['tab']) ? clean($_GET['tab']) : 'proposals';

// Fetch pending proposals
$proposals = $pdo->query("
    SELECT p.*, c.name as client_name, 
           COALESCE(r.name, r.full_name, u.name, u.full_name) as requested_by_name
    FROM proposals p
    LEFT JOIN partners c ON p.client_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN approval_requests ar ON ar.entity_type = 'proposal' AND ar.entity_id = p.id AND ar.status = 'pending'
    LEFT JOIN users r ON ar.requested_by = r.id
    WHERE p.approval_status = 'pending_approval'
    ORDER BY p.created_at DESC
")->fetchAll();

// Fetch pending POs
$pos = $pdo->query("
    SELECT po.*, v.name as vendor_name, 
           COALESCE(r.name, r.full_name, u.name, u.full_name) as requested_by_name
    FROM purchase_orders po
    LEFT JOIN partners v ON po.vendor_id = v.id
    LEFT JOIN users u ON po.employee_id = u.id
    LEFT JOIN approval_requests ar ON ar.entity_type = 'purchase_order' AND ar.entity_id = po.id AND ar.status = 'pending'
    LEFT JOIN users r ON ar.requested_by = r.id
    WHERE po.approval_status = 'pending_approval'
    ORDER BY po.po_date DESC
")->fetchAll();

// Fetch pending bookings (Disabled)
$bookings = [];

// Fetch pending invoices
$invoices = $pdo->query("
    SELECT i.*, b.campaign_name, b.brand_name, b.customer_po_no, b.customer_po_file, b.customer_po_date, b.email_date, b.confirmation_type, c.name as client_name,
           COALESCE(r.name, r.full_name, prop_creator.name, prop_creator.full_name) as requested_by_name
    FROM invoices i
    LEFT JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN partners c ON b.client_id = c.id
    LEFT JOIN approval_requests ar ON ar.entity_type = 'invoice' AND ar.entity_id = i.id AND ar.status = 'pending'
    LEFT JOIN users r ON ar.requested_by = r.id
    LEFT JOIN proposals prop ON b.proposal_id = prop.id
    LEFT JOIN users prop_creator ON prop.created_by = prop_creator.id
    WHERE i.approval_status = 'pending_approval'
    ORDER BY i.created_at DESC
")->fetchAll();

// Fetch pending client printing invoices
$clientPrintings = $pdo->query("
    SELECT 
        r.po_number,
        r.client_id,
        c.name as client_name,
        MAX(r.campaign_name) as campaign_name,
        MAX(r.brand_name) as brand_name,
        MAX(r.custom_invoice_number) as custom_invoice_number,
        MAX(r.custom_invoice_date) as custom_invoice_date,
        MAX(r.customer_po_file) as customer_po_file,
        SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as sub_total,
        COALESCE(p.name, p.full_name) as requested_by_name,
        MAX(ar.created_at) as requested_at
    FROM client_printing_rates r
    JOIN partners c ON r.client_id = c.id
    LEFT JOIN sites s ON r.site_id = s.id
    LEFT JOIN approval_requests ar ON ar.entity_type = 'client_printing' AND ar.entity_ref COLLATE utf8mb4_unicode_ci = r.po_number COLLATE utf8mb4_unicode_ci AND ar.status = 'pending'
    LEFT JOIN users p ON ar.requested_by = p.id
    WHERE r.approval_status = 'pending_approval'
    GROUP BY r.po_number, r.client_id, c.name, p.name, p.full_name
    ORDER BY MIN(r.id) DESC
")->fetchAll();

// Fetch pending client mounting invoices
$clientMountings = $pdo->query("
    SELECT 
        r.po_number,
        r.client_id,
        c.name as client_name,
        MAX(r.campaign_name) as campaign_name,
        MAX(r.brand_name) as brand_name,
        MAX(r.custom_invoice_number) as custom_invoice_number,
        MAX(r.custom_invoice_date) as custom_invoice_date,
        MAX(r.attachments) as attachments,
        MAX(r.sub_total) as sub_total,
        MAX(r.cgst) as cgst,
        MAX(r.sgst) as sgst,
        MAX(r.igst) as igst,
        MAX(r.total_amount) as total_amount,
        COALESCE(p.name, p.full_name) as requested_by_name,
        MAX(ar.created_at) as requested_at
    FROM client_mounting_rates r
    JOIN partners c ON r.client_id = c.id
    LEFT JOIN approval_requests ar ON ar.entity_type = 'client_mounting' AND ar.entity_ref COLLATE utf8mb4_unicode_ci = r.po_number COLLATE utf8mb4_unicode_ci AND ar.status = 'pending'
    LEFT JOIN users p ON ar.requested_by = p.id
    WHERE r.approval_status = 'pending_approval'
    GROUP BY r.po_number, r.client_id, c.name, p.name, p.full_name
    ORDER BY MIN(r.id) DESC
")->fetchAll();

// Fetch pending payments
$payments = $pdo->query("
    SELECT p.*, c.name as partner_name, 
           COALESCE(u.name, u.full_name) as requested_by_name,
           inv.invoice_number as linked_invoice_number,
           inv.total_amount as invoice_total,
           inv.sub_total as invoice_sub_total,
           (inv.cgst + inv.sgst + inv.igst) as invoice_tax,
           po.po_number as linked_po_number,
           po.total_amount as po_total,
           COALESCE(b.campaign_name, po.campaign_name) as campaign_name,
           COALESCE(b.brand_name, po.brand_name) as brand_name
    FROM payments p
    LEFT JOIN partners c ON p.partner_id = c.id
    LEFT JOIN approval_requests ar ON ar.entity_type = 'payment' AND ar.entity_id = p.id
    LEFT JOIN users u ON ar.requested_by = u.id
    LEFT JOIN invoices inv ON p.invoice_id = inv.id
    LEFT JOIN bookings b ON inv.booking_id = b.id
    LEFT JOIN purchase_orders po ON p.proposal_id = po.id
    WHERE p.approval_status = 'pending_approval'
    ORDER BY p.created_at DESC
")->fetchAll();

// Fetch recent actions (approved/rejected)
$recentActions = $pdo->query("
    SELECT ar.*, COALESCE(u1.name, u1.full_name) as requested_name, COALESCE(u2.name, u2.full_name) as reviewed_name
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
$pendingClientMountings = count($clientMountings);
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

    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); cursor: pointer; <?php echo $tab === 'client_mounting' ? 'border-left: 4px solid var(--primary);' : ''; ?>" onclick="window.location='?tab=client_mounting'">
        <div style="background: #e0e7ff; color: #4f46e5; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
            <i class="fas fa-hammer"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Client Mounting</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: <?php echo $pendingClientMountings > 0 ? '#4f46e5' : '#0f172a'; ?>;"><?php echo $pendingClientMountings; ?></div>
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
            'invoices'  => ['Invoices', 'fa-file-invoice-dollar', $pendingInvoices],
            'client_printing' => ['Client Printing', 'fa-print', $pendingClientPrintings],
            'client_mounting' => ['Client Mounting', 'fa-hammer', $pendingClientMountings],
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
                    <th>Campaign / Brand</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Requested By</th>
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
                    <td>
                        <?php if (!empty($p['campaign_name'])): ?>
                            <div style="font-size: 0.75rem; color: #2563eb; font-weight: 700; background: #eff6ff; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($p['campaign_name']); ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(htmlspecialchars_decode($p['client_name'] ?: '—')); ?></td>
                    <td>
                        <div style="font-weight: 800; color: #059669; font-size: 0.9rem;"><?php echo formatCurrency($p['total_amount']); ?></div>
                        <div style="font-size: 0.68rem; color: #64748b;">+GST: <?php echo formatCurrency($p['tax_amount']); ?></div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #0f172a; margin-top: 2px;">Total: <?php echo formatCurrency($p['grand_total']); ?></div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #475569;">
                                <?php echo substr($p['requested_by_name'] ?: '?', 0, 1); ?>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($p['requested_by_name'] ?: 'System'); ?></span>
                        </div>
                    </td>
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
                    <th>Campaign / Brand</th>
                    <th>Amount</th>
                    <th>Requested By</th>
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
                    <td><?php echo htmlspecialchars(htmlspecialchars_decode($po['vendor_name'] ?: '—')); ?></td>
                    <td>
                        <?php 
                        $camp_brand = [];
                        if (!empty($po['campaign_name'])) $camp_brand[] = trim($po['campaign_name']);
                        if (!empty($po['brand_name'])) $camp_brand[] = trim($po['brand_name']);
                        $display_camp_brand = implode(' / ', $camp_brand);
                        if (!empty($display_camp_brand)):
                        ?>
                            <div style="font-size: 0.75rem; color: #2563eb; font-weight: 700; background: #eff6ff; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $poSubTotal = floatval($po['po_amount']);
                        $poCgst = floatval($po['cgst_amount']);
                        $poSgst = floatval($po['sgst_amount']);
                        $poIgst = floatval($po['igst_amount']);
                        $poGrand = floatval($po['total_amount']);
                        $poTax = $poCgst + $poSgst + $poIgst;
                        ?>
                        <div style="font-weight: 800; color: #059669; font-size: 0.9rem;">₹<?php echo number_format($poSubTotal, 2); ?></div>
                        <div style="font-size: 0.68rem; color: #64748b;">+GST: ₹<?php echo number_format($poTax, 2); ?></div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #0f172a; margin-top: 2px;">Total: ₹<?php echo number_format($poGrand, 2); ?></div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #475569;">
                                <?php echo substr($po['requested_by_name'] ?: '?', 0, 1); ?>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($po['requested_by_name'] ?: 'System'); ?></span>
                        </div>
                    </td>
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


    <!-- ====== INVOICES TAB ====== -->
    <?php if ($tab === 'invoices'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Type</th>
                    <th>Campaign / Brand</th>
                    <th>Client</th>
                    <th>Requested By</th>
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
                        <a href="../financials/invoice_view.php?id=<?php echo $inv['id']; ?>" target="_blank" style="text-decoration: none; color: var(--primary);">
                            <?php echo htmlspecialchars($inv['invoice_number']); ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        $invType = $inv['type'] ?? 'tax';
                        if ($invType === 'proforma') echo '<span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">DRAFT</span>';
                        elseif ($invType === 'estimate') echo '<span style="background: #e0f2fe; color: #0284c7; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">ESTIMATE</span>';
                        elseif ($invType === 'ro') echo '<span style="background: #e2e8f0; color: #475569; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">RO</span>';
                        else echo '<span style="background: #ecfdf5; color: #059669; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800;">TAX</span>';
                        ?>
                    </td>
                    <td>
                        <?php 
                        $camp_brand = [];
                        if (!empty($inv['campaign_name'])) $camp_brand[] = trim($inv['campaign_name']);
                        if (!empty($inv['brand_name'])) $camp_brand[] = trim($inv['brand_name']);
                        $display_camp_brand = implode(' / ', $camp_brand);
                        if (!empty($display_camp_brand)):
                        ?>
                            <div style="font-size: 0.75rem; color: #2563eb; font-weight: 700; background: #eff6ff; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(htmlspecialchars_decode($inv['client_name'] ?: '—')); ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #475569;">
                                <?php echo substr($inv['requested_by_name'] ?: '?', 0, 1); ?>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($inv['requested_by_name'] ?: 'System'); ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($invType === 'tax' || $invType === 'ro'): ?>
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
                    <td>
                        <?php 
                        $subTotal = floatval($inv['sub_total']);
                        $cgst = floatval($inv['cgst']);
                        $sgst = floatval($inv['sgst']);
                        $igst = floatval($inv['igst']);
                        $grandTotal = floatval($inv['total_amount']);
                        ?>
                        <div style="font-weight: 800; color: #059669; font-size: 0.9rem;">₹<?php echo number_format($subTotal, 2); ?></div>
                        <?php if ($igst > 0): ?>
                            <div style="font-size: 0.68rem; color: #64748b;">IGST: ₹<?php echo number_format($igst, 2); ?></div>
                        <?php elseif ($cgst > 0 || $sgst > 0): ?>
                            <div style="font-size: 0.68rem; color: #64748b;">CGST+SGST: ₹<?php echo number_format($cgst + $sgst, 2); ?></div>
                        <?php else: ?>
                            <div style="font-size: 0.68rem; color: #94a3b8;">No GST</div>
                        <?php endif; ?>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #0f172a; margin-top: 2px;">Total: ₹<?php echo number_format($grandTotal, 2); ?></div>
                    </td>
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
                <tr><td colspan="9" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All invoices have been reviewed!</td></tr>
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
                    <th>PO Ref & Invoice Details</th>
                    <th>Campaign / Brand</th>
                    <th>Client</th>
                    <th>Requested By</th>
                    <th>Amount Details</th>
                    <th>Document</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientPrintings as $cp): ?>
                <tr id="row-client_printing-<?php echo htmlspecialchars($cp['po_number']); ?>">
                    <td>
                        <a href="../partners/view_client_printing_po.php?client_id=<?php echo $cp['client_id']; ?>&po_number=<?php echo urlencode($cp['po_number']); ?>" target="_blank" style="font-weight: 700; color: var(--primary); text-decoration: none;">
                            #<?php echo htmlspecialchars($cp['po_number']); ?>
                        </a>
                        <div style="font-size: 0.75rem; color: #475569; margin-top: 4px;">
                            <strong>Inv #:</strong> <?php echo htmlspecialchars($cp['custom_invoice_number'] ?: 'N/A'); ?><br>
                            <strong>Inv Date:</strong> <?php echo $cp['custom_invoice_date'] ? date('d M Y', strtotime($cp['custom_invoice_date'])) : 'N/A'; ?>
                        </div>
                        <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 6px;">
                            Req: <?php echo $cp['requested_at'] ? date('d M Y, h:i A', strtotime($cp['requested_at'])) : date('d M Y, h:i A'); ?>
                        </div>
                    </td>
                    <td>
                        <?php 
                        $camp_brand = [];
                        if (!empty($cp['campaign_name'])) $camp_brand[] = trim($cp['campaign_name']);
                        if (!empty($cp['brand_name'])) $camp_brand[] = trim($cp['brand_name']);
                        $display_camp_brand = implode(' / ', $camp_brand);
                        if (!empty($display_camp_brand)):
                        ?>
                            <div style="font-size: 0.75rem; color: #2563eb; font-weight: 700; background: #eff6ff; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars(htmlspecialchars_decode($cp['client_name'] ?: 'Unknown')); ?></strong></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #475569;">
                                <?php echo substr($cp['requested_by_name'] ?: '?', 0, 1); ?>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($cp['requested_by_name'] ?: 'System'); ?></span>
                        </div>
                    </td>
                    <td>
                        <?php 
                        $subTotal = floatval($cp['sub_total']);
                        $gst = $subTotal * 0.18;
                        $grandTotal = $subTotal + $gst;
                        ?>
                        <div style="font-weight: 800; color: #059669; font-size: 0.95rem;">₹<?php echo number_format($subTotal, 2); ?></div>
                        <div style="font-size: 0.68rem; color: #64748b;">+GST: ₹<?php echo number_format($gst, 2); ?></div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #0f172a; margin-top: 2px;">Total: ₹<?php echo number_format($grandTotal, 2); ?></div>
                    </td>
                    <td>
                        <?php if ($cp['customer_po_file']): ?>
                            <a href="../../<?php echo htmlspecialchars($cp['customer_po_file']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; color: #0284c7; background: #f0f9ff; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-decoration: none;">
                                <i class="fas fa-file-pdf"></i> View Attachment
                            </a>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 0.8rem;"><i class="fas fa-minus-circle"></i> No Doc</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-primary" onclick="approveEntity('client_printing', '<?php echo $cp['po_number']; ?>')" style="padding: 6px 12px; font-size: 0.8rem; background: #10b981; border: none; cursor: pointer; color: white; border-radius: 6px; font-weight: 700;"><i class="fas fa-check"></i> Approve</button>
                            <button class="btn btn-primary" onclick="rejectEntity('client_printing', '<?php echo $cp['po_number']; ?>')" style="padding: 6px 12px; font-size: 0.8rem; background: #ef4444; border: none; cursor: pointer; color: white; border-radius: 6px; font-weight: 700;"><i class="fas fa-times"></i> Reject</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($clientPrintings)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All client printing requests have been reviewed!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ====== CLIENT MOUNTING TAB ====== -->
    <?php if ($tab === 'client_mounting'): ?>
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th>PO Ref & Invoice Details</th>
                    <th>Campaign / Brand</th>
                    <th>Client</th>
                    <th>Requested By</th>
                    <th>Amount Details</th>
                    <th>Document</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientMountings as $cm): ?>
                <tr id="row-client_mounting-<?php echo htmlspecialchars($cm['po_number']); ?>">
                    <td>
                        <a href="../operations/view_mounting_invoice.php?client_id=<?php echo $cm['client_id']; ?>&po_number=<?php echo urlencode($cm['po_number']); ?>" target="_blank" style="font-weight: 700; color: var(--primary); text-decoration: none;">
                            #<?php echo htmlspecialchars($cm['po_number']); ?>
                        </a>
                        <div style="font-size: 0.75rem; color: #475569; margin-top: 4px;">
                            <strong>Inv #:</strong> <?php echo htmlspecialchars($cm['custom_invoice_number'] ?: 'N/A'); ?><br>
                            <strong>Inv Date:</strong> <?php echo $cm['custom_invoice_date'] ? date('d M Y', strtotime($cm['custom_invoice_date'])) : 'N/A'; ?>
                        </div>
                        <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 6px;">
                            Req: <?php echo $cm['requested_at'] ? date('d M Y, h:i A', strtotime($cm['requested_at'])) : date('d M Y, h:i A'); ?>
                        </div>
                    </td>
                    <td>
                        <?php 
                        $camp_brand = [];
                        if (!empty($cm['campaign_name'])) $camp_brand[] = trim($cm['campaign_name']);
                        if (!empty($cm['brand_name'])) $camp_brand[] = trim($cm['brand_name']);
                        $display_camp_brand = implode(' / ', $camp_brand);
                        if (!empty($display_camp_brand)):
                        ?>
                            <div style="font-size: 0.75rem; color: #2563eb; font-weight: 700; background: #eff6ff; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars(htmlspecialchars_decode($cm['client_name'] ?: 'Unknown')); ?></strong></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #475569;">
                                <?php echo substr($cm['requested_by_name'] ?: '?', 0, 1); ?>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($cm['requested_by_name'] ?: 'System'); ?></span>
                        </div>
                    </td>
                    <td>
                        <?php 
                        $subTotal = floatval($cm['sub_total']);
                        $cgst = floatval($cm['cgst']);
                        $sgst = floatval($cm['sgst']);
                        $igst = floatval($cm['igst']);
                        $grandTotal = floatval($cm['total_amount']);
                        ?>
                        <div style="font-weight: 800; color: #059669; font-size: 0.95rem;">₹<?php echo number_format($subTotal, 2); ?></div>
                        <?php if ($igst > 0): ?>
                            <div style="font-size: 0.68rem; color: #64748b;">IGST: ₹<?php echo number_format($igst, 2); ?></div>
                        <?php else: ?>
                            <div style="font-size: 0.68rem; color: #64748b;">CGST+SGST: ₹<?php echo number_format($cgst + $sgst, 2); ?></div>
                        <?php endif; ?>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #0f172a; margin-top: 2px;">Total: ₹<?php echo number_format($grandTotal, 2); ?></div>
                    </td>
                    <td>
                        <?php if ($cm['attachments']): ?>
                            <a href="../../<?php echo htmlspecialchars($cm['attachments']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; color: #0284c7; background: #f0f9ff; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-decoration: none;">
                                <i class="fas fa-file-pdf"></i> View Attachment
                            </a>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 0.8rem;"><i class="fas fa-minus-circle"></i> No Doc</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-primary" onclick="approveEntity('client_mounting', '<?php echo $cm['po_number']; ?>')" style="padding: 6px 12px; font-size: 0.8rem; background: #10b981; border: none; cursor: pointer; color: white; border-radius: 6px; font-weight: 700;"><i class="fas fa-check"></i> Approve</button>
                            <button class="btn btn-primary" onclick="rejectEntity('client_mounting', '<?php echo $cm['po_number']; ?>')" style="padding: 6px 12px; font-size: 0.8rem; background: #ef4444; border: none; cursor: pointer; color: white; border-radius: 6px; font-weight: 700;"><i class="fas fa-times"></i> Reject</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($clientMountings)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All client mounting requests have been reviewed!</td></tr>
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
                    <th>Campaign / Brand</th>
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
                        <div style="font-size: 0.85rem; margin-top: 4px;">
                            <strong>Txn ID:</strong> <span style="color: #0f172a; font-weight: 700;"><?php echo htmlspecialchars($pay['transaction_id'] ?: 'No Txn ID'); ?></span>
                        </div>
                        <div style="font-size: 0.75rem; color: #475569; margin-top: 4px; border-top: 1px dashed #e2e8f0; padding-top: 4px; display: flex; flex-direction: column; gap: 2px;">
                            <?php if ($pay['type'] === 'receivable' && !empty($pay['linked_invoice_number'])): ?>
                                <div><strong>Inv #:</strong> <a href="../financials/invoice_view.php?id=<?php echo $pay['invoice_id']; ?>" target="_blank" style="color: var(--primary); font-weight: 700; text-decoration: none;"><?php echo htmlspecialchars($pay['linked_invoice_number']); ?></a></div>
                                <div><strong>Inv Total:</strong> <span style="font-weight:600;"><?php echo formatCurrency($pay['invoice_total']); ?></span> <span style="font-size:0.68rem; color:#64748b;">(Base: <?php echo formatCurrency($pay['invoice_sub_total']); ?> + GST: <?php echo formatCurrency($pay['invoice_tax']); ?>)</span></div>
                            <?php elseif ($pay['type'] === 'payable' && !empty($pay['linked_po_number'])): ?>
                                <div><strong>PO #:</strong> <a href="../financials/po_view.php?id=<?php echo $pay['proposal_id']; ?>" target="_blank" style="color: var(--primary); font-weight: 700; text-decoration: none;"><?php echo htmlspecialchars($pay['linked_po_number']); ?></a></div>
                                <div><strong>PO Total:</strong> <span style="font-weight:600;"><?php echo formatCurrency($pay['po_total']); ?></span></div>
                            <?php endif; ?>
                            <div><strong>Payment Date:</strong> <?php echo date('d M Y', strtotime($pay['payment_date'])); ?></div>
                            <?php if (!empty($pay['notes'])): ?>
                                <div style="margin-top: 4px; background: #f8fafc; border-left: 2px solid #cbd5e1; padding: 2px 6px; font-style: italic; color: #64748b;">"<?php echo htmlspecialchars($pay['notes']); ?>"</div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php 
                        $camp_brand = [];
                        if (!empty($pay['campaign_name'])) $camp_brand[] = trim($pay['campaign_name']);
                        if (!empty($pay['brand_name'])) $camp_brand[] = trim($pay['brand_name']);
                        $display_camp_brand = implode(' / ', $camp_brand);
                        if (!empty($display_camp_brand)):
                        ?>
                            <div style="font-size: 0.75rem; color: #2563eb; font-weight: 700; background: #eff6ff; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars(htmlspecialchars_decode($pay['partner_name'] ?: 'Unknown')); ?></strong></td>
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
                <tr><td colspan="6" style="text-align: center; padding: 3rem; color: #94a3b8;"><i class="fas fa-check-circle" style="font-size: 2rem; display: block; margin-bottom: 0.75rem; color: #10b981;"></i>All payment requests have been reviewed!</td></tr>
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
