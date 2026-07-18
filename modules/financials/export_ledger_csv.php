<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

requirePermission('financials', 'view');

$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
if (!$partner_id) die("Invalid Partner ID.");

$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();
if (!$partner) die("Partner not found.");

$pType       = $partner['type'];
$partnerName = $partner['name'];
$fromDate = $_GET['from_date'] ?? '';
$toDate   = $_GET['to_date']   ?? '';
$campaign = $_GET['campaign']  ?? '';

// ── Build ledger entries ────────────────────────────────────────────────────
$ledger = [];

if ($pType === 'client') {

    // Booking invoices
    $stmtInv = $pdo->prepare("
        SELECT i.id, 'Invoice' as entry_type, i.created_at as date,
               i.invoice_number as ref, 
               COALESCE(i.invoice_date, DATE(i.created_at)) as ref_date,
               '' as remark,
               i.sub_total as base_amt,
               (i.cgst + i.sgst + i.igst) as tax_amt,
               i.total_amount as total_amt,
               i.total_amount as debit, 0 as credit,
               CONVERT(b.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(b.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(b.customer_po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               b.customer_po_date as po_date
         FROM invoices i JOIN bookings b ON i.booking_id = b.id
         WHERE b.client_id = ? AND i.approval_status = 'approved'
    ");
    $stmtInv->execute([$partner_id]);
    foreach ($stmtInv->fetchAll() as $r) $ledger[] = $r;

    // Client printing rates
    $stmtPR = $pdo->prepare("
        SELECT MIN(r.id) as id, 'Printing Invoice' as entry_type,
               COALESCE(MIN(r.custom_invoice_date), DATE(MIN(r.created_at))) as date,
               COALESCE(MIN(r.custom_invoice_number), r.po_number, CONCAT('SCRP-',MIN(r.id))) as ref,
               COALESCE(MIN(r.custom_invoice_date), DATE(MIN(r.created_at))) as ref_date,
               '' as remark,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as base_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 0.18 as tax_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 1.18 as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 1.18 as debit,
               0 as credit,
               CONVERT(MAX(r.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(MAX(r.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(MAX(r.customer_po_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               MAX(r.customer_po_date) as po_date
        FROM client_printing_rates r LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.client_id = ? AND r.is_final_invoice = 1 AND r.approval_status = 'approved'
        GROUP BY COALESCE(r.po_number, r.id)
    ");
    $stmtPR->execute([$partner_id]);
    foreach ($stmtPR->fetchAll() as $r) $ledger[] = $r;

    // Client mounting rates
    $stmtMR = $pdo->prepare("
        SELECT MIN(m.id) as id, 'Mounting Invoice' as entry_type,
               COALESCE(MIN(m.custom_invoice_date), DATE(MIN(m.created_at))) as date,
               COALESCE(m.custom_invoice_number, m.po_number, CONCAT('CMI-',MIN(m.id))) as ref,
               COALESCE(MIN(m.custom_invoice_date), DATE(MIN(m.created_at))) as ref_date,
               '' as remark,
               SUM(m.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as base_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 0.18 as tax_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 1.18 as total_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 1.18 as debit,
               0 as credit,
               CONVERT(MAX(m.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(MAX(m.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(MAX(m.customer_po_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               MAX(m.customer_po_date) as po_date
        FROM client_mounting_rates m LEFT JOIN sites s ON m.site_id = s.id
        WHERE m.client_id = ? AND m.is_final_invoice = 1 AND m.approval_status = 'approved'
        GROUP BY COALESCE(m.po_number, m.id)
    ");
    $stmtMR->execute([$partner_id]);
    foreach ($stmtMR->fetchAll() as $r) $ledger[] = $r;

    // Payments received
    $stmtPay = $pdo->prepare("
        SELECT p.id, 'Payment Received' as entry_type, p.payment_date as date,
               COALESCE(NULLIF(p.transaction_id,''), CONCAT('PAY-',p.id)) as ref,
               '' as ref_date,
               COALESCE(p.notes,'') as remark,
               p.amount as base_amt, 0 as tax_amt, p.amount as total_amt,
               0 as debit, p.amount as credit,
               COALESCE(CONVERT(b.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(prop.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as campaign_name,
               COALESCE(CONVERT(b.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as brand_name,
               COALESCE(CONVERT(b.customer_po_no USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as po_number,
               b.customer_po_date as po_date
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN bookings b ON i.booking_id = b.id
        LEFT JOIN proposals prop ON p.proposal_id = prop.id
        WHERE p.partner_id = ? AND p.type = 'receivable' AND p.approval_status = 'approved'
    ");
    $stmtPay->execute([$partner_id]);
    foreach ($stmtPay->fetchAll() as $r) $ledger[] = $r;

} else {

    // Purchase orders
    $stmtPO = $pdo->prepare("
        SELECT id, 'Purchase Order' as entry_type, po_date as date, po_number as ref,
               po_date as ref_date,
               '' as remark,
               po_amount as base_amt,
               (COALESCE(cgst_amount,0)+COALESCE(sgst_amount,0)+COALESCE(igst_amount,0)) as tax_amt,
               COALESCE(NULLIF(total_amount,0), po_amount+COALESCE(cgst_amount,0)+COALESCE(sgst_amount,0)+COALESCE(igst_amount,0)) as total_amt,
               COALESCE(NULLIF(total_amount,0), po_amount+COALESCE(cgst_amount,0)+COALESCE(sgst_amount,0)+COALESCE(igst_amount,0)) as debit,
               0 as credit,
               CONVERT(campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               vendor_invoice_date as po_date
        FROM purchase_orders WHERE vendor_id = ?
    ");
    $stmtPO->execute([$partner_id]);
    foreach ($stmtPO->fetchAll() as $r) $ledger[] = $r;

    // Vendor printing rates
    $stmtVPR = $pdo->prepare("
        SELECT MIN(r.id) as id, 'Vendor Printing PO' as entry_type,
               DATE(MIN(r.created_at)) as date,
               COALESCE(r.po_number, CONCAT('VPO-',MIN(r.id))) as ref,
               DATE(MIN(r.created_at)) as ref_date,
               '' as remark,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as base_amt,
               0 as tax_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as debit,
               0 as credit,
               CONVERT(MAX(r.campaign_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as campaign_name,
               CONVERT(MAX(r.brand_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci as brand_name,
               CONVERT(MAX(r.vendor_invoice_no) USING utf8mb4) COLLATE utf8mb4_unicode_ci as po_number,
               MAX(r.vendor_invoice_date) as po_date
        FROM vendor_printing_rates r LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.vendor_id = ? AND (r.po_number IS NULL OR r.po_number NOT IN (SELECT po_number FROM purchase_orders WHERE po_number IS NOT NULL))
        GROUP BY COALESCE(r.po_number, r.id)
    ");
    $stmtVPR->execute([$partner_id]);
    foreach ($stmtVPR->fetchAll() as $r) $ledger[] = $r;

    // Payments made
    $stmtPay = $pdo->prepare("
        SELECT p.id, 'Payment Made' as entry_type, p.payment_date as date,
               COALESCE(NULLIF(p.transaction_id,''), CONCAT('PAY-',p.id)) as ref,
               '' as ref_date,
               COALESCE(p.notes,'') as remark,
               p.amount as base_amt, 0 as tax_amt, p.amount as total_amt,
               0 as debit, p.amount as credit,
               COALESCE(CONVERT(po.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.campaign_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as campaign_name,
               COALESCE(CONVERT(po.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.brand_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as brand_name,
               COALESCE(CONVERT(po.vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(vpr.vendor_invoice_no USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci) as po_number,
               COALESCE(po.vendor_invoice_date, vpr.vendor_invoice_date) as po_date
        FROM payments p
        LEFT JOIN purchase_orders po ON p.proposal_id = po.id
        LEFT JOIN vendor_printing_rates vpr ON p.proposal_id = vpr.id
        WHERE p.partner_id = ? AND p.type = 'payable' AND p.approval_status = 'approved'
    ");
    $stmtPay->execute([$partner_id]);
    foreach ($stmtPay->fetchAll() as $r) $ledger[] = $r;
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

// Compute totals
$totalDebit = $totalCredit = 0;
foreach ($ledger as $r) { $totalDebit += $r['debit']; $totalCredit += $r['credit']; }
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
 </Styles>
 <Worksheet ss:Name="Ledger">
  <Table>
   <Column ss:Width="35"/>
   <Column ss:Width="90"/>
   <Column ss:Width="120"/>
   <Column ss:Width="130"/>
   <Column ss:Width="90"/> <!-- Invoice Date / PO Date -->
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
    <Cell ss:MergeAcross="13" ss:StyleID="TitleMain"><Data ss:Type="String"><?php echo strtoupper(htmlspecialchars($partnerName)); ?> — Account Statement</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="13" ss:StyleID="SubTitle"><Data ss:Type="String"><?php 
        echo ucfirst($pType) . " | Period: " . ($fromDate ? date('d M Y', strtotime($fromDate)) : 'All Time') . 
             " to " . ($toDate ? date('d M Y', strtotime($toDate)) : date('d M Y')) . 
             " | Generated: " . date('d M Y, h:i A'); 
    ?></Data></Cell>
   </Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>

   <!-- Summary row -->
   <Row ss:Height="20">
    <Cell ss:MergeAcross="4" ss:StyleID="CellSummaryLabel"><Data ss:Type="String">Total Billed</Data></Cell>
    <Cell ss:MergeAcross="3" ss:StyleID="CellSummaryVal"><Data ss:Type="Number"><?php echo $totalDebit; ?></Data></Cell>
    <Cell ss:MergeAcross="1" ss:StyleID="CellSummaryLabel"><Data ss:Type="String"><?php echo $pType==='client'?'Total Received':'Total Paid'; ?></Data></Cell>
    <Cell ss:MergeAcross="2" ss:StyleID="CellSummaryVal"><Data ss:Type="Number"><?php echo $totalCredit; ?></Data></Cell>
   </Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>

   <!-- Column headers -->
   <Row ss:Height="22">
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">SL#</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Date</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String">Type</Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String"><?php echo ($pType === 'client') ? 'Invoice Number' : 'PO Number'; ?></Data></Cell>
    <Cell ss:StyleID="HeaderCol"><Data ss:Type="String"><?php echo ($pType === 'client') ? 'Invoice Date' : 'PO Date'; ?></Data></Cell>
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
       $runBalance += $row['debit'] - $row['credit'];
       $balLabel   = $runBalance > 0 ? ($pType==='client' ? 'DUE' : 'PAYABLE') : 'ADV';
       
       $cellStyle       = $isPayment ? 'CellPayment' : 'CellInvoice';
       $cellStyleCenter = $isPayment ? 'CellPaymentCenter' : 'CellInvoiceCenter';
       $cellStyleRight  = $isPayment ? 'CellPaymentRight' : 'CellInvoiceRight';
       
       $balClass = $runBalance > 0 ? 'CellBalDue' : 'CellBalAdv';
   ?>
   <Row>
    <Cell ss:StyleID="<?php echo $cellStyleCenter; ?>"><Data ss:Type="Number"><?php echo $sl++; ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php echo date('d M Y', strtotime($row['date'])); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($row['entry_type']); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyle; ?>"><Data ss:Type="String"><?php echo htmlspecialchars($row['ref'] ?? ''); ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $cellStyleCenter; ?>"><Data ss:Type="String"><?php echo (!empty($row['ref_date']) && $row['ref_date'] !== '0000-00-00') ? date('d M Y', strtotime($row['ref_date'])) : ''; ?></Data></Cell>
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
    <Cell ss:StyleID="<?php echo $isPayment ? 'CellPaymentRightGreen' : 'CellInvoiceRight'; ?>"><?php if ($isPayment) { ?><Data ss:Type="Number"><?php echo $row['total_amt']; ?></Data><?php } else { ?><Data ss:Type="String">—</Data><?php } ?></Cell>
    <Cell ss:StyleID="<?php echo $balClass; ?>"><Data ss:Type="String">₹<?php echo number_format(abs($runBalance),2); ?> <?php echo $balLabel; ?></Data></Cell>
   </Row>
   <?php endforeach; ?>

   <!-- Totals footer -->
   <Row ss:Height="22">
    <Cell ss:MergeAcross="8" ss:StyleID="TtlLabel"><Data ss:Type="String">Closing Balance</Data></Cell>
    <Cell ss:StyleID="TtlVal"><Data ss:Type="Number"><?php echo $totalDebit; ?></Data></Cell>
    <Cell ss:StyleID="TtlVal"><Data ss:Type="String">—</Data></Cell>
    <Cell ss:StyleID="TtlVal"><Data ss:Type="Number"><?php echo $totalDebit; ?></Data></Cell>
    <Cell ss:StyleID="TtlValGreen"><Data ss:Type="Number"><?php echo $totalCredit; ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $outstanding>0 ? 'TtlBalDue' : 'TtlBalAdv'; ?>"><Data ss:Type="String">₹<?php echo number_format(abs($outstanding),2); ?> <?php echo $outstanding>0?($pType==='client'?'DUE':'PAYABLE'):'ADV'; ?></Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>
