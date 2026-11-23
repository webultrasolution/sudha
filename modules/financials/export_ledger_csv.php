<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$activeEntityId = $_SESSION['active_entity_id'] ?? null;

$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
if (!$partner_id) die("Invalid Partner ID.");

$token = $_GET['token'] ?? '';
$isValidToken = ($partner_id && $token && $token === md5($partner_id . 'sudha_statement_secret_2026'));

if (!$isValidToken) {
    requirePermission('financials', 'view');
}

$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();
if (!$partner) die("Partner not found.");

$pType       = $partner['type'];
$partnerName = $partner['name'];
$fromDate = $_GET['from_date'] ?? '';
$toDate   = $_GET['to_date']   ?? '';
$campaign = $_GET['campaign']  ?? '';

$ledger = [];

if ($pType === 'client') {
    // 1. Fetch Invoices (client invoices approved)
    $invSql = "
        SELECT i.id, COALESCE(i.invoice_date, DATE(i.created_at)) as date, CONVERT(i.invoice_number USING utf8mb4) COLLATE utf8mb4_unicode_ci as ref,
               CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci as remark,
               i.sub_total as base_amt, (i.cgst + i.sgst + i.igst) as tax_amt, i.total_amount as total_amt,
               i.total_amount as debit, 0 as credit,
               CONVERT(b.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(b.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(CASE WHEN b.confirmation_type = 'email' THEN 'Email Conf' ELSE b.customer_po_no END USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               CASE WHEN b.confirmation_type = 'email' THEN b.email_date ELSE b.customer_po_date END as po_date,
               'Invoice' as entry_type,
               'Renting' as service_type,
               b.start_date, b.end_date,
               CONVERT(b.customer_po_file USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file
        FROM invoices i
        JOIN bookings b ON i.booking_id = b.id
        WHERE b.client_id = ? AND i.approval_status = 'approved'" . ($activeEntityId ? " AND i.entity_id = ?" : "") . "
    ";
    
    $invParams = [$partner_id];
    if ($activeEntityId) {
        $invParams = [$partner_id, $activeEntityId];
    }
    
    $stmtInv = $pdo->prepare($invSql);
    $stmtInv->execute($invParams);
    foreach ($stmtInv->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ledger[] = $r;
    }

    // 2. Client printing rates
    $stmtPR = $pdo->prepare("
        SELECT MIN(r.id) as id, COALESCE(MIN(r.custom_invoice_date), DATE(MIN(r.created_at))) as date,
               COALESCE(CONVERT(r.custom_invoice_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(r.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('RATE-', MIN(r.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as ref,
               COALESCE(MIN(r.custom_invoice_date), DATE(MIN(r.created_at))) as ref_date,
               '' as remark,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as base_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 0.18 as tax_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as debit,
               0 as credit, 
               CONVERT(MAX(r.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(MAX(r.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(MAX(r.customer_po_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               MAX(r.customer_po_date) as po_date,
               'Printing Invoice' as entry_type,
               'Printing' as service_type,
               MIN(b.start_date) as start_date, MIN(b.end_date) as end_date,
               CONVERT(MAX(r.attachments) USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file
        FROM client_printing_rates r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN bookings b ON r.booking_id = b.id
        WHERE r.client_id = ? AND r.is_final_invoice = 1 AND r.approval_status = 'approved' " . ($activeEntityId ? " AND b.entity_id = ?" : "") . "
        GROUP BY COALESCE(r.po_number, r.id)
    ");
    $prParams = [$partner_id];
    if ($activeEntityId) {
        $prParams[] = $activeEntityId;
    }
    $stmtPR->execute($prParams);
    foreach ($stmtPR->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ledger[] = $r;
    }

    // 3. Client mounting rates
    $stmtMR = $pdo->prepare("
        SELECT MIN(m.id) as id, COALESCE(MIN(m.custom_invoice_date), DATE(MIN(m.created_at))) as date,
               COALESCE(CONVERT(m.custom_invoice_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(m.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('CMI-', MIN(m.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as ref,
               COALESCE(MIN(m.custom_invoice_date), DATE(MIN(m.created_at))) as ref_date,
               '' as remark,
               SUM(m.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as base_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 0.18 as tax_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as total_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as debit,
               0 as credit, 
               CONVERT(MAX(m.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(MAX(m.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(MAX(m.customer_po_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               MAX(m.customer_po_date) as po_date,
               'Mounting Invoice' as entry_type,
               'Mounting' as service_type,
               MIN(b.start_date) as start_date, MIN(b.end_date) as end_date,
               CONVERT(MAX(m.attachments) USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file
        FROM client_mounting_rates m
        LEFT JOIN sites s ON m.site_id = s.id
        LEFT JOIN bookings b ON m.booking_id = b.id
        WHERE m.client_id = ? AND m.is_final_invoice = 1 AND m.approval_status = 'approved' " . ($activeEntityId ? " AND b.entity_id = ?" : "") . "
        GROUP BY COALESCE(m.po_number, m.id)
    ");
    $mrParams = [$partner_id];
    if ($activeEntityId) {
        $mrParams[] = $activeEntityId;
    }
    $stmtMR->execute($mrParams);
    foreach ($stmtMR->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ledger[] = $r;
    }

} else {
    // Vendor Logic: Purchase orders (vendor invoices approved)
    $poSql = "
        SELECT po.id, po.vendor_invoice_date as date, CONVERT(po.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci as ref, 
               CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci as remark,
               CASE 
                   WHEN COALESCE(po.total_amount, 0) > 0 THEN ROUND(po.vendor_invoice_amount * (po.po_amount / po.total_amount), 2)
                   ELSE po.vendor_invoice_amount
               END as base_amt,
               CASE 
                   WHEN COALESCE(po.total_amount, 0) > 0 THEN ROUND(po.vendor_invoice_amount * ((po.cgst_amount + po.sgst_amount + po.igst_amount) / po.total_amount), 2)
                   ELSE 0
               END as tax_amt, 
               po.vendor_invoice_amount as total_amt, 
               po.vendor_invoice_amount as debit, 
               0 as credit, 
               CONVERT(po.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(po.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(po.vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               po.vendor_invoice_date as po_date,
               'Vendor Invoice' as entry_type,
               CASE WHEN po.type = 'printing' THEN 'Printing' ELSE 'Renting' END as service_type,
               COALESCE(b.start_date, poi.min_start) as start_date, COALESCE(b.end_date, poi.max_end) as end_date,
               CONVERT((SELECT GROUP_CONCAT(CONCAT('uploads/pos/', filename) SEPARATOR '||') FROM po_attachments WHERE po_id = po.id) USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_po_file
        FROM purchase_orders po
        LEFT JOIN bookings b ON po.campaign_id = b.id
        LEFT JOIN (SELECT po_id, MIN(start_date) as min_start, MAX(end_date) as max_end FROM po_items GROUP BY po_id) poi ON po.id = poi.po_id
        WHERE po.vendor_id = ? AND po.type != 'printing' AND po.vendor_invoice_approval_status = 'approved'" . ($activeEntityId ? " AND po.entity_id = ?" : "") . "
    ";
    
    $poParams = [$partner_id];
    if ($activeEntityId) {
        $poParams = [$partner_id, $activeEntityId];
    }

        $stmtPO = $pdo->prepare($poSql);
    $stmtPO->execute($poParams);
    foreach ($stmtPO->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['ref_date'] = $r['date'];
        $ledger[] = $r;
    }

    // Vendor printing rates
    $prSql = "
        SELECT MIN(r.id) as id, DATE(MIN(r.created_at)) as date, 
               COALESCE(CONVERT(r.po_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(CONCAT('RATE-', MIN(r.id)) USING utf8mb4) COLLATE utf8mb4_unicode_ci) as ref,
               CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci as remark,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as base_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 0.18 as tax_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) * 1.18 as debit,
               0 as credit,
               CONVERT(MAX(r.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(MAX(r.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(MAX(r.vendor_invoice_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               MAX(r.vendor_invoice_date) as po_date,
               'Vendor Invoice' as entry_type,
               'Printing' as service_type,
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
    ";

    $prParams = [$partner_id];
    if ($activeEntityId) {
        $prParams[] = $activeEntityId;
    }
    $stmtPR = $pdo->prepare($prSql);
    $stmtPR->execute($prParams);
    foreach ($stmtPR->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['ref_date'] = $r['date'];
        $ledger[] = $r;
    }
}

// 3. Fetch Payments (Receivable/Payable)
$dbType = ($pType == 'client') ? 'receivable' : 'payable';
$paySql = "
    SELECT p.id, p.payment_date as date, p.transaction_id as ref,
           p.notes as remark,
           p.amount as base_amt, 0 as tax_amt, p.amount as total_amt,
           0 as debit, p.amount as credit, 
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
           p.approval_status,
           CASE 
               WHEN p.type = 'receivable' THEN 
                   CASE WHEN i.type = 'printing' THEN 'Printing' ELSE 'Renting' END
               WHEN p.type = 'payable' THEN
                   CASE 
                       WHEN po.id IS NOT NULL THEN CASE WHEN po.type = 'printing' THEN 'Printing' ELSE 'Renting' END
                       WHEN vpr.id IS NOT NULL THEN 'Printing'
                       ELSE 'Renting'
                   END
               ELSE 'Renting'
           END as service_type,
           NULL as start_date, NULL as end_date
    FROM payments p
    LEFT JOIN invoices i ON (p.type = 'receivable' AND p.invoice_id = i.id)
    LEFT JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN proposals prop ON (p.type = 'receivable' AND p.proposal_id = prop.id)
    LEFT JOIN purchase_orders po ON (p.type = 'payable' AND p.proposal_id = po.id AND po.vendor_id = p.partner_id)
    LEFT JOIN vendor_printing_rates vpr ON (p.type = 'payable' AND po.id IS NULL AND p.proposal_id = vpr.id AND vpr.vendor_id = p.partner_id)
    WHERE p.partner_id = ? AND p.type = ? AND p.approval_status = 'approved'" . ($activeEntityId ? " AND p.entity_id = ?" : "");

$payParams = [$partner_id, $dbType];
if ($activeEntityId) {
    $payParams[] = $activeEntityId;
}

$stmtPay = $pdo->prepare($paySql);
$stmtPay->execute($payParams);
$payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

// Map to Excel compatible field names and build a map of paid amounts to deduct running balances
$invoicePaidMap = [];
$poPaidMap = [];

foreach ($payments as $pay) {
    if (!empty($pay['invoice_id'])) {
        $invoicePaidMap[$pay['invoice_id']] = ($invoicePaidMap[$pay['invoice_id']] ?? 0) + $pay['base_amt'];
    }
    if (!empty($pay['proposal_id'])) {
        $poPaidMap[$pay['proposal_id']] = ($poPaidMap[$pay['proposal_id']] ?? 0) + $pay['base_amt'];
    }
}

foreach ($payments as $pay) {
    $pay['entry_type'] = ($pType == 'client') ? 'Payment Received' : 'Payment Made';
    $pay['ref_date'] = $pay['date'];
    $pay['debit'] = 0;
    $pay['credit'] = $pay['total_amt'];
    $ledger[] = $pay;
}

// Sort by date
usort($ledger, fn($a,$b) => strtotime($a['date']) - strtotime($b['date']));

// Filter by Date and Campaign
if ($fromDate || $toDate || $campaign) {
    $ledger = array_filter($ledger, function($r) use ($fromDate, $toDate, $campaign) {
        if ($fromDate || $toDate) {
            $d = strtotime($r['date']);
            if ($fromDate && $d < strtotime($fromDate)) return false;
            if ($toDate   && $d > strtotime($toDate))   return false;
        }
        if ($campaign) {
            $parts = [];
            if (!empty($r['campaign_name'])) $parts[] = trim($r['campaign_name']);
            if (!empty($r['brand_name'])) $parts[] = trim($r['brand_name']);
            $combined = implode(' / ', $parts);
            if (strcasecmp($combined, $campaign) !== 0) return false;
        }
        return true;
    });
}

// Group payments directly below their parent invoices/POs, keeping the rest chronological
$parents = [];
$payments = [];
$unlinkedPayments = [];
$groupedPayments = [];
$parentsMap = [];

foreach ($ledger as $item) {
    $isPayment = (stripos($item['entry_type'] ?? '', 'Payment') !== false);
    if ($isPayment) {
        $payments[] = $item;
    } else {
        $parents[] = $item;
        $key = ($pType === 'client') ? 'invoice_' . $item['id'] : 'po_' . $item['id'];
        $parentsMap[$key] = true;
    }
}

// Sort parents chronologically by date
usort($parents, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Group linked payments
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
$finalLedger = [];
foreach ($baseList as $item) {
    $finalLedger[] = $item;
    $isPayment = (stripos($item['entry_type'] ?? '', 'Payment') !== false);
    if (!$isPayment) {
        $parentKey = ($pType === 'client') ? 'invoice_' . $item['id'] : 'po_' . $item['id'];
        if (isset($groupedPayments[$parentKey])) {
            $linked = $groupedPayments[$parentKey];
            usort($linked, function($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });
            foreach ($linked as $pay) {
                $finalLedger[] = $pay;
            }
        }
    }
}
$ledger = $finalLedger;

// Compute totals
$totalDebit = $totalCredit = 0;
$totalBase = 0;
$totalTax = 0;
foreach ($ledger as $r) { 
    $isPayment = stripos($r['entry_type'] ?? '', 'Payment') !== false;
    if (!$isPayment) {
        $totalDebit += floatval($r['debit'] ?? 0);
        $totalBase += floatval($r['base_amt'] ?? 0);
        $totalTax += floatval($r['tax_amt'] ?? 0);
    } else {
        $totalCredit += floatval($r['credit'] ?? 0);
    }
}
$outstanding = $totalDebit - $totalCredit;

// ── Output Excel ────────────────────────────────────────────────────────────
$filename = preg_replace('/[^a-zA-Z0-9_-]/s','_', $partnerName) . '_Ledger_' . date('Ymd') . '.xls';
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Sudha Creative</Author>
  <Created><?php echo date('Y-m-d\TH:i:s\Z'); ?></Created>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="TitleMain">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="14" ss:Bold="1"/>
  </Style>
  <Style ss:ID="SubTitle">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Color="#475569"/>
  </Style>
  <Style ss:ID="HeaderCol">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#17A589" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="CellInvoice">
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <Interior ss:Color="#FFF7ED" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="CellInvoiceCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <Interior ss:Color="#FFF7ED" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="CellInvoiceRight">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <Interior ss:Color="#FFF7ED" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="CellInvoiceRightBold">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>
   <Interior ss:Color="#FFF7ED" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="CellPayment">
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <Interior ss:Color="#ECFDF5" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="CellPaymentCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <Interior ss:Color="#ECFDF5" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="CellPaymentRight">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10"/>
   <Interior ss:Color="#ECFDF5" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="CellPaymentRightGreen">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Color="#059669"/>
   <Interior ss:Color="#ECFDF5" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="CellSummaryLabel">
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="CellSummaryVal">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="TtlLabel">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>
   <Interior ss:Color="#FEF9C3" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TtlVal">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>
   <Interior ss:Color="#FEF9C3" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="TtlValGreen">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1" ss:Color="#059669"/>
   <Interior ss:Color="#FEF9C3" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="CellBalDue">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1" ss:Color="#DC2626"/>
  </Style>
  <Style ss:ID="CellBalAdv">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1" ss:Color="#059669"/>
  </Style>
  <Style ss:ID="TtlBalDue">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1" ss:Color="#DC2626"/>
   <Interior ss:Color="#FEF9C3" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TtlBalAdv">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1" ss:Color="#059669"/>
   <Interior ss:Color="#FEF9C3" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="CellBalNil">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Color="#64748B"/>
  </Style>
  <Style ss:ID="TtlBalNil">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AAAAAA"/>
   </Borders>
   <Font ss:FontName="Arial" x:Family="Swiss" ss:Size="10" ss:Bold="1" ss:Color="#475569"/>
   <Interior ss:Color="#FEF9C3" ss:Pattern="Solid"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Ledger">
  <Table>
   <Column ss:Width="35"/>
   <Column ss:Width="130"/>
   <Column ss:Width="90"/>
   <Column ss:Width="150"/> <!-- Period -->
   <Column ss:Width="90"/> <!-- Type -->
   <Column ss:Width="120"/> <!-- Service Type -->
   <Column ss:Width="130"/>
   <Column ss:Width="90"/>
   <Column ss:Width="200"/>
   <Column ss:Width="200"/>
   <Column ss:Width="110"/>
   <Column ss:Width="90"/>
   <Column ss:Width="110"/>
   <Column ss:Width="110"/>
   <Column ss:Width="135"/>

   <Row ss:Height="25">
    <Cell ss:MergeAcross="14" ss:StyleID="TitleMain"><Data ss:Type="String"><?php echo strtoupper(htmlspecialchars($partnerName)); ?> — Account Statement</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="14" ss:StyleID="SubTitle"><Data ss:Type="String"><?php 
        echo ucfirst($pType) . " | Period: " . ($fromDate ? date('d M Y', strtotime($fromDate)) : 'All Time') . 
             " to " . ($toDate ? date('d M Y', strtotime($toDate)) : date('d M Y')) . 
             " | Generated: " . date('d M Y, h:i A'); 
    ?></Data></Cell>
   </Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>

   <!-- Summary row -->
   <Row ss:Height="20">
    <Cell ss:MergeAcross="5" ss:StyleID="CellSummaryLabel"><Data ss:Type="String">Total Billed</Data></Cell>
    <Cell ss:MergeAcross="3" ss:StyleID="CellSummaryVal"><Data ss:Type="Number"><?php echo $totalDebit; ?></Data></Cell>
    <Cell ss:MergeAcross="1" ss:StyleID="CellSummaryLabel"><Data ss:Type="String"><?php echo $pType==='client'?'Total Received':'Total Paid'; ?></Data></Cell>
    <Cell ss:MergeAcross="2" ss:StyleID="CellSummaryVal"><Data ss:Type="Number"><?php echo $totalCredit; ?></Data></Cell>
   </Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>

   <!-- Column headers -->
   <Row ss:Height="22">
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">SL#</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String"><?php echo ($pType === 'client') ? 'Invoice Number' : 'PO Number'; ?></Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Date</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Period</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Type</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Service Type</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String"><?php echo ($pType === 'client') ? 'Customer PO Number' : 'Inv Number'; ?></Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String"><?php echo ($pType === 'client') ? 'PO Date' : 'Inv Date'; ?></Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Campaign / Brand</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Remark / Notes</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Base Amount</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Tax (GST)</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Grand Total</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String"><?php echo $pType==='client'?'Received':'Paid Out'; ?></Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Running Balance</Data></Cell>
   </Row>

   <!-- Data Rows -->
   <?php
   $sl = 1; $runBalance = 0;
   foreach ($ledger as $row):
       $isPayment = stripos($row['entry_type'], 'Payment') !== false;
       
       $paid = 0;
       $isInvoiceOrPo = stripos($row['entry_type'] ?? '', 'Invoice') !== false || stripos($row['entry_type'] ?? '', 'PO') !== false || ($row['entry_type'] ?? '') === 'Vendor Invoice';
       if ($isInvoiceOrPo) {
           $isPO = ($pType === 'vendor');
           $paid = $isPO ? ($poPaidMap[$row['id']] ?? 0) : ($invoicePaidMap[$row['id']] ?? 0);
       }
       
       $rowOutstanding = 0;
       if (!$isPayment) {
           $rowOutstanding = $row['total_amt'] - $paid;
           $runBalance += $row['total_amt'];
        } else {
           $runBalance -= $row['total_amt'];
       }
       
       if (abs($runBalance) < 0.01) {
           $balText  = '₹0.00';
           $balClass = 'CellBalNil';
       } elseif ($runBalance > 0) {
           $balText  = '₹' . number_format($runBalance, 2) . ' ' . ($pType === 'client' ? 'DUE' : 'PAYABLE');
           $balClass = 'CellBalDue';
       } else {
           $balText  = '₹' . number_format(abs($runBalance), 2) . ' ADV';
           $balClass = 'CellBalAdv';
       }
       
       $cellStyle       = $isPayment ? 'CellPayment' : 'CellInvoice';
       $cellStyleCenter = $isPayment ? 'CellPaymentCenter' : 'CellInvoiceCenter';
       $cellStyleRight  = $isPayment ? 'CellPaymentRight' : 'CellInvoiceRight';
   ?>
   <Row>
    <Cell ss:StyleID="<?php echo $cellStyleCenter; ?>"><Data ss:Type="Number"><?php echo $sl++; ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php 
        if ($isPayment) {
            if ($pType === 'client' && !empty($row['invoice_number'])) {
                echo 'pay Against ' . htmlspecialchars($row['invoice_number']);
            } elseif ($pType === 'vendor' && !empty($row['po_number_linked'])) {
                echo 'pay Against ' . htmlspecialchars($row['po_number_linked']);
            } elseif ($pType === 'vendor' && !empty($row['po_number'])) {
                echo 'pay Against ' . htmlspecialchars($row['po_number']);
            } else {
                echo htmlspecialchars($row['ref'] ?? '');
            }
        } else {
            echo htmlspecialchars($row['ref'] ?? '');
        }
    ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyleCenter; ?>"><Data ss:Type="String"><?php echo date('d M Y', strtotime($row['date'])); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php 
        echo (!empty($row['start_date']) && !empty($row['end_date'])) 
            ? (date('d M Y', strtotime($row['start_date'])) . ' - ' . date('d M Y', strtotime($row['end_date']))) 
            : ''; 
    ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($row['entry_type']); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php 
        echo htmlspecialchars($row['service_type'] ?? ''); 
    ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($row['po_number'] ?? ''); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyleCenter; ?>"><Data ss:Type="String"><?php echo (!empty($row['po_date']) && $row['po_date'] !== '0000-00-00') ? date('d M Y', strtotime($row['po_date'])) : ''; ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php 
        $cParts = [];
        if (!empty($row['campaign_name'])) $cParts[] = trim($row['campaign_name']);
        if (!empty($row['brand_name'])) $cParts[] = trim($row['brand_name']);
        echo htmlspecialchars(implode(' / ', $cParts));
    ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($row['remark'] ?? ''); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyleRight; ?>"><?php if (!$isPayment) { ?><Data ss:Type="Number"><?php echo $row['base_amt']; ?></Data><?php } else { ?><Data ss:Type="String">—</Data><?php } ?></Cell>
    <Cell ss:StyleID="<?php echo $cellStyleRight; ?>"><?php if (!$isPayment && $row['tax_amt'] > 0) { ?><Data ss:Type="Number"><?php echo $row['tax_amt']; ?></Data><?php } else { ?><Data ss:Type="String">—</Data><?php } ?></Cell>
    <Cell ss:StyleID="<?php echo $isPayment ? 'CellPaymentRight' : 'CellInvoiceRightBold'; ?>"><?php if (!$isPayment) { ?><Data ss:Type="Number"><?php echo $row['total_amt']; ?></Data><?php } else { ?><Data ss:Type="String">—</Data><?php } ?></Cell>
    <Cell ss:StyleID="<?php echo $isPayment ? 'CellPaymentRightGreen' : 'CellInvoiceRight'; ?>"><?php 
        if ($isPayment) { 
            ?><Data ss:Type="Number"><?php echo $row['total_amt']; ?></Data><?php 
        } else { 
            ?><Data ss:Type="String">—</Data><?php 
        } 
     ?></Cell>
    <Cell ss:StyleID="<?php echo $balClass; ?>"><Data ss:Type="String"><?php echo $balText; ?></Data></Cell>
   </Row>
   <?php endforeach; ?>

   <!-- Totals footer -->
   <Row ss:Height="22">
    <Cell ss:MergeAcross="9" ss:StyleID="TtlLabel"><Data ss:Type="String">Closing Balance</Data></Cell>
    <Cell ss:StyleID="TtlVal"><Data ss:Type="Number"><?php echo $totalBase; ?></Data></Cell>
    <Cell ss:StyleID="TtlVal"><Data ss:Type="Number"><?php echo $totalTax; ?></Data></Cell>
    <Cell ss:StyleID="TtlVal"><Data ss:Type="Number"><?php echo $totalDebit; ?></Data></Cell>
    <Cell ss:StyleID="TtlValGreen"><Data ss:Type="Number"><?php echo $totalCredit; ?></Data></Cell>
    <?php
    if (abs($outstanding) < 0.01) {
        $ttlBalStyle = 'TtlBalNil';
        $ttlBalStr   = '₹0.00 NIL';
    } elseif ($outstanding > 0) {
        $ttlBalStyle = 'TtlBalDue';
        $ttlBalStr   = '₹' . number_format($outstanding, 2) . ' ' . ($pType === 'client' ? 'DUE' : 'PAYABLE');
    } else {
        $ttlBalStyle = 'TtlBalAdv';
        $ttlBalStr   = '₹' . number_format(abs($outstanding), 2) . ' ADV';
    }
    ?>
    <Cell ss:StyleID="<?php echo $ttlBalStyle; ?>"><Data ss:Type="String"><?php echo $ttlBalStr; ?></Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>
