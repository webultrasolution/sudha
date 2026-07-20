<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
if (!$booking_id) die("Booking ID is required.");

$stmtInvCheck = $pdo->prepare("SELECT * FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
$stmtInvCheck->execute([$booking_id]);
$invoiceData = $stmtInvCheck->fetch();

if (session_status() === PHP_SESSION_NONE) session_start();
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($invoiceData && ($invoiceData['approval_status'] ?? '') === 'pending_approval' && !$isAdmin) {
    die("<div style='font-family:sans-serif;padding:2rem;text-align:center;color:#64748b;'><h3>Access Denied</h3><p>This invoice is pending admin approval and cannot be viewed or printed yet.</p></div>");
}

if ($invoiceData && ($invoiceData['approval_status'] ?? '') === 'rejected' && !$isAdmin) {
    die("<div style='font-family:sans-serif;padding:2rem;text-align:center;color:#ef4444;'><h3>Access Denied</h3><p>This invoice has been rejected by the administrator and cannot be viewed or printed.</p></div>");
}

$stmt = $pdo->prepare("
    SELECT b.*, c.name as client_name, c.address as client_address, c.gstin as client_gstin,
           c.state as client_state, c.additional_gst, c.contact_person, c.phone, c.pan as client_pan
    FROM bookings b
    JOIN partners c ON b.client_id = c.id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$b = $stmt->fetch();
if (!$b) die("Booking not found.");

if (!empty($b['billing_gstin']) && $b['billing_gstin'] !== $b['client_gstin'] && !empty($b['additional_gst'])) {
    $addGsts = json_decode($b['additional_gst'], true);
    if (is_array($addGsts)) {
        foreach ($addGsts as $g) {
            if ($g['gstin'] === $b['billing_gstin']) {
                $b['client_gstin']   = $g['gstin'];
                $b['client_address'] = $g['address'];
                $b['client_state']   = $g['state'];
                break;
            }
        }
    }
}

$stmtItems = $pdo->prepare("
    SELECT bi.*, COALESCE(bi.custom_site_name, s.name) as site_name, s.site_code,
           COALESCE(bi.custom_location, s.location) as location,
           s.city, s.width, s.height, s.light_type, s.hsn_code, s.type as media_type
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    WHERE bi.booking_id = ?
");
$stmtItems->execute([$booking_id]);
$items = $stmtItems->fetchAll();

// Company details — entity priority: invoice entity → active session entity → settings
$co                   = resolveCompanyDetails($invoiceData['entity_id'] ?? null);
$company_name         = $co['name'];
$company_gstin        = $co['gstin'];
$company_pan          = $co['pan'];
$company_address      = $co['address'];
$company_phone        = $co['phone'];
$company_email        = $co['email'];
$company_logo         = $co['logo'];
$company_letterhead   = $co['letterhead'];
$company_signature    = $co['signature'];
$company_bank_details    = $co['bank_details'];
$company_terms           = $co['invoice_terms'];
$company_msme            = $co['msme_number'];
$company_cin             = $co['cin'] ?? '';
$company_tan             = $co['tan'] ?? '';

// Derive short name (first 2 words) for large header text
$name_words       = explode(' ', trim($company_name));
$company_short    = strtoupper(implode(' ', array_slice($name_words, 0, 2)));
$company_full_uc  = strtoupper($company_name);

$invoiceLines = [];
$sn = 1;
foreach ($items as $item) {
    $sDate = (!empty($item['start_date']) && $item['start_date'] != '0000-00-00') ? $item['start_date'] : $b['start_date'];
    $eDate = (!empty($item['end_date'])   && $item['end_date']   != '0000-00-00') ? $item['end_date']   : $b['end_date'];
    
    $parts = [];
    if (!empty(trim($item['city'])))       $parts[] = trim($item['city']);
    if (!empty(trim($item['site_name'])))  $parts[] = trim($item['site_name']);
    
    $siteDetails = implode(', ', $parts) . " (" . $item['site_code'] . ")";
    
    // 1. Rental Line
    if (floatval($item['amount']) > 0) {
        $invoiceLines[] = [
            'sn' => $sn++,
            'desc' => "Space Rental - " . $siteDetails,
            'hsn' => $item['hsn_code'] ?: '998366',
            'size' => $item['width'] . "'&times;" . $item['height'] . "'",
            'period' => date('d.m.Y', strtotime($sDate)) . " to " . date('d.m.Y', strtotime($eDate)),
            'amount' => floatval($item['amount'])
        ];
    }
    
    // 2. Printing Line
    if (floatval($item['printing_amount'] ?? 0) > 0) {
        $invoiceLines[] = [
            'sn' => $sn++,
            'desc' => "Printing Service (" . ($item['media_type'] ?: 'Flex') . ") - " . $siteDetails,
            'hsn' => '998912',
            'size' => $item['width'] . "'&times;" . $item['height'] . "'",
            'period' => 'One-time',
            'amount' => floatval($item['printing_amount'])
        ];
    }
    
    // 3. Mounting Line
    if (floatval($item['mounting_amount'] ?? 0) > 0) {
        $invoiceLines[] = [
            'sn' => $sn++,
            'desc' => "Mounting / Installation Service (" . ($item['mounting_type'] ?? 'Standard') . ") - " . $siteDetails,
            'hsn' => '998739',
            'size' => $item['width'] . "'&times;" . $item['height'] . "'",
            'period' => 'One-time',
            'amount' => floatval($item['mounting_amount'])
        ];
    }
}

// Tax
$subtotal = 0;
foreach ($invoiceLines as $line) {
    $subtotal += $line['amount'];
}
$isInterState = (strtolower(trim($b['client_state'] ?? '')) !== 'west bengal');
$gst          = calculateGST($subtotal, $isInterState);
$grand_total  = $subtotal + $gst['total'];

$inv_number = !empty($invoiceData['invoice_number'])
    ? $invoiceData['invoice_number']
    : ('SCR/' . getFinancialYear($b['created_at']) . '/' . str_pad($b['id'], 4, '0', STR_PAD_LEFT));

$inv_date = (!empty($invoiceData['invoice_date']) && $invoiceData['invoice_date'] !== '0000-00-00')
    ? date('d-m-Y', strtotime($invoiceData['invoice_date']))
    : date('d-m-Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tax Invoice - <?php echo $inv_number; ?></title>
<style>
@page {
    size: A4;
    margin: 8mm 10mm;
    @bottom-right {
        content: counter(page) "/" counter-pages;
        font-family: Arial, sans-serif;
        font-size: 9px;
        color: #555;
    }
}
.avoid-break {
    page-break-inside: avoid;
    break-inside: avoid;
    margin-top: 20px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Arial', sans-serif;
    margin: 0;
    padding: 20px;
    color: #000;
    font-size: 11px;
    line-height: 1.3;
    background: #f1f5f9;
}
.invoice-wrapper {
    border: 1px solid #d1d5db;
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    position: relative;
    background: #fff;
    padding: 12mm 14mm;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    display: block;
}
.header-top {
    border-bottom: 1px solid #000;
    padding: 5px 10px;
}
.header-top p {
    margin: 0;
}
.main-info {
    display: flex;
    border-bottom: 1px solid #000;
    margin-bottom: 10px;
}
.info-col {
    flex: 1;
    padding: 6px;
}
.info-col:first-child {
    border-right: 1px solid #000;
}
.info-row {
    display: flex;
    margin-bottom: 3px;
}
.info-label {
    width: 110px;
    font-weight: normal;
}
.info-sep {
    width: 15px;
}
.info-value {
    flex: 1;
    font-weight: normal;
}
.section-title {
    font-weight: bold;
    text-decoration: underline;
    margin-bottom: 5px;
    font-style: italic;
}
table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
}
th {
    border-bottom: 1px solid #000;
    border-right: 1px solid #000;
    padding: 4px;
    text-align: center;
    font-weight: bold;
    font-size: 10px;
}
th:last-child {
    border-right: none;
}
td {
    border-bottom: 1px solid #000;
    border-right: 1px solid #000;
    padding: 4px 5px;
    vertical-align: top;
    text-align: center;
    font-size: 10px;
}
td:last-child {
    border-right: none;
}
.totals-row td {
    border-bottom: none;
    border-top: 1px solid #000;
    font-weight: bold;
}
.gst-row td {
    border-bottom: none;
    font-weight: normal;
    color: #444;
}
.footer {
    display: flex;
    border: 1px solid #000;
}
.footer-left {
    flex: 2;
    padding: 10px;
    border-right: 1px solid #000;
    min-height: 70px;
}
.footer-right {
    flex: 1;
    padding: 10px;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.btn-print {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: #000;
    color: #fff;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 4px;
    font-weight: bold;
    z-index: 999;
}
@media print {
    .btn-print {
        display: none;
    }
    body {
        padding: 0;
        background: #fff;
    }
    .invoice-wrapper {
        border: none;
        width: 100%;
        min-height: auto;
        box-shadow: none;
        padding: 0;
        margin: 0;
        display: block;
    }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; break-inside: avoid; }
    .avoid-break {
        margin-top: 15px;
        display: block;
    }
}
</style>
</head>
<body>

<button class="btn-print" onclick="window.print()">&#128438; PRINT INVOICE</button>

<div class="invoice-wrapper">

    <!-- Header with Letterhead or Manual Info -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 10px 2px;">
        <div style="flex: 1.4; text-align: left;">
            <?php if ($company_letterhead && file_exists(__DIR__ . '/../../assets/images/' . $company_letterhead)): ?>
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_letterhead; ?>"
                    style="max-height: 110px; width: auto; display: block; margin-bottom: 5px;">
            <?php else: ?>
                <h2 style="margin: 0; text-transform: uppercase; font-size: 18px; color: #8B1A1A;"><?php echo htmlspecialchars($company_name); ?></h2>
            <?php endif; ?>
        </div>
        <div style="flex: 1.2; text-align: center; padding-top: 15px;">
            <div style="font-size: 15px; font-weight: bold; text-decoration: underline; letter-spacing: 1.5px; text-transform: uppercase;">TAX INVOICE</div>
        </div>
        <div style="flex: 0.8; text-align: right; font-style: italic; font-size: 10px; padding-top: 15px; color: #555;">
            Original Copy
        </div>
    </div>
    <div style="padding: 0 10px 10px; font-size: 10px; line-height: 1.4; color: #000; border-bottom: 1px solid #000; margin-bottom: 10px;">
        <?php echo nl2br(htmlspecialchars($company_address)); ?><br>
        Ph : <?php echo htmlspecialchars($company_phone); ?> &nbsp;|&nbsp; Email : <?php echo htmlspecialchars($company_email); ?>
    </div>

    <!-- Order & Invoice Info -->
    <div class="main-info">
        <div class="info-col">
            <div class="info-row">
                <span class="info-label">Order No.</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $b['customer_po_no'] ?: 'N/A'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Order Date</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $b['customer_po_date'] ? date('d.m.Y', strtotime($b['customer_po_date'])) : 'N/A'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Campaign</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo htmlspecialchars($b['campaign_name'] ?? 'N/A'); ?></strong></span>
            </div>

            <div style="margin-top: 15px;">
                <div class="section-title">Client Details:</div>
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 2px;">
                    <?php echo $b['client_name']; ?>
                </div>
                <div style="width: 250px;"><?php echo $b['client_address']; ?></div>
                <div class="info-row" style="margin-top: 5px;">
                    <span class="info-label">Place of Supply</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo $b['client_state']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Customer PAN</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo $b['client_pan'] ?: 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">GSTIN / UIN</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo $b['client_gstin'] ?: 'N/A'; ?></span>
                </div>
            </div>
        </div>

        <div class="info-col">
            <div class="info-row">
                <span class="info-label">Invoice No.</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo htmlspecialchars($inv_number); ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Invoice Date</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $inv_date; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">PAN No.</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_pan; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">GSTIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_gstin; ?></span>
            </div>
            <?php if ($company_msme): ?>
            <div class="info-row">
                <span class="info-label">MSME</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_msme); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($company_cin)): ?>
            <div class="info-row">
                <span class="info-label">CIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_cin); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($company_tan)): ?>
            <div class="info-row">
                <span class="info-label">TAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_tan); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── ITEMS TABLE ── -->
    <table>
        <thead>
            <tr>
                <th style="width:30px;">S.N.</th>
                <th>LOCATION / DESCRIPTION</th>
                <th style="width:65px;">HSN/SAC<br>Code</th>
                <th style="width:60px;">SIZE</th>
                <th style="width:100px;">PERIOD</th>
                <th style="width:90px;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoiceLines as $line): ?>
            <tr>
                <td><?php echo $line['sn']; ?></td>
                <td style="text-align:left; padding-left:10px;">
                    <strong><?php echo htmlspecialchars($line['desc']); ?></strong>
                </td>
                <td><?php echo htmlspecialchars($line['hsn']); ?></td>
                <td><?php echo $line['size']; ?></td>
                <td style="font-size:9px; line-height:1.6;">
                    <?php echo $line['period']; ?>
                </td>
                <td style="text-align:right; padding-right:10px; font-weight:bold;">
                    <?php echo number_format($line['amount'], 2); ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <!-- Total Before Tax -->
            <tr class="gst-row" style="font-weight: bold; border-top: 1.5px solid #000;">
                <td colspan="5" style="text-align:right; padding-right:10px;">Total Before Tax</td>
                <td style="text-align:right; padding-right:10px; font-weight:bold;"><?php echo number_format($subtotal, 2); ?></td>
            </tr>

            <!-- GST Breakdown -->
            <tr class="gst-row" style="font-weight: bold;">
                <td colspan="5" style="text-align:right; padding-right:10px;">CGST (<?php echo $isInterState ? '0' : '9'; ?>%)</td>
                <td style="text-align:right; padding-right:10px;"><?php echo number_format($gst['cgst'], 2); ?></td>
            </tr>
            <tr class="gst-row" style="font-weight: bold;">
                <td colspan="5" style="text-align:right; padding-right:10px;">SGST (<?php echo $isInterState ? '0' : '9'; ?>%)</td>
                <td style="text-align:right; padding-right:10px;"><?php echo number_format($gst['sgst'], 2); ?></td>
            </tr>
            <tr class="gst-row" style="font-weight: bold;">
                <td colspan="5" style="text-align:right; padding-right:10px;">IGST (<?php echo $isInterState ? '18' : '0'; ?>%)</td>
                <td style="text-align:right; padding-right:10px;"><?php echo number_format($gst['igst'], 2); ?></td>
            </tr>

            <!-- Grand Total -->
            <tr class="totals-row">
                <td colspan="5" style="text-align:right; padding-right:10px; font-size:12px;">Grand Total</td>
                <td style="text-align:right; padding-right:10px; font-weight:bold; font-size:12px;"><?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="avoid-break">
        <!-- ── HSN/SAC TAX BREAKDOWN ── -->
        <?php
        // Group items by HSN code
        $hsnGroups = [];
        foreach ($invoiceLines as $line) {
            $hsn = $line['hsn'];
            $hsnGroups[$hsn] = ($hsnGroups[$hsn] ?? 0) + $line['amount'];
        }
        ?>
        <table style="border:1px solid #000; margin-top: 10px;">
            <thead>
                <tr style="background:#f2f2f2;">
                    <th style="text-align:left; padding-left:8px; width:90px; border-top:none;">HSN/SAC</th>
                    <th style="width:70px; border-top:none;">Tax Rate</th>
                    <th style="text-align:right; padding-right:8px; border-top:none;">Taxable Amt.</th>
                    <th style="text-align:right; padding-right:8px; border-top:none;">CGST Amt.</th>
                    <th style="text-align:right; padding-right:8px; border-top:none;">SGST Amt.</th>
                    <th style="text-align:right; padding-right:8px; border-top:none; border-right:none;">IGST Amt.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hsnGroups as $hsn => $taxableAmt):
                    $hsnGst = calculateGST($taxableAmt, $isInterState);
                ?>
                <tr>
                    <td style="text-align:left; padding-left:8px; border-left:none;"><?php echo htmlspecialchars($hsn); ?></td>
                    <td style="text-align:center;">18%</td>
                    <td style="text-align:right; padding-right:8px;"><?php echo number_format($taxableAmt, 2); ?></td>
                    <td style="text-align:right; padding-right:8px;"><?php echo number_format($hsnGst['cgst'], 2); ?></td>
                    <td style="text-align:right; padding-right:8px;"><?php echo number_format($hsnGst['sgst'], 2); ?></td>
                    <td style="text-align:right; padding-right:8px; border-right:none;"><?php echo number_format($hsnGst['igst'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ── AMOUNT IN WORDS ── -->
        <div style="padding:10px; border-top:1px solid #000; border-bottom:1px solid #000; font-size:11px;">
            <strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($grand_total); ?> Only</span>
        </div>

        <!-- ── FOOTER (BANK + TERMS & SIGNATURE) ── -->
        <div class="footer">
            <div class="footer-left" style="padding: 6px 10px;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 3px;">Bank Details:</div>
                <div style="line-height: 1.2; margin-bottom: 6px; font-size: 9.5px;">
                    <?php
                    $bank = $company_bank_details ?: "A/C Name: {$company_name}\nBank: STATE BANK OF INDIA\nA/C No: 1234567890123\nIFSC: SBIN0000123";
                    echo nl2br(htmlspecialchars(strtoupper($bank)));
                    ?>
                </div>
                <div style="border-top: 1px dashed #ccc; padding-top: 4px; font-size: 8.5px; line-height: 1.2;">
                    <div style="font-weight: bold; margin-bottom: 2px;">Terms &amp; Conditions</div>
                    <?php if (!empty($company_terms)): ?>
                        <?php echo nl2br(htmlspecialchars($company_terms)); ?>
                    <?php else: ?>
                        <div>E.&amp; O.E.</div>
                        <div>1. Interest @ 18% p.a. will be charged if the payment is not made within the stipulated time.</div>
                        <div>2. Subject to &lsquo;Malda&rsquo; Jurisdiction only.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-right" style="padding: 6px 10px;">
                <div>For <strong><?php echo strtoupper($company_name); ?></strong></div>
                <div style="margin-top: 15px;">
                    <img src="<?php echo BASE_URL; ?>assets/images/<?php echo htmlspecialchars($company_signature); ?>"
                         style="height: 45px; display: block; margin: 0 auto;" onerror="this.style.display='none'">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 5px auto 0;"></div>
                    <div style="font-weight: bold; margin-top: 2px;">Authorised Signatory</div>
                </div>
            </div>
        </div>
    </div>


</div><!-- /.invoice-wrapper -->

<div style="max-width:780px; margin:8px auto; text-align:center; font-size:9px; color:#999;">
    This is a computer generated invoice and does not require a physical signature.
</div>

</body>
</html>
