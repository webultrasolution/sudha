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
        SELECT i.created_at as dated, b.customer_po_no as po_num, b.customer_po_date as po_date, 
               i.invoice_number as bill_no, i.total_amount as bill_amount
        FROM invoices i
        JOIN bookings b ON i.booking_id = b.id
        WHERE b.client_id = ? $dateFilterInv
        ORDER BY i.created_at ASC
    ");
    $stmtInv->execute($params);
    $entries = $stmtInv->fetchAll();
} else {
    $reportTitle = "Bills Payable";
    $stmtPO = $pdo->prepare("
        SELECT po_date as dated, po_number as po_num, po_date as po_date, 
               '' as bill_no, 
               COALESCE(NULLIF(total_amount, 0), po_amount + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0)) as bill_amount
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
            <td colspan="5" class="title">SUDHA CREATIVE</td>
        </tr>
        <tr>
            <td colspan="5" class="subtitle">MAHANANDA PALLY, MALDA - 732102</td>
        </tr>
        <tr>
            <td colspan="5" class="report-title"><?php echo $reportTitle; ?></td>
        </tr>
        <tr><td colspan="5"></td></tr>
        <tr>
            <td colspan="5" class="bold" style="font-family: Arial, sans-serif;">Account : <?php echo $partnerName; ?>.</td>
        </tr>
        <tr>
            <td colspan="5" class="bold" style="font-family: Arial, sans-serif;">From <?php echo $minDate; ?> to <?php echo $maxDate; ?></td>
        </tr>
        <tr><td colspan="5"></td></tr>
        <tr>
            <td class="table-header" style="width: 120px;">Dated</td>
            <td class="table-header" style="width: 200px;">PO Num</td>
            <td class="table-header" style="width: 120px;">PO Date</td>
            <td class="table-header" style="width: 180px;">Bill No.</td>
            <td class="table-header" style="width: 150px;">Bill Amount</td>
        </tr>
        <?php foreach ($entries as $row): 
            $totalBillAmount += $row['bill_amount'];
            $dated = $row['dated'] ? date('d-m-Y', strtotime($row['dated'])) : '';
            $po_date = $row['po_date'] ? date('d.m.Y', strtotime($row['po_date'])) : '';
        ?>
        <tr>
            <td class="table-cell"><?php echo $dated; ?></td>
            <td class="table-cell"><?php echo htmlspecialchars($row['po_num'] ?? ''); ?></td>
            <td class="table-cell"><?php echo $po_date; ?></td>
            <td class="table-cell"><?php echo htmlspecialchars($row['bill_no']); ?></td>
            <td class="table-cell-right"><?php echo number_format($row['bill_amount'], 2, '.', ''); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="3" style="border: none;"></td>
            <td class="total-row text-center" style="text-align: center;">Total</td>
            <td class="total-row table-cell-right"><?php echo number_format($totalBillAmount, 2, '.', ''); ?></td>
        </tr>
    </table>
</body>
</html>
