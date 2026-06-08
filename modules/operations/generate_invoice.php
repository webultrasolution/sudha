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
$company_signature    = $co['signature'];
$company_bank_details = $co['bank_details'];

// Derive short name (first 2 words) for large header text
$name_words       = explode(' ', trim($company_name));
$company_short    = strtoupper(implode(' ', array_slice($name_words, 0, 2)));
$company_full_uc  = strtoupper($company_name);

// Tax
$subtotal     = $b['total_amount'];
$isInterState = (strtolower(trim($b['client_state'] ?? '')) !== 'west bengal');
$gst          = calculateGST($subtotal, $isInterState);

$inv_number = !empty($invoiceData['invoice_number'])
    ? $invoiceData['invoice_number']
    : ('SCR/' . date('y', strtotime($b['created_at'])) . '-' . date('y', strtotime($b['created_at'] . ' +1 year')) . '/' . str_pad($b['id'], 3, '0', STR_PAD_LEFT));

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
@page { size: A4; margin: 12mm 14mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #000;
    line-height: 1.45;
    background: #fff;
}
.invoice-wrapper {
    max-width: 780px;
    margin: 0 auto;
    padding: 10px 12px;
    border: 1px solid #000;
}

/* ── HEADER ─────────────────────────────── */
.inv-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 6px;
}
.inv-header-left {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    flex: 1.4;
}
.company-logo {
    width: 58px;
    height: auto;
    flex-shrink: 0;
}
.company-name-block { line-height: 1.2; }
.company-name-block .short-name {
    font-size: 20px;
    font-weight: bold;
    color: #8B1A1A;
    letter-spacing: 1px;
}
.company-name-block .full-name {
    font-size: 9.5px;
    font-weight: bold;
    color: #8B1A1A;
    letter-spacing: 0.4px;
    margin-top: 2px;
}
.inv-header-center {
    flex: 1;
    text-align: center;
    padding-top: 8px;
}
.inv-title {
    font-size: 15px;
    font-weight: bold;
    text-decoration: underline;
    letter-spacing: 2px;
}
.inv-header-right {
    flex: 1;
    text-align: right;
    font-style: italic;
    font-size: 11px;
    padding-top: 4px;
}

/* ── DIVIDER ─────────────────────────────── */
.divider {
    border: none;
    border-top: 1.5px solid #000;
    margin: 0 0 0;
}

/* ── INFO SECTION ────────────────────────── */
.info-section {
    display: flex;
    padding: 10px 0 8px;
    border-bottom: 1px solid #555;
}
.info-left  { flex: 1.3; padding-right: 15px; }
.info-right { flex: 1; border-left: 1px solid #ccc; padding-left: 15px; padding-top: 0; }
.info-row {
    display: flex;
    margin-bottom: 3px;
    font-size: 11px;
}
.info-label { min-width: 115px; color: #222; }
.info-sep   { width: 12px; text-align: center; }
.info-value { flex: 1; }
.client-title {
    font-style: italic;
    font-weight: bold;
    text-decoration: underline;
    margin: 12px 0 5px;
    font-size: 11px;
}
.client-name {
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 4px;
}
.client-address {
    max-width: 270px;
    line-height: 1.55;
    margin-bottom: 6px;
    color: #111;
}

/* ── TABLE ───────────────────────────────── */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
}
thead tr { background: #f2f2f2; }
th {
    border: 1px solid #000;
    padding: 5px 4px;
    text-align: center;
    font-size: 10px;
    font-weight: bold;
    line-height: 1.3;
}
td {
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-bottom: 1px solid #ddd;
    padding: 7px 4px;
    vertical-align: middle;
    text-align: center;
    font-size: 10px;
}
.gst-row td {
    border-bottom: none;
    color: #333;
}
.total-row td {
    border-top: 1.5px solid #000;
    border-bottom: 1.5px solid #000;
    font-weight: bold;
    font-size: 11.5px;
}

/* footer styles are inline for flexibility */

/* ── PRINT BUTTON ────────────────────────── */
.btn-print {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1e293b;
    color: #fff;
    border: none;
    padding: 9px 20px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    z-index: 999;
}
@media print {
    .btn-print { display: none; }
    body { padding: 0; }
}
</style>
</head>
<body>

<button class="btn-print" onclick="window.print()">&#128438; PRINT INVOICE</button>

<div class="invoice-wrapper">

    <!-- ── HEADER ── -->
    <div class="inv-header">
        <div class="inv-header-left">
            <?php if ($company_logo): ?>
            <img src="<?php echo BASE_URL; ?>assets/images/<?php echo htmlspecialchars($company_logo); ?>"
                 class="company-logo" onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="company-name-block">
                <div class="short-name"><?php echo htmlspecialchars($company_short); ?></div>
                <div class="full-name"><?php echo htmlspecialchars($company_full_uc); ?></div>
            </div>
        </div>
        <div class="inv-header-center">
            <div class="inv-title">TAX INVOICE</div>
        </div>
        <div class="inv-header-right"><em>Original Copy</em></div>
    </div>
    <div class="company-contact">
        <?php echo nl2br(htmlspecialchars($company_address)); ?><br>
        Ph : <?php echo htmlspecialchars($company_phone); ?> &nbsp;&nbsp;
        Email : <?php echo htmlspecialchars($company_email); ?>
    </div>
    <hr class="divider">

    <!-- ── INFO SECTION ── -->
    <div class="info-section">
        <!-- Left: Order info + Client details -->
        <div class="info-left">
            <div class="info-row">
                <span class="info-label">Order No.</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($b['customer_po_no'] ?: 'NA'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Order Date</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $b['customer_po_date'] ? date('d-m-Y', strtotime($b['customer_po_date'])) : 'NA'; ?></span>
            </div>

            <div class="client-title">Client Details :</div>
            <div class="client-name"><?php echo htmlspecialchars($b['client_name']); ?></div>
            <div class="client-address"><?php echo nl2br(htmlspecialchars($b['client_address'])); ?></div>

            <div class="info-row">
                <span class="info-label">Place of Supply</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($b['client_state']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Customer PAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($b['client_pan'] ?: 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">GSTIN / UIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($b['client_gstin'] ?: 'N/A'); ?></span>
            </div>
        </div>

        <!-- Right: Invoice details -->
        <div class="info-right">
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
                <span class="info-value"><?php echo htmlspecialchars($company_pan); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">GSTIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_gstin); ?></span>
            </div>
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
            <?php foreach ($items as $idx => $item):
                $sDate = (!empty($item['start_date']) && $item['start_date'] != '0000-00-00') ? $item['start_date'] : $b['start_date'];
                $eDate = (!empty($item['end_date'])   && $item['end_date']   != '0000-00-00') ? $item['end_date']   : $b['end_date'];
                $parts = [];
                if (!empty(trim($item['city'])))       $parts[] = trim($item['city']);
                if (!empty(trim($item['site_name'])))  $parts[] = trim($item['site_name']);
                if (!empty(trim($item['location'])))   $parts[] = trim($item['location']);
                if (!empty(trim($item['media_type']))) $parts[] = trim($item['media_type']);
            ?>
            <tr>
                <td><?php echo $idx + 1; ?></td>
                <td style="text-align:left; padding-left:10px;">
                    <strong><?php echo htmlspecialchars(implode(', ', $parts)); ?></strong>
                </td>
                <td><?php echo htmlspecialchars($item['hsn_code'] ?: '998366'); ?></td>
                <td><?php echo $item['width']; ?>'&times;<?php echo $item['height']; ?>'</td>
                <td style="font-size:9px; line-height:1.6;">
                    <?php echo date('d.m.Y', strtotime($sDate)); ?> to<br>
                    <?php echo date('d.m.Y', strtotime($eDate)); ?>
                </td>
                <td style="text-align:right; padding-right:10px; font-weight:bold;">
                    <?php echo number_format($item['amount'], 2); ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <!-- Grand Total -->
            <tr class="total-row">
                <td colspan="4" style="text-align:right; padding-right:10px; font-weight:bold;">Grand Total</td>
                <td style="text-align:center; font-weight:bold; border-left:1px solid #000;">&#8377;</td>
                <td style="text-align:right; padding-right:10px; font-weight:bold;"><?php echo number_format($b['grand_total'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- ── HSN/SAC TAX BREAKDOWN ── -->
    <?php
    // Group items by HSN code
    $hsnGroups = [];
    foreach ($items as $item) {
        $hsn = $item['hsn_code'] ?: '998366';
        $hsnGroups[$hsn] = ($hsnGroups[$hsn] ?? 0) + floatval($item['amount'] ?? 0);
    }
    ?>
    <table style="border-top:1.5px solid #000; border-bottom:1px solid #000;">
        <thead>
            <tr style="background:#f2f2f2;">
                <th style="text-align:left; padding-left:8px; width:90px; border-top:none;">HSN/SAC</th>
                <th style="width:70px; border-top:none;">Tax Rate</th>
                <th style="text-align:right; padding-right:8px; border-top:none;">Taxable Amt.</th>
                <?php if ($isInterState): ?>
                <th style="text-align:right; padding-right:8px; border-top:none;" colspan="2">IGST Amt.</th>
                <?php else: ?>
                <th style="text-align:right; padding-right:8px; border-top:none;">CGST Amt.</th>
                <th style="text-align:right; padding-right:8px; border-top:none; border-right:none;">SGST Amt.</th>
                <?php endif; ?>
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
                <?php if ($isInterState): ?>
                <td style="text-align:right; padding-right:8px;" colspan="2"><?php echo number_format($hsnGst['igst'], 2); ?></td>
                <?php else: ?>
                <td style="text-align:right; padding-right:8px;"><?php echo number_format($hsnGst['cgst'], 2); ?></td>
                <td style="text-align:right; padding-right:8px; border-right:none;"><?php echo number_format($hsnGst['sgst'], 2); ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ── AMOUNT IN WORDS ── -->
    <div style="padding:8px 10px; border-bottom:1px solid #000; font-size:11px;">
        <strong><?php echo ucwords(strtolower(amountInWords($b['grand_total']))); ?> Only</strong>
    </div>

    <!-- ── BANK DETAILS ── -->
    <div style="display:flex; border-bottom:1px solid #000;">
        <div style="padding:8px 10px; font-size:10px; min-width:90px; font-weight:bold; border-right:1px solid #000; flex-shrink:0;">
            Bank Details
        </div>
        <div style="padding:8px 12px; font-size:10px; line-height:1.6; flex:1;">
            <?php
            $bank = $company_bank_details ?: "A/C Name: {$company_name}\nBank: STATE BANK OF INDIA\nA/C No: 1234567890123\nIFSC: SBIN0000123";
            echo nl2br(htmlspecialchars(strtoupper($bank)));
            ?>
        </div>
    </div>

    <!-- ── TERMS & SIGNATURE ── -->
    <div style="display:flex; min-height:110px;">
        <!-- Terms & Conditions -->
        <div style="flex:1.4; padding:10px; border-right:1px solid #000; font-size:9.5px; line-height:1.6;">
            <div style="font-weight:bold; margin-bottom:4px;">Terms &amp; Conditions</div>
            <div>E.&amp; O.E.</div>
            <div>1. Interest @ 18% p.a. will be charged if the payment is not made with in the stipulated time.</div>
            <div>2. Subject to &lsquo;Malda&rsquo; Jurisdiction only.</div>
        </div>
        <!-- Authorised Signatory -->
        <div style="flex:1; padding:10px; display:flex; flex-direction:column; justify-content:space-between; text-align:center; font-size:10px;">
            <div style="text-align:left; font-weight:bold; font-size:10px;">
                For, <?php echo htmlspecialchars(strtoupper($company_name)); ?>
            </div>
            <div>
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo htmlspecialchars($company_signature); ?>"
                     style="height:48px; display:block; margin:0 auto;" onerror="this.style.display='none'">
                <div style="font-weight:bold; margin-top:4px;">Authorised Signatory</div>
            </div>
        </div>
    </div>

</div><!-- /.invoice-wrapper -->

<div style="max-width:780px; margin:8px auto; text-align:center; font-size:9px; color:#999;">
    This is a computer generated invoice and does not require a physical signature.
</div>

</body>
</html>
