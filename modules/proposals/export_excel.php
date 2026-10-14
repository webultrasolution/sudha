<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
checkAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'excel';

$stmt = $pdo->prepare("
    SELECT p.*, 
    c.name as client_name, c.address as client_address, c.city as client_city, c.gstin as client_gstin, c.contact_person, c.phone as client_phone, c.email as client_email
    FROM proposals p
    JOIN partners c ON p.client_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal) die("Proposal not found.");

$items = $pdo->prepare("
    SELECT pi.*, s.site_code, COALESCE(pi.custom_site_name, s.name) as site_name, COALESCE(pi.custom_location, s.location) as location, s.city as site_city, s.area, s.state, s.district, s.type as site_type, s.width, s.height, s.latitude, s.longitude, s.light_type, s.sqft
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
    ORDER BY pi.sort_order ASC, pi.id ASC
");
$items->execute([$id]);
$items = $items->fetchAll();

$company = resolveCompanyDetails();
$cleanCampaign = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $proposal['campaign_name'] ?: 'MediaPlan');

// If raw CSV format is requested
if ($format === 'csv') {
    $filename = "MediaPlan_" . $cleanCampaign . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $output = fopen("php://output", "w");
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    $proposalTitle = "PROPOSAL & MEDIA RATE SHEET - " . strtoupper($proposal['campaign_name']);
    fputcsv($output, [$proposalTitle]);

    $periodStr = (!empty($proposal['start_date']) && $proposal['start_date'] !== '0000-00-00') 
        ? (date('d M Y', strtotime($proposal['start_date'])) . ' To ' . date('d M Y', strtotime($proposal['end_date']))) 
        : "N/A";

    fputcsv($output, [
        "Proposal No: " . ($proposal['proposal_number'] ?? ('PR-' . $proposal['id'])),
        "Date: " . date('d-M-Y', strtotime($proposal['created_at'] ?? 'now')),
        "Campaign: " . $proposal['campaign_name'],
        "Campaign Period: " . $periodStr
    ]);
    fputcsv($output, []);

    fputcsv($output, ["AGENCY / SERVICE PROVIDER:", "", "", "", "", "", "CLIENT / ADVERTISER:"]);
    fputcsv($output, [
        "Company:", $company['name'], "", "", "", "",
        "Client Name:", strtoupper($proposal['client_name'])
    ]);
    fputcsv($output, [
        "Address:", str_replace(["\r\n", "\n", "\r"], ", ", $company['address']), "", "", "", "",
        "Address:", ($proposal['client_address'] ? str_replace(["\r\n", "\n", "\r"], ", ", $proposal['client_address']) : 'N/A') . ($proposal['client_city'] ? ', ' . $proposal['client_city'] : '')
    ]);
    fputcsv($output, [
        "Contact:", ($company['phone'] ?: '') . ($company['email'] ? ' | ' . $company['email'] : ''), "", "", "", "",
        "Contact Person:", ($proposal['contact_person'] ?: 'Concerned Person') . ($proposal['client_phone'] ? ' (' . $proposal['client_phone'] . ')' : '')
    ]);
    if (!empty($company['gstin']) || !empty($proposal['client_gstin'])) {
        fputcsv($output, [
            "GSTIN:", $company['gstin'] ?: 'N/A', "", "", "", "",
            "GSTIN:", $proposal['client_gstin'] ?: 'N/A'
        ]);
    }
    fputcsv($output, []);

    fputcsv($output, [
        "Sr.", "State", "District", "City", "Area / Location", "Site Name", "Latitude", "Longitude",
        "Media Type", "Qty.", "Size (WxH)", "Total Area (Sq Ft.)", "Lit", "From Date", "To Date", "Total Amount (Rs.)"
    ]);

    $sn = 1;
    $totalQty = 0;
    $totalSqft = 0;
    $totalAmt = 0;

    foreach ($items as $item) {
        $lit = 'Non Lit';
        $lt = strtoupper(trim($item['light_type'] ?? ''));
        if ($lt === 'BL' || strpos($lt, 'BACK') !== false) $lit = 'Back Lit';
        elseif ($lt === 'FL' || strpos($lt, 'FRONT') !== false || $lt === 'LIT') $lit = 'Front Lit';
        
        $startDate = (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00') 
            ? date('d M Y', strtotime($item['start_date'])) 
            : ((!empty($proposal['start_date']) && $proposal['start_date'] !== '0000-00-00') ? date('d M Y', strtotime($proposal['start_date'])) : 'N/A');
            
        $endDate = (!empty($item['end_date']) && $item['end_date'] !== '0000-00-00') 
            ? date('d M Y', strtotime($item['end_date'])) 
            : ((!empty($proposal['end_date']) && $proposal['end_date'] !== '0000-00-00') ? date('d M Y', strtotime($proposal['end_date'])) : 'N/A');

        $qty = intval($item['unit'] ?? 1);
        if ($qty <= 0) $qty = 1;

        $w = floatval($item['width'] ?? 0);
        $h = floatval($item['height'] ?? 0);
        $itemSqft = floatval($item['sqft'] ?? ($w * $h));
        $lineSqft = round($itemSqft * $qty);
        $amount = floatval($item['amount'] ?? 0);

        $totalQty += $qty;
        $totalSqft += $lineSqft;
        $totalAmt += $amount;

        fputcsv($output, [
            $sn++,
            $item['state'] ?: 'West Bengal',
            $item['district'] ?: ($item['site_city'] ?? ''),
            $item['site_city'] ?? '',
            $item['area'] ?: $item['location'],
            $item['site_name'],
            $item['latitude'] ?: '-',
            $item['longitude'] ?: '-',
            $item['site_type'],
            $qty,
            (intval($w) == $w ? intval($w) : $w) . ' X ' . (intval($h) == $h ? intval($h) : $h) . ' Ft',
            $lineSqft,
            $lit,
            $startDate,
            $endDate,
            number_format($amount, 2, '.', '')
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, [
        "TOTAL", "", "", "", "", "", "", "", "TOTAL UNITS:", $totalQty, "TOTAL AREA:", $totalSqft . " Sq.Ft", "", "", "Subtotal (Rs.)", number_format(floatval($proposal['total_amount'] ?? $totalAmt), 2, '.', '')
    ]);

    if (floatval($proposal['tax_amount'] ?? 0) > 0) {
        fputcsv($output, [
            "", "", "", "", "", "", "", "", "", "", "", "", "", "", "GST (18%)", number_format(floatval($proposal['tax_amount']), 2, '.', '')
        ]);
    }

    fputcsv($output, [
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "GRAND TOTAL (Rs.)", number_format(floatval($proposal['grand_total'] ?? ($proposal['total_amount'] ?? $totalAmt)), 2, '.', '')
    ]);

    fputcsv($output, []);
    fputcsv($output, ["TERMS & CONDITIONS:"]);
    fputcsv($output, ["1.", "All media sites are subject to availability at the time of final booking confirmation."]);
    fputcsv($output, ["2.", "The agency will provide Proof of Execution and Closure Reports with date/GPS-stamped inspection images."]);
    fputcsv($output, ["3.", "Printing & mounting costs are subject to change based on the final material specifications and site dimensions."]);
    fputcsv($output, ["4.", "As per regulations, advertising display content must comply with local municipal guidelines."]);
    fputcsv($output, ["5.", "Cancellations must be communicated with at least 7 days prior written notice via email."]);
    fputcsv($output, ["6.", "The agency will not be held responsible for display loss/damage caused by force majeure, severe weather, or natural calamities."]);
    fputcsv($output, ["7.", "Display of offensive, obscene, or prohibited content is strictly not allowed."]);
    fputcsv($output, ["8.", "The above rate estimate is valid for 7 days from the date of issuance."]);

    fclose($output);
    exit;
}

// Default: Rich Colorful Excel (.xls) Format
$filename = "MediaPlan_" . $cleanCampaign . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$periodStr = (!empty($proposal['start_date']) && $proposal['start_date'] !== '0000-00-00') 
    ? (date('d M Y', strtotime($proposal['start_date'])) . ' To ' . date('d M Y', strtotime($proposal['end_date']))) 
    : "N/A";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Media Plan</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    body, table { font-family: 'Segoe UI', Calibri, Arial, sans-serif; font-size: 11pt; color: #1e293b; }
    .main-title { background-color: #0f766e; color: #ffffff; font-size: 15pt; font-weight: bold; text-align: center; height: 42px; vertical-align: middle; border: 1px solid #0f766e; }
    .meta-bar { background-color: #ccfbf1; color: #0f766e; font-size: 10.5pt; font-weight: bold; height: 26px; vertical-align: middle; border-bottom: 2px solid #0d9488; padding: 4px 8px; }
    .card-header-agency { background-color: #1e293b; color: #ffffff; font-weight: bold; font-size: 11pt; padding: 6px; border: 1px solid #1e293b; }
    .card-header-client { background-color: #0284c7; color: #ffffff; font-weight: bold; font-size: 11pt; padding: 6px; border: 1px solid #0284c7; }
    .card-label { background-color: #f8fafc; font-weight: bold; color: #475569; width: 120px; border: 1px solid #e2e8f0; padding: 5px; }
    .card-val { background-color: #ffffff; color: #0f172a; border: 1px solid #e2e8f0; padding: 5px; }
    
    .th-header { background-color: #0d9488; color: #ffffff; font-weight: bold; text-align: center; height: 35px; vertical-align: middle; border: 1px solid #0f766e; padding: 6px; }
    
    .row-even { background-color: #ffffff; border: 1px solid #cbd5e1; }
    .row-odd { background-color: #f0fdfa; border: 1px solid #cbd5e1; }
    
    .cell-center { text-align: center; vertical-align: middle; border: 1px solid #cbd5e1; padding: 5px; }
    .cell-left { text-align: left; vertical-align: middle; border: 1px solid #cbd5e1; padding: 5px 8px; }
    .cell-right { text-align: right; vertical-align: middle; border: 1px solid #cbd5e1; padding: 5px 8px; mso-number-format: "\#\,\#\#0\.00"; }
    .cell-amount { text-align: right; vertical-align: middle; border: 1px solid #cbd5e1; font-weight: bold; color: #0f766e; padding: 5px 8px; mso-number-format: "\#\,\#\#0\.00"; }
    
    .badge-lit { background-color: #dcfce7; color: #166534; font-weight: bold; text-align: center; border: 1px solid #bbf7d0; padding: 5px; }
    .badge-nonlit { background-color: #f1f5f9; color: #475569; text-align: center; border: 1px solid #cbd5e1; padding: 5px; }
    
    .total-banner { background-color: #0f172a; color: #ffffff; font-weight: bold; font-size: 11pt; height: 32px; vertical-align: middle; border: 1px solid #0f172a; padding: 5px 8px; }
    .total-sub { background-color: #e2e8f0; color: #0f172a; font-weight: bold; height: 28px; vertical-align: middle; border: 1px solid #cbd5e1; padding: 5px 8px; }
    .total-grand { background-color: #dc2626; color: #ffffff; font-weight: bold; font-size: 12pt; height: 35px; vertical-align: middle; border: 1px solid #b91c1c; padding: 5px 8px; }
    
    .terms-head { background-color: #f8fafc; color: #334155; font-weight: bold; font-size: 11pt; border-top: 2px solid #94a3b8; padding: 6px; }
    .terms-item { color: #64748b; font-size: 9.5pt; padding: 3px 6px; }
</style>
</head>
<body>
<table border="0" cellpadding="4" cellspacing="0" style="border-collapse: collapse; width: 100%;">
    <!-- 1. Title Banner -->
    <tr>
        <th colspan="16" class="main-title">PROPOSAL &amp; MEDIA RATE SHEET &mdash; <?php echo htmlspecialchars(strtoupper($proposal['campaign_name'])); ?></th>
    </tr>
    <tr>
        <td colspan="4" class="meta-bar">PROPOSAL NO: <?php echo htmlspecialchars($proposal['proposal_number'] ?? ('PR-' . $proposal['id'])); ?></td>
        <td colspan="4" class="meta-bar">DATE: <?php echo date('d-M-Y', strtotime($proposal['created_at'] ?? 'now')); ?></td>
        <td colspan="4" class="meta-bar">CAMPAIGN: <?php echo htmlspecialchars($proposal['campaign_name']); ?></td>
        <td colspan="4" class="meta-bar">PERIOD: <?php echo htmlspecialchars($periodStr); ?></td>
    </tr>
    <tr><td colspan="16" style="height: 10px;"></td></tr>

    <!-- 2. Company & Client Cards -->
    <tr>
        <th colspan="8" class="card-header-agency">AGENCY / SERVICE PROVIDER</th>
        <th colspan="8" class="card-header-client">CLIENT / ADVERTISER</th>
    </tr>
    <tr>
        <td colspan="2" class="card-label">Company:</td>
        <td colspan="6" class="card-val"><?php echo htmlspecialchars($company['name']); ?></td>
        <td colspan="2" class="card-label">Client Name:</td>
        <td colspan="6" class="card-val" style="font-weight: bold; color: #0284c7;"><?php echo htmlspecialchars(strtoupper($proposal['client_name'])); ?></td>
    </tr>
    <tr>
        <td colspan="2" class="card-label">Address:</td>
        <td colspan="6" class="card-val"><?php echo htmlspecialchars(str_replace(["\r\n", "\n", "\r"], ", ", $company['address'])); ?></td>
        <td colspan="2" class="card-label">Address:</td>
        <td colspan="6" class="card-val"><?php echo htmlspecialchars(($proposal['client_address'] ? str_replace(["\r\n", "\n", "\r"], ", ", $proposal['client_address']) : 'N/A') . ($proposal['client_city'] ? ', ' . $proposal['client_city'] : '')); ?></td>
    </tr>
    <tr>
        <td colspan="2" class="card-label">Contact:</td>
        <td colspan="6" class="card-val"><?php echo htmlspecialchars(($company['phone'] ?: '') . ($company['email'] ? ' | ' . $company['email'] : '')); ?></td>
        <td colspan="2" class="card-label">Contact Person:</td>
        <td colspan="6" class="card-val"><?php echo htmlspecialchars(($proposal['contact_person'] ?: 'Concerned Person') . ($proposal['client_phone'] ? ' (' . $proposal['client_phone'] . ')' : '')); ?></td>
    </tr>
    <tr>
        <td colspan="2" class="card-label">GSTIN:</td>
        <td colspan="6" class="card-val"><?php echo htmlspecialchars($company['gstin'] ?: 'N/A'); ?></td>
        <td colspan="2" class="card-label">GSTIN:</td>
        <td colspan="6" class="card-val"><?php echo htmlspecialchars($proposal['client_gstin'] ?: 'N/A'); ?></td>
    </tr>
    <tr><td colspan="16" style="height: 15px;"></td></tr>

    <!-- 3. Table Header (Exact Same 16 Fields) -->
    <tr style="height: 35px;">
        <th class="th-header" style="width: 45px;">Sr.</th>
        <th class="th-header" style="width: 110px;">State</th>
        <th class="th-header" style="width: 120px;">District</th>
        <th class="th-header" style="width: 110px;">City</th>
        <th class="th-header" style="width: 180px;">Area / Location</th>
        <th class="th-header" style="width: 220px;">Site Name</th>
        <th class="th-header" style="width: 100px;">Latitude</th>
        <th class="th-header" style="width: 100px;">Longitude</th>
        <th class="th-header" style="width: 110px;">Media Type</th>
        <th class="th-header" style="width: 60px;">Qty.</th>
        <th class="th-header" style="width: 110px;">Size (WxH)</th>
        <th class="th-header" style="width: 120px;">Total Area (Sq Ft.)</th>
        <th class="th-header" style="width: 90px;">Lit</th>
        <th class="th-header" style="width: 100px;">From Date</th>
        <th class="th-header" style="width: 100px;">To Date</th>
        <th class="th-header" style="width: 130px;">Total Amount (Rs.)</th>
    </tr>

    <!-- 4. Data Rows -->
    <?php 
    $sn = 1;
    $totalQty = 0;
    $totalSqft = 0;
    $totalAmt = 0;

    foreach ($items as $item): 
        $rowClass = ($sn % 2 == 0) ? 'row-even' : 'row-odd';
        
        $lit = 'Non Lit';
        $isLit = false;
        $lt = strtoupper(trim($item['light_type'] ?? ''));
        if ($lt === 'BL' || strpos($lt, 'BACK') !== false) { $lit = 'Back Lit'; $isLit = true; }
        elseif ($lt === 'FL' || strpos($lt, 'FRONT') !== false || $lt === 'LIT') { $lit = 'Front Lit'; $isLit = true; }
        
        $startDate = (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00') 
            ? date('d M Y', strtotime($item['start_date'])) 
            : ((!empty($proposal['start_date']) && $proposal['start_date'] !== '0000-00-00') ? date('d M Y', strtotime($proposal['start_date'])) : 'N/A');
            
        $endDate = (!empty($item['end_date']) && $item['end_date'] !== '0000-00-00') 
            ? date('d M Y', strtotime($item['end_date'])) 
            : ((!empty($proposal['end_date']) && $proposal['end_date'] !== '0000-00-00') ? date('d M Y', strtotime($proposal['end_date'])) : 'N/A');

        $qty = intval($item['unit'] ?? 1);
        if ($qty <= 0) $qty = 1;

        $w = floatval($item['width'] ?? 0);
        $h = floatval($item['height'] ?? 0);
        $itemSqft = floatval($item['sqft'] ?? ($w * $h));
        $lineSqft = round($itemSqft * $qty);
        $amount = floatval($item['amount'] ?? 0);

        $totalQty += $qty;
        $totalSqft += $lineSqft;
        $totalAmt += $amount;
    ?>
    <tr class="<?php echo $rowClass; ?>">
        <td class="cell-center" style="font-weight: bold; color: #64748b;"><?php echo $sn++; ?></td>
        <td class="cell-left"><?php echo htmlspecialchars($item['state'] ?: 'West Bengal'); ?></td>
        <td class="cell-left"><?php echo htmlspecialchars($item['district'] ?: ($item['site_city'] ?? '')); ?></td>
        <td class="cell-left" style="font-weight: 600;"><?php echo htmlspecialchars($item['site_city'] ?? ''); ?></td>
        <td class="cell-left"><?php echo htmlspecialchars($item['area'] ?: $item['location']); ?></td>
        <td class="cell-left" style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($item['site_name']); ?></td>
        <td class="cell-center" style="color: #64748b;"><?php echo htmlspecialchars($item['latitude'] ?: '-'); ?></td>
        <td class="cell-center" style="color: #64748b;"><?php echo htmlspecialchars($item['longitude'] ?: '-'); ?></td>
        <td class="cell-center"><?php echo htmlspecialchars($item['site_type']); ?></td>
        <td class="cell-center" style="font-weight: bold;"><?php echo $qty; ?></td>
        <td class="cell-center" style="font-weight: 600;"><?php echo (intval($w) == $w ? intval($w) : $w) . ' X ' . (intval($h) == $h ? intval($h) : $h) . ' Ft'; ?></td>
        <td class="cell-center" style="font-weight: 600;"><?php echo $lineSqft; ?></td>
        <td class="<?php echo $isLit ? 'badge-lit' : 'badge-nonlit'; ?>"><?php echo $lit; ?></td>
        <td class="cell-center"><?php echo $startDate; ?></td>
        <td class="cell-center"><?php echo $endDate; ?></td>
        <td class="cell-amount"><?php echo number_format($amount, 2, '.', ''); ?></td>
    </tr>
    <?php endforeach; ?>

    <tr><td colspan="16" style="height: 10px;"></td></tr>

    <!-- 5. Totals & Summary -->
    <tr>
        <td colspan="8" class="total-banner" style="text-align: right; padding-right: 15px;">TOTAL SUMMARY:</td>
        <td class="total-banner" style="text-align: center;">UNITS:</td>
        <td class="total-banner" style="text-align: center; color: #5eead4;"><?php echo $totalQty; ?></td>
        <td class="total-banner" style="text-align: center;">TOTAL SQ.FT:</td>
        <td class="total-banner" style="text-align: center; color: #5eead4;"><?php echo number_format($totalSqft); ?></td>
        <td colspan="2" class="total-banner" style="text-align: right; padding-right: 10px;">Subtotal:</td>
        <td colspan="2" class="total-banner" style="text-align: right; color: #5eead4; padding-right: 10px; mso-number-format: '\#\,\#\#0\.00';"><?php echo number_format(floatval($proposal['total_amount'] ?? $totalAmt), 2, '.', ''); ?></td>
    </tr>

    <?php if (floatval($proposal['tax_amount'] ?? 0) > 0): ?>
    <tr>
        <td colspan="14" class="total-sub" style="text-align: right; padding-right: 10px;">GST / Tax (18%):</td>
        <td colspan="2" class="total-sub" style="text-align: right; padding-right: 10px; color: #0284c7; mso-number-format: '\#\,\#\#0\.00';"><?php echo number_format(floatval($proposal['tax_amount']), 2, '.', ''); ?></td>
    </tr>
    <?php endif; ?>

    <tr>
        <td colspan="14" class="total-grand" style="text-align: right; padding-right: 10px;">GRAND TOTAL (Rs.):</td>
        <td colspan="2" class="total-grand" style="text-align: right; padding-right: 10px; color: #ffffff; mso-number-format: '\#\,\#\#0\.00';"><?php echo number_format(floatval($proposal['grand_total'] ?? ($proposal['total_amount'] ?? $totalAmt)), 2, '.', ''); ?></td>
    </tr>

    <tr><td colspan="16" style="height: 20px;"></td></tr>

    <!-- 6. Terms & Conditions -->
    <tr>
        <th colspan="16" class="terms-head" style="text-align: left; padding: 8px;">TERMS &amp; CONDITIONS:</th>
    </tr>
    <tr><td colspan="16" class="terms-item">1. All media sites are subject to availability at the time of final booking confirmation.</td></tr>
    <tr><td colspan="16" class="terms-item">2. The agency will provide Proof of Execution and Closure Reports with date/GPS-stamped inspection images.</td></tr>
    <tr><td colspan="16" class="terms-item">3. Printing &amp; mounting costs are subject to change based on the final material specifications and site dimensions.</td></tr>
    <tr><td colspan="16" class="terms-item">4. As per regulations, advertising display content must comply with local municipal guidelines.</td></tr>
    <tr><td colspan="16" class="terms-item">5. Cancellations must be communicated with at least 7 days prior written notice via email.</td></tr>
    <tr><td colspan="16" class="terms-item">6. The agency will not be held responsible for display loss/damage caused by force majeure, severe weather, or natural calamities.</td></tr>
    <tr><td colspan="16" class="terms-item">7. Display of offensive, obscene, or prohibited content is strictly not allowed.</td></tr>
    <tr><td colspan="16" class="terms-item">8. The above rate estimate is valid for 7 days from the date of issuance.</td></tr>
</table>
</body>
</html>
