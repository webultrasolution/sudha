<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;

if (!$partner_id) {
    die("Invalid Partner ID.");
}

// Fetch Partner Info
$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();

if (!$partner) {
    die("Partner not found.");
}

$pType = $partner['type']; // 'client' or 'vendor'
$partnerName = htmlspecialchars($partner['name']); 

$entries = [];
$totalBillAmount = 0;

$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';

$dateFilterInv = "";
$dateFilterPO = "";
$params = [$partner_id];

if ($fromDate) {
    $dateFilterInv .= " AND DATE(i.created_at) >= ? ";
    $dateFilterPO .= " AND DATE(po_date) >= ? ";
    $params[] = $fromDate;
}
if ($toDate) {
    $dateFilterInv .= " AND DATE(i.created_at) <= ? ";
    $dateFilterPO .= " AND DATE(po_date) <= ? ";
    $params[] = $toDate;
}

if ($pType == 'client') {
    $reportTitle = "Bills Receivable";
    $stmtInv = $pdo->prepare("
        SELECT i.created_at as dated, 
               COALESCE(NULLIF(b.customer_po_no, ''), NULLIF(p.proposal_number, ''), NULLIF(b.external_po, '')) as po_num, 
               COALESCE(NULLIF(b.customer_po_date, '0000-00-00'), DATE(p.created_at), DATE(b.created_at)) as po_date, 
               i.invoice_number as bill_no, i.total_amount as bill_amount, 
               COALESCE(NULLIF(b.campaign_name, ''), NULLIF(p.campaign_name, ''), NULLIF(b.brand_name, ''), 'General Campaign') as campaign_name
        FROM invoices i
        JOIN bookings b ON i.booking_id = b.id
        LEFT JOIN proposals p ON b.proposal_id = p.id
        WHERE b.client_id = ? $dateFilterInv
        ORDER BY i.created_at ASC
    ");
    $stmtInv->execute($params);
    $entries = $stmtInv->fetchAll();
} else {
    $reportTitle = "Bills Payable";
    $stmtPO = $pdo->prepare("
        SELECT po_date as dated, po_number as po_num, po_date as po_date, 
               vendor_invoice_no as bill_no, vendor_invoice_date as bill_date,
               COALESCE(NULLIF(total_amount, 0), po_amount + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0)) as bill_amount,
               campaign_name
        FROM purchase_orders 
        WHERE vendor_id = ? $dateFilterPO
        ORDER BY po_date ASC
    ");
    $stmtPO->execute($params);
    $entries = $stmtPO->fetchAll();
}

$minDate = $fromDate ? date('d-m-Y', strtotime($fromDate)) : '';
$maxDate = $toDate ? date('d-m-Y', strtotime($toDate)) : '';
if (count($entries) > 0) {
    if (!$minDate) $minDate = date('d-m-Y', strtotime($entries[0]['dated']));
    if (!$maxDate) $maxDate = date('d-m-Y', strtotime($entries[count($entries)-1]['dated']));
}

$filename = str_replace(" ", "_", $reportTitle) . "_" . preg_replace('/[^a-zA-Z0-9_ -]/s', '', $partner['name']) . "_" . date('Ymd') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta charset="utf-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Statement</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .title { font-size: 20px; font-weight: bold; text-align: center; font-family: Arial, sans-serif; }
        .subtitle { font-size: 14px; text-align: center; font-family: Arial, sans-serif; }
        .report-title { font-size: 16px; font-weight: bold; text-align: center; font-family: Arial, sans-serif; }
        .table-header { background-color: #ffff00; font-weight: bold; text-align: center; border: 1px solid #000000; font-family: Arial, sans-serif; }
        .table-cell { border: 1px solid #000000; text-align: center; font-family: Arial, sans-serif; }
        .table-cell-right { border: 1px solid #000000; text-align: right; font-family: Arial, sans-serif; }
        .total-row { background-color: #ffff00; font-weight: bold; text-align: right; border: 1px solid #000000; font-family: Arial, sans-serif; }
    </style>
</head>
<body>
    <table style="border-collapse: collapse; width: 100%;">
        <tr>
            <td colspan="7" class="title"><?php echo mb_strtoupper($partnerName); ?></td>
        </tr>
        <tr>
            <td colspan="7" class="subtitle"><?php echo htmlspecialchars($partner['address'] ?? ''); ?></td>
        </tr>
        <tr>
            <td colspan="7" class="report-title"><?php echo $reportTitle; ?></td>
        </tr>
        <tr><td colspan="7"></td></tr>
        <tr>
            <td colspan="7" class="bold" style="font-family: Arial, sans-serif;">Account : <?php echo $partnerName; ?></td>
        </tr>
        <tr>
            <td colspan="7" class="bold" style="font-family: Arial, sans-serif;">From <?php echo $minDate; ?> to <?php echo $maxDate; ?></td>
        </tr>
        <tr><td colspan="7"></td></tr>
        <tr>
            <td class="table-header" style="width: 80px;">SL No.</td>
            <td class="table-header" style="width: 200px;">PO Num</td>
            <td class="table-header" style="width: 120px;">PO Date</td>
            <td class="table-header" style="width: 180px;">Bill No.</td>
            <td class="table-header" style="width: 120px;">Bill Date</td>
            <td class="table-header" style="width: 150px;">Bill Amount</td>
            <td class="table-header" style="width: 200px;">Campaign</td>
        </tr>
        <?php 
        $sl_no = 1;
        foreach ($entries as $row): 
            $totalBillAmount += $row['bill_amount'];
            $po_date = $row['po_date'] ? date('d.m.Y', strtotime($row['po_date'])) : '';
            $raw_bill_date = !empty($row['bill_date']) ? $row['bill_date'] : $row['dated'];
            $bill_date = $raw_bill_date ? date('d-m-Y', strtotime($raw_bill_date)) : '';
        ?>
        <tr>
            <td class="table-cell"><?php echo $sl_no++; ?></td>
            <td class="table-cell"><?php echo htmlspecialchars($row['po_num'] ?? ''); ?></td>
            <td class="table-cell"><?php echo $po_date; ?></td>
            <td class="table-cell"><?php echo htmlspecialchars($row['bill_no'] ?? ''); ?></td>
            <td class="table-cell"><?php echo $bill_date; ?></td>
            <td class="table-cell-right"><?php echo number_format($row['bill_amount'], 2, '.', ''); ?></td>
            <td class="table-cell"><?php echo htmlspecialchars($row['campaign_name'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="4" style="border: none;"></td>
            <td class="total-row text-center" style="text-align: center;">Total</td>
            <td class="total-row table-cell-right"><?php echo number_format($totalBillAmount, 2, '.', ''); ?></td>
            <td style="border: none;"></td>
        </tr>
    </table>
</body>
</html>
