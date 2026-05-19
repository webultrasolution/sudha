<?php
// Disable deprecation errors
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Prevent timezone warnings
date_default_timezone_set('Asia/Kolkata');

$po_id = isset($_GET['po_id']) ? intval($_GET['po_id']) : 0;
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$mode = $_GET['mode'] ?? '';

// 1. If PO ID is provided, fetch everything from DB (Works for Direct POs saved to DB)
if ($po_id) {
    $stmtB = $pdo->prepare("
        SELECT po.*, c.name as client_name, po.campaign_name as campaign_name, po.po_number as proposal_number
        FROM purchase_orders po
        LEFT JOIN partners c ON po.customer_id = c.id
        WHERE po.id = ?
    ");
    $stmtB->execute([$po_id]);
    $b = $stmtB->fetch();
    
    if (!$b) die("Purchase Order not found.");
    $vendor_id = $b['vendor_id'];
    $mode = 'saved_po'; // Internal mode for logic below

    // Pull overall date range from po_items as fallback for $b
    $stmtDates = $pdo->prepare("SELECT MIN(start_date) as min_start, MAX(end_date) as max_end FROM po_items WHERE po_id = ?");
    $stmtDates->execute([$po_id]);
    $dateRange = $stmtDates->fetch();
    if (!empty($dateRange['min_start'])) {
        $b['start_date'] = $dateRange['min_start'];
        $b['end_date']   = $dateRange['max_end'];
    } else {
        $b['start_date'] = date('Y-m-d');
        $b['end_date']   = date('Y-m-d', strtotime('+1 month'));
    }
}

if (!$vendor_id && $mode !== 'saved_po') {
    die("Invalid request: Vendor ID is required.");
}

// Fetch Vendor Info (Always required)
$stmtV = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$stmtV->execute([$vendor_id]);
$v = $stmtV->fetch();
if (!$v) die("Vendor not found.");

$vendor_gst_filter = $_GET['vendor_gst'] ?? '';

if ($mode === 'direct') {
    // Standalone mode: uses provided data instead of DB records
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    $client_name = 'Direct Client';
    if ($client_id) {
        $stmtC = $pdo->prepare("SELECT name FROM partners WHERE id = ?");
        $stmtC->execute([$client_id]);
        $client_name = $stmtC->fetchColumn() ?: 'Direct Client';
    }

    $b = [
        'campaign_name' => $_GET['campaign_name'] ?? 'Direct Campaign',
        'client_name' => $client_name,
        'remark' => $_GET['remark'] ?? '',
        'start_date' => $_GET['start_date'] ?? date('Y-m-d'),
        'end_date' => $_GET['end_date'] ?? date('Y-m-d', strtotime('+1 month')),
        'proposal_number' => 'DPO-' . date('ymd') . '-' . rand(10, 99)
    ];
    
    $site_ids = $_GET['site_ids'] ?? [];
    if (empty($site_ids)) die("No sites selected.");
    
    $site_list = implode(',', array_map('intval', $site_ids));
    $itemSql = "
        SELECT s.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.light_type, s.hsn_code, s.vendor_gst, s.type as media_type,
               s.purchase_rate as purchase_amount, ? as start_date, ? as end_date
        FROM sites s
        WHERE s.id IN ($site_list) AND s.vendor_id = ?
    ";
    $itemParams = [$b['start_date'], $b['end_date'], $vendor_id];

    // Get custom rates if any
    $custom_rates = $_GET['rates'] ?? [];
} else if ($mode === 'saved_po') {
    // Fetch Items from po_items
    $itemSql = "
        SELECT pi.*, pi.cost as purchase_amount, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.light_type, s.hsn_code, s.vendor_gst, s.type as media_type
        FROM po_items pi
        JOIN sites s ON pi.site_id = s.id
        WHERE pi.po_id = ?
    ";
    $itemParams = [$po_id];
} else {
    // Normal mode: Fetch from Booking or Proposal
    $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
    $proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;
    
    if ($booking_id) {
        $stmtB = $pdo->prepare("
            SELECT b.*, c.name as client_name, p.campaign_name, p.proposal_number, p.id as prop_id
            FROM bookings b
            JOIN partners c ON b.client_id = c.id
            LEFT JOIN proposals p ON b.proposal_id = p.id
            WHERE b.id = ?
        ");
        $stmtB->execute([$booking_id]);
        $b = $stmtB->fetch();
        $proposal_id = $b['prop_id'];
    } else {
        $stmtB = $pdo->prepare("
            SELECT p.*, c.name as client_name, p.proposal_number as prop_no, p.proposal_number
            FROM proposals p
            JOIN partners c ON p.client_id = c.id
            WHERE p.id = ?
        ");
        $stmtB->execute([$proposal_id]);
        $b = $stmtB->fetch();
        // Normalize fields for PO display
        $b['campaign_name'] = $b['campaign_name'] ?? 'General Campaign';
    }

    if (!$b) die("Data not found for the given IDs.");

    // Fetch Items (from proposal_items if booking doesn't exist, or from booking_items)
    $vendor_gst_filter = $_GET['vendor_gst'] ?? '';

    if ($booking_id) {
        $itemSql = "
            SELECT bi.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.light_type, s.hsn_code, s.vendor_gst, s.type as media_type
            FROM booking_items bi
            JOIN sites s ON bi.site_id = s.id
            WHERE bi.booking_id = ? AND s.vendor_id = ?
        ";
        $itemParams = [$booking_id, $vendor_id];
    } else {
        $itemSql = "
            SELECT pi.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.light_type, s.hsn_code, s.vendor_gst, s.type as media_type,
                pi.purchase_rate as purchase_amount, ? as start_date, ? as end_date
            FROM proposal_items pi
            JOIN sites s ON pi.site_id = s.id
            WHERE pi.proposal_id = ? AND s.vendor_id = ?
        ";
        $itemParams = [$b['start_date'], $b['end_date'], $proposal_id, $vendor_id];
    }
}

if ($vendor_gst_filter !== '') {
    $itemSql .= " AND (s.vendor_gst = ? OR s.vendor_gst IS NULL AND ? = '')";
    $itemParams[] = $vendor_gst_filter;
    $itemParams[] = $vendor_gst_filter;
}

$stmtItems = $pdo->prepare($itemSql);
$stmtItems->execute($itemParams);
$items = $stmtItems->fetchAll();

// Safe PO Numbering
$po_id = !empty($b['id']) ? $b['id'] : 0;
$po_ref = ($po_id > 0) ? str_pad((string)$po_id, 3, '0', STR_PAD_LEFT) : ($b['proposal_number'] ?? 'DPO-' . date('His'));
$ref_date = $b['po_date'] ?? $b['start_date'] ?? date('Y-m-d');
$po_number = "PO/" . date('y', strtotime($ref_date)) . "-" . date('y', strtotime($ref_date . ' +1 year')) . "/" . $po_ref;
$po_date = date('d-M-Y', strtotime($ref_date));

// Company Settings
$company_name = getSetting('company_name', 'Sudha Creative & Advertising');
$company_gstin = getSetting('company_gstin', '19AHRPT4740Q1Z6'); 
$company_pan = getSetting('company_pan', 'AHRPT4740Q');
$company_address = getSetting('company_address', 'Deshbandhu Para, P.O - Jhaljhalia, Dist - Malda - 732102, West Bengal');
$company_phone = getSetting('company_phone', '8158854313');
$company_email = getSetting('company_email', 'sudhacreativemalda@gmail.com');
$company_letterhead = getSetting('company_letterhead');
$company_signature = getSetting('company_signature', 'signature.png');

// Dynamic Category of Service Calculation
$serviceCategory = 'Display on Hoardings / Billboards';
if (!empty($items)) {
    $firstType = strtolower($items[0]['media_type'] ?? '');
    if (strpos($firstType, 'hoarding') !== false) {
        $serviceCategory = 'Display on Hoardings';
    } elseif (strpos($firstType, 'led') !== false) {
        $serviceCategory = 'Display on LED Screens';
    } elseif (strpos($firstType, 'kiosk') !== false) {
        $serviceCategory = 'Display on Kiosks';
    } elseif (strpos($firstType, 'gantry') !== false) {
        $serviceCategory = 'Display on Gantries';
    } elseif (!empty($items[0]['media_type'])) {
        $serviceCategory = 'Display on ' . ucwords($items[0]['media_type']) . 's';
    }
}

function getStateName($gstin) {
    $code = substr(trim($gstin ?? ''), 0, 2);
    $states = [
        '01' => 'JAMMU AND KASHMIR', '02' => 'HIMACHAL PRADESH', '03' => 'PUNJAB',
        '04' => 'CHANDIGARH', '05' => 'UTTARAKHAND', '06' => 'HARYANA',
        '07' => 'DELHI', '08' => 'RAJASTHAN', '09' => 'UTTAR PRADESH',
        '10' => 'BIHAR', '11' => 'SIKKIM', '12' => 'ARUNACHAL PRADESH',
        '13' => 'NAGALAND', '14' => 'MANIPUR', '15' => 'MIZORAM',
        '16' => 'TRIPURA', '17' => 'MEGHALAYA', '18' => 'ASSAM',
        '19' => 'WEST BENGAL', '20' => 'JHARKHAND', '21' => 'ODISHA',
        '22' => 'CHHATTISGARH', '23' => 'MADHYA PRADESH', '24' => 'GUJARAT',
        '26' => 'DADRA AND NAGAR HAVELI AND DAMAN AND DIU', '27' => 'MAHARASHTRA',
        '28' => 'ANDHRA PRADESH (BEFORE DIVISION)', '29' => 'KARNATAKA',
        '30' => 'GOA', '31' => 'LAKSHADWEEP', '32' => 'KERALA',
        '33' => 'TAMIL NADU', '34' => 'PUDUCHERRY', '35' => 'ANDAMAN AND NICOBAR ISLANDS',
        '36' => 'TELANGANA', '37' => 'ANDHRA PRADESH'
    ];
    return $states[$code] ?? 'WEST BENGAL';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - <?php echo $po_number; ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; color: #000; font-size: 11px; line-height: 1.3; }
        .po-wrapper { border: 1px solid #000; max-width: 800px; margin: 0 auto; position: relative; }
        
        .header-top { border-bottom: 1px solid #000; padding: 5px 10px; }
        .header-top p { margin: 0; }
        
        .main-info { display: flex; border-bottom: 1px solid #000; }
        .info-col { flex: 1; padding: 10px; }
        .info-col:first-child { border-right: 1px solid #000; }
        
        .info-row { display: flex; margin-bottom: 3px; }
        .info-label { width: 90px; font-weight: normal; }
        .info-sep { width: 15px; }
        .info-value { flex: 1; font-weight: normal; }
        
        .section-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; font-style: italic; }
        .table-title { background: #f0f0f0; border-bottom: 1px solid #000; text-align: center; font-weight: bold; padding: 4px; letter-spacing: 2px; text-transform: uppercase; }
        
        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 6px; text-align: center; font-weight: bold; background: #fafafa; }
        th:last-child { border-right: none; }
        td { border-bottom: 1px solid #d0d0d0; border-right: 1px solid #000; padding: 8px 5px; vertical-align: top; text-align: center; }
        td:last-child { border-right: none; }
        
        .totals-row td { border-bottom: none; border-top: 1px solid #000; font-weight: bold; }
        .footer { display: flex; border-top: 1px solid #000; }
        .footer-left { flex: 2; padding: 10px; border-right: 1px solid #000; min-height: 120px; }
        .footer-right { flex: 1; padding: 10px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; }
        
        .btn-print { position: fixed; bottom: 30px; right: 30px; background: #000; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        @media print { .btn-print { display: none; } body { padding: 0; } .po-wrapper { border: none; width: 100%; } }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">PRINT PURCHASE ORDER</button>

<div class="po-wrapper">
    <!-- Header -->
    <?php if ($company_letterhead): ?>
        <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_letterhead; ?>" style="width: 100%; height: auto; display: block; border-bottom: 1px solid #000;">
    <?php else: ?>
        <div class="header-top" style="text-align: center;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo $company_name; ?></h2>
            <p><?php echo $company_address; ?></p>
            <p>Ph: <?php echo $company_phone; ?> Email: <?php echo $company_email; ?></p>
        </div>
    <?php endif; ?>

    <!-- PO Info -->
    <table style="width: 100%; border: 1px solid #000; border-collapse: collapse; margin-bottom: 15px; font-size: 10px;">
        <tr>
            <td style="width: 50%; border-right: 1px solid #000; padding: 8px 10px; text-align: left; vertical-align: top; line-height: 1.45;">
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Category of Service</span><span style="width: 10px;">:</span><span style="flex: 1;"><?php echo htmlspecialchars($serviceCategory); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Campaign</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($b['campaign_name'] ?? ''); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Purchase Order No.</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; color: #1e293b;"><?php echo htmlspecialchars($po_number); ?></span></div>
                <div style="display: flex; margin-bottom: 6px;"><span style="width: 150px; font-weight: bold;">Purchase Order Date</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold;"><?php echo htmlspecialchars($po_date); ?></span></div>
                
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Supplier Name</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($v['name']); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Supplier Address</span><span style="width: 10px;">:</span><span style="flex: 1; font-size: 9.5px;"><?php echo htmlspecialchars($v['address']); ?></span></div>
                
                <?php 
                $supplierGstin = trim($vendor_gst_filter ?: $v['gstin'] ?: '');
                $supplierPan = strlen($supplierGstin) >= 12 ? substr($supplierGstin, 2, 10) : '';
                $supplierStateCode = strlen($supplierGstin) >= 2 ? substr($supplierGstin, 0, 2) : '19';
                ?>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Supplier Pan No</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($supplierPan ?: 'N/A'); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Supplier GSTIN No.</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($supplierGstin ?: 'N/A'); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Supplier State Code</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold;"><?php echo htmlspecialchars($supplierStateCode); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Supplier MSME Registration</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo (strlen($supplierGstin) >= 15) ? 'REGISTERED' : 'UNREGISTERED'; ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Supplier State Name</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo getStateName($supplierGstin); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Place Of Supply</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo getStateName($company_gstin); ?></span></div>
            </td>
            
            <td style="width: 50%; padding: 8px 10px; text-align: left; vertical-align: top; line-height: 1.45;">
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Buyer's Name</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($company_name); ?></span></div>
                <div style="display: flex; margin-bottom: 6px;"><span style="width: 150px; font-weight: bold;">Buyer's Address</span><span style="width: 10px;">:</span><span style="flex: 1; font-size: 9.5px;"><?php echo htmlspecialchars($company_address); ?></span></div>
                
                <?php 
                $buyerGstin = trim($company_gstin);
                $buyerPan = trim($company_pan);
                $buyerStateCode = substr($buyerGstin, 0, 2);
                ?>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Buyer Pan No.</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($buyerPan); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Buyer GSTIN No.</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($buyerGstin); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Buyer MSME Registration</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo (strlen($buyerGstin) >= 15) ? 'REGISTERED' : 'UNREGISTERED'; ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Buyer State Code</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold;"><?php echo htmlspecialchars($buyerStateCode); ?></span></div>
                <div style="display: flex; margin-bottom: 2px;"><span style="width: 150px; font-weight: bold;">Buyer State Name</span><span style="width: 10px;">:</span><span style="flex: 1; font-weight: bold; text-transform: uppercase;"><?php echo getStateName($company_gstin); ?></span></div>
            </td>
        </tr>
    </table>

    <div class="table-title">Purchase Order Details:</div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 35px;">Sl No</th>
                <th rowspan="2" style="width: 70px;">HSN / SAC<br>CODE</th>
                <th rowspan="2" style="width: 90px;">City</th>
                <th rowspan="2">Location</th>
                <th rowspan="2" style="width: 50px;">Size (W)</th>
                <th rowspan="2" style="width: 50px;">Size (H)</th>
                <th rowspan="2" style="width: 60px;">Lit /<br>Non Lit</th>
                <th rowspan="2" style="width: 90px;">Display<br>Charges Per<br>Month</th>
                <th colspan="2" style="width: 150px; border-bottom: 1px solid #000;">Charged Period</th>
                <th rowspan="2" style="width: 70px;">Period</th>
                <th rowspan="2" style="width: 95px;">Amount</th>
            </tr>
            <tr>
                <th style="font-size: 8px; padding: 2px; width: 75px; border-right: 1px solid #000;">From</th>
                <th style="font-size: 8px; padding: 2px; width: 75px;">To</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $net_total = 0;
            foreach ($items as $idx => $item): 
                // Normalize purchase amount
                $item['purchase_amount'] = floatval($item['purchase_amount'] ?? 0);

                // Override with custom rate if in direct mode
                if ($mode === 'direct' && isset($custom_rates[$item['id']])) {
                    $item['purchase_amount'] = floatval($custom_rates[$item['id']]);
                }
                
                $net_total += $item['purchase_amount'];
                // Use item-level dates first; fall back to PO-level dates; final fallback = today
                $sDate = (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00')
                            ? $item['start_date']
                            : ((!empty($b['start_date']) && $b['start_date'] !== '0000-00-00') ? $b['start_date'] : date('Y-m-d'));
                $eDate = (!empty($item['end_date']) && $item['end_date'] !== '0000-00-00')
                            ? $item['end_date']
                            : ((!empty($b['end_date']) && $b['end_date'] !== '0000-00-00') ? $b['end_date'] : date('Y-m-d', strtotime('+30 days')));

                // Calculate duration and monthly display charges
                $date1 = date_create($sDate);
                $date2 = date_create($eDate);
                $diff = date_diff($date1, $date2);
                $days = $diff->days + 1;

                if ($days >= 28 && $days <= 31) {
                    $periodStr = "1 Month";
                    $monthlyCharges = $item['purchase_amount'];
                } else {
                    $months = round($days / 30, 1);
                    $periodStr = $months . " Month" . ($months > 1 ? 's' : '');
                    $monthlyCharges = $item['purchase_amount'] / ($days / 30);
                }

                // Lit / Non Lit Abbreviation
                $lt = strtolower($item['light_type'] ?? '');
                if (strpos($lt, 'front') !== false || $lt === 'lit' || $lt === 'fl') {
                    $litLabel = 'FL';
                } elseif (strpos($lt, 'back') !== false || $lt === 'bl') {
                    $litLabel = 'BL';
                } else {
                    $litLabel = 'NL';
                }
            ?>
            <tr>
                <td><?php echo $idx + 1; ?></td>
                <td><?php echo htmlspecialchars($item['hsn_code'] ?: '998366'); ?></td>
                <td><?php echo htmlspecialchars($item['city']); ?></td>
                <td style="text-align: left; padding-left: 5px;">
                    <div style="font-weight: bold; font-size: 10px; color: #1e293b;"><?php echo htmlspecialchars($item['site_name'] ?? $item['name'] ?? 'N/A'); ?></div>
                    <?php if (!empty($item['location'])): ?>
                        <div style="font-size: 8px; color: #64748b; margin-top: 2px; font-weight: 500;"><?php echo htmlspecialchars($item['location']); ?></div>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($item['width']); ?></td>
                <td><?php echo htmlspecialchars($item['height']); ?></td>
                <td><?php echo $litLabel; ?></td>
                <td style="text-align: right; padding-right: 5px;"><?php echo number_format($monthlyCharges, 2); ?></td>
                <td><?php echo date('d-m-Y', strtotime($sDate)); ?></td>
                <td><?php echo date('d-m-Y', strtotime($eDate)); ?></td>
                <td><?php echo $periodStr; ?></td>
                <td style="text-align: right; padding-right: 5px; font-weight: bold;"><?php echo number_format($item['purchase_amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php 
            $cgst_amount = 0;
            $sgst_amount = 0;
            $igst_amount = 0;
            $gst_label = 'GST (18%)';

            if ($mode === 'saved_po') {
                $cgst_amount = floatval($b['cgst_amount'] ?? 0);
                $sgst_amount = floatval($b['sgst_amount'] ?? 0);
                $igst_amount = floatval($b['igst_amount'] ?? 0);
                $grand_total = floatval($b['total_amount'] ?? 0);
                
                if ($cgst_amount > 0 || $sgst_amount > 0) {
                    $gst_label = 'CGST + SGST (9%+9%)';
                    $gst_amount = $cgst_amount + $sgst_amount;
                } elseif ($igst_amount > 0) {
                    $gst_label = 'IGST (18%)';
                    $gst_amount = $igst_amount;
                } else {
                    $gst_label = 'GST (0%)';
                    $gst_amount = 0;
                }
            } else {
                $vendor_has_gst = vendorHasGST($v['gstin'] ?? '');
                if ($vendor_has_gst) {
                    $gst_label = 'IGST (18%)';
                    $gst_amount = $net_total * 0.18;
                } else {
                    $gst_label = 'GST (0%)';
                    $gst_amount = 0;
                }
                $grand_total = $net_total + $gst_amount;
            }
            ?>
            
            <tr class="totals-row">
                <td colspan="11" style="text-align: right; padding-right: 10px;">Taxable Amount (Total Cost)</td>
                <td style="text-align: right; padding-right: 10px; font-weight: bold;"><?php echo number_format($net_total, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="11" style="text-align: right; padding-right: 10px;"><?php echo $gst_label; ?></td>
                <td style="text-align: right; padding-right: 10px; font-weight: bold;"><?php echo number_format($gst_amount, 2); ?></td>
            </tr>
            <tr class="totals-row" style="background: #f9f9f9; border-top: 2px solid #000;">
                <td colspan="11" style="text-align: right; padding-right: 10px; font-size: 12px; height: 30px; vertical-align: middle;">Gross Payable Amount</td>
                <td style="text-align: right; padding-right: 10px; font-size: 12px; vertical-align: middle; font-weight: bold;"><?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div style="padding: 10px; border-top: 1px solid #000;">
        <strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($grand_total); ?> Only</span>
    </div>

    <div style="padding: 10px; border-top: 1px solid #000; font-size: 9px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
            <div style="font-weight: bold; text-decoration: underline; font-size: 10px;">Terms & Conditions</div>
            <div style="font-weight: bold; color: #cc0000; font-size: 11px;"><?php echo getSetting('po_important_note', 'Filing of GSTR-1 within time is mandatory for acceptance of Invoice.'); ?></div>
        </div>
        <div style="margin: 0; line-height: 1.2; white-space: pre-wrap;">
            <?php echo nl2br(getSetting('po_terms', '')); ?>
        </div>
    </div>

    <div class="footer">
        <div class="footer-left">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 5px;">Payment Terms:</div>
            <p style="margin: 2px 0;">- 50% Advance with PO</p>
            <p style="margin: 2px 0;">- 50% Balance after mounting with proofs</p>
            <p style="margin: 2px 0;">- Cheque/NEFT in favor of: <strong><?php echo $v['name']; ?></strong></p>
        </div>
        <div class="footer-right">
            <div>For <strong><?php echo $company_name; ?></strong></div>
            <div style="margin-top: 30px;">
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_signature; ?>" style="height: 40px; display: block; margin: 0 auto;" onerror="this.style.display='none'">
                <div style="border-top: 1px solid #000; width: 150px; margin: 5px auto 0;"></div>
                <div style="font-weight: bold; margin-top: 2px;">Authorised Signatory</div>
            </div>
        </div>
    </div>
</div>

<div style="max-width: 800px; margin: 10px auto; text-align: center; font-size: 9px; color: #888;">
    This is a computer generated Purchase Order and does not require physical signature.
</div>

</body>
</html>
