<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Prevent timezone warnings
date_default_timezone_set('Asia/Kolkata');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if (!$booking_id || !$vendor_id) {
    die("Invalid request parameters.");
}

// Fetch Booking & Client Info
$stmtB = $pdo->prepare("
    SELECT b.*, c.name as client_name, p.campaign_name, p.proposal_number
    FROM bookings b
    JOIN partners c ON b.client_id = c.id
    LEFT JOIN proposals p ON b.proposal_id = p.id
    WHERE b.id = ?
");
$stmtB->execute([$booking_id]);
$b = $stmtB->fetch();

// Fetch Vendor Info
$stmtV = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$stmtV->execute([$vendor_id]);
$v = $stmtV->fetch();

if (!$b || !$v) {
    die("Booking or Vendor not found.");
}

// Fetch Items for this vendor
$vendor_gst_filter = $_GET['vendor_gst'] ?? '';
$itemSql = "
    SELECT bi.*, s.site_code, s.location, s.city, s.width, s.height, s.light_type, s.hsn_code, s.vendor_gst, s.type as media_type
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    WHERE bi.booking_id = ? AND s.vendor_id = ?
";
$itemParams = [$booking_id, $vendor_id];

if ($vendor_gst_filter !== '') {
    $itemSql .= " AND (s.vendor_gst = ? OR s.vendor_gst IS NULL AND ? = '')";
    $itemParams[] = $vendor_gst_filter;
    $itemParams[] = $vendor_gst_filter;
}

$stmtItems = $pdo->prepare($itemSql);
$stmtItems->execute($itemParams);
$items = $stmtItems->fetchAll();

$po_number = "PO/" . date('y', strtotime($b['start_date'])) . "-" . date('y', strtotime($b['start_date'] . ' +1 year')) . "/" . str_pad($b['id'], 3, '0', STR_PAD_LEFT);
$po_date = date('d-m-Y');

// Company Settings
$company_name = getSetting('company_name', 'Sudha Creative & Advertising');
$company_gstin = getSetting('company_gstin', '19AHRPT4740Q1Z6'); 
$company_pan = getSetting('company_pan', 'AHRPT4740Q');
$company_address = getSetting('company_address', 'Deshbandhu Para, P.O - Jhaljhalia, Dist - Malda - 732102, West Bengal');
$company_phone = getSetting('company_phone', '8158854313');
$company_email = getSetting('company_email', 'sudhacreativemalda@gmail.com');
$company_letterhead = getSetting('company_letterhead');
$company_signature = getSetting('company_signature', 'signature.png');

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
    <div class="main-info">
        <div class="info-col">
            <div style="margin-bottom: 15px;">
                <div class="section-title">Supplier / Vendor:</div>
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 2px;"><?php echo $v['name']; ?></div>
                <div style="width: 250px;"><?php echo $v['address']; ?></div>
                <div class="info-row" style="margin-top: 5px;">
                    <span class="info-label">GSTIN / UIN</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo $vendor_gst_filter ?: $v['gstin']; ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact Person</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo $v['contact_person']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo $v['phone']; ?></span>
                </div>
            </div>
        </div>
        
        <div class="info-col">
            <div class="info-row">
                <span class="info-label">PO Number</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo $po_number; ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">PO Date</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $po_date; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Booking Ref</span>
                <span class="info-sep">:</span>
                <span class="info-value">#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Campaign</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $b['campaign_name']; ?></span>
            </div>
            <div class="info-row" style="margin-top: 10px;">
                <span class="info-label">Buyer PAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_pan; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Buyer GSTIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_gstin; ?></span>
            </div>
        </div>
    </div>

    <div class="table-title">Purchase Order Details:</div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">S.N.</th>
                <th>LOCATION & MEDIA TYPE</th>
                <th style="width: 70px;">HSN/SAC<br>Code</th>
                <th style="width: 80px;">SIZE</th>
                <th style="width: 100px;">PERIOD</th>
                <th style="width: 80px;">Days</th>
                <th style="width: 90px;">Total Cost(₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $net_total = 0;
            foreach ($items as $idx => $item): 
                $net_total += $item['purchase_amount'];
                $sDate = (!empty($item['start_date']) && $item['start_date'] != '0000-00-00') ? $item['start_date'] : $b['start_date'];
                $eDate = (!empty($item['end_date']) && $item['end_date'] != '0000-00-00') ? $item['end_date'] : $b['end_date'];
            ?>
            <tr>
                <td><?php echo $idx + 1; ?></td>
                <td style="text-align: left; padding-left: 10px;">
                    <div style="font-weight: bold;"><?php echo $item['location']; ?></div>
                    <div style="font-size: 9px; color: #555;"><?php echo $item['city']; ?> • <?php echo $item['media_type']; ?></div>
                </td>
                <td><?php echo $item['hsn_code'] ?: '998366'; ?></td>
                <td><?php echo $item['width']; ?>'x<?php echo $item['height']; ?>'</td>
                <td style="font-size: 9px;">
                    <?php echo date('d.m.Y', strtotime($sDate)); ?> to<br>
                    <?php echo date('d.m.Y', strtotime($eDate)); ?>
                </td>
                <td><?php echo $item['days']; ?></td>
                <td style="text-align: right; padding-right: 10px; font-weight: bold;"><?php echo number_format($item['purchase_amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php 
            $gst_amount = $net_total * 0.18;
            $grand_total = $net_total + $gst_amount;
            ?>
            
            <tr class="totals-row">
                <td colspan="6" style="text-align: right; padding-right: 10px;">Taxable Amount (Total Cost)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($net_total, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="6" style="text-align: right; padding-right: 10px;">GST (18%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($gst_amount, 2); ?></td>
            </tr>
            <tr class="totals-row" style="background: #f9f9f9; border-top: 2px solid #000;">
                <td colspan="6" style="text-align: right; padding-right: 10px; font-size: 12px; height: 30px; vertical-align: middle;">Gross Payable Amount</td>
                <td style="text-align: right; padding-right: 10px; font-size: 12px; vertical-align: middle;"><?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div style="padding: 10px; border-top: 1px solid #000;">
        <strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($grand_total); ?> Only</span>
    </div>

    <div style="padding: 10px; border-top: 1px solid #000; font-size: 9px;">
        <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">Terms & Conditions:</div>
        <ol style="margin: 0; padding-left: 15px;">
            <li>Flex mounting and cleaning will be free of cost as per standard agreement.</li>
            <li>Filing of GSTR-1 within time is mandatory for payment processing.</li>
            <li>Non-illumination of lit sites will lead to deduction on pro-rata basis.</li>
            <li>Contract period starts from the date of physical display verification.</li>
        </ol>
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
