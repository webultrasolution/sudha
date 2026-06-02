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
$fromDate    = $_GET['from_date'] ?? '';
$toDate      = $_GET['to_date']   ?? '';

// ── Build ledger entries ────────────────────────────────────────────────────
$ledger = [];

if ($pType === 'client') {

    // Booking invoices
    $stmtInv = $pdo->prepare("
        SELECT i.id, 'Invoice' as entry_type, i.created_at as date,
               i.invoice_number as ref, '' as remark,
               i.sub_total as base_amt,
               (i.cgst + i.sgst + i.igst) as tax_amt,
               i.total_amount as total_amt,
               i.total_amount as debit, 0 as credit
        FROM invoices i JOIN bookings b ON i.booking_id = b.id
        WHERE b.client_id = ?
    ");
    $stmtInv->execute([$partner_id]);
    foreach ($stmtInv->fetchAll() as $r) $ledger[] = $r;

    // Client printing rates
    $stmtPR = $pdo->prepare("
        SELECT MIN(r.id) as id, 'Printing Invoice' as entry_type,
               DATE(MIN(r.created_at)) as date,
               COALESCE(r.po_number, CONCAT('CPPO-',MIN(r.id))) as ref, '' as remark,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as base_amt,
               0 as tax_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as debit,
               0 as credit
        FROM client_printing_rates r LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.client_id = ?
        GROUP BY COALESCE(r.po_number, r.id)
    ");
    $stmtPR->execute([$partner_id]);
    foreach ($stmtPR->fetchAll() as $r) $ledger[] = $r;

    // Client mounting rates
    $stmtMR = $pdo->prepare("
        SELECT MIN(m.id) as id, 'Mounting Invoice' as entry_type,
               DATE(MIN(m.created_at)) as date,
               COALESCE(m.custom_invoice_number, m.po_number, CONCAT('CMI-',MIN(m.id))) as ref, '' as remark,
               SUM(m.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as base_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 0.18 as tax_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 1.18 as total_amt,
               SUM(m.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) * 1.18 as debit,
               0 as credit
        FROM client_mounting_rates m LEFT JOIN sites s ON m.site_id = s.id
        WHERE m.client_id = ?
        GROUP BY COALESCE(m.po_number, m.id)
    ");
    $stmtMR->execute([$partner_id]);
    foreach ($stmtMR->fetchAll() as $r) $ledger[] = $r;

    // Payments received
    $stmtPay = $pdo->prepare("
        SELECT id, 'Payment Received' as entry_type, payment_date as date,
               COALESCE(NULLIF(transaction_id,''), CONCAT('PAY-',id)) as ref,
               COALESCE(notes,'') as remark,
               amount as base_amt, 0 as tax_amt, amount as total_amt,
               0 as debit, amount as credit
        FROM payments
        WHERE partner_id = ? AND type = 'receivable' AND approval_status = 'approved'
    ");
    $stmtPay->execute([$partner_id]);
    foreach ($stmtPay->fetchAll() as $r) $ledger[] = $r;

} else {

    // Purchase orders
    $stmtPO = $pdo->prepare("
        SELECT id, 'Purchase Order' as entry_type, po_date as date, po_number as ref, '' as remark,
               po_amount as base_amt,
               (COALESCE(cgst_amount,0)+COALESCE(sgst_amount,0)+COALESCE(igst_amount,0)) as tax_amt,
               COALESCE(NULLIF(total_amount,0), po_amount+COALESCE(cgst_amount,0)+COALESCE(sgst_amount,0)+COALESCE(igst_amount,0)) as total_amt,
               COALESCE(NULLIF(total_amount,0), po_amount+COALESCE(cgst_amount,0)+COALESCE(sgst_amount,0)+COALESCE(igst_amount,0)) as debit,
               0 as credit
        FROM purchase_orders WHERE vendor_id = ?
    ");
    $stmtPO->execute([$partner_id]);
    foreach ($stmtPO->fetchAll() as $r) $ledger[] = $r;

    // Vendor printing rates
    $stmtVPR = $pdo->prepare("
        SELECT MIN(r.id) as id, 'Vendor Printing PO' as entry_type,
               DATE(MIN(r.created_at)) as date,
               COALESCE(r.po_number, CONCAT('VPO-',MIN(r.id))) as ref, '' as remark,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as base_amt,
               0 as tax_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width,0) * COALESCE(s.height,0)) as debit,
               0 as credit
        FROM vendor_printing_rates r LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.vendor_id = ?
        GROUP BY COALESCE(r.po_number, r.id)
    ");
    $stmtVPR->execute([$partner_id]);
    foreach ($stmtVPR->fetchAll() as $r) $ledger[] = $r;

    // Payments made
    $stmtPay = $pdo->prepare("
        SELECT id, 'Payment Made' as entry_type, payment_date as date,
               COALESCE(NULLIF(transaction_id,''), CONCAT('PAY-',id)) as ref,
               COALESCE(notes,'') as remark,
               amount as base_amt, 0 as tax_amt, amount as total_amt,
               0 as debit, amount as credit
        FROM payments
        WHERE partner_id = ? AND type = 'payable' AND approval_status = 'approved'
    ");
    $stmtPay->execute([$partner_id]);
    foreach ($stmtPay->fetchAll() as $r) $ledger[] = $r;
}

// Sort by date
usort($ledger, fn($a,$b) => strtotime($a['date']) - strtotime($b['date']));

// Date filter
if ($fromDate || $toDate) {
    $ledger = array_filter($ledger, function($r) use ($fromDate, $toDate) {
        $d = strtotime($r['date']);
        if ($fromDate && $d < strtotime($fromDate)) return false;
        if ($toDate   && $d > strtotime($toDate))   return false;
        return true;
    });
}

// Compute totals
$totalDebit = $totalCredit = 0;
foreach ($ledger as $r) { $totalDebit += $r['debit']; $totalCredit += $r['credit']; }
$outstanding = $totalDebit - $totalCredit;

// ── Output Excel ────────────────────────────────────────────────────────────
$filename = preg_replace('/[^a-zA-Z0-9_-]/s','_', $partnerName) . '_Ledger_' . date('Ymd') . '.xls';
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="utf-8">
<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
<x:Name>Ledger</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
<style>
body, td { font-family: Arial, sans-serif; font-size: 11px; }
.hdr  { background:#17a589; color:#ffffff; font-weight:bold; text-align:center; border:1px solid #aaa; }
.cell { border:1px solid #ddd; }
.r    { border:1px solid #ddd; text-align:right; }
.bold { font-weight:bold; }
.ttl  { background:#fef9c3; font-weight:bold; text-align:right; border:1px solid #aaa; }
.inv  { background:#fff7ed; }
.pay  { background:#ecfdf5; }
.bal-due { color:#dc2626; font-weight:bold; }
.bal-adv { color:#059669; font-weight:bold; }
</style>
</head>
<body>
<table border="0" cellspacing="0" cellpadding="4" style="border-collapse:collapse; width:100%;">

    <!-- Header -->
    <tr><td colspan="10" style="font-size:16px; font-weight:bold; text-align:center; padding:10px;">
        <?php echo strtoupper(htmlspecialchars($partnerName)); ?> — Account Statement
    </td></tr>
    <tr><td colspan="10" style="text-align:center; color:#475569; font-size:12px;">
        <?php echo ucfirst($pType); ?> &nbsp;|&nbsp;
        Period: <?php echo $fromDate ? date('d M Y', strtotime($fromDate)) : 'All Time'; ?>
        to <?php echo $toDate ? date('d M Y', strtotime($toDate)) : date('d M Y'); ?>
        &nbsp;|&nbsp; Generated: <?php echo date('d M Y, h:i A'); ?>
    </td></tr>
    <tr><td colspan="10"></td></tr>

    <!-- Summary row -->
    <tr>
        <td colspan="3" class="bold" style="background:#f1f5f9; border:1px solid #ddd; padding:8px;">Total Billed</td>
        <td colspan="3" class="bold r" style="background:#f1f5f9; border:1px solid #ddd; padding:8px;">₹<?php echo number_format($totalDebit,2); ?></td>
        <td colspan="2" class="bold" style="background:#f1f5f9; border:1px solid #ddd; padding:8px;"><?php echo $pType==='client'?'Total Received':'Total Paid'; ?></td>
        <td colspan="2" class="bold r" style="background:#f1f5f9; border:1px solid #ddd; padding:8px;">₹<?php echo number_format($totalCredit,2); ?></td>
    </tr>
    <tr><td colspan="10"></td></tr>

    <!-- Column headers -->
    <tr>
        <td class="hdr" style="width:35px;">SL#</td>
        <td class="hdr" style="width:90px;">Date</td>
        <td class="hdr" style="width:120px;">Type</td>
        <td class="hdr" style="width:160px;">Reference</td>
        <td class="hdr" style="width:200px;">Remark / Notes</td>
        <td class="hdr" style="width:110px;">Base Amount</td>
        <td class="hdr" style="width:90px;">Tax (GST)</td>
        <td class="hdr" style="width:110px;">Grand Total</td>
        <td class="hdr" style="width:110px;"><?php echo $pType==='client'?'Received':'Paid Out'; ?></td>
        <td class="hdr" style="width:120px;">Running Balance</td>
    </tr>

    <?php
    $sl = 1; $runBalance = 0;
    foreach ($ledger as $row):
        $isPayment = stripos($row['entry_type'], 'Payment') !== false;
        $runBalance += $row['debit'] - $row['credit'];
        $balLabel   = $runBalance > 0 ? ($pType==='client' ? 'DUE' : 'PAYABLE') : 'ADV';
        $rowClass   = $isPayment ? 'pay' : 'inv';
        $balClass   = $runBalance > 0 ? 'bal-due' : 'bal-adv';
    ?>
    <tr class="<?php echo $rowClass; ?>">
        <td class="cell" style="text-align:center;"><?php echo $sl++; ?></td>
        <td class="cell"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
        <td class="cell bold"><?php echo htmlspecialchars($row['entry_type']); ?></td>
        <td class="cell"><?php echo htmlspecialchars($row['ref'] ?? ''); ?></td>
        <td class="cell" style="color:#0d9488; font-style:italic;"><?php echo htmlspecialchars($row['remark'] ?? ''); ?></td>
        <td class="r"><?php echo !$isPayment ? '₹'.number_format($row['base_amt'],2) : '—'; ?></td>
        <td class="r"><?php echo (!$isPayment && $row['tax_amt'] > 0) ? '₹'.number_format($row['tax_amt'],2) : '—'; ?></td>
        <td class="r bold"><?php echo !$isPayment ? '₹'.number_format($row['total_amt'],2) : '—'; ?></td>
        <td class="r" style="color:#059669;"><?php echo $isPayment ? '₹'.number_format($row['total_amt'],2) : '—'; ?></td>
        <td class="r <?php echo $balClass; ?>">₹<?php echo number_format(abs($runBalance),2); ?> <small><?php echo $balLabel; ?></small></td>
    </tr>
    <?php endforeach; ?>

    <!-- Totals footer -->
    <tr>
        <td colspan="5" class="ttl" style="text-align:right; font-size:12px;">Closing Balance</td>
        <td class="ttl">₹<?php echo number_format($totalDebit,2); ?></td>
        <td class="ttl">—</td>
        <td class="ttl">₹<?php echo number_format($totalDebit,2); ?></td>
        <td class="ttl" style="color:#059669;">₹<?php echo number_format($totalCredit,2); ?></td>
        <td class="ttl <?php echo $outstanding>0?'bal-due':'bal-adv'; ?>">
            ₹<?php echo number_format(abs($outstanding),2); ?> <?php echo $outstanding>0?($pType==='client'?'DUE':'PAYABLE'):'ADV'; ?>
        </td>
    </tr>

</table>
</body>
</html>
