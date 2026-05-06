<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.name as client_name 
    FROM proposals p
    JOIN partners c ON p.client_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal) die("Proposal not found.");

$items = $pdo->prepare("
    SELECT pi.*, s.site_code, s.location, s.city as site_city, s.type as site_type, s.width, s.height
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
");
$items->execute([$id]);
$items = $items->fetchAll();

$filename = "Proposal_" . $proposal['proposal_number'] . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Proposal</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
</head>
<body>
<table border="1">
    <tr>
        <th colspan="7" style="background-color: #0d9488; color: white; font-size: 16pt;">PROPOSAL: <?php echo $proposal['proposal_number']; ?></th>
    </tr>
    <tr>
        <th colspan="7">Campaign: <?php echo $proposal['campaign_name']; ?> | Client: <?php echo $proposal['client_name']; ?></th>
    </tr>
    <tr>
        <th>S.No</th>
        <th>Site Code</th>
        <th>Media Type</th>
        <th>Location</th>
        <th>City</th>
        <th>Size</th>
        <th>Total Amount</th>
    </tr>
    <?php $sn=1; foreach($items as $item): ?>
    <tr>
        <td><?php echo $sn++; ?></td>
        <td><?php echo $item['site_code']; ?></td>
        <td><?php echo $item['site_type']; ?></td>
        <td><?php echo $item['location']; ?></td>
        <td><?php echo $item['site_city']; ?></td>
        <td><?php echo $item['width']; ?>' x <?php echo $item['height']; ?>'</td>
        <td align="right"><?php echo $item['amount']; ?></td>
    </tr>
    <?php endforeach; ?>
    <tr>
        <th colspan="6" align="right">Subtotal</th>
        <th align="right"><?php echo $proposal['total_amount']; ?></th>
    </tr>
    <tr>
        <th colspan="6" align="right">Tax (GST)</th>
        <th align="right"><?php echo $proposal['tax_amount']; ?></th>
    </tr>
    <tr>
        <th colspan="6" align="right" style="background-color: #f1f5f9;">Grand Total</th>
        <th align="right" style="background-color: #f1f5f9; font-weight: bold;"><?php echo $proposal['grand_total']; ?></th>
    </tr>
</table>
</body>
</html>
