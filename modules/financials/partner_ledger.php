<?php
$activePage = 'ledger';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : (isset($_GET['client_id']) ? intval($_GET['client_id']) : 0);

if (!$partner_id) {
    $pageTitle = 'Partner Ledger';
    include_once __DIR__ . '/../../includes/header.php';
    echo "<div class='card'>Invalid Partner ID.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch Partner Info
$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();

if (!$partner) {
    $pageTitle = 'Partner Ledger';
    include_once __DIR__ . '/../../includes/header.php';
    echo "<div class='card'>Partner not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$pType = $partner['type']; // 'client' or 'vendor'
$pageTitle = ($pType == 'client') ? 'Client Ledger' : 'Party Ledger';

include_once __DIR__ . '/../../includes/header.php';

// Fetch all Bills (Debits)
$ledgerEntries = [];

if ($pType == 'client') {

    // 2. Fetch Invoices
    $stmtInv = $pdo->prepare("
          SELECT i.id, CONVERT('invoice' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type, i.created_at as date, CONVERT(i.invoice_number USING utf8mb4) COLLATE utf8mb4_unicode_ci as ref,
                 CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci as remark,
                 i.sub_total as base_amt, (i.cgst + i.sgst + i.igst) as tax_amount, i.total_amount as total_amt,
                 i.total_amount as debit, 0 as credit, CONVERT('Billed' USING utf8mb4) COLLATE utf8mb4_unicode_ci as status, CONVERT(i.approval_status USING utf8mb4) COLLATE utf8mb4_unicode_ci as approval_status,
                 CONVERT(b.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
                 CONVERT(b.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
                 CONVERT(CASE WHEN b.confirmation_type = 'email' THEN 'Email Conf' ELSE b.customer_po_no END USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
                 CASE WHEN b.confirmation_type = 'email' THEN b.email_date ELSE b.customer_po_date END as po_date
          FROM invoices i
          JOIN bookings b ON i.booking_id = b.id
          WHERE b.client_id = ? AND i.approval_status = 'approved'

          UNION ALL

          SELECT MIN(r.id) as id, CONVERT('invoice' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type, COALESCE(MIN(r.custom_invoice_date), DATE(MIN(r.created_at))) as date,
                 COALESCE(CONVERT(r.custom_invoice_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(r.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('RATE-', MIN(r.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as ref,
                 CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci as remark,
                 SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as base_amt,
                 SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 0.18 as tax_amount,
                 SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as total_amt,
                 SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as debit,
                 0 as credit, CONVERT('Billed' USING utf8mb4) COLLATE utf8mb4_unicode_ci as status, CONVERT('approved' USING utf8mb4) COLLATE utf8mb4_unicode_ci as approval_status,
                 CONVERT(MAX(r.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
                 CONVERT(MAX(r.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
                 CONVERT(MAX(r.customer_po_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
                 MAX(r.customer_po_date) as po_date
          FROM client_printing_rates r
          LEFT JOIN sites s ON r.site_id = s.id
          WHERE r.client_id = ? AND r.is_final_invoice = 1 AND r.approval_status = 'approved'
          GROUP BY COALESCE(r.po_number, r.id)

          UNION ALL

          SELECT MIN(m.id) as id, CONVERT('invoice' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type, COALESCE(MIN(m.custom_invoice_date), DATE(MIN(m.created_at))) as date,
                 COALESCE(CONVERT(m.custom_invoice_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(m.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('CMI-', MIN(m.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as ref,
                 CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci as remark,
                 SUM(m.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as base_amt,
                 SUM(m.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 0.18 as tax_amount,
                 SUM(m.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as total_amt,
                 SUM(m.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as debit,
                 0 as credit, CONVERT('Mounting' USING utf8mb4) COLLATE utf8mb4_unicode_ci as status, CONVERT('approved' USING utf8mb4) COLLATE utf8mb4_unicode_ci as approval_status,
                 CONVERT(MAX(m.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
                 CONVERT(MAX(m.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
                 CONVERT(MAX(m.customer_po_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
                 MAX(m.customer_po_date) as po_date
          FROM client_mounting_rates m
          LEFT JOIN sites s ON m.site_id = s.id
          WHERE m.client_id = ? AND m.is_final_invoice = 1 AND m.approval_status = 'approved'
          GROUP BY COALESCE(m.po_number, m.id)
    ");
    $stmtInv->execute([$partner_id, $partner_id, $partner_id]);
    $invoices = $stmtInv->fetchAll();
    foreach ($invoices as $inv) {
        $ledgerEntries[] = $inv;
    }
} else {
    // Vendor Logic
    $stmtPO = $pdo->prepare("
        SELECT id, CONVERT('po' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type, po_date as date, CONVERT(po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci as ref, 
               po_amount as base_amt, (COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0)) as tax_amount, 
               COALESCE(NULLIF(total_amount, 0), po_amount + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0)) as total_amt, 
               COALESCE(NULLIF(total_amount, 0), po_amount + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0)) as debit, 
               0 as credit, CONVERT(status USING utf8mb4) COLLATE utf8mb4_unicode_ci as status,
               CONVERT(campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               vendor_invoice_date as po_date
        FROM purchase_orders 
        WHERE vendor_id = ? AND approval_status = 'approved'
        
        UNION ALL
        
        SELECT MIN(r.id) as id, CONVERT('po' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type, DATE(MIN(r.created_at)) as date, 
               COALESCE(CONVERT(r.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('RATE-', MIN(r.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as ref,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as base_amt,
               0 as tax_amount,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as debit,
               0 as credit, CONVERT('approved' USING utf8mb4) COLLATE utf8mb4_unicode_ci as status,
               CONVERT(MAX(r.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(MAX(r.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(MAX(r.vendor_invoice_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               MAX(r.vendor_invoice_date) as po_date
        FROM vendor_printing_rates r
        LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.vendor_id = ? AND (r.po_number IS NULL OR r.po_number NOT IN (SELECT po_number FROM purchase_orders WHERE po_number IS NOT NULL))
        GROUP BY COALESCE(r.po_number, r.id)
    ");
    $stmtPO->execute([$partner_id, $partner_id]);
    $pos = $stmtPO->fetchAll();
    foreach ($pos as $po) {
        $ledgerEntries[] = $po;
    }
}

// 3. Fetch Payments
$pMode = ($pType == 'client') ? 'receivable' : 'payable';
$dbType = ($pType == 'client') ? 'receivable' : 'payable';
$stmtPay = $pdo->prepare("
    SELECT p.id, 'payment' as type, p.payment_date as date, p.transaction_id as ref,
           p.notes as remark,
           p.amount as base_amt, 0 as tax_amount, p.amount as total_amt,
           0 as debit, p.amount as credit, p.payment_mode as status, p.approval_status,
           COALESCE(p.rejection_reason, ar.remarks) as rejection_reason,
           COALESCE(CONVERT(b.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(prop.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(po.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as campaign_name,
           COALESCE(CONVERT(b.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(po.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as brand_name,
           COALESCE(CONVERT(CASE WHEN b.confirmation_type = 'email' THEN 'Email Conf' ELSE b.customer_po_no END USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(po.vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as po_number,
           COALESCE(CASE WHEN b.confirmation_type = 'email' THEN b.email_date ELSE b.customer_po_date END, po.vendor_invoice_date, vpr.vendor_invoice_date) as po_date,
           p.invoice_id, p.proposal_id
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.id
    LEFT JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN proposals prop ON p.proposal_id = prop.id
    LEFT JOIN purchase_orders po ON (p.type = 'payable' AND p.proposal_id = po.id)
    LEFT JOIN vendor_printing_rates vpr ON (p.type = 'payable' AND p.proposal_id = vpr.id)
    LEFT JOIN approval_requests ar ON (ar.entity_type = 'payment' AND ar.entity_id = p.id)
    WHERE p.partner_id = ? AND p.type = ?
");
$stmtPay->execute([$partner_id, $dbType]);
$payments = $stmtPay->fetchAll();
foreach ($payments as $pay) {
    $ledgerEntries[] = $pay;
}

// Sort by Date
usort($ledgerEntries, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Summary Stats
$totalContracted = 0;
$totalInvoiced = 0;
$totalReceived = 0;

$invoicePaidMap = [];
$poPaidMap = [];

foreach ($ledgerEntries as $item) {
    if ($item['type'] === 'invoice' || $item['type'] === 'po') $totalInvoiced += $item['total_amt'];
    if ($item['type'] === 'payment') {
        if ($item['approval_status'] === 'approved' || empty($item['approval_status'])) {
            $totalReceived += $item['total_amt'];
            if (!empty($item['invoice_id'])) {
                $invoicePaidMap[$item['invoice_id']] = ($invoicePaidMap[$item['invoice_id']] ?? 0) + $item['base_amt'];
            }
            if (!empty($item['proposal_id'])) {
                $poPaidMap[$item['proposal_id']] = ($poPaidMap[$item['proposal_id']] ?? 0) + $item['base_amt'];
            }
        }
    }
}
$outstanding = $totalInvoiced - $totalReceived;

$balance = 0;
$balanceLabel = $pType == 'client' ? 'DUE' : 'PAYABLE';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;"><?php echo ($pType == 'client') ? 'Client Ledger' : 'Party Ledger'; ?></h1>
        <p style="color: #64748b; margin: 0; font-weight: 500;">Partner: <strong style="color: #0f172a;"><?php echo $partner['name']; ?></strong></p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <button onclick="exportExcel()" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <i class="fas fa-file-excel" style="color: #10b981;"></i> Export Excel
        </button>
        <button onclick="window.print()" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; cursor: pointer;">
            <i class="fas fa-print"></i> Print Statement
        </button>
        <a href="ledgers.php?type=<?php echo $pType; ?>" class="btn" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <?php if (canAdd('financials')): ?>
        <button class="btn btn-primary" onclick="addPayment(<?php echo $partner_id; ?>, '<?php echo $pMode; ?>')" style="padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; background: var(--primary); color: white; border: none; cursor: pointer;">
            <i class="fas fa-plus"></i> <?php echo ($pType == 'client') ? 'Record Receipt' : 'Record Payment Made'; ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.25rem; border-left: 4px solid #f59e0b; border-radius: 12px;">
        <div style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Total Billed</div>
        <div style="font-size: 1.5rem; font-weight: 900; color: #1e293b; margin-top: 0.25rem;"><?php echo formatCurrency($totalInvoiced); ?></div>
    </div>
    <div class="card" style="padding: 1.25rem; border-left: 4px solid #10b981; border-radius: 12px;">
        <div style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase;"><?php echo ($pType == 'client') ? 'Total Received' : 'Total Paid'; ?></div>
        <div style="font-size: 1.5rem; font-weight: 900; color: #1e293b; margin-top: 0.25rem;"><?php echo formatCurrency($totalReceived); ?></div>
    </div>
    <div class="card" style="padding: 1.25rem; border-left: 4px solid #ef4444; background: #fef2f2; border-radius: 12px;">
        <div style="font-size: 0.7rem; font-weight: 800; color: #ef4444; text-transform: uppercase;">Outstanding</div>
        <div style="font-size: 1.5rem; font-weight: 900; color: #b91c1c; margin-top: 0.25rem;"><?php echo formatCurrency($outstanding); ?></div>
    </div>
</div>

<div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;" class="no-print">
    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div style="display: flex; gap: 1rem; align-items: center;">
            <label style="font-size: 0.85rem; font-weight: 700; color: #475569;">Date Filter:</label>
            <input type="date" id="filterFromDate" onchange="filterLedger()" style="padding: 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.9rem;">
            <span style="font-weight: 700; color: #94a3b8;">to</span>
            <input type="date" id="filterToDate" onchange="filterLedger()" style="padding: 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.9rem;">
        </div>
        
        <?php
        $uniqueCampaigns = [];
        foreach ($ledgerEntries as $item) {
            $parts = [];
            if (!empty($item['campaign_name'])) $parts[] = trim($item['campaign_name']);
            if (!empty($item['brand_name'])) $parts[] = trim($item['brand_name']);
            if (!empty($parts)) {
                $combined = implode(' / ', $parts);
                $uniqueCampaigns[$combined] = true;
            }
        }
        ksort($uniqueCampaigns);
        ?>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <label style="font-size: 0.85rem; font-weight: 700; color: #475569;">Campaign/Brand:</label>
            <select id="filterCampaign" onchange="filterLedger()" style="padding: 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.9rem; min-width: 200px;">
                <option value="">All Campaigns/Brands</option>
                <?php foreach (array_keys($uniqueCampaigns) as $cOption): ?>
                    <option value="<?php echo htmlspecialchars($cOption); ?>"><?php echo htmlspecialchars($cOption); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <input type="text" id="ledgerSearch" placeholder="Search transactions..." style="padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0; width: 300px; font-size: 0.9rem;" onkeyup="filterLedger()">
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <table class="table" id="ledgerTable" style="margin: 0; width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Date</th>
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Type</th>
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;"><?php echo ($pType == 'client') ? 'Invoice Number' : 'PO Number'; ?></th>
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;"><?php echo ($pType == 'client') ? 'Customer PO Number' : 'Inv Number'; ?></th>
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;"><?php echo ($pType == 'client') ? 'PO Date' : 'Inv Date'; ?></th>
                <th style="padding: 1rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Campaign / Brand</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Base Amt</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Tax (GST)</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Grand Total</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;"><?php echo ($pType == 'client') ? 'Received' : 'Paid Out'; ?></th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Actual Balance</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;" class="no-print">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ledgerEntries as $item): 
                // Check if payment is linked to any Invoice or PO, or if it explicitly mentions "Against" in remarks
                $isLinkedPayment = false;
                if ($item['type'] === 'payment') {
                    if ($item['approval_status'] === 'approved' || empty($item['approval_status'])) {
                        if (!empty($item['invoice_id']) || !empty($item['proposal_id'])) {
                            $isLinkedPayment = true;
                        } elseif (!empty($item['remark']) && stripos($item['remark'], 'Against') !== false) {
                            $isLinkedPayment = true;
                        }
                    }
                }

                $paid = 0;
                if ($item['type'] === 'invoice' || $item['type'] === 'po') {
                    $paid = ($item['type'] === 'po') ? ($poPaidMap[$item['id']] ?? 0) : ($invoicePaidMap[$item['id']] ?? 0);
                }
                $rowOutstanding = ($item['type'] === 'invoice' || $item['type'] === 'po') ? ($item['total_amt'] - $paid) : 0;

                // Only Invoices and Payments affect the financial balance
                if ($item['type'] === 'invoice' || $item['type'] === 'po') {
                    $balance += $rowOutstanding;
                } elseif ($item['type'] === 'payment') {
                    if (!$isLinkedPayment && ($item['approval_status'] === 'approved' || empty($item['approval_status']))) {
                        $balance -= $item['total_amt'];
                    }
                }
                
                $balanceLabel = $balance > 0 ? ($pType == 'client' ? 'DUE' : 'PAYABLE') : 'ADV';
            ?>
            <?php if ($item['type'] !== 'payment' && !$isLinkedPayment): ?>
            <tr style="background: <?php echo $item['type'] === 'booking' ? '#f8fafc' : '#fff'; ?>; border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 1rem; color: #475569; font-size: 0.85rem; white-space: nowrap;"><?php echo date('d M Y', strtotime($item['date'])); ?></td>
                <td style="padding: 1rem; white-space: nowrap;">
                    <?php if ($item['type'] === 'booking'): ?>
                        <span style="background: #eff6ff; color: #1e40af; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">PROPOSAL</span>
                    <?php elseif ($item['type'] === 'invoice'): ?>
                        <span style="background: #fff7ed; color: #9a3412; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">INVOICE</span>
                    <?php elseif ($item['type'] === 'po'): ?>
                        <span style="background: #fff7ed; color: #9a3412; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">PURCHASE ORDER</span>
                    <?php else: ?>
                        <span style="background: #ecfdf5; color: #065f46; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">PAYMENT</span>
                        <?php if (($item['approval_status'] ?? '') === 'pending_approval' || ($item['approval_status'] ?? '') === 'pending'): ?>
                            <span style="background: #fff7ed; color: #c2410c; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; margin-left: 5px; display: inline-block;">PENDING</span>
                        <?php elseif (($item['approval_status'] ?? '') === 'rejected'): ?>
                            <span style="background: #fef2f2; color: #b91c1c; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; margin-left: 5px; display: inline-block;">REJECTED</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="padding: 1rem; white-space: nowrap;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.85rem;">
                        <?php echo htmlspecialchars($item['ref'] ?: 'N/A'); ?>
                    </div>
                    <?php if (!empty($item['remark'])): ?>
                        <?php if (stripos($item['remark'], 'Against ') === 0): ?>
                            <div style="font-size: 0.72rem; color: #16a34a; font-weight: 700; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px; background: #f0fdf4; padding: 2px 6px; border-radius: 4px; border: 1px solid #dcfce7;">
                                <i class="fas fa-link" style="font-size:0.65rem;"></i> <?php echo htmlspecialchars($item['remark']); ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 0.7rem; color: #0d9488; font-style: italic; margin-top: 3px;">
                                <i class="fas fa-sticky-note" style="font-size:0.6rem;"></i> <?php echo htmlspecialchars($item['remark']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($item['status']): ?>
                        <div style="font-size: 0.65rem; color: #94a3b8; text-transform: capitalize; margin-top: 3px;"><?php echo $item['status']; ?></div>
                    <?php endif; ?>
                    <?php if ($item['type'] === 'payment' && ($item['approval_status'] ?? '') === 'rejected'): ?>
                        <div style="font-size: 0.72rem; color: #ef4444; font-weight: 700; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px; background: #fef2f2; padding: 2px 6px; border-radius: 4px; border: 1px solid #fee2e2;">
                            <i class="fas fa-ban" style="font-size:0.65rem;"></i> Rejected<?php if (!empty($item['rejection_reason'])) echo ': ' . htmlspecialchars($item['rejection_reason']); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="padding: 1rem; font-weight: 700; color: #0f172a; font-size: 0.85rem; white-space: nowrap;">
                    <?php echo !empty($item['po_number']) ? htmlspecialchars($item['po_number']) : '<span style="color: #cbd5e1;">-</span>'; ?>
                </td>
                <td style="padding: 1rem; color: #475569; font-size: 0.85rem; white-space: nowrap;">
                    <?php echo (!empty($item['po_date']) && $item['po_date'] !== '0000-00-00') ? date('d M Y', strtotime($item['po_date'])) : '<span style="color: #cbd5e1;">-</span>'; ?>
                </td>
                <?php
                $parts = [];
                if (!empty($item['campaign_name'])) $parts[] = trim($item['campaign_name']);
                if (!empty($item['brand_name'])) $parts[] = trim($item['brand_name']);
                $combinedCampaignBrand = implode(' / ', $parts);
                ?>
                <td style="padding: 1rem;" data-campaign-brand="<?php echo htmlspecialchars($combinedCampaignBrand); ?>">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <?php if (!empty($item['campaign_name'])): ?>
                            <div style="font-size: 0.72rem; color: #2563eb; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; padding: 2px 6px; border-radius: 4px; align-self: flex-start;">
                                <i class="fas fa-bullhorn" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($item['campaign_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['brand_name'])): ?>
                            <div style="font-size: 0.72rem; color: #475569; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; align-self: flex-start;">
                                <i class="fas fa-tag" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($item['brand_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (empty($item['campaign_name']) && empty($item['brand_name'])): ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 600; color: #475569; font-size: 0.85rem;">
                    <?php echo $item['base_amt'] > 0 ? formatCurrency($item['base_amt']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 600; color: #64748b; font-size: 0.85rem;">
                    <?php echo $item['tax_amount'] > 0 ? formatCurrency($item['tax_amount']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 700; color: #1e293b; font-size: 0.85rem;">
                    <?php echo ($item['type'] !== 'payment') ? formatCurrency($item['total_amt']) : '-'; ?>
                </td>
                <td style="padding: 1rem; text-align: right; font-weight: 700; color: #059669; font-size: 0.85rem;">
                    <?php 
                      if ($item['type'] === 'payment') {
                          if ($item['approval_status'] === 'approved' || empty($item['approval_status'])) {
                              echo formatCurrency($item['total_amt']);
                          } else {
                              echo '-';
                          }
                      } else {
                          echo $paid > 0 ? formatCurrency($paid) : '-';
                      }
                    ?>
                </td>
                <td style="padding: 1rem; text-align: right;">
                    <?php if ($balance > 0): ?>
                        <span style="background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; display: inline-flex; align-items: center; gap: 4px; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 800; font-size: 0.82rem; box-shadow: 0 1px 2px rgba(153, 27, 27, 0.05);">
                            <?php echo formatCurrency(abs($balance)); ?> 
                            <span style="font-size: 0.65rem; background: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 4px; font-weight: 900; margin-left: 2px;">
                                <?php echo $balanceLabel; ?>
                            </span>
                        </span>
                    <?php else: ?>
                        <span style="background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; display: inline-flex; align-items: center; gap: 4px; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 800; font-size: 0.82rem; box-shadow: 0 1px 2px rgba(6, 95, 70, 0.05);">
                            <?php echo formatCurrency(abs($balance)); ?> 
                            <span style="font-size: 0.65rem; background: #d1fae5; color: #065f46; padding: 1px 6px; border-radius: 4px; font-weight: 900; margin-left: 2px;">
                                <?php echo $balanceLabel; ?>
                            </span>
                        </span>
                    <?php endif; ?>
                </td>
                <td style="padding: 1rem; text-align: right; white-space: nowrap;" class="no-print">
                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                        <?php if (($item['type'] === 'invoice' || $item['type'] === 'po') && $pType === 'client' && canAdd('financials') && $rowOutstanding > 0): ?>
                            <button onclick="receiveAgainstInvoice(<?php echo $partner_id; ?>, <?php echo floatval($rowOutstanding); ?>, '<?php echo addslashes(htmlspecialchars($item['ref'])); ?>', <?php echo intval($item['id']); ?>)"
                                style="background: #10b981; color: white; border: none; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.72rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2); transition: all 0.2s;"
                                onmouseover="this.style.background='#059669'; this.style.boxShadow='0 4px 6px rgba(16, 185, 129, 0.3)'"
                                onmouseout="this.style.background='#10b981'; this.style.boxShadow='0 2px 4px rgba(16, 185, 129, 0.2)'">
                                <i class="fas fa-hand-holding-usd"></i> Receive
                            </button>
                        <?php endif; ?>

                        <?php if (($item['type'] === 'invoice' || $item['type'] === 'po') && $pType === 'vendor' && canAdd('financials') && $rowOutstanding > 0): ?>
                            <button onclick="payAgainstPO(<?php echo $partner_id; ?>, <?php echo floatval($rowOutstanding); ?>, '<?php echo addslashes(htmlspecialchars($item['ref'])); ?>', <?php echo intval($item['id']); ?>)"
                                style="background: #10b981; color: white; border: none; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.72rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2); transition: all 0.2s;"
                                onmouseover="this.style.background='#059669'; this.style.boxShadow='0 4px 6px rgba(16, 185, 129, 0.3)'"
                                onmouseout="this.style.background='#10b981'; this.style.boxShadow='0 2px 4px rgba(16, 185, 129, 0.2)'">
                                <i class="fas fa-wallet"></i> Pay Now
                            </button>
                        <?php endif; ?>

                        <?php if (($item['type'] === 'invoice' || $item['type'] === 'po') && canAdd('financials')): ?>
                            <button onclick="showInvoicePayments('<?php echo addslashes(htmlspecialchars($item['ref'])); ?>')"
                                style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.72rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                                onmouseover="this.style.background='#e2e8f0'; this.style.borderColor='#94a3b8'"
                                onmouseout="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1'"
                                title="View Payments History">
                                <i class="fas fa-history"></i> History
                            </button>
                        <?php endif; ?>
                        
                        <?php if (($item['type'] === 'invoice' || $item['type'] === 'po') && canAdd('financials')): ?>
                            <a href="#" onclick="viewInvoice('<?php echo addslashes($item['ref']); ?>')" 
                                style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.72rem; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; box-shadow: 0 1px 2px rgba(29, 78, 216, 0.05);"
                                onmouseover="this.style.background='#dbeafe'; this.style.borderColor='#93c5fd'"
                                onmouseout="this.style.background='#eff6ff'; this.style.borderColor='#bfdbfe'">
                                <i class="fas fa-eye"></i> View
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($item['type'] === 'payment' && canDelete('financials')): ?>
                            <button onclick="deletePayment(<?php echo $item['id']; ?>)" 
                                style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.72rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; box-shadow: 0 1px 2px rgba(185, 28, 28, 0.05);"
                                onmouseover="this.style.background='#fee2e2'; this.style.borderColor='#fca5a5'"
                                onmouseout="this.style.background='#fef2f2'; this.style.borderColor='#fecaca'">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f1f5f9; border-top: 2px solid #e2e8f0;">
                <td colspan="8" style="padding: 1rem; text-align: right; font-weight: 800; color: #475569; text-transform: uppercase;">Totals & Closing Balance</td>
                <td style="padding: 1rem; text-align: right; font-weight: 800; color: #1e293b;"><?php echo formatCurrency($totalInvoiced); ?></td>
                <td style="padding: 1rem; text-align: right; font-weight: 800; color: #059669;"><?php echo formatCurrency($totalReceived); ?></td>
                <td style="padding: 1rem; text-align: right; font-weight: 900; font-size: 1.1rem; color: <?php echo $balance > 0 ? '#e11d48' : '#059669'; ?>;">
                    <?php echo formatCurrency(abs($balance)); ?> <span style="font-size: 0.8rem; font-weight: 800;"><?php echo $balanceLabel; ?></span>
                </td>
                <td class="no-print"></td>
            </tr>
        </tfoot>
    </table>
</div>
<div style="height: 100px;" class="no-print"></div>

<style>
@media print {
    .no-print, .btn, #ledgerSearch { display: none !important; }
    body { background: white; padding: 0; }
    .card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
}
</style>

<script>
function exportExcel() {
    const from = document.getElementById('filterFromDate').value;
    const to = document.getElementById('filterToDate').value;
    const campaign = document.getElementById('filterCampaign').value;
    let url = 'export_ledger_csv.php?partner_id=<?php echo $partner_id; ?>';
    if (from) url += '&from_date=' + encodeURIComponent(from);
    if (to) url += '&to_date=' + encodeURIComponent(to);
    if (campaign) url += '&campaign=' + encodeURIComponent(campaign);
    window.location.href = url;
}

function filterLedger() {
    const input = document.getElementById('ledgerSearch');
    const filter = input.value.toLowerCase();
    const fromDateVal = document.getElementById('filterFromDate').value;
    const toDateVal = document.getElementById('filterToDate').value;
    const campaignVal = document.getElementById('filterCampaign').value.toLowerCase();
    
    const fromDate = fromDateVal ? new Date(fromDateVal) : null;
    const toDate = toDateVal ? new Date(toDateVal) : null;
    if (fromDate) fromDate.setHours(0,0,0,0);
    if (toDate) toDate.setHours(23,59,59,999);

    const table = document.getElementById('ledgerTable');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length - 1; i++) { // Skip header and footer
        const td = tr[i].getElementsByTagName('td');
        if (!td || td.length === 0) continue;
        
        let txtValue = "";
        for (let j = 0; j < td.length; j++) {
            txtValue += td[j].textContent || td[j].innerText;
        }
        let textMatch = txtValue.toLowerCase().indexOf(filter) > -1;
        
        let dateMatch = true;
        let rowDateStr = td[0].textContent || td[0].innerText;
        let rowDate = new Date(rowDateStr);
        if (fromDate && rowDate < fromDate) dateMatch = false;
        if (toDate && rowDate > toDate) dateMatch = false;

        let campaignMatch = true;
        if (campaignVal) {
            const campaignColIndex = 5;
            let rowCampaignStr = (td[campaignColIndex].getAttribute('data-campaign-brand') || '').trim().toLowerCase();
            if (rowCampaignStr.indexOf(campaignVal) === -1) {
                campaignMatch = false;
            }
        }

        if (textMatch && dateMatch && campaignMatch) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

function addPayment(clientId, type) {
    const title = type === 'receivable' ? 'Record Receipt' : 'Record Payment Made';
    
    fetch('../../ajax/get_partner_docs.php?id=' + clientId)
    .then(r => r.json())
    .then(data => {
        let docOptions = '<option value="">No Link (General Payment)</option>';
        if (type === 'receivable') {
            data.invoices.forEach(i => {
                docOptions += '<option value="' + i.id + '">Inv: ' + i.invoice_number + ' (₹' + i.total_amount + ')</option>';
            });
        } else {
            data.pos.forEach(p => {
                docOptions += '<option value="' + p.id + '">PO: ' + p.po_number + ' (₹' + p.grand_total + ')</option>';
            });
        }

        Swal.fire({
            title: title,
            html: 
                '<div style="text-align: left;">' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">LINK TO DOCUMENT</label>' +
                    '<select id="pay_doc_id" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; font-size: 0.9rem;">' + docOptions + '</select>' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">AMOUNT (₹)</label>' +
                    '<input id="pay_amount" type="number" class="swal2-input" placeholder="0.00" style="margin: 0 0 1rem 0; width: 100%;">' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">DATE</label>' +
                    '<input id="pay_date" type="date" class="swal2-input" value="<?php echo date('Y-m-d'); ?>" style="margin: 0 0 1rem 0; width: 100%;">' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">PAYMENT MODE</label>' +
                    '<select id="pay_mode" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%;">' +
                        '<option value="NEFT">Bank Transfer (NEFT/IMPS)</option>' +
                        '<option value="Cheque">Cheque</option>' +
                        '<option value="Cash">Cash</option>' +
                        '<option value="UPI">UPI</option>' +
                    '</select>' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">REFERENCE / TRANS ID</label>' +
                    '<input id="pay_ref" class="swal2-input" placeholder="e.g. UTR / Cheque No / Bank Ref" style="margin: 0 0 1rem 0; width: 100%;">' +
                    '<label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">REMARK (optional)</label>' +
                    '<input id="pay_remark" class="swal2-input" placeholder="e.g. Against Invoice SCR/26-27/0001" style="margin: 0; width: 100%;">' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: 'Save Transaction',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                Swal.showLoading();
                const amount = document.getElementById('pay_amount').value;
                const date = document.getElementById('pay_date').value;
                const ref = document.getElementById('pay_ref').value;
                const mode = document.getElementById('pay_mode').value;
                const docId = document.getElementById('pay_doc_id').value;

                if (!amount || amount <= 0) {
                    Swal.showValidationMessage('Please enter a valid amount');
                    return false;
                }

                let params = new URLSearchParams();
                params.append('client_id', clientId);
                params.append('amount', amount);
                params.append('payment_date', date);
                params.append('reference_no', ref);
                params.append('notes', document.getElementById('pay_remark') ? document.getElementById('pay_remark').value : '');
                params.append('payment_mode', mode);
                params.append('doc_id', docId);
                params.append('type', type);

                return fetch('../../ajax/save_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                }).then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        throw new Error('Server Error: ' + text);
                    }
                })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Unknown Error');
                    return data;
                }).catch(error => {
                    Swal.showValidationMessage('Failed: ' + error.message);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let resData = result.value;
                if (resData && resData.approval_status === 'pending_approval') {
                    Swal.fire('Sent for Approval!', 'Your payment record has been submitted to admin for approval.', 'info').then(() => location.reload());
                } else {
                    Swal.fire('Success', 'Transaction recorded', 'success').then(() => location.reload());
                }
            }
        });
    });
}
function receiveAgainstInvoice(clientId, invoiceAmount, invoiceRef, invoiceId) {
    Swal.fire({
        title: 'Record Receipt',
        html:
            '<div style="text-align:left;">' +
                '<div style="background:#ecfdf5; border-radius:8px; padding:10px 14px; margin-bottom:1rem; font-size:0.8rem; color:#065f46; font-weight:700;">' +
                    '<i class="fas fa-file-invoice-dollar"></i> Against Invoice: <strong>' + invoiceRef + '</strong>' +
                '</div>' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">AMOUNT (₹)</label>' +
                '<input id="rp_amount" type="number" class="swal2-input" value="' + invoiceAmount + '" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">DATE</label>' +
                '<input id="rp_date" type="date" class="swal2-input" value="<?php echo date('Y-m-d'); ?>" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">PAYMENT MODE</label>' +
                '<select id="rp_mode" class="swal2-input" style="margin:0 0 1rem 0;width:100%;">' +
                    '<option value="NEFT">Bank Transfer (NEFT/IMPS)</option>' +
                    '<option value="Cheque">Cheque</option>' +
                    '<option value="Cash">Cash</option>' +
                    '<option value="UPI">UPI</option>' +
                '</select>' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">REFERENCE / TRANS ID</label>' +
                '<input id="rp_ref" class="swal2-input" placeholder="e.g. UTR / Cheque No / Bank Ref" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">REMARK</label>' +
                '<input id="rp_remark" class="swal2-input" placeholder="e.g. Against ' + invoiceRef + '" value="Against ' + invoiceRef + '" style="margin:0;width:100%;">' +
            '</div>',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Save Receipt',
        confirmButtonColor: '#0d9488',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const amount = document.getElementById('rp_amount').value;
            if (!amount || amount <= 0) { Swal.showValidationMessage('Enter a valid amount'); return false; }
            const params = new URLSearchParams();
            params.append('client_id', clientId);
            params.append('amount', amount);
            params.append('payment_date', document.getElementById('rp_date').value);
            params.append('payment_mode', document.getElementById('rp_mode').value);
            params.append('reference_no', document.getElementById('rp_ref').value);
            params.append('notes', document.getElementById('rp_remark').value);
            params.append('type', 'receivable');
            params.append('doc_id', invoiceId || '');
            return fetch('../../ajax/save_payment.php', {
                method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params.toString()
            }).then(r => r.json()).then(d => { if(!d.success) throw new Error(d.message); return d; })
            .catch(e => { Swal.showValidationMessage('Failed: ' + e.message); });
        }
    }).then(result => {
        if (result.isConfirmed) {
            if (result.value?.approval_status === 'pending_approval') {
                Swal.fire('Sent for Approval!', 'Receipt submitted for admin approval.', 'info').then(() => location.reload());
            } else {
                Swal.fire('Success', 'Receipt recorded successfully.', 'success').then(() => location.reload());
            }
        }
    });
}

function payAgainstPO(clientId, poAmount, poRef, poId) {
    Swal.fire({
        title: 'Record Payment Made',
        html:
            '<div style="text-align:left;">' +
                '<div style="background:#ecfdf5; border-radius:8px; padding:10px 14px; margin-bottom:1rem; font-size:0.8rem; color:#065f46; font-weight:700;">' +
                    '<i class="fas fa-file-invoice-dollar"></i> Against Purchase Order: <strong>' + poRef + '</strong>' +
                '</div>' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">AMOUNT (₹)</label>' +
                '<input id="vp_amount" type="number" class="swal2-input" value="' + poAmount + '" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">DATE</label>' +
                '<input id="vp_date" type="date" class="swal2-input" value="<?php echo date('Y-m-d'); ?>" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">PAYMENT MODE</label>' +
                '<select id="vp_mode" class="swal2-input" style="margin:0 0 1rem 0;width:100%;">' +
                    '<option value="NEFT">Bank Transfer (NEFT/IMPS)</option>' +
                    '<option value="Cheque">Cheque</option>' +
                    '<option value="Cash">Cash</option>' +
                    '<option value="UPI">UPI</option>' +
                '</select>' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">REFERENCE / TRANS ID</label>' +
                '<input id="vp_ref" class="swal2-input" placeholder="e.g. UTR / Cheque No / Bank Ref" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">REMARK</label>' +
                '<input id="vp_remark" class="swal2-input" placeholder="e.g. Against ' + poRef + '" value="Against ' + poRef + '" style="margin:0;width:100%;">' +
            '</div>',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Save Payment',
        confirmButtonColor: '#0d9488',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const amount = document.getElementById('vp_amount').value;
            if (!amount || amount <= 0) { Swal.showValidationMessage('Enter a valid amount'); return false; }
            const params = new URLSearchParams();
            params.append('client_id', clientId);
            params.append('amount', amount);
            params.append('payment_date', document.getElementById('vp_date').value);
            params.append('payment_mode', document.getElementById('vp_mode').value);
            params.append('reference_no', document.getElementById('vp_ref').value);
            params.append('notes', document.getElementById('vp_remark').value);
            params.append('type', 'payable');
            params.append('doc_id', poId);
            return fetch('../../ajax/save_payment.php', {
                method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params.toString()
            }).then(r => r.json()).then(d => { if(!d.success) throw new Error(d.message); return d; })
            .catch(e => { Swal.showValidationMessage('Failed: ' + e.message); });
        }
    }).then(result => {
        if (result.isConfirmed) {
            if (result.value?.approval_status === 'pending_approval') {
                Swal.fire('Sent for Approval!', 'Payment submitted for admin approval.', 'info').then(() => location.reload());
            } else {
                Swal.fire('Success', 'Payment recorded successfully.', 'success').then(() => location.reload());
            }
        }
    });
}

function viewInvoice(ref) {
    window.open('view_invoice_redirect.php?ref=' + encodeURIComponent(ref), '_blank');
}

function deletePayment(id) {
    Swal.fire({
        title: 'Delete this transaction?',
        text: "This will remove the payment entry from the ledger. Are you sure?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../ajax/delete_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}

function showInvoicePayments(ref) {
    Swal.fire({
        title: 'Fetching payment history...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('../../ajax/get_invoice_payments.php?ref=' + encodeURIComponent(ref))
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            Swal.fire({
                icon: 'info',
                title: 'No Payments',
                text: data.message || 'No payments recorded against this reference yet.',
                confirmButtonColor: '#475569'
            });
            return;
        }

        // Calculate totals
        const docTotal = data.doc_total;
        let totalPaid = 0;
        data.payments.forEach(p => {
            if (p.approval_status === 'approved' || !p.approval_status) {
                totalPaid += parseFloat(p.amount);
            }
        });
        const balance = docTotal !== null ? (docTotal - totalPaid) : null;

        // Formatter function
        const formatCur = (val) => {
            if (val === null || val === undefined || isNaN(val)) return '-';
            return new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
                maximumFractionDigits: 2
            }).format(val);
        };

        // Create HTML table
        let rowsHtml = '';
        if (data.payments.length === 0) {
            rowsHtml = `
                <tr>
                    <td colspan="6" style="padding: 1.5rem; text-align: center; color: #64748b; font-style: italic;">
                        No approved payments recorded for this reference.
                    </td>
                </tr>
            `;
        } else {
            data.payments.forEach((p, idx) => {
                const dateFormatted = new Date(p.payment_date).toLocaleDateString('en-IN', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
                const bg = idx % 2 === 0 ? '#ffffff' : '#f8fafc';
                
                // Construct clean edit params
                const editParams = JSON.stringify({
                    id: p.id,
                    amount: p.amount,
                    date: p.payment_date,
                    mode: p.payment_mode,
                    ref: p.transaction_id,
                    notes: p.notes
                }).replace(/"/g, '&quot;');

                let statusBadge = '';
                if (p.approval_status === 'pending_approval' || p.approval_status === 'pending') {
                    statusBadge = '<span style="background: #fff7ed; color: #c2410c; padding: 2px 4px; border-radius: 4px; font-weight: 700; font-size: 0.65rem; margin-left: 4px; display: inline-block;">PENDING</span>';
                } else if (p.approval_status === 'rejected') {
                    statusBadge = '<span style="background: #fef2f2; color: #b91c1c; padding: 2px 4px; border-radius: 4px; font-weight: 700; font-size: 0.65rem; margin-left: 4px; display: inline-block;">REJECTED</span>';
                }

                let remarksContent = p.notes || '-';
                if (p.approval_status === 'rejected' && p.rejection_reason) {
                    remarksContent += `<div style="font-size: 0.68rem; color: #ef4444; font-weight: 600; margin-top: 3px; background: #fef2f2; padding: 2px 6px; border-radius: 4px; border: 1px solid #fee2e2; display: inline-block;">Reason: ${p.rejection_reason}</div>`;
                }

                let actionButtons = '';
                let canUserEdit = <?php echo canEdit('financials') ? 'true' : 'false'; ?> || (p.approval_status !== 'approved');
                let canUserDelete = <?php echo canDelete('financials') ? 'true' : 'false'; ?> || (p.approval_status !== 'approved');

                if (canUserEdit) {
                    actionButtons += `
                        <button onclick="editPaymentInHistory(${editParams}, '${data.doc_number}')" 
                            style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;"
                            onmouseover="this.style.background='#dbeafe'; this.style.borderColor='#93c5fd'"
                            onmouseout="this.style.background='#eff6ff'; this.style.borderColor='#bfdbfe'">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    `;
                }
                if (canUserDelete) {
                    actionButtons += `
                        <button onclick="deletePayment(${p.id})" 
                            style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; margin-left: 4px;"
                            onmouseover="this.style.background='#fee2e2'; this.style.borderColor='#fca5a5'"
                            onmouseout="this.style.background='#fef2f2'; this.style.borderColor='#fecaca'">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    `;
                }

                rowsHtml += `
                    <tr style="background: ${bg}; border-bottom: 1px solid #e2e8f0; opacity: ${p.approval_status === 'rejected' ? '0.75' : '1'}">
                        <td style="padding: 0.75rem 1rem; font-size: 0.8rem; color: #334155; text-align: left; font-weight: 500;">${dateFormatted}</td>
                        <td style="padding: 0.75rem 1rem; font-size: 0.8rem; color: #475569; text-align: left;">
                            <span style="background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.7rem;">${p.payment_mode || 'N/A'}</span>${statusBadge}
                        </td>
                        <td style="padding: 0.75rem 1rem; font-size: 0.8rem; color: #0f172a; text-align: left; font-family: monospace; font-weight: 600;">${p.transaction_id || 'N/A'}</td>
                        <td style="padding: 0.75rem 1rem; font-size: 0.75rem; color: #64748b; text-align: left; max-width: 220px;" title="${p.notes || ''}">${remarksContent}</td>
                        <td style="padding: 0.75rem 1rem; font-size: 0.8rem; color: ${p.approval_status === 'rejected' ? '#ef4444' : '#059669'}; text-align: right; font-weight: 700; ${p.approval_status === 'rejected' ? 'text-decoration: line-through;' : ''}">${formatCur(p.amount)}</td>
                        <td style="padding: 0.75rem 1rem; text-align: center; white-space: nowrap;">
                            ${actionButtons}
                        </td>
                    </tr>
                `;
            });
        }

        // Summary cards/elements HTML
        let summaryHtml = '';
        if (docTotal !== null) {
            let balanceStyle = 'background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2;';
            let balanceLabel = 'Balance Due';
            if (balance <= 0) {
                balanceStyle = 'background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5;';
                balanceLabel = 'Advance / Settled';
            }

            summaryHtml = `
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 1.25rem;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; text-align: left;">
                        <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase; font-weight: 800; margin-bottom: 2px;">Doc Total</div>
                        <div style="font-size: 1rem; font-weight: 800; color: #1e293b;">${formatCur(docTotal)}</div>
                    </div>
                    <div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; padding: 8px 12px; text-align: left;">
                        <div style="font-size: 0.65rem; color: #16a34a; text-transform: uppercase; font-weight: 800; margin-bottom: 2px;">Total Paid</div>
                        <div style="font-size: 1rem; font-weight: 800; color: #15803d;">${formatCur(totalPaid)}</div>
                    </div>
                    <div style="${balanceStyle} border-radius: 8px; padding: 8px 12px; text-align: left;">
                        <div style="font-size: 0.65rem; text-transform: uppercase; font-weight: 800; margin-bottom: 2px;">${balanceLabel}</div>
                        <div style="font-size: 1rem; font-weight: 800;">${formatCur(Math.abs(balance))}</div>
                    </div>
                </div>
            `;
        } else {
            summaryHtml = `
                <div style="display: flex; gap: 10px; margin-bottom: 1.25rem;">
                    <div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; padding: 8px 12px; flex: 1; text-align: left;">
                        <div style="font-size: 0.65rem; color: #16a34a; text-transform: uppercase; font-weight: 800; margin-bottom: 2px;">Total Payments Linked</div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #15803d;">${formatCur(totalPaid)}</div>
                    </div>
                </div>
            `;
        }

        const modalHtml = `
            <div style="font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #1e293b; text-align: left;">
                ${summaryHtml}
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #ffffff;">
                    <table style="width: 100%; border-collapse: collapse; margin: 0;">
                        <thead>
                            <tr style="background: #f1f5f9; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 0.75rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #475569; font-weight: 800; text-align: left;">Date</th>
                                <th style="padding: 0.75rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #475569; font-weight: 800; text-align: left;">Mode</th>
                                <th style="padding: 0.75rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #475569; font-weight: 800; text-align: left;">Ref / Txn ID</th>
                                <th style="padding: 0.75rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #475569; font-weight: 800; text-align: left;">Remarks</th>
                                <th style="padding: 0.75rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #475569; font-weight: 800; text-align: right;">Amount</th>
                                <th style="padding: 0.75rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #475569; font-weight: 800; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        Swal.fire({
            title: `<div style="font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;"><i class="fas fa-history" style="color: #64748b;"></i> Payment History: <span style="color: #0284c7;">${data.doc_number}</span></div>`,
            html: modalHtml,
            width: '850px',
            showConfirmButton: true,
            confirmButtonText: 'Close',
            confirmButtonColor: '#475569',
            customClass: {
                popup: 'swal2-premium-popup'
            }
        });
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to retrieve payment details: ' + err.message
        });
    });
}

function editPaymentInHistory(p, documentRef) {
    Swal.fire({
        title: 'Edit Transaction',
        html:
            '<div style="text-align:left;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">AMOUNT (₹)</label>' +
                '<input id="ep_amount" type="number" class="swal2-input" value="' + p.amount + '" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">DATE</label>' +
                '<input id="ep_date" type="date" class="swal2-input" value="' + p.date + '" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">PAYMENT MODE</label>' +
                '<select id="ep_mode" class="swal2-input" style="margin:0 0 1rem 0;width:100%;">' +
                    '<option value="NEFT" ' + (p.mode === 'NEFT' ? 'selected' : '') + '>Bank Transfer (NEFT/IMPS)</option>' +
                    '<option value="Cheque" ' + (p.mode === 'Cheque' ? 'selected' : '') + '>Cheque</option>' +
                    '<option value="Cash" ' + (p.mode === 'Cash' ? 'selected' : '') + '>Cash</option>' +
                    '<option value="UPI" ' + (p.mode === 'UPI' ? 'selected' : '') + '>UPI</option>' +
                '</select>' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">REFERENCE / TRANS ID</label>' +
                '<input id="ep_ref" class="swal2-input" value="' + (p.ref || '') + '" placeholder="e.g. UTR / Cheque No / Bank Ref" style="margin:0 0 1rem 0;width:100%;">' +
                '<label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">REMARK</label>' +
                '<input id="ep_remark" class="swal2-input" value="' + (p.notes || '') + '" placeholder="e.g. Against ' + documentRef + '" style="margin:0;width:100%;">' +
            '</div>',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Save Changes',
        confirmButtonColor: '#0d9488',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const amount = document.getElementById('ep_amount').value;
            if (!amount || amount <= 0) { Swal.showValidationMessage('Enter a valid amount'); return false; }
            const params = new URLSearchParams();
            params.append('id', p.id);
            params.append('amount', amount);
            params.append('payment_date', document.getElementById('ep_date').value);
            params.append('payment_mode', document.getElementById('ep_mode').value);
            params.append('reference_no', document.getElementById('ep_ref').value);
            params.append('notes', document.getElementById('ep_remark').value);
            
            return fetch('../../ajax/edit_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(r => r.json())
            .then(d => { 
                if (!d.success) throw new Error(d.message); 
                return d; 
            })
            .catch(e => { 
                Swal.showValidationMessage('Failed: ' + e.message); 
            });
        }
    }).then(result => {
        if (result.isConfirmed) {
            if (result.value?.approval_status === 'pending_approval') {
                Swal.fire('Sent for Approval!', 'Changes submitted for admin approval.', 'info').then(() => location.reload());
            } else {
                Swal.fire('Success', 'Transaction updated successfully.', 'success').then(() => location.reload());
            }
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
