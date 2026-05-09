<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT p.*, 
    c.name as client_name, c.address as client_address, c.city as client_city, c.gstin as client_gstin, c.contact_person
    FROM proposals p
    JOIN partners c ON p.client_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal) die("Proposal not found.");

$items = $pdo->prepare("
    SELECT pi.*, s.site_code, s.name as site_name, s.location, s.city as site_city, s.area, s.state, s.type as site_type, s.width, s.height, s.latitude, s.longitude, s.light_type, s.sqft
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
");
$items->execute([$id]);
$items = $items->fetchAll();

$filename = "MediaPlan_" . str_replace(' ', '_', $proposal['campaign_name']) . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
    td { font-family: 'Arial', sans-serif; font-size: 10pt; }
    .header-main { background-color: #FFFF00; font-weight: bold; font-size: 12pt; text-align: center; border: 1px solid #000; }
    .header-col { background-color: #FFFF00; font-weight: bold; border: 1px solid #000; padding: 5px; text-align: center; }
    .cell { border: 1px solid #ccc; padding: 4px; }
    .cell-bold { font-weight: bold; }
    .terms { font-size: 9pt; color: #333; margin-top: 20px; }
</style>
</head>
<body>
<table>
    <!-- Main Header -->
    <tr>
        <th colspan="16" class="header-main">Proposal - <?php echo $proposal['campaign_name']; ?> (<?php echo date('d M Y', strtotime($proposal['start_date'])); ?> To <?php echo date('d M Y', strtotime($proposal['end_date'])); ?>)</th>
    </tr>
    <tr><td colspan="16"></td></tr>
    
    <!-- Company Info -->
    <tr>
        <td colspan="16" class="cell-bold">From : <?php echo COMPANY_NAME; ?></td>
    </tr>
    <tr>
        <td colspan="16">Address : <?php echo COMPANY_ADDRESS; ?>, <?php echo COMPANY_CITY; ?></td>
    </tr>
    <tr>
        <td colspan="16">Phone : <?php echo COMPANY_PHONE; ?></td>
    </tr>
    <tr>
        <td colspan="16">Email : <?php echo COMPANY_EMAIL; ?></td>
    </tr>
    
    <!-- Client Info -->
    <tr>
        <td colspan="16" class="cell-bold">To : <?php echo strtoupper($proposal['client_name']); ?></td>
    </tr>
    <tr>
        <td colspan="16">Contact Person : <?php echo $proposal['contact_person'] ?: 'Concerned Person'; ?></td>
    </tr>
    <tr><td colspan="16"></td></tr>

    <!-- Table Header -->
    <tr>
        <th class="header-col">Sr.</th>
        <th class="header-col">State</th>
        <th class="header-col">City</th>
        <th class="header-col">Area</th>
        <th class="header-col">Site name</th>
        <th class="header-col">Latitude</th>
        <th class="header-col">Longitude</th>
        <th class="header-col">Media Type</th>
        <th class="header-col">Qty.</th>
        <th class="header-col">Size (WxH)</th>
        <th class="header-col">Total Area(Sq Ft.)</th>
        <th class="header-col">Lit</th>
        <th class="header-col">From Date</th>
        <th class="header-col">To Date</th>
        <th class="header-col">Days</th>
        <th class="header-col">Monthly Rental</th>
    </tr>

    <!-- Data Rows -->
    <?php 
    $sn = 1; 
    foreach($items as $item): 
        $lit = 'Non Lit';
        if ($item['light_type'] == 'BL') $lit = 'Back Lit';
        if ($item['light_type'] == 'FL') $lit = 'Front Lit';
    ?>
    <tr>
        <td align="center" class="cell"><?php echo $sn++; ?></td>
        <td class="cell"><?php echo $item['state'] ?: 'West Bengal'; ?></td>
        <td class="cell"><?php echo $item['site_city']; ?></td>
        <td class="cell"><?php echo $item['area'] ?: $item['location']; ?></td>
        <td class="cell"><?php echo $item['site_name']; ?></td>
        <td class="cell"><?php echo $item['latitude'] ?: 'N/A'; ?></td>
        <td class="cell"><?php echo $item['longitude'] ?: 'N/A'; ?></td>
        <td class="cell"><?php echo $item['site_type']; ?></td>
        <td align="center" class="cell">1</td>
        <td align="center" class="cell"><?php echo (int)$item['width']; ?> X <?php echo (int)$item['height']; ?></td>
        <td align="center" class="cell"><?php echo (int)$item['sqft']; ?></td>
        <td align="center" class="cell"><?php echo $lit; ?></td>
        <td align="center" class="cell"><?php echo date('d M Y', strtotime($proposal['start_date'])); ?></td>
        <td align="center" class="cell"><?php echo date('d M Y', strtotime($proposal['end_date'])); ?></td>
        <td align="center" class="cell"><?php echo $item['days']; ?></td>
        <td align="right" class="cell"><?php echo number_format($item['sale_rate'], 2); ?></td>
    </tr>
    <?php endforeach; ?>

    <!-- Totals -->
    <tr>
        <td colspan="14"></td>
        <td align="right" class="cell-bold">Subtotal</td>
        <td align="right" class="cell-bold"><?php echo number_format($proposal['total_amount'], 2); ?></td>
    </tr>
    <tr>
        <td colspan="14"></td>
        <td align="right" class="cell-bold">GST (18%)</td>
        <td align="right" class="cell-bold"><?php echo number_format($proposal['tax_amount'], 2); ?></td>
    </tr>
    <tr>
        <td colspan="14"></td>
        <td align="right" class="cell-bold" style="font-size: 11pt;">Grand Total</td>
        <td align="right" class="cell-bold" style="font-size: 11pt; color: #D00;"><?php echo number_format($proposal['grand_total'], 2); ?></td>
    </tr>

    <tr><td colspan="16"></td></tr>

    <!-- Terms & Conditions -->
    <tr>
        <td colspan="16" class="cell-bold" style="text-decoration: underline;">Terms & Conditions:</td>
    </tr>
    <tr>
        <td colspan="16" class="terms">1) All media are subject to availability at the time of booking confirmation.</td>
    </tr>
    <tr>
        <td colspan="16" class="terms">2) The agency will provide a Proof of Execution Report and a Closure Report, including newspaper clippings or GPS-stamped images.</td>
    </tr>
    <tr>
        <td colspan="16" class="terms">3) Printing costs are subject to change based on the final material specifications and requirements.</td>
    </tr>
    <tr>
        <td colspan="16" class="terms">4) As per government regulations, all advertising/display content must include at least 60% regional language.</td>
    </tr>
    <tr>
        <td colspan="16" class="terms">5) Cancellations must be made with at least 7 days' prior notice via written email communication.</td>
    </tr>
    <tr>
        <td colspan="16" class="terms">6) The agency will not be held responsible for any loss or damage to the display caused by vandalism, theft, natural calamities, or any other events beyond our control.</td>
    </tr>
    <tr>
        <td colspan="16" class="terms">7) The display of offensive, obscene, or inappropriate content is strictly prohibited. This includes material that promotes hatred, discrimination, violence, or explicit content.</td>
    </tr>
    <tr>
        <td colspan="16" class="terms">10) The above estimate is valid for 7 days from the date of issuance.</td>
    </tr>
</table>
</body>
</html>
