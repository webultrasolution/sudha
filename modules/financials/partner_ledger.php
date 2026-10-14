<?php
$activePage = 'ledger';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

$isAdmin = hasRole('admin') || (($_SESSION['user_role'] ?? '') === 'admin');

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

function getInvoiceServices($pdo, $bookingId) {
    if (!$bookingId) return [];
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN bi.amount > 0 THEN 1 ELSE 0 END) as has_renting,
            SUM(CASE WHEN bi.client_print_rate > 0 THEN 1 ELSE 0 END) as has_printing,
            SUM(CASE WHEN bi.client_mount_rate > 0 THEN 1 ELSE 0 END) as has_mounting
        FROM booking_items bi
        WHERE bi.booking_id = ?
    ");
    $stmt->execute([$bookingId]);
    $res = $stmt->fetch();
    $services = [];
    if (!empty($res['has_renting']) && $res['has_renting'] > 0) {
        $services[] = 'Renting';
    }
    if (!empty($res['has_printing']) && $res['has_printing'] > 0) {
        $services[] = 'Printing';
    }
    if (!empty($res['has_mounting']) && $res['has_mounting'] > 0) {
        $services[] = 'Mounting';
    }
    return $services;
}

include_once __DIR__ . '/../../includes/header.php';

// Fetch all Bills (Debits)
$ledgerEntries = [];
$activeEntityId = $_SESSION['active_entity_id'] ?? null;

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
                 CASE WHEN b.confirmation_type = 'email' THEN b.email_date ELSE b.customer_po_date END as po_date,
                 b.start_date, b.end_date,
                 CONVERT(b.customer_po_file USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file,
                 i.booking_id as booking_id,
                 CONVERT('rental_tax' USING utf8mb4) COLLATE utf8mb4_unicode_ci as sub_type
          FROM invoices i
          JOIN bookings b ON i.booking_id = b.id
          WHERE b.client_id = ? AND i.approval_status = 'approved'" . ($activeEntityId ? " AND i.entity_id = ?" : "") . "

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
                 MAX(r.customer_po_date) as po_date,
                 MIN(b.start_date) as start_date, MIN(b.end_date) as end_date,
                 CONVERT(MAX(r.attachments) USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file,
                 0 as booking_id,
                 CONVERT('printing' USING utf8mb4) COLLATE utf8mb4_unicode_ci as sub_type
          FROM client_printing_rates r
          LEFT JOIN sites s ON r.site_id = s.id
          LEFT JOIN bookings b ON r.booking_id = b.id
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
                 MAX(m.customer_po_date) as po_date,
                 MIN(b.start_date) as start_date, MIN(b.end_date) as end_date,
                 CONVERT(MAX(m.attachments) USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file,
                 0 as booking_id,
                 CONVERT('mounting' USING utf8mb4) COLLATE utf8mb4_unicode_ci as sub_type
          FROM client_mounting_rates m
          LEFT JOIN sites s ON m.site_id = s.id
          LEFT JOIN bookings b ON m.booking_id = b.id
          WHERE m.client_id = ? AND m.is_final_invoice = 1 AND m.approval_status = 'approved'
          GROUP BY COALESCE(m.po_number, m.id)
    ");
    $invParams = [$partner_id];
    if ($activeEntityId) {
        $invParams[] = $activeEntityId;
    }
    $invParams[] = $partner_id;
    $invParams[] = $partner_id;
    $stmtInv->execute($invParams);
    $invoices = $stmtInv->fetchAll();
    foreach ($invoices as $inv) {
        $ledgerEntries[] = $inv;
    }
} else {
    // Vendor Logic
    $stmtPO = $pdo->prepare("
        SELECT po.id, CONVERT('po' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type, po.po_date as date, CONVERT(po.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci as ref, 
               po.po_amount as base_amt, (COALESCE(po.cgst_amount,0) + COALESCE(po.sgst_amount,0) + COALESCE(po.igst_amount,0)) as tax_amount, 
               COALESCE(NULLIF(po.total_amount, 0), po.po_amount + COALESCE(po.cgst_amount,0) + COALESCE(po.sgst_amount,0) + COALESCE(po.igst_amount,0)) as total_amt, 
               COALESCE(NULLIF(po.total_amount, 0), po.po_amount + COALESCE(po.cgst_amount,0) + COALESCE(po.sgst_amount,0) + COALESCE(po.igst_amount,0)) as debit, 
               0 as credit, CONVERT(po.status USING utf8mb4) COLLATE utf8mb4_unicode_ci as status,
               CONVERT(po.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(po.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(po.vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               po.vendor_invoice_date as po_date,
               COALESCE(b.start_date, poi.min_start) as start_date, COALESCE(b.end_date, poi.max_end) as end_date,
               CONVERT((SELECT GROUP_CONCAT(CONCAT('uploads/pos/', filename) SEPARATOR '||') FROM po_attachments WHERE po_id = po.id) USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file
        FROM purchase_orders po
        LEFT JOIN bookings b ON po.campaign_id = b.id
        LEFT JOIN (SELECT po_id, MIN(start_date) as min_start, MAX(end_date) as max_end FROM po_items GROUP BY po_id) poi ON po.id = poi.po_id
        WHERE po.vendor_id = ? AND po.type != 'printing' AND po.approval_status = 'approved' AND po.vendor_invoice_approval_status = 'approved'" . ($activeEntityId ? " AND po.entity_id = ?" : "") . "
        
        UNION ALL
        
        SELECT MIN(r.id) as id, CONVERT('po' USING utf8mb4) COLLATE utf8mb4_unicode_ci as type, DATE(MIN(r.created_at)) as date, 
               COALESCE(CONVERT(r.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('RATE-', MIN(r.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as ref,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as base_amt,
               0 as tax_amount,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as debit,
               0 as credit, CONVERT('approved' USING utf8mb4) COLLATE utf8mb4_unicode_ci as status,
               CONVERT(MAX(r.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(MAX(r.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(MAX(r.vendor_invoice_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               MAX(r.vendor_invoice_date) as po_date,
               COALESCE(MIN(b.start_date), MIN(poi.min_start)) as start_date, COALESCE(MIN(b.end_date), MAX(poi.max_end)) as end_date,
               CONVERT(COALESCE((SELECT GROUP_CONCAT(CONCAT('uploads/pos/', filename) SEPARATOR '||') FROM po_attachments WHERE po_id = po.id), MAX(r.attachments)) USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file
        FROM vendor_printing_rates r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN purchase_orders po ON r.po_number = po.po_number
        LEFT JOIN bookings b ON po.campaign_id = b.id
        LEFT JOIN (SELECT po_id, MIN(start_date) as min_start, MAX(end_date) as max_end FROM po_items GROUP BY po_id) poi ON po.id = poi.po_id
        WHERE r.vendor_id = ? AND " . ($activeEntityId 
            ? "r.po_number IN (SELECT po_number FROM purchase_orders WHERE type = 'printing' AND approval_status = 'approved' AND vendor_invoice_approval_status = 'approved' AND entity_id = ?)" 
            : "(r.po_number IS NULL OR r.po_number IN (SELECT po_number FROM purchase_orders WHERE type = 'printing' AND approval_status = 'approved' AND vendor_invoice_approval_status = 'approved'))"
        ) . "
        GROUP BY COALESCE(r.po_number, r.id)
    ");
    $poParams = [$partner_id];
    if ($activeEntityId) {
        $poParams[] = $activeEntityId;
    }
    $poParams[] = $partner_id;
    if ($activeEntityId) {
        $poParams[] = $activeEntityId;
    }
    $stmtPO->execute($poParams);
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
           COALESCE(
               CASE 
                   WHEN p.type = 'payable' THEN COALESCE(CONVERT(po.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci)
                   ELSE COALESCE(CONVERT(b.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(prop.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci)
               END,
               CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci
           ) as campaign_name,
           COALESCE(
               CASE 
                   WHEN p.type = 'payable' THEN COALESCE(CONVERT(po.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci)
                   ELSE CONVERT(b.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci
               END,
               CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci
           ) as brand_name,
           COALESCE(
               CASE 
                   WHEN p.type = 'payable' THEN COALESCE(CONVERT(po.vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci)
                   ELSE CONVERT(CASE WHEN b.confirmation_type = 'email' THEN 'Email Conf' ELSE b.customer_po_no END USING utf8mb4) COLLATE utf8mb4_unicode_ci
               END,
               CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci
           ) as po_number,
           COALESCE(
               CASE 
                   WHEN p.type = 'payable' THEN COALESCE(po.vendor_invoice_date, vpr.vendor_invoice_date)
                   ELSE (CASE WHEN b.confirmation_type = 'email' THEN b.email_date ELSE b.customer_po_date END)
               END,
               NULL
           ) as po_date,
           p.invoice_id, p.proposal_id,
           CONVERT(i.invoice_number USING utf8mb4) COLLATE utf8mb4_unicode_ci as invoice_number,
           COALESCE(CONVERT(po.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci) as po_number_linked,
           NULL as start_date, NULL as end_date
    FROM payments p
    LEFT JOIN invoices i ON (p.type = 'receivable' AND p.invoice_id = i.id)
    LEFT JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN proposals prop ON (p.type = 'receivable' AND p.proposal_id = prop.id)
    LEFT JOIN purchase_orders po ON (p.type = 'payable' AND p.proposal_id = po.id)
    LEFT JOIN vendor_printing_rates vpr ON (p.type = 'payable' AND p.proposal_id = vpr.id)
    LEFT JOIN approval_requests ar ON (ar.entity_type = 'payment' AND ar.entity_id = p.id)
    WHERE p.partner_id = ? AND p.type = ?" . ($activeEntityId ? " AND p.entity_id = ?" : "") . "
");
$payParams = [$partner_id, $dbType];
if ($activeEntityId) {
    $payParams[] = $activeEntityId;
}
$stmtPay->execute($payParams);
$payments = $stmtPay->fetchAll();
foreach ($payments as $pay) {
    $ledgerEntries[] = $pay;
}

// Group payments directly below their parent invoices/POs, and sort other entries chronologically
$parents = [];
$payments = [];
$unlinkedPayments = [];
$groupedPayments = [];
$parentsMap = [];

foreach ($ledgerEntries as $item) {
    if ($item['type'] === 'payment') {
        $payments[] = $item;
    } else {
        $parents[] = $item;
        $parentsMap[$item['type'] . '_' . $item['id']] = true;
    }
}

// Sort parents chronologically by date
usort($parents, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Group linked payments, keep unlinked ones separate
foreach ($payments as $pay) {
    $parentKey = null;
    if ($pType === 'client' && !empty($pay['invoice_id'])) {
        $parentKey = 'invoice_' . $pay['invoice_id'];
    } elseif ($pType === 'vendor' && !empty($pay['proposal_id'])) {
        $parentKey = 'po_' . $pay['proposal_id'];
    }

    if ($parentKey && isset($parentsMap[$parentKey])) {
        $groupedPayments[$parentKey][] = $pay;
    } else {
        $unlinkedPayments[] = $pay;
    }
}

// Merge parents and unlinked payments, sort chronologically
$baseList = array_merge($parents, $unlinkedPayments);
usort($baseList, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Reconstruct ledger entries, inserting linked payments immediately after their parent document
$finalLedgerEntries = [];
foreach ($baseList as $item) {
    $finalLedgerEntries[] = $item;
    if ($item['type'] !== 'payment') {
        $parentKey = $item['type'] . '_' . $item['id'];
        if (isset($groupedPayments[$parentKey])) {
            $linked = $groupedPayments[$parentKey];
            usort($linked, function($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });
            foreach ($linked as $pay) {
                $finalLedgerEntries[] = $pay;
            }
        }
    }
}
$ledgerEntries = $finalLedgerEntries;

// Summary Stats
// Summary Stats
$totalContracted = 0;
$totalInvoiced = 0;
$totalReceived = 0;
$totalBaseAmt = 0;
$totalTaxAmt = 0;

$invoicePaidMap = [];
$poPaidMap = [];

foreach ($ledgerEntries as $item) {
    if ($item['type'] === 'invoice' || $item['type'] === 'po') {
        $totalInvoiced += $item['total_amt'];
        $totalBaseAmt += ($item['base_amt'] ?? 0);
        $totalTaxAmt += ($item['tax_amount'] ?? 0);
    }
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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span><?php echo ($pType == 'client') ? 'Client Ledger' : 'Party Ledger'; ?></span>
        </h1>
        <p style="color: #64748b; margin: 0.2rem 0 0 0; font-size: 0.9rem; font-weight: 500;">Partner: <strong style="color: #0f172a;"><?php echo htmlspecialchars($partner['name']); ?></strong></p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <button type="button" onclick="sendLedgerEmail()" class="btn" style="background: #ea4335; color: #ffffff; padding: 0.5rem 0.85rem; border-radius: 8px; font-weight: 800; border: none; cursor: pointer; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 1px 3px rgba(234,67,53,0.3);">
            <i class="fas fa-paper-plane"></i> Email (SMTP)
        </button>
        <button type="button" onclick="sendLedgerWhatsApp()" class="btn" style="background: #25D366; color: #ffffff; padding: 0.5rem 0.85rem; border-radius: 8px; font-weight: 800; border: none; cursor: pointer; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 1px 3px rgba(37,211,102,0.3);">
            <i class="fab fa-whatsapp"></i> WhatsApp Share
        </button>
        <button onclick="openInGSheet()" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #1e293b; padding: 0.5rem 0.85rem; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.82rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#0F9D58"/>
                <path d="M7 7H17V9H7V7ZM7 11H17V13H7V11ZM7 15H14V17H7V15Z" fill="white"/>
            </svg>
            Open in GSheet
        </button>
        <button onclick="exportPDF()" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #334155; padding: 0.5rem 0.85rem; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.82rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
            <i class="fas fa-file-pdf" style="color: #ef4444;"></i> Export PDF
        </button>
        <button onclick="exportExcel()" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #334155; padding: 0.5rem 0.85rem; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.82rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
            <i class="fas fa-file-excel" style="color: #10b981;"></i> Export Excel
        </button>
        <button onclick="window.print()" class="btn" style="background: white; border: 1px solid #cbd5e1; color: #334155; padding: 0.5rem 0.85rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
            <i class="fas fa-print"></i> Print Statement
        </button>
        <a href="ledgers.php?type=<?php echo $pType; ?>" class="btn" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; padding: 0.5rem 0.85rem; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <?php if (canAdd('financials')): ?>
        <button class="btn btn-primary" onclick="addPayment(<?php echo $partner_id; ?>, '<?php echo $pMode; ?>')" style="padding: 0.5rem 1rem; border-radius: 8px; font-weight: 800; background: var(--primary); color: white; border: none; cursor: pointer; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 4px rgba(13, 148, 136, 0.25);">
            <i class="fas fa-plus"></i> <?php echo ($pType == 'client') ? 'Record Receipt' : 'Record Payment Made'; ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<?php
$isAdvance = $outstanding < 0;
$outstandingCardTitle = $isAdvance ? 'Advance Balance' : 'Outstanding';
$outstandingCardBorder = $isAdvance ? '#10b981' : ($outstanding > 0 ? '#ef4444' : '#10b981');
$outstandingCardBg = $isAdvance ? '#ecfdf5' : ($outstanding > 0 ? '#fff5f5' : '#ffffff');
$outstandingCardColor = $isAdvance ? '#059669' : ($outstanding > 0 ? '#b91c1c' : '#1e293b');
$outstandingCardLabelColor = $isAdvance ? '#059669' : ($outstanding > 0 ? '#ef4444' : '#64748b');
?>
<!-- Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="padding: 1rem 1.25rem; border-left: 4px solid #f59e0b; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
        <div style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Total Billed</div>
        <div style="font-size: 1.4rem; font-weight: 900; color: #1e293b; margin-top: 0.25rem;"><?php echo formatCurrency($totalInvoiced); ?></div>
    </div>
    <div class="card" style="padding: 1rem 1.25rem; border-left: 4px solid #10b981; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
        <div style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo ($pType == 'client') ? 'Total Received' : 'Total Paid'; ?></div>
        <div style="font-size: 1.4rem; font-weight: 900; color: #1e293b; margin-top: 0.25rem;"><?php echo formatCurrency($totalReceived); ?></div>
    </div>
    <div class="card" style="padding: 1rem 1.25rem; border-left: 4px solid <?php echo $outstandingCardBorder; ?>; background: <?php echo $outstandingCardBg; ?>; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
        <div style="font-size: 0.7rem; font-weight: 800; color: <?php echo $outstandingCardLabelColor; ?>; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo $outstandingCardTitle; ?></div>
        <div style="font-size: 1.4rem; font-weight: 900; color: <?php echo $outstandingCardColor; ?>; margin-top: 0.25rem;">
            <?php echo formatCurrency(abs($outstanding)); ?>
            <?php if ($isAdvance): ?>
                <span style="font-size: 0.75rem; font-weight: 800; background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; vertical-align: middle; margin-left: 4px;">ADV</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Unified Filter & Search Toolbar -->
<div style="background: white; padding: 0.85rem 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(0,0,0,0.03);" class="no-print">
    <div style="display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap;">
        <div style="display: flex; gap: 0.4rem; align-items: center;">
            <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Date:</label>
            <input type="date" id="filterFromDate" onchange="filterLedger()" style="padding: 0.45rem 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.82rem; font-family: inherit; background: #f8fafc;">
            <span style="font-weight: 700; color: #94a3b8; font-size: 0.8rem;">to</span>
            <input type="date" id="filterToDate" onchange="filterLedger()" style="padding: 0.45rem 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.82rem; font-family: inherit; background: #f8fafc;">
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
        <div style="display: flex; gap: 0.4rem; align-items: center;">
            <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Campaign/Brand:</label>
            <select id="filterCampaign" onchange="filterLedger()" style="padding: 0.45rem 0.65rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.82rem; min-width: 170px; font-family: inherit; background: #f8fafc;">
                <option value="">All Campaigns/Brands</option>
                <?php foreach (array_keys($uniqueCampaigns) as $cOption): ?>
                    <option value="<?php echo htmlspecialchars($cOption); ?>"><?php echo htmlspecialchars($cOption); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="position: relative; display: flex; align-items: center;">
            <i class="fas fa-search" style="position: absolute; left: 10px; color: #94a3b8; font-size: 0.8rem;"></i>
            <input type="text" id="ledgerSearch" placeholder="Search transactions..." style="padding: 0.45rem 0.75rem 0.45rem 2rem; border-radius: 8px; border: 1px solid #cbd5e1; width: 220px; font-size: 0.82rem; font-family: inherit; background: #f8fafc;" onkeyup="filterLedger()">
        </div>
    </div>
    
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <?php if ($pType === 'vendor' && canAdd('financials')): ?>
            <button type="button" id="btnBulkPayment" onclick="openBulkPaymentModal()" class="btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 0.45rem 0.85rem; border: none; border-radius: 8px; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.25); transition: all 0.2s;" title="Pay selected bills and generate payment advice">
                <i class="fas fa-layer-group"></i> Pay Selected Bills (<span id="bulkSelectedCount">0</span>)
            </button>
        <?php endif; ?>
        <button type="button" onclick="document.getElementById('filterFromDate').value=''; document.getElementById('filterToDate').value=''; document.getElementById('filterCampaign').value=''; document.getElementById('ledgerSearch').value=''; filterLedger();" class="btn" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 0.45rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
            <i class="fas fa-undo"></i> Reset
        </button>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); width: 100%;">
    <div style="overflow-x: auto; width: 100%;">
        <table class="table" id="ledgerTable" style="margin: 0; width: 100%; min-width: 1000px; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                <?php if ($pType === 'vendor' && canAdd('financials')): ?>
                    <th style="padding: 0.75rem 0.5rem; text-align: center; width: 38px;" class="no-print">
                        <input type="checkbox" id="selectAllBills" onchange="toggleSelectAllBills(this.checked)" title="Select All Payable Bills" style="cursor: pointer; width: 16px; height: 16px; accent-color: #10b981;">
                    </th>
                <?php endif; ?>
                <th style="padding: 0.75rem 0.5rem; text-align: left; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;"><?php echo ($pType == 'client') ? 'Invoice Number' : 'PO Number'; ?></th>
                <th style="padding: 0.75rem 0.5rem; text-align: left; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Date</th>
                <th style="padding: 0.75rem 0.5rem; text-align: left; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Period</th>
                <th style="padding: 0.75rem 0.5rem; text-align: left; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Type</th>
                <th style="padding: 0.75rem 0.5rem; text-align: left; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;"><?php echo ($pType == 'client') ? 'Customer PO Number' : 'Inv Number'; ?></th>
                <th style="padding: 0.75rem 0.5rem; text-align: center; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Attachment</th>
                <th style="padding: 0.75rem 0.5rem; text-align: left; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;"><?php echo ($pType == 'client') ? 'PO Date' : 'Inv Date'; ?></th>
                <th style="padding: 0.75rem 0.5rem; text-align: left; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Campaign / Brand</th>
                <th style="padding: 0.75rem 0.5rem; text-align: right; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Base Amt</th>
                <th style="padding: 0.75rem 0.5rem; text-align: right; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Tax (GST)</th>
                <th style="padding: 0.75rem 0.5rem; text-align: right; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Grand Total</th>
                <th style="padding: 0.75rem 0.5rem; text-align: right; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;"><?php echo ($pType == 'client') ? 'Received' : 'Paid Out'; ?></th>
                <th style="padding: 0.75rem 0.5rem; text-align: right; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;">Actual Balance</th>
                <th style="padding: 0.75rem 0.5rem; text-align: right; font-size: 0.68rem; text-transform: uppercase; color: #64748b; font-weight: 800; white-space: nowrap;" class="no-print">Action</th>
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
                    $balance += $item['total_amt'];
                } elseif ($item['type'] === 'payment') {
                    if ($item['approval_status'] === 'approved' || empty($item['approval_status'])) {
                        $balance -= $item['total_amt'];
                    }
                }
                
                $balanceLabel = $balance > 0 ? ($pType == 'client' ? 'DUE' : 'PAYABLE') : 'ADV';

                $parts = [];
                if (!empty($item['campaign_name'])) $parts[] = trim($item['campaign_name']);
                if (!empty($item['brand_name'])) $parts[] = trim($item['brand_name']);
                $combinedCampaignBrand = implode(' / ', $parts);
            ?>
            <?php if (true): // Display all entries including linked payments ?>
            <tr style="background: <?php echo $item['type'] === 'booking' ? '#f8fafc' : '#fff'; ?>; border-bottom: 1px solid #f1f5f9;">
                <?php if ($pType === 'vendor' && canAdd('financials')): ?>
                    <td style="padding: 0.65rem 0.5rem; text-align: center;" class="no-print">
                        <?php if ($item['type'] === 'po' && $rowOutstanding > 0): ?>
                            <input type="checkbox" class="bill-select-checkbox" 
                                data-id="<?php echo intval($item['id']); ?>"
                                data-ref="<?php echo htmlspecialchars($item['ref'] ?? '', ENT_QUOTES); ?>"
                                data-inv-no="<?php echo htmlspecialchars($item['po_number'] ?? ($item['ref'] ?? ''), ENT_QUOTES); ?>"
                                data-inv-date="<?php echo htmlspecialchars((!empty($item['po_date']) && $item['po_date'] !== '0000-00-00') ? $item['po_date'] : ($item['date'] ?? ''), ENT_QUOTES); ?>"
                                data-campaign="<?php echo htmlspecialchars($combinedCampaignBrand ?: ($item['campaign_name'] ?? '-'), ENT_QUOTES); ?>"
                                data-total="<?php echo floatval($item['total_amt']); ?>"
                                data-paid="<?php echo floatval($paid); ?>"
                                data-outstanding="<?php echo floatval($rowOutstanding); ?>"
                                onchange="onBillSelectionChange()"
                                style="cursor: pointer; width: 16px; height: 16px; accent-color: #10b981;">
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <td style="padding: 0.65rem 0.5rem; white-space: nowrap;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.82rem;">
                        <?php 
                        if ($item['type'] === 'payment') {
                            if ($pType === 'client' && !empty($item['invoice_number'])) {
                                echo 'pay Against ' . htmlspecialchars($item['invoice_number']);
                            } elseif ($pType === 'vendor' && !empty($item['po_number_linked'])) {
                                echo 'pay Against ' . htmlspecialchars($item['po_number_linked']);
                            } elseif ($pType === 'vendor' && !empty($item['po_number'])) {
                                echo 'pay Against ' . htmlspecialchars($item['po_number']);
                            } else {
                                echo htmlspecialchars($item['ref'] ?: 'N/A');
                            }
                        } else {
                            echo htmlspecialchars($item['ref'] ?: 'N/A');
                        }
                        ?>
                    </div>
                    <?php 
                    if ($pType === 'client' && $item['type'] === 'invoice') {
                        $services = [];
                        if (($item['sub_type'] ?? '') === 'rental_tax') {
                            $services = getInvoiceServices($pdo, $item['booking_id'] ?? 0);
                        } elseif (($item['sub_type'] ?? '') === 'printing') {
                            $services = ['Printing'];
                        } elseif (($item['sub_type'] ?? '') === 'mounting') {
                            $services = ['Mounting'];
                        }
                        if (!empty($services)) {
                            echo '<div style="font-size: 0.68rem; color: #475569; font-weight: 700; margin-top: 2px; display: inline-flex; align-items: center; gap: 4px; background: #f8fafc; padding: 1px 5px; border-radius: 4px; border: 1px solid #e2e8f0;">';
                            echo '<i class="fas fa-cog" style="font-size: 0.6rem; color: var(--primary);"></i> ' . htmlspecialchars(implode(', ', $services));
                            echo '</div>';
                        }
                    }
                    ?>
                    <?php if (!empty($item['remark'])): ?>
                        <?php if (stripos($item['remark'], 'Against ') === 0): ?>
                            <div style="font-size: 0.68rem; color: #16a34a; font-weight: 700; margin-top: 2px; display: inline-flex; align-items: center; gap: 4px; background: #f0fdf4; padding: 1px 5px; border-radius: 4px; border: 1px solid #dcfce7;">
                                <i class="fas fa-link" style="font-size:0.6rem;"></i> <?php echo htmlspecialchars($item['remark']); ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 0.68rem; color: #0d9488; font-style: italic; margin-top: 2px;">
                                <i class="fas fa-sticky-note" style="font-size:0.58rem;"></i> <?php echo htmlspecialchars($item['remark']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($item['status']): ?>
                        <div style="font-size: 0.62rem; color: #94a3b8; text-transform: capitalize; margin-top: 2px;"><?php echo $item['status']; ?></div>
                    <?php endif; ?>
                    <?php if ($item['type'] === 'payment' && ($item['approval_status'] ?? '') === 'rejected'): ?>
                        <div style="font-size: 0.68rem; color: #ef4444; font-weight: 700; margin-top: 2px; display: inline-flex; align-items: center; gap: 4px; background: #fef2f2; padding: 1px 5px; border-radius: 4px; border: 1px solid #fee2e2;">
                            <i class="fas fa-ban" style="font-size:0.6rem;"></i> Rejected<?php if (!empty($item['rejection_reason'])) echo ': ' . htmlspecialchars($item['rejection_reason']); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; color: #475569; font-size: 0.8rem; white-space: nowrap;"><?php echo date('d M Y', strtotime($item['date'])); ?></td>
                <td style="padding: 0.65rem 0.5rem; color: #475569; font-size: 0.8rem; white-space: nowrap;">
                    <?php 
                    if (!empty($item['start_date']) && !empty($item['end_date'])) {
                        echo date('d M Y', strtotime($item['start_date'])) . ' - ' . date('d M Y', strtotime($item['end_date']));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; white-space: nowrap;">
                    <?php if ($item['type'] === 'booking'): ?>
                        <span style="background: #eff6ff; color: #1e40af; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.62rem; font-weight: 800; text-transform: uppercase;">PROPOSAL</span>
                    <?php elseif ($item['type'] === 'invoice'): ?>
                        <span style="background: #fff7ed; color: #9a3412; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.62rem; font-weight: 800; text-transform: uppercase;">INVOICE</span>
                    <?php elseif ($item['type'] === 'po'): ?>
                        <span style="background: #fff7ed; color: #9a3412; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.62rem; font-weight: 800; text-transform: uppercase;">VENDOR BILL</span>
                    <?php else: ?>
                        <span style="background: #ecfdf5; color: #065f46; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.62rem; font-weight: 800; text-transform: uppercase;">PAYMENT</span>
                        <?php if (($item['approval_status'] ?? '') === 'pending_approval' || ($item['approval_status'] ?? '') === 'pending'): ?>
                            <span style="background: #fff7ed; color: #c2410c; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.62rem; font-weight: 800; text-transform: uppercase; margin-left: 3px; display: inline-block;">PENDING</span>
                        <?php elseif (($item['approval_status'] ?? '') === 'rejected'): ?>
                            <span style="background: #fef2f2; color: #b91c1c; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.62rem; font-weight: 800; text-transform: uppercase; margin-left: 3px; display: inline-block;">REJECTED</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; font-weight: 700; color: #0f172a; font-size: 0.8rem; white-space: nowrap;">
                    <?php echo !empty($item['po_number']) ? htmlspecialchars($item['po_number']) : '<span style="color: #cbd5e1;">-</span>'; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; text-align: center; white-space: nowrap;">
                    <?php 
                    $attFiles = getAttachmentUrls($item['customer_po_file'] ?? '');
                    $bookingId = intval($item['booking_id'] ?? 0);
                    $poId = ($item['type'] === 'po') ? intval($item['id'] ?? 0) : 0;
                    $itemRefSafe = htmlspecialchars($item['ref'] ?? 'Document', ENT_QUOTES, 'UTF-8');
                    $jsonFiles = !empty($attFiles) ? htmlspecialchars(json_encode($attFiles), ENT_QUOTES, 'UTF-8') : '[]';

                    if (!empty($attFiles)): 
                        if (count($attFiles) > 1): ?>
                            <button type="button" onclick="<?php echo ($item['type'] === 'po') ? "showPoAttachments($jsonFiles, '$itemRefSafe', $poId)" : "showInvoiceAttachments($jsonFiles, '$itemRefSafe', $bookingId)"; ?>" title="View <?php echo count($attFiles); ?> Attachments" style="color: #4338ca; background: #e0e7ff; padding: 2px 6px; border-radius: 6px; border: 1px solid #c7d2fe; font-size: 0.72rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">
                                <i class="fas fa-paperclip"></i>
                                <span style="font-size: 0.65rem; margin-left: 3px;"><?php echo count($attFiles); ?></span>
                            </button>
                        <?php else: 
                            $firstAtt = $attFiles[0];
                            $fullAttUrl = (strpos($firstAtt, 'http') === 0) ? $firstAtt : (BASE_URL . $firstAtt);
                        ?>
                            <a href="<?php echo htmlspecialchars($fullAttUrl); ?>" target="_blank" title="View Attachment" style="color: #2563eb; background: #eff6ff; padding: 3px 6px; border-radius: 6px; border: 1px solid #bfdbfe; font-size: 0.72rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;"><i class="fas fa-paperclip"></i></a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($isAdmin && $item['type'] === 'po' && $poId > 0): ?>
                            <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" class="btn-icon" style="margin: 0; color: #94a3b8; background: #f8fafc; border: 1px dashed #cbd5e1; width: 26px; height: 26px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;" title="Attach File / Edit Vendor Invoice (Admin)">
                                <i class="fas fa-paperclip" style="font-size: 0.7rem;"></i>
                            </button>
                        <?php elseif ($isAdmin && $item['type'] === 'invoice' && $bookingId > 0): ?>
                            <button type="button" onclick="openInvoicePopup(<?php echo $bookingId; ?>)" class="btn-icon" style="margin: 0; color: #94a3b8; background: #f8fafc; border: 1px dashed #cbd5e1; width: 26px; height: 26px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;" title="Attach File / Edit Invoice (Admin)">
                                <i class="fas fa-paperclip" style="font-size: 0.7rem;"></i>
                            </button>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; color: #475569; font-size: 0.8rem; white-space: nowrap;">
                    <?php echo (!empty($item['po_date']) && $item['po_date'] !== '0000-00-00') ? date('d M Y', strtotime($item['po_date'])) : '<span style="color: #cbd5e1;">-</span>'; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem;" data-campaign-brand="<?php echo htmlspecialchars($combinedCampaignBrand); ?>">
                    <div style="display: flex; flex-direction: column; gap: 3px;">
                        <?php if (!empty($item['campaign_name'])): ?>
                            <div style="font-size: 0.68rem; color: #2563eb; font-weight: 700; display: inline-flex; align-items: center; gap: 3px; background: #eff6ff; padding: 1px 5px; border-radius: 4px; align-self: flex-start;">
                                <i class="fas fa-bullhorn" style="font-size: 0.6rem;"></i> <?php echo htmlspecialchars($item['campaign_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['brand_name'])): ?>
                            <div style="font-size: 0.68rem; color: #475569; font-weight: 700; display: inline-flex; align-items: center; gap: 3px; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; align-self: flex-start;">
                                <i class="fas fa-tag" style="font-size: 0.6rem;"></i> <?php echo htmlspecialchars($item['brand_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (empty($item['campaign_name']) && empty($item['brand_name'])): ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="padding: 0.65rem 0.5rem; text-align: right; font-weight: 600; color: #475569; font-size: 0.8rem; white-space: nowrap;">
                    <?php echo ($item['type'] !== 'payment' && $item['base_amt'] > 0) ? formatCurrency($item['base_amt']) : '-'; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; text-align: right; font-weight: 600; color: #64748b; font-size: 0.8rem; white-space: nowrap;">
                    <?php echo ($item['type'] !== 'payment' && $item['tax_amount'] > 0) ? formatCurrency($item['tax_amount']) : '-'; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; text-align: right; font-weight: 700; color: #1e293b; font-size: 0.8rem; white-space: nowrap;">
                    <?php echo ($item['type'] !== 'payment') ? formatCurrency($item['total_amt']) : '-'; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; text-align: right; font-weight: 700; color: #059669; font-size: 0.8rem; white-space: nowrap;">
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
                <td style="padding: 0.65rem 0.5rem; text-align: right; white-space: nowrap;">
                    <?php if ($item['type'] === 'payment'): ?>
                        <span style="color: #cbd5e1;">-</span>
                    <?php elseif ($rowOutstanding > 0): ?>
                        <span style="background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; display: inline-flex; align-items: center; gap: 3px; padding: 0.25rem 0.5rem; border-radius: 9999px; font-weight: 800; font-size: 0.75rem; box-shadow: 0 1px 2px rgba(153, 27, 27, 0.05);">
                            <?php echo formatCurrency(abs($rowOutstanding)); ?> 
                            <span style="font-size: 0.6rem; background: #fee2e2; color: #991b1b; padding: 1px 4px; border-radius: 3px; font-weight: 900; margin-left: 2px;">
                                <?php echo ($pType == 'client') ? 'DUE' : 'PAYABLE'; ?>
                            </span>
                        </span>
                    <?php elseif ($rowOutstanding < 0): ?>
                        <span style="background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; display: inline-flex; align-items: center; gap: 3px; padding: 0.25rem 0.5rem; border-radius: 9999px; font-weight: 800; font-size: 0.75rem; box-shadow: 0 1px 2px rgba(6, 95, 70, 0.05);">
                            <?php echo formatCurrency(abs($rowOutstanding)); ?> 
                            <span style="font-size: 0.6rem; background: #d1fae5; color: #065f46; padding: 1px 4px; border-radius: 3px; font-weight: 900; margin-left: 2px;">
                                ADV
                            </span>
                        </span>
                    <?php else: ?>
                        <span style="color: #059669; font-weight: 700; font-size: 0.8rem;">
                            <?php echo formatCurrency(0); ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td style="padding: 0.65rem 0.5rem; text-align: right; white-space: nowrap;" class="no-print">
                    <div style="display: inline-flex; gap: 4px; align-items: center; justify-content: flex-end; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 4px; border-radius: 8px;">
                        <?php if (($item['type'] === 'invoice' || $item['type'] === 'po') && $pType === 'client' && canAdd('financials') && $rowOutstanding > 0): ?>
                            <button onclick="receiveAgainstInvoice(<?php echo $partner_id; ?>, <?php echo floatval($rowOutstanding); ?>, '<?php echo addslashes(htmlspecialchars($item['ref'])); ?>', <?php echo intval($item['id']); ?>)"
                                style="background: #10b981; color: white; border: none; width: 28px; height: 28px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 2px rgba(16, 185, 129, 0.2); transition: all 0.2s;"
                                onmouseover="this.style.background='#059669'"
                                onmouseout="this.style.background='#10b981'"
                                title="Receive Payment">
                                <i class="fas fa-hand-holding-usd"></i>
                            </button>
                        <?php endif; ?>

                        <?php if (($item['type'] === 'invoice' || $item['type'] === 'po') && $pType === 'vendor' && canAdd('financials') && $rowOutstanding > 0): ?>
                            <button onclick="payAgainstPO(<?php echo $partner_id; ?>, <?php echo floatval($rowOutstanding); ?>, '<?php echo addslashes(htmlspecialchars($item['ref'])); ?>', <?php echo intval($item['id']); ?>, '<?php echo addslashes(htmlspecialchars($item['po_number'] ?? '')); ?>')"
                                style="background: #10b981; color: white; border: none; width: 28px; height: 28px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 2px rgba(16, 185, 129, 0.2); transition: all 0.2s;"
                                onmouseover="this.style.background='#059669'"
                                onmouseout="this.style.background='#10b981'"
                                title="Pay Now">
                                <i class="fas fa-wallet"></i>
                            </button>
                        <?php endif; ?>

                        <?php if (($item['type'] === 'invoice' || $item['type'] === 'po') && canAdd('financials')): ?>
                            <button onclick="showInvoicePayments('<?php echo addslashes(htmlspecialchars($item['ref'])); ?>')"
                                style="background: #ffffff; color: #475569; border: 1px solid #cbd5e1; width: 28px; height: 28px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;"
                                onmouseover="this.style.background='#f1f5f9'"
                                onmouseout="this.style.background='#ffffff'"
                                title="View Payments History">
                                <i class="fas fa-history"></i>
                            </button>
                        <?php endif; ?>
                        
                        <?php if (($item['type'] === 'invoice' || $item['type'] === 'po') && canAdd('financials')): ?>
                            <a href="#" onclick="viewInvoice('<?php echo addslashes($item['ref']); ?>')" 
                                style="background: #ffffff; color: #0d9488; border: 1px solid #99f6e4; width: 28px; height: 28px; border-radius: 6px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;"
                                onmouseover="this.style.background='#f0fdfa'"
                                onmouseout="this.style.background='#ffffff'"
                                title="View Document">
                                <i class="fas fa-eye"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($item['type'] === 'payment'): ?>
                            <a href="payment_advice_voucher.php?id=<?php echo $item['id']; ?>" target="_blank" 
                                style="background: #ffffff; color: #0284c7; border: 1px solid #bae6fd; width: 28px; height: 28px; border-radius: 6px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;"
                                onmouseover="this.style.background='#f0f9ff'"
                                onmouseout="this.style.background='#ffffff'"
                                title="<?php echo ($pType === 'client') ? 'View Official Letterhead Payment Receipt' : 'View Official Letterhead Payment Voucher'; ?>">
                                <i class="fas <?php echo ($pType === 'client') ? 'fa-receipt' : 'fa-file-invoice-dollar'; ?>"></i>
                            </a>
                        <?php endif; ?>

                        <?php if ($item['type'] === 'payment' && canDelete('financials')): ?>
                            <button onclick="deletePayment(<?php echo $item['id']; ?>)" 
                                style="background: #ffffff; color: #dc2626; border: 1px solid #fecaca; width: 28px; height: 28px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;"
                                onmouseover="this.style.background='#fef2f2'"
                                onmouseout="this.style.background='#ffffff'"
                                title="Delete Payment">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f1f5f9; border-top: 2px solid #cbd5e1;">
                <td colspan="<?php echo ($pType === 'vendor' && canAdd('financials')) ? '9' : '8'; ?>" style="padding: 0.85rem 0.75rem; text-align: right; font-weight: 800; color: #475569; text-transform: uppercase; font-size: 0.75rem;">Totals & Closing Balance</td>
                <td style="padding: 0.85rem 0.5rem; text-align: right; font-weight: 700; color: #475569; font-size: 0.8rem; white-space: nowrap;"><?php echo formatCurrency($totalBaseAmt); ?></td>
                <td style="padding: 0.85rem 0.5rem; text-align: right; font-weight: 700; color: #64748b; font-size: 0.8rem; white-space: nowrap;"><?php echo formatCurrency($totalTaxAmt); ?></td>
                <td style="padding: 0.85rem 0.5rem; text-align: right; font-weight: 800; color: #1e293b; font-size: 0.8rem; white-space: nowrap;"><?php echo formatCurrency($totalInvoiced); ?></td>
                <td style="padding: 0.85rem 0.5rem; text-align: right; font-weight: 800; color: #059669; font-size: 0.8rem; white-space: nowrap;"><?php echo formatCurrency($totalReceived); ?></td>
                <td style="padding: 0.85rem 0.5rem; text-align: right; font-weight: 900; font-size: 0.85rem; color: <?php echo $balance > 0 ? '#e11d48' : '#059669'; ?>; white-space: nowrap;">
                    <?php echo formatCurrency(abs($balance)); ?> <span style="font-size: 0.65rem; font-weight: 800;"><?php echo $balanceLabel; ?></span>
                </td>
                <td class="no-print"></td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>
<div style="height: 100px;" class="no-print"></div>

<style>
@media print {
    .no-print, .btn, #ledgerSearch, .sidebar, .top-header, header, nav, footer, .main-header, [onclick*="addPayment"] { display: none !important; }
    body { background: white !important; padding: 10px !important; margin: 0 !important; color: #000 !important; }
    .card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
    table { width: 100% !important; border-collapse: collapse !important; }
    th, td { border: 1px solid #cbd5e1 !important; padding: 4px 6px !important; color: #000 !important; font-size: 7.5pt !important; }
    tr { page-break-inside: avoid !important; }
}
</style>

<script>
async function openInGSheet() {
    const from = document.getElementById('filterFromDate').value;
    const to = document.getElementById('filterToDate').value;
    const campaign = document.getElementById('filterCampaign').value;

    Swal.fire({
        title: 'Preparing Google Sheet...',
        text: 'Generating formatted ledger statement...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    let url = 'export_ledger_sheet.php?partner_id=<?php echo $partner_id; ?>&token=<?php echo md5($partner_id . 'sudha_statement_secret_2026'); ?>';
    if (from) url += '&from_date=' + encodeURIComponent(from);
    if (to) url += '&to_date=' + encodeURIComponent(to);
    if (campaign) url += '&campaign=' + encodeURIComponent(campaign);

    try {
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to fetch ledger data');
        }

        // Copy formatted HTML table & TSV to clipboard
        let copied = false;
        try {
            if (navigator.clipboard && window.ClipboardItem) {
                const blobHtml = new Blob([data.html], { type: 'text/html' });
                const blobText = new Blob([data.tsv], { type: 'text/plain' });
                await navigator.clipboard.write([
                    new ClipboardItem({
                        'text/html': blobHtml,
                        'text/plain': blobText
                    })
                ]);
                copied = true;
            } else if (navigator.clipboard) {
                await navigator.clipboard.writeText(data.tsv);
                copied = true;
            }
        } catch (clipErr) {
            const ta = document.createElement('textarea');
            ta.value = data.tsv;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            copied = true;
        }

        // Open Google Sheets in new tab
        const gsheetWin = window.open('https://sheets.new', '_blank');

        Swal.fire({
            icon: 'success',
            title: '<div style="font-size: 1.25rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; justify-content: center; gap: 8px;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#0F9D58"/><path d="M7 7H17V9H7V7ZM7 11H17V13H7V11ZM7 15H14V17H7V15Z" fill="white"/></svg> Google Sheets Ready!</div>',
            html: `
                <div style="text-align: left; font-size: 0.9rem; color: #334155; line-height: 1.5; padding: 5px 10px;">
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px; margin-bottom: 12px; color: #065f46;">
                        <strong style="font-size: 0.95rem; display: block; margin-bottom: 4px;"><i class="fas fa-check-circle"></i> Ledger Statement Copied to Clipboard!</strong>
                        New Google Sheet has opened in a new tab.
                    </div>
                    <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px;">Next Step in Google Sheets:</div>
                    <ol style="margin-left: 20px; color: #475569; font-size: 0.88rem;">
                        <li style="margin-bottom: 4px;">Click inside <strong>Cell A1</strong> in the new Google Sheet tab.</li>
                        <li>Press <kbd style="background: #e2e8f0; border: 1px solid #cbd5e1; padding: 2px 6px; border-radius: 4px; font-weight: 800; color: #0f172a;">Ctrl + V</kbd> (or <kbd style="background: #e2e8f0; border: 1px solid #cbd5e1; padding: 2px 6px; border-radius: 4px; font-weight: 800; color: #0f172a;">Cmd + V</kbd> on Mac).</li>
                    </ol>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 10px; font-style: italic;">
                        All colors, borders, titles, and numbers will paste instantly into Google Sheets.
                    </div>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: '<i class="fas fa-external-link-alt"></i> Go to Google Sheet',
            confirmButtonColor: '#0f9d58',
            showCancelButton: true,
            cancelButtonText: 'Done',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                if (gsheetWin) {
                    gsheetWin.focus();
                } else {
                    window.open('https://sheets.new', '_blank');
                }
            }
        });

    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message
        });
    }
}

function exportPDF() {
    const from = document.getElementById('filterFromDate').value;
    const to = document.getElementById('filterToDate').value;
    const campaign = document.getElementById('filterCampaign').value;
    let url = 'export_ledger_pdf.php?partner_id=<?php echo $partner_id; ?>&token=<?php echo md5($partner_id . 'sudha_statement_secret_2026'); ?>';
    if (from) url += '&from_date=' + encodeURIComponent(from);
    if (to) url += '&to_date=' + encodeURIComponent(to);
    if (campaign) url += '&campaign=' + encodeURIComponent(campaign);
    window.open(url, '_blank');
}

function exportExcel() {
    const from = document.getElementById('filterFromDate').value;
    const to = document.getElementById('filterToDate').value;
    const campaign = document.getElementById('filterCampaign').value;
    let url = 'export_ledger_csv.php?partner_id=<?php echo $partner_id; ?>&token=<?php echo md5($partner_id . 'sudha_statement_secret_2026'); ?>';
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
        
        const colOffset = document.querySelector('#ledgerTable th input#selectAllBills') ? 1 : 0;
        let dateMatch = true;
        let rowDateStr = td[1 + colOffset].textContent || td[1 + colOffset].innerText;
        let rowDate = new Date(rowDateStr);
        if (fromDate && rowDate < fromDate) dateMatch = false;
        if (toDate && rowDate > toDate) dateMatch = false;

        let campaignMatch = true;
        if (campaignVal) {
            const campaignColIndex = 7 + colOffset;
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
    if (typeof onBillSelectionChange === 'function') {
        onBillSelectionChange();
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
                '<input id="rp_remark" class="swal2-input" placeholder="e.g. Against ' + invoiceRef + '" value="" style="margin:0;width:100%;">' +
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

const partnerInfo = {
    id: <?php echo intval($partner_id); ?>,
    name: <?php echo json_encode($partner['name'] ?? ''); ?>,
    phone: <?php echo json_encode($partner['phone'] ?? ''); ?>,
    email: <?php echo json_encode($partner['email'] ?? ''); ?>,
    type: <?php echo json_encode($pType); ?>
};

function toggleSelectAllBills(checked) {
    const checkboxes = document.querySelectorAll('tbody tr input.bill-select-checkbox');
    checkboxes.forEach(chk => {
        const tr = chk.closest('tr');
        if (!tr || tr.style.display !== 'none') {
            chk.checked = checked;
        }
    });
    onBillSelectionChange();
}

function onBillSelectionChange() {
    const checkedBoxes = document.querySelectorAll('tbody tr input.bill-select-checkbox:checked');
    const count = checkedBoxes.length;
    const badge = document.getElementById('bulkSelectedCount');
    if (badge) {
        badge.textContent = count;
    }
    const btn = document.getElementById('btnBulkPayment');
    if (btn) {
        if (count > 0) {
            btn.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.45)';
        } else {
            btn.style.boxShadow = '0 2px 4px rgba(16, 185, 129, 0.25)';
        }
    }
    const allVisible = Array.from(document.querySelectorAll('tbody tr input.bill-select-checkbox'))
        .filter(chk => chk.closest('tr') && chk.closest('tr').style.display !== 'none');
    const headerChk = document.getElementById('selectAllBills');
    if (headerChk && allVisible.length > 0) {
        headerChk.checked = (checkedBoxes.length === allVisible.length);
    }
}

function recalcBulkModalTotals() {
    let totalPay = 0;
    document.querySelectorAll('.bulk-pay-input').forEach(inp => {
        const val = parseFloat(inp.value) || 0;
        totalPay += val;
    });
    const el = document.getElementById('modalTotalPayVal');
    if (el) {
        el.textContent = '₹' + totalPay.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const btn = Swal.getConfirmButton();
    if (btn) {
        btn.innerHTML = `<i class="fas fa-check-circle"></i> Confirm & Record Payment (₹${totalPay.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`;
        btn.disabled = (totalPay <= 0);
    }
}

function openBulkPaymentModal(providedItems = null) {
    let items = [];
    if (providedItems && Array.isArray(providedItems) && providedItems.length > 0) {
        items = providedItems;
    } else {
        const checkedBoxes = document.querySelectorAll('tbody tr input.bill-select-checkbox:checked');
        if (checkedBoxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Bills Selected',
                text: 'Please select at least one bill using the checkboxes before proceeding.',
                confirmButtonColor: '#10b981'
            });
            return;
        }
        checkedBoxes.forEach(chk => {
            items.push({
                id: parseInt(chk.getAttribute('data-id'), 10),
                ref: chk.getAttribute('data-ref') || '',
                inv_no: chk.getAttribute('data-inv-no') || '',
                inv_date: chk.getAttribute('data-inv-date') || '',
                campaign: chk.getAttribute('data-campaign') || '',
                total: parseFloat(chk.getAttribute('data-total')) || 0,
                paid: parseFloat(chk.getAttribute('data-paid')) || 0,
                outstanding: parseFloat(chk.getAttribute('data-outstanding')) || 0
            });
        });
    }

    let totalBillsVal = 0;
    let totalOutstanding = 0;
    let rowsHtml = '';
    const billMap = {};

    items.forEach(it => {
        totalBillsVal += it.total;
        totalOutstanding += it.outstanding;
        billMap[it.id] = it;

        const invDisplay = it.inv_no ? it.inv_no : it.ref;
        const poSub = (it.ref && it.ref !== invDisplay) ? `<div style="font-size:10.5px; color:#64748b;">PO: ${it.ref}</div>` : '';

        rowsHtml += `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 7px 8px; text-align: left;">
                    <strong style="color: #0f172a; font-size: 12.5px;">${invDisplay}</strong>
                    ${poSub}
                </td>
                <td style="padding: 7px 8px; text-align: left; font-size: 12px; color: #475569; white-space: nowrap;">
                    ${it.inv_date || '-'}
                </td>
                <td style="padding: 7px 8px; text-align: left; font-size: 11.5px; color: #0284c7; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    ${it.campaign || '-'}
                </td>
                <td style="padding: 7px 8px; text-align: right; font-size: 12px; color: #475569; white-space: nowrap;">
                    ₹${it.total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </td>
                <td style="padding: 7px 8px; text-align: right; font-size: 12px; font-weight: 700; color: #b91c1c; white-space: nowrap;">
                    ₹${it.outstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </td>
                <td style="padding: 7px 8px; text-align: right; white-space: nowrap;">
                    <div style="display: inline-flex; align-items: center; gap: 2px;">
                        <span style="font-weight: 700; color: #059669; font-size: 12px;">₹</span>
                        <input type="number" step="0.01" min="0" max="${it.outstanding}" 
                            class="bulk-pay-input" 
                            data-po-id="${it.id}" 
                            value="${it.outstanding.toFixed(2)}" 
                            style="width: 105px; padding: 4px 6px; border: 1.5px solid #10b981; border-radius: 6px; font-weight: 800; text-align: right; font-size: 12.5px; color: #065f46; background: #f0fdf4;"
                            oninput="recalcBulkModalTotals()" 
                            onfocus="this.select()">
                    </div>
                </td>
            </tr>
        `;
    });

    const todayStr = '<?php echo date('Y-m-d'); ?>';

    const modalHtml = `
        <div style="text-align: left; font-size: 13px; color: #1e293b;">
            <!-- Vendor Info Bar -->
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Vendor:</span>
                    <strong style="font-size: 13.5px; color: #0f172a; margin-left: 4px;">${partnerInfo.name}</strong>
                </div>
                <div style="background: #10b981; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 800;">
                    ${items.length} Bill${items.length > 1 ? 's' : ''} Selected
                </div>
            </div>

            <!-- Bills Table -->
            <div style="border: 1px solid #e2e8f0; border-radius: 8px; max-height: 210px; overflow-y: auto; margin-bottom: 12px; background: #fff;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; color: #64748b; font-size: 10.5px; text-transform: uppercase;">
                            <th style="padding: 7px 8px; text-align: left;">Bill / PO No</th>
                            <th style="padding: 7px 8px; text-align: left;">Date</th>
                            <th style="padding: 7px 8px; text-align: left;">Campaign</th>
                            <th style="padding: 7px 8px; text-align: right;">Bill Total</th>
                            <th style="padding: 7px 8px; text-align: right;">Bal Due</th>
                            <th style="padding: 7px 8px; text-align: right; width: 130px;">Amount to Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>

            <!-- Summary Strip -->
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 8px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 12px; color: #065f46;">
                    Total Selected: <strong>₹${totalBillsVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                    <span style="margin: 0 6px; color: #6ee7b7;">|</span>
                    Total Outstanding: <strong>₹${totalOutstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                </div>
                <div style="font-size: 14px; font-weight: 900; color: #059669;">
                    Total Paying: <span id="modalTotalPayVal">₹${totalOutstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                </div>
            </div>

            <!-- Payment Inputs Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 3px;">
                        <i class="far fa-calendar-alt"></i> Payment Date
                    </label>
                    <input type="date" id="bulk_pay_date" value="${todayStr}" style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; font-family: inherit;">
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 3px;">
                        <i class="fas fa-credit-card"></i> Payment Mode
                    </label>
                    <select id="bulk_pay_mode" style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; font-family: inherit; background: white;">
                        <option value="NEFT">Bank Transfer (NEFT / RTGS / IMPS)</option>
                        <option value="UPI">UPI Payment</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Cash">Cash</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 3px;">
                        <i class="fas fa-hashtag"></i> UTR / Bank Reference / Cheque No
                    </label>
                    <input type="text" id="bulk_pay_ref" placeholder="e.g. UTR2498239 / Chq 44521" style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; font-family: inherit;">
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 3px;">
                        <i class="fas fa-comment-alt"></i> Remarks / Internal Notes
                    </label>
                    <input type="text" id="bulk_pay_notes" placeholder="e.g. Paid via ICICI Bank A/c" style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; font-family: inherit;">
                </div>
            </div>

            <!-- Standard Payment Advice Channels (WhatsApp & SMTP) -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <div style="font-size: 11px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.3px;">
                        <i class="fas fa-paper-plane" style="color: #2563eb;"></i> Dispatch Payment Advice
                    </div>
                    <span style="font-size: 11px; color: #64748b;">(WhatsApp & SMTP Email)</span>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <!-- WhatsApp Box -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 10px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; color: #15803d; font-size: 12px; cursor: pointer;">
                            <input type="checkbox" id="bulk_send_whatsapp" style="width: 15px; height: 15px; accent-color: #16a34a;">
                            <i class="fab fa-whatsapp" style="font-size: 15px;"></i> Send via WhatsApp
                        </label>
                        <input type="text" id="bulk_vendor_phone" value="${partnerInfo.phone || ''}" placeholder="10-digit mobile number" style="margin-top: 5px; width: 100%; box-sizing: border-box; padding: 5px 8px; font-size: 11.5px; border: 1px solid #86efac; border-radius: 5px; background: #fff;">
                    </div>
                    <!-- Email SMTP Box -->
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 8px 10px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; color: #1d4ed8; font-size: 12px; cursor: pointer;">
                            <input type="checkbox" id="bulk_send_email" ${partnerInfo.email ? 'checked' : ''} style="width: 15px; height: 15px; accent-color: #2563eb;">
                            <i class="fas fa-envelope" style="font-size: 14px;"></i> Send via Email (SMTP)
                        </label>
                        <input type="email" id="bulk_vendor_email" value="${partnerInfo.email || ''}" placeholder="vendor@example.com" style="margin-top: 5px; width: 100%; box-sizing: border-box; padding: 5px 8px; font-size: 11.5px; border: 1px solid #93c5fd; border-radius: 5px; background: #fff;">
                    </div>
                </div>
            </div>
        </div>
    `;

    Swal.fire({
        title: '<div style="font-size: 18px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;"><i class="fas fa-money-check-alt" style="color: #10b981;"></i> Vendor Payment & Advice</div>',
        html: modalHtml,
        width: '780px',
        showCancelButton: true,
        confirmButtonText: `<i class="fas fa-check-circle"></i> Confirm & Record Payment (₹${totalOutstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`,
        confirmButtonColor: '#10b981',
        cancelButtonText: 'Cancel',
        cancelButtonColor: '#64748b',
        focusConfirm: false,
        didOpen: () => {
            recalcBulkModalTotals();
        },
        preConfirm: () => {
            const dateVal = document.getElementById('bulk_pay_date').value;
            const modeVal = document.getElementById('bulk_pay_mode').value;
            const refVal = document.getElementById('bulk_pay_ref').value.trim();
            const notesVal = document.getElementById('bulk_pay_notes').value.trim();
            const sendWa = document.getElementById('bulk_send_whatsapp').checked ? 1 : 0;
            const phoneVal = document.getElementById('bulk_vendor_phone').value.trim();
            const sendMail = document.getElementById('bulk_send_email').checked ? 1 : 0;
            const emailVal = document.getElementById('bulk_vendor_email').value.trim();

            if (!dateVal) {
                Swal.showValidationMessage('Please select a payment date.');
                return false;
            }

            const billsPayload = [];
            let totalToPay = 0;
            document.querySelectorAll('.bulk-pay-input').forEach(inp => {
                const amt = parseFloat(inp.value) || 0;
                if (amt > 0) {
                    const poId = parseInt(inp.getAttribute('data-po-id'), 10);
                    const meta = billMap[poId];
                    if (meta) {
                        billsPayload.push({
                            po_id: poId,
                            amount: amt,
                            total: meta.total,
                            inv_no: meta.inv_no,
                            inv_date: meta.inv_date,
                            campaign: meta.campaign,
                            po_ref: meta.ref
                        });
                        totalToPay += amt;
                    }
                }
            });

            if (billsPayload.length === 0 || totalToPay <= 0) {
                Swal.showValidationMessage('Please enter a payment amount greater than 0 for at least one bill.');
                return false;
            }

            if (sendWa && !phoneVal) {
                Swal.showValidationMessage('Please enter vendor phone number for WhatsApp advice, or uncheck WhatsApp.');
                return false;
            }

            if (sendMail && !emailVal) {
                Swal.showValidationMessage('Please enter vendor email address for SMTP advice, or uncheck Email.');
                return false;
            }

            return {
                partner_id: partnerInfo.id,
                payment_date: dateVal,
                payment_mode: modeVal,
                reference_no: refVal,
                notes: notesVal,
                send_whatsapp: sendWa,
                phone: phoneVal,
                send_email: sendMail,
                email: emailVal,
                bills: billsPayload
            };
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            submitBulkPayment(result.value);
        }
    });
}

function submitBulkPayment(payload) {
    Swal.fire({
        title: 'Processing Payment...',
        html: '<div style="font-size: 13px; color: #64748b;">Recording payments and generating payment advice...</div>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('../../ajax/save_bulk_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            throw new Error(res.message || 'Payment processing failed.');
        }

        // WhatsApp URL construction with Stamped Image link
        let cleanPhone = (payload.phone || res.partner_phone || '').replace(/[^0-9]/g, '');
        if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;
        let waText = res.advice_text || '';
        const waUrl = cleanPhone 
            ? 'https://api.whatsapp.com/send?phone=' + cleanPhone + (waText ? '&text=' + encodeURIComponent(waText) : '')
            : (waText ? 'https://api.whatsapp.com/send?text=' + encodeURIComponent(waText) : '');

        // User directive: "afer paemnt recieved , dont redirect on whats app automatically"
        // NO automatic redirection to WhatsApp! User can click manually below if desired.

        // Success Popup with Actions
        const emailBadge = (payload.send_email && (payload.email || res.partner_email))
            ? `<span style="background: #eff6ff; color: #1d4ed8; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-paperclip"></i> Voucher PDF Attached & Emailed to ${res.partner_email || payload.email}</span>`
            : '';

        const waBadge = `<span style="background: #f0fdf4; color: #15803d; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px;"><i class="fab fa-whatsapp"></i> Stamped Voucher Image Ready</span>`;

        const approvalNote = (res.approval_status === 'pending_approval')
            ? '<div style="background: #fff7ed; color: #c2410c; padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; margin-bottom: 10px;"><i class="fas fa-clock"></i> Payment submitted for Admin Approval.</div>'
            : '';

        const successHtml = `
            <div style="text-align: left; font-size: 13px; color: #1e293b;">
                ${approvalNote}
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #065f46; font-size: 12.5px;">Paid <strong>${res.count} Bill(s)</strong>:</span>
                        <span style="font-size: 16px; font-weight: 900; color: #059669;">₹${parseFloat(res.total_paid || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>
                </div>

                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">
                    ${emailBadge}
                    ${waBadge}
                </div>

                <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-top: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="${res.voucher_download_url || ('payment_advice_voucher.php?ids=' + (res.payment_ids ? res.payment_ids.join(',') : '') + '&download=1')}" target="_blank" style="background: #0284c7; color: white; padding: 7px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(2,132,199,0.3);" title="Download Official PDF Voucher with Letterhead">
                            <i class="fas fa-file-pdf"></i> Download Letterhead PDF
                        </a>
                        <a href="${res.voucher_url || ('payment_advice_voucher.php?ids=' + (res.payment_ids ? res.payment_ids.join(',') : ''))}" target="_blank" style="background: #ffffff; color: #1e293b; border: 1px solid #cbd5e1; padding: 7px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;" title="View & Print Official GST Payment Voucher">
                            <i class="fas fa-print"></i> View / Print Voucher
                        </a>
                        ${waUrl ? `<a href="${waUrl}" target="_blank" style="background: #16a34a; color: white; padding: 7px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(22,163,74,0.3);"><i class="fab fa-whatsapp"></i> Send Stamped Img on WhatsApp</a>` : ''}
                    </div>
                    <div>
                        <button type="button" onclick="location.reload()" style="background: #0f172a; color: white; padding: 7px 16px; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer;">
                            Done & Refresh
                        </button>
                    </div>
                </div>
            </div>
        `;

        if (res.voucher_download_url) {
            const hiddenIframe = document.createElement('iframe');
            hiddenIframe.style.position = 'fixed';
            hiddenIframe.style.left = '-9999px';
            hiddenIframe.style.top = '0';
            hiddenIframe.style.width = '1050px';
            hiddenIframe.style.height = '1450px';
            hiddenIframe.style.opacity = '0';
            hiddenIframe.style.pointerEvents = 'none';
            hiddenIframe.style.border = 'none';
            let vUrl = res.voucher_download_url;
            if (payload.send_email && (payload.email || res.partner_email)) {
                vUrl += '&send_email=1&email=' + encodeURIComponent(payload.email || res.partner_email);
            }
            hiddenIframe.src = vUrl;
            document.body.appendChild(hiddenIframe);
            setTimeout(() => hiddenIframe.remove(), 60000);
        }

        Swal.fire({
            title: '<div style="font-size: 18px; font-weight: 800; color: #059669; display: flex; align-items: center; gap: 6px;"><i class="fas fa-check-circle"></i> Payment Recorded!</div>',
            html: successHtml,
            width: '680px',
            showConfirmButton: false,
            showCloseButton: true,
            allowOutsideClick: false
        });
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Payment Failed',
            text: err.message || 'An error occurred while recording the payment.',
            confirmButtonColor: '#ef4444'
        });
    });
}

function copyAdviceTextToClipboard() {
    const txt = document.getElementById('paymentAdvicePreviewText');
    if (!txt) return;
    txt.select();
    txt.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(txt.value).then(() => {
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
        toast.fire({
            icon: 'success',
            title: 'Payment Advice copied!'
        });
    });
}

function payAgainstPO(clientId, poAmount, poRef, poId, invoiceNo, invDate, campaign, totalAmt) {
    const chk = document.querySelector(`.bill-select-checkbox[data-id="${poId}"]`);
    let item;
    if (chk) {
        item = {
            id: poId,
            ref: chk.getAttribute('data-ref'),
            inv_no: chk.getAttribute('data-inv-no'),
            inv_date: chk.getAttribute('data-inv-date'),
            campaign: chk.getAttribute('data-campaign'),
            total: parseFloat(chk.getAttribute('data-total')) || poAmount,
            paid: parseFloat(chk.getAttribute('data-paid')) || 0,
            outstanding: parseFloat(chk.getAttribute('data-outstanding')) || poAmount
        };
    } else {
        item = {
            id: poId,
            ref: poRef,
            inv_no: invoiceNo || poRef,
            inv_date: invDate || '',
            campaign: campaign || '-',
            total: totalAmt || poAmount,
            paid: (totalAmt || poAmount) - poAmount,
            outstanding: poAmount
        };
    }
    openBulkPaymentModal([item]);
}

function viewInvoice(ref) {
    window.open('view_invoice_redirect.php?ref=' + encodeURIComponent(ref) + '&from=' + encodeURIComponent(window.location.href), '_blank');
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

                let remarksContent = (p.notes && !p.notes.toLowerCase().startsWith('against ')) ? p.notes : '-';
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
                '<div style="background:#ecfdf4; border: 1px solid #a7f3d0; border-radius:8px; padding:10px 14px; margin-bottom:1rem; font-size:0.8rem; color:#065f46; font-weight:700; display:flex; align-items:center; gap:6px;">' +
                    '<i class="fas fa-file-invoice-dollar" style="color:#059669;"></i> <?php echo ($pType === "client") ? "Against Invoice Number" : "Against PO Number"; ?>: <strong>' + documentRef + '</strong>' +
                '</div>' +
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
                '<input id="ep_remark" class="swal2-input" value="' + (p.notes && !p.notes.toLowerCase().startsWith('against ') ? p.notes.replace(/"/g, '&quot;') : '') + '" placeholder="e.g. Against ' + documentRef + '" style="margin:0;width:100%;">' +
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

// ----------------------------------------------------
// STATEMENT PDF RENDERING (HTML2CANVAS + JSPDF)
// ----------------------------------------------------
async function generateLedgerPdfBlob() {
    try {
        const from = document.getElementById('filterFromDate')?.value || '';
        const to = document.getElementById('filterToDate')?.value || '';
        const campaign = document.getElementById('filterCampaign')?.value || '';
        let url = 'export_ledger_pdf.php?partner_id=<?php echo $partner_id; ?>&token=<?php echo md5($partner_id . 'sudha_statement_secret_2026'); ?>';
        if (from) url += '&from_date=' + encodeURIComponent(from);
        if (to) url += '&to_date=' + encodeURIComponent(to);
        if (campaign) url += '&campaign=' + encodeURIComponent(campaign);

        const resp = await fetch(url);
        if (!resp.ok) return null;
        const html = await resp.text();

        let iframe = document.getElementById('ledger-pdf-render-iframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'ledger-pdf-render-iframe';
            iframe.style.cssText = 'position: fixed; left: -9999px; top: 0; width: 1050px; height: 1600px; border: none; opacity: 0; pointer-events: none; z-index: -1;';
            document.body.appendChild(iframe);
        }

        const iframeDoc = iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(html);
        iframeDoc.close();

        // Inject print stylesheet overrides
        const styleElem = iframeDoc.createElement('style');
        styleElem.innerHTML = `
            * { box-sizing: border-box !important; }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                font-family: Arial, sans-serif !important;
                width: 1050px !important;
            }
            .page-container {
                width: 1050px !important;
                max-width: 1050px !important;
                min-height: auto !important;
                margin: 0 auto !important;
                padding: 10mm 12mm !important;
                box-shadow: none !important;
                border: none !important;
                box-sizing: border-box !important;
                background: #ffffff !important;
            }
            .action-bar, .no-print, .btn, button { display: none !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            img { max-width: 100% !important; }
        `;
        iframeDoc.head.appendChild(styleElem);

        // Wait for all images inside iframe to load
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

        // Pause for layout stability
        await new Promise(resolve => setTimeout(resolve, 350));

        const targetElem = iframeDoc.querySelector('.page-container') || iframeDoc.body;
        const canvas = await html2canvas(targetElem, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: 1050,
            windowWidth: 1050
        });

        const JsPdfConstructor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : ((typeof jsPDF !== 'undefined') ? jsPDF : (window.jsPDF || null));
        if (!JsPdfConstructor) {
            console.error('jsPDF not loaded');
            return null;
        }

        const pdf = new JsPdfConstructor({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4',
            compress: true
        });

        const pageWidth = 210;
        const pageHeight = 297;
        const imgWidth = pageWidth;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const imgData = canvas.toDataURL('image/jpeg', 0.95);

        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
        heightLeft -= pageHeight;

        while (heightLeft > 5) {
            position = heightLeft - imgHeight;
            pdf.addPage('a4', 'portrait');
            pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
            heightLeft -= pageHeight;
        }

        return pdf.output('blob');
    } catch (e) {
        console.error('Error generating Ledger PDF blob:', e);
        return null;
    }
}

async function generateLedgerPdfBase64() {
    const blob = await generateLedgerPdfBlob();
    if (!blob) return null;
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            const dataUrl = reader.result;
            resolve(dataUrl.split(',')[1] || null);
        };
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(blob);
    });
}

// ----------------------------------------------------
// SEND STATEMENT VIA SMTP EMAIL
// ----------------------------------------------------
async function sendLedgerEmail() {
    const partnerId = <?php echo $partner_id; ?>;
    const partnerName = "<?php echo addslashes($partner['name']); ?>";
    const partnerEmail = "<?php echo addslashes($partner['email'] ?? ''); ?>";
    const pType = "<?php echo $pType; ?>";
    const totalBilled = <?php echo floatval($totalInvoiced); ?>;
    const totalReceived = <?php echo floatval($totalReceived); ?>;
    const outstanding = <?php echo floatval($outstanding); ?>;
    const balanceLabel = "<?php echo $balanceLabel; ?>";
    const isAdv = outstanding < 0;

    const fromDate = document.getElementById('filterFromDate')?.value || '';
    const toDate = document.getElementById('filterToDate')?.value || '';
    const campaign = document.getElementById('filterCampaign')?.value || '';

    const defaultSubject = (pType === 'client') 
        ? `Statement of Account: ${partnerName}` 
        : `Vendor Account Statement: ${partnerName}`;

    const defaultMsg = `Dear ${partnerName},\n\nPlease find attached your official Statement of Accounts.\n\nTotal Billed: ₹${totalBilled.toLocaleString('en-IN', {minimumFractionDigits: 2})}\nTotal Received: ₹${totalReceived.toLocaleString('en-IN', {minimumFractionDigits: 2})}\n${isAdv ? 'Advance Balance' : 'Outstanding Due'}: ₹${Math.abs(outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2})}\n\nKindly review and let us know if you have any questions.\n\nThank you!`;

    const { value: formValues } = await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#ea4335;"><i class="fas fa-paper-plane"></i> Email Statement via SMTP</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.5rem;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 1rem; font-size: 0.85rem;">
                    <strong style="color: #0f172a;">${partnerName}</strong> (${pType.toUpperCase()})<br>
                    <span style="color: #64748b;">Current Balance: </span><strong style="color: ${isAdv ? '#059669' : '#b91c1c'};">₹${Math.abs(outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2})} ${isAdv ? 'ADV' : (pType === 'client' ? 'DUE' : 'PAYABLE')}</strong>
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.25rem; font-size: 0.82rem;">Recipient Email(s) <span style="color: #ef4444;">*</span></label>
                    <input id="smtp_ledger_email" type="text" value="${partnerEmail}" placeholder="e.g. accounts@client.com, owner@client.com" style="width: 100%; box-sizing: border-box; padding: 0.55rem 0.75rem; border-radius: 6px; border: 1.5px solid #cbd5e1; font-size: 0.9rem;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.25rem; font-size: 0.82rem;">Email Subject</label>
                    <input id="smtp_ledger_subject" type="text" value="${defaultSubject}" style="width: 100%; box-sizing: border-box; padding: 0.55rem 0.75rem; border-radius: 6px; border: 1.5px solid #cbd5e1; font-size: 0.9rem;">
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <label style="font-weight: 700; color: #334155; display: block; margin-bottom: 0.25rem; font-size: 0.82rem;">Message / Remarks</label>
                    <textarea id="smtp_ledger_message" rows="4" style="width: 100%; box-sizing: border-box; padding: 0.55rem 0.75rem; border-radius: 6px; border: 1.5px solid #cbd5e1; font-size: 0.85rem; font-family: inherit;">${defaultMsg}</textarea>
                </div>
                <div style="font-size: 0.75rem; color: #059669; display: flex; align-items: center; gap: 4px; margin-top: 6px;">
                    <i class="fas fa-paperclip"></i> Full Statement PDF will be automatically generated and attached.
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Email via SMTP',
        confirmButtonColor: '#ea4335',
        cancelButtonText: '<i class="fab fa-google"></i> Open Gmail Web',
        cancelButtonColor: '#64748b',
        showCloseButton: true,
        preConfirm: () => {
            const emailVal = document.getElementById('smtp_ledger_email').value.trim();
            const subjectVal = document.getElementById('smtp_ledger_subject').value.trim();
            const msgVal = document.getElementById('smtp_ledger_message').value.trim();
            if (!emailVal) {
                Swal.showValidationMessage('Please enter recipient email address.');
                return false;
            }
            return { email: emailVal, subject: subjectVal, message: msgVal };
        }
    });

    if (formValues) {
        Swal.fire({
            title: 'Generating & Sending Statement...',
            html: `
                <div style="padding: 10px 0; text-align: center;">
                    <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #ea4335; margin-bottom: 12px;"></i><br>
                    Rendering statement PDF & dispatching via SMTP to <b>${formValues.email}</b>...
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const pdfBase64 = await generateLedgerPdfBase64();
            const cleanName = partnerName.replace(/[^a-zA-Z0-9_-]/g, '_');
            const pdfFileName = `Statement_${cleanName}.pdf`;

            const formData = new FormData();
            formData.append('partner_id', partnerId);
            formData.append('token', '<?php echo md5($partner_id . "sudha_statement_secret_2026"); ?>');
            formData.append('email', formValues.email);
            formData.append('subject', formValues.subject);
            formData.append('message', formValues.message);
            formData.append('from_date', fromDate);
            formData.append('to_date', toDate);
            formData.append('campaign', campaign);
            formData.append('total_billed', totalBilled);
            formData.append('total_received', totalReceived);
            formData.append('outstanding', outstanding);
            formData.append('balance_label', balanceLabel);
            if (pdfBase64) {
                formData.append('ledger_pdf_base64', pdfBase64);
                formData.append('ledger_pdf_name', pdfFileName);
            }

            const resp = await fetch('../../ajax/send_ledger_email.php', {
                method: 'POST',
                body: formData
            });
            const rawText = await resp.text();
            let res;
            try {
                res = JSON.parse(rawText);
            } catch(e) {
                throw new Error(rawText || 'Invalid response from server.');
            }

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Statement Sent Successfully!',
                    html: `<b>${res.message || 'Statement of Account sent successfully!'}</b><br><br><span style="color:#059669;font-size:0.88rem;"><i class="fas fa-paperclip"></i> Official PDF Attached (${pdfFileName})</span>`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Sending Failed',
                    text: res.message || 'Could not send statement email via SMTP server.'
                });
            }
        } catch (err) {
            Swal.fire('SMTP Error', err.message || 'Network error occurred.', 'error');
        }
    } else if (Swal.getDismissReason() === Swal.DismissReason.cancel) {
        const emailInput = document.getElementById('smtp_ledger_email')?.value.trim() || partnerEmail;
        const subjectInput = document.getElementById('smtp_ledger_subject')?.value.trim() || defaultSubject;
        const msgInput = document.getElementById('smtp_ledger_message')?.value.trim() || defaultMsg;
        const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailInput)}&su=${encodeURIComponent(subjectInput)}&body=${encodeURIComponent(msgInput)}`;
        window.open(gmailUrl, '_blank');
    }
}

// ----------------------------------------------------
// SEND STATEMENT VIA WHATSAPP SHARE
// ----------------------------------------------------
async function sendLedgerWhatsApp() {
    const partnerName = "<?php echo addslashes($partner['name']); ?>";
    const partnerPhone = "<?php echo addslashes($partner['phone'] ?? ''); ?>";
    const pType = "<?php echo $pType; ?>";
    const outstanding = <?php echo floatval($outstanding); ?>;
    const isAdv = outstanding < 0;

    const cleanName = partnerName.replace(/[^a-zA-Z0-9_-]/g, '_');
    const pdfFileName = `Statement_${cleanName}.pdf`;

    await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#25D366;"><i class="fab fa-whatsapp"></i> Share Statement via WhatsApp</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">WhatsApp Number</label>
                    <div style="position: relative;">
                        <i class="fab fa-whatsapp" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #25D366; font-size: 1.1rem;"></i>
                        <input id="wa_ledger_phone" type="text" value="${partnerPhone}" placeholder="e.g. 9876543210 (or leave blank for chat selection)" style="width: 100%; box-sizing: border-box; font-size: 0.95rem; font-weight: 600; padding: 0.6rem 0.75rem 0.6rem 2.4rem; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none;">
                    </div>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.8rem; flex-shrink: 0;"></i>
                    <div style="flex: 1; overflow: hidden;">
                        <div style="font-weight: 800; color: #0f172a; font-size: 0.9rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${pdfFileName}</div>
                        <div style="font-size: 0.75rem; color: #15803d; margin-top: 2px;">${partnerName} • Balance: ₹${Math.abs(outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2})} ${isAdv ? 'ADV' : (pType === 'client' ? 'DUE' : 'PAYABLE')} • Ready for WhatsApp</div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                    <button type="button" id="btnShareLedgerWaWeb" style="width: 100%; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.92rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(37,211,102,0.35); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                        <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Open & Share in WhatsApp Web
                    </button>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" id="btnShareLedgerWaApp" style="flex: 1; background: #0284c7; color: white; border: none; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fas fa-share-alt"></i> WhatsApp App / Mobile
                        </button>
                        <button type="button" id="btnDownloadLedgerPdf" style="flex: 1; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fas fa-download"></i> Download PDF Only
                        </button>
                    </div>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times"></i> Close',
        cancelButtonColor: '#94a3b8',
        showCloseButton: true,
        didOpen: () => {
            document.getElementById('btnShareLedgerWaWeb')?.addEventListener('click', () => {
                executeLedgerShare('web');
            });
            document.getElementById('btnShareLedgerWaApp')?.addEventListener('click', () => {
                executeLedgerShare('app');
            });
            document.getElementById('btnDownloadLedgerPdf')?.addEventListener('click', () => {
                executeLedgerShare('download');
            });
        }
    });

    async function executeLedgerShare(mode) {
        const phoneInput = document.getElementById('wa_ledger_phone')?.value.trim().replace(/[^0-9]/g, '') || '';
        let phoneFormatted = phoneInput;
        if (phoneFormatted && phoneFormatted.length === 10) phoneFormatted = '91' + phoneFormatted;

        if (mode === 'download') {
            Swal.fire({
                title: 'Generating PDF...',
                text: 'Please wait...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });
            const pdfBlob = await generateLedgerPdfBlob();
            if (pdfBlob) {
                const blobUrl = URL.createObjectURL(pdfBlob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = pdfFileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);
                Swal.fire({
                    icon: 'success',
                    title: 'Statement PDF Downloaded!',
                    text: `${pdfFileName} has been saved to your downloads.`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire('Error', 'Failed to generate PDF.', 'error');
            }
            return;
        }

        Swal.fire({
            title: 'Preparing Statement PDF...',
            html: `
                <div style="padding: 10px 0; text-align: center;">
                    <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #25D366; margin-bottom: 12px;"></i><br>
                    Rendering official <b>${pdfFileName}</b> for WhatsApp...
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const pdfBlob = await generateLedgerPdfBlob();
            if (!pdfBlob) throw new Error('Could not generate Statement PDF document.');

            const pdfFile = new File([pdfBlob], pdfFileName, { type: 'application/pdf' });

            if (mode === 'app') {
                if (navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
                    Swal.close();
                    try {
                        await navigator.share({
                            files: [pdfFile]
                        });
                    } catch(shareErr) {
                        if (shareErr.name !== 'AbortError') {
                            triggerDesktopLedgerWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                        }
                    }
                } else {
                    triggerDesktopLedgerWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                }
            } else {
                // WhatsApp Web mode
                triggerDesktopLedgerWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'web');
            }
        } catch(err) {
            Swal.fire('Error', err.message || 'Failed to prepare Statement PDF for WhatsApp.', 'error');
        }
    }
}

function triggerDesktopLedgerWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, target = 'web') {
    // 1. Download PDF to user's device
    const blobUrl = URL.createObjectURL(pdfBlob);
    const a = document.createElement('a');
    a.href = blobUrl;
    a.download = pdfFileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(blobUrl);

    const fromVal = document.getElementById('filterFromDate')?.value || '';
    const toVal = document.getElementById('filterToDate')?.value || '';
    const campVal = document.getElementById('filterCampaign')?.value || '';
    const stmtToken = '<?php echo md5($partner_id . "sudha_statement_secret_2026"); ?>';
    const periodText = (fromVal ? fromVal : 'All Historical') + ' to ' + (toVal ? toVal : '<?php echo date("d M Y"); ?>');

    const balLabel = isAdv ? 'Advance Balance' : (pType === 'client' ? 'Outstanding Due' : 'Outstanding Payable');
    const balStatus = isAdv ? 'ADVANCE' : (pType === 'client' ? 'DUE' : 'PAYABLE');

    const stmtOnlineUrl = `https://sudhacreative.com/modules/financials/export_ledger_pdf.php?partner_id=<?php echo $partner_id; ?>&token=${stmtToken}${fromVal ? '&from_date='+encodeURIComponent(fromVal) : ''}${toVal ? '&to_date='+encodeURIComponent(toVal) : ''}${campVal ? '&campaign='+encodeURIComponent(campVal) : ''}`;

    const waText = `📋 *STATEMENT OF ACCOUNTS*
━━━━━━━━━━━━━━━━━━━━━━━━
Dear *${partnerName}*,

Please find the official Statement of Accounts from *Sudha Creative & Advertising (OPC) Private Limited*.

📊 *Account Summary:*
• Period: *${periodText}*
• Total ${pType === 'client' ? 'Invoiced' : 'Billed'}: *₹${totalBilled.toLocaleString('en-IN', {minimumFractionDigits: 2})}*
• Total ${pType === 'client' ? 'Received' : 'Paid'}: *₹${totalReceived.toLocaleString('en-IN', {minimumFractionDigits: 2})}*
• ${balLabel}: *₹${Math.abs(outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2})}* (*${balStatus}*)

📄 *Online Statement PDF & View:*
${stmtOnlineUrl}

*(Note: The Statement PDF has also been downloaded to your computer and can be attached here directly)*

Regards,
*Accounts Dept - Sudha Creative*`;

    let waUrl = '';
    if (target === 'app') {
        waUrl = phoneFormatted 
            ? `whatsapp://send?phone=${phoneFormatted}&text=${encodeURIComponent(waText)}` 
            : `whatsapp://send?text=${encodeURIComponent(waText)}`;
    } else {
        waUrl = phoneFormatted 
            ? `https://api.whatsapp.com/send?phone=${phoneFormatted}&text=${encodeURIComponent(waText)}`
            : `https://api.whatsapp.com/send?text=${encodeURIComponent(waText)}`;
    }
    window.open(waUrl, '_blank');

    Swal.fire({
        icon: 'success',
        title: 'PDF Downloaded & WhatsApp Web Opened!',
        html: `
            <div style="text-align: left; font-size: 0.9rem; line-height: 1.5;">
                <p style="margin: 0 0 8px 0;">📄 <b>${pdfFileName}</b> has been downloaded to your computer.</p>
                <p style="margin: 0 0 8px 0;">💬 <b>${target === 'app' ? 'WhatsApp App' : 'WhatsApp Web'}</b> has been opened in a new window/tab.</p>
                <div style="color: #065f46; font-weight: 600; background: #ecfdf5; padding: 10px 14px; border-radius: 8px; border: 1px solid #a7f3d0; margin-top: 10px;">
                    👉 Simply drag & drop the downloaded Statement PDF into WhatsApp chat or click 📎 to attach and send!
                </div>
            </div>
        `,
        confirmButtonText: 'Got It!',
        confirmButtonColor: '#25D366'
    });
}
</script>

<!-- Include html2canvas & jsPDF for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
