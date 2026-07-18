<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

$po_id = isset($_GET['po_id']) ? intval($_GET['po_id']) : 0;
if (!$po_id) die("Invalid request: PO ID is required.");

// Fetch PO Info
$stmtPO = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
$stmtPO->execute([$po_id]);
$po = $stmtPO->fetch();
if (!$po) die("PO not found.");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
if (in_array($po['approval_status'] ?? '', ['pending_approval', 'rejected']) && !$isAdmin) {
    die("<div style='padding: 50px; text-align: center; font-family: Arial, sans-serif; color: #c2410c; background: #fff; min-height: 100vh; box-sizing: border-box;'>
            <h2>Awaiting Admin Approval</h2>
            <p>This Purchase Order (#" . htmlspecialchars($po['po_number']) . ") requires Admin approval before it can be printed or viewed.</p>
            <a href='../../modules/partners/printing_rates.php?vendor_id=" . intval($po['vendor_id']) . "' style='color: #0d9488; font-weight: bold; text-decoration: none;'>Back</a>
         </div>");
}

$vendor_id = $po['vendor_id'];

// Fetch Vendor Info
$stmtV = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$stmtV->execute([$vendor_id]);
$v = $stmtV->fetch();

// Fetch PO Items
$stmtItems = $pdo->prepare("
    SELECT pi.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.type as media_type_site, s.hsn_code, s.vendor_gst
    FROM po_items pi
    LEFT JOIN sites s ON pi.site_id = s.id
    WHERE pi.po_id = ?
");
$stmtItems->execute([$po_id]);
$rates = $stmtItems->fetchAll();

$po_remark = $po['remarks'] ?? '';
$po_number = $po['po_number'];
$po_date = date('d-m-Y', strtotime($po['po_date']));

// Company Settings — uses active session entity
$co                = resolveCompanyDetails();
$company_name      = $co['name'];
$company_gstin     = $co['gstin'];
$company_pan       = $co['pan'];
$company_address   = $co['address'];
$company_phone     = $co['phone'];
$company_email     = $co['email'];
$company_signature = $co['signature'];
$company_msme      = $co['msme_number'];
$company_cin       = $co['cin'] ?? '';
$company_tan       = $co['tan'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Printing PO - <?php echo $po_number; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 8mm 10mm;
            @bottom-right {
                content: counter(page) "/" counter(pages);
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
        body { margin: 0; padding: 20px; font-family: 'Roboto', Arial, sans-serif; background: #f1f5f9; }
        .po-container {
            border: 1px solid #d1d5db;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 12mm 14mm;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            box-sizing: border-box;
            display: block;
        }
        .print-btn { display: block; width: 120px; margin: 0 auto 20px; text-align: center; padding: 10px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; cursor: pointer; border: none; }
        .print-btn:hover { background: #1d4ed8; }
        @media print {
            body { background: #fff; padding: 0; }
            .po-container { box-shadow: none; max-width: 100%; padding: 0; border: none; min-height: auto; width: 100%; margin: 0; display: block; }
            .print-btn { display: none !important; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; break-inside: avoid; }
            .avoid-break {
                margin-top: 15px;
                display: block;
            }
        }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .company-title { font-size: 22px; font-weight: 900; color: #1e3a8a; margin: 0; text-transform: uppercase; }
        .company-details { font-size: 10px; color: #333; line-height: 1.4; }
        .po-title { text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
        
        .info-section { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12px; }
        .info-col { width: 48%; }
        .info-row { display: flex; margin-bottom: 3px; }
        .info-label { width: 110px; font-weight: bold; }
        .info-sep { width: 10px; }
        .info-value { flex: 1; }
 
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: center; }
        th { background-color: #f1f5f9; font-weight: bold; text-transform: uppercase; }
        .totals-row td { font-weight: bold; }
        
        .footer { display: flex; justify-content: space-between; margin-top: 20px; font-size: 11px; }
        .footer-left { width: 60%; }
        .footer-right { width: 35%; text-align: center; }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()"><svg style="width:16px; height:16px; vertical-align:middle; margin-right:5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg> Print PO</button>

<div class="po-container">
    <!-- Header with Letterhead or Manual Info -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 10px 2px;">
        <div style="flex: 1.4; text-align: left;">
            <?php if (!empty($co['letterhead'])): ?>
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $co['letterhead']; ?>"
                    style="max-height: 110px; width: auto; display: block; margin-bottom: 5px;">
            <?php else: ?>
                <h1 class="company-title" style="margin: 0; text-transform: uppercase; font-size: 18px; color: #8B1A1A;"><?php echo htmlspecialchars($company_name); ?></h1>
            <?php endif; ?>
        </div>
        <div style="flex: 1.2; text-align: center; padding-top: 15px;">
            <div style="font-size: 15px; font-weight: bold; text-decoration: underline; letter-spacing: 1.5px; text-transform: uppercase;">PRINTING PURCHASE ORDER</div>
        </div>
        <div style="flex: 0.8; text-align: right; font-style: italic; font-size: 10px; padding-top: 15px; color: #555;">
            Original Copy
        </div>
    </div>
    <div style="padding: 0 10px 10px; font-size: 10px; line-height: 1.4; color: #000; border-bottom: 2px solid #000; margin-bottom: 20px;">
        <div class="company-details">
            <?php echo nl2br(htmlspecialchars($company_address)); ?><br>
            Phone: <?php echo htmlspecialchars($company_phone); ?> | Email: <?php echo htmlspecialchars($company_email); ?>
        </div>
    </div>


    <div class="info-section">
        <div class="info-col">
            <div style="border: 1px solid #000; padding: 8px; min-height: 100px;">
                <div style="font-weight: bold; margin-bottom: 5px; border-bottom: 1px solid #ccc; padding-bottom: 3px;">To Vendor:</div>
                <div style="font-size: 13px; font-weight: bold; color: #1e293b;"><?php echo $v['name']; ?></div>
                <div style="margin-top: 3px; font-size: 11px;"><?php echo nl2br($v['address']); ?></div>
                <div class="info-row" style="margin-top: 5px;">
                    <span class="info-label">GSTIN / UIN</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo $v['gstin'] ?: 'N/A'; ?></strong></span>
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
                <span class="info-label">Type</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong>PRINTING ORDER</strong></span>
            </div>
            <?php if ($po_remark): ?>
            <div class="info-row">
                <span class="info-label">Remark</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($po_remark); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-row" style="margin-top: 5px;">
                <span class="info-label">Vendor Inv No.</span>
                <span class="info-sep">:</span>
                <span class="info-value" style="border-bottom: 1px dashed #999; display: inline-block; width: 150px;"></span>
            </div>
            <div class="info-row">
                <span class="info-label">Vendor Inv Dt.</span>
                <span class="info-sep">:</span>
                <span class="info-value" style="border-bottom: 1px dashed #999; display: inline-block; width: 150px;"></span>
            </div>

            <div class="info-row" style="margin-top: 5px;">
                <span class="info-label">Buyer PAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_pan; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Buyer GSTIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_gstin; ?></span>
            </div>
            <?php if (!empty($company_cin)): ?>
            <div class="info-row">
                <span class="info-label">Buyer CIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_cin); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($company_tan)): ?>
            <div class="info-row">
                <span class="info-label">Buyer TAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_tan); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-title" style="font-weight: bold; margin-bottom: 5px; font-size: 12px;">Printing Order Details:</div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">S.N.</th>
                <th>SITE / LOCATION</th>
                <th style="width: 70px;">HSN/SAC<br>Code</th>
                <th style="width: 70px;">SIZE</th>
                <th style="width: 70px;">SQFT</th>
                <th style="width: 70px;">MEDIA</th>
                <th style="width: 70px;">Rate/SQFT</th>
                <th style="width: 90px;">Total Cost(₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $net_total = 0;
            foreach ($rates as $idx => $item):
                $sqft = ($item['width'] && $item['height']) ? floatval($item['width']) * floatval($item['height']) : 0;
                $item_total = $item['cost'];
                $net_total += $item_total;
            ?>
            <tr>
                <td><?php echo $idx + 1; ?></td>
                <td style="text-align: left; padding-left: 10px;">
                    <?php if ($item['site_id']): ?>
                        <div style="font-weight: bold;"><?php echo $item['site_name']; ?></div>
                        <div style="font-size: 9px; color: #555;"><?php echo $item['site_code']; ?> • <?php echo $item['location'] ?? $item['city'] ?? ''; ?></div>
                    <?php else: ?>
                        <div style="font-weight: bold;">Generic Printing</div>
                        <div style="font-size: 9px; color: #555;"><?php echo $item['media_type']; ?></div>
                    <?php endif; ?>
                </td>
                <td><?php echo $item['hsn_code'] ?: ''; ?></td>
                <td><?php echo ($item['width'] && $item['height']) ? $item['width'] . "'x" . $item['height'] . "'" : '-'; ?></td>
                <td><?php echo $sqft > 0 ? number_format($sqft) : '-'; ?></td>
                <td style="font-size: 9px;"><?php echo $item['media_type_site']; ?></td>
                <td>₹<?php echo number_format($item['monthly_rate'], 2); ?></td>
                <td style="text-align: right; padding-right: 10px; font-weight: bold;"><?php echo number_format($item_total, 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">Taxable Amount (Total Cost)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($po['po_amount'], 2); ?></td>
            </tr>
            <?php
            $vendor_state = trim($v['state'] ?? '');
            $vendor_gstin = trim($v['gstin'] ?? '');
            $has_gst = !empty($vendor_gstin);
            if (!$has_gst) {
                $cgst_pct = 0;
                $sgst_pct = 0;
                $igst_pct = 0;
            } else {
                $is_interstate = (strcasecmp($vendor_state, 'West Bengal') !== 0 && substr($vendor_gstin, 0, 2) !== '19');
                $cgst_pct = $is_interstate ? 0 : 9;
                $sgst_pct = $is_interstate ? 0 : 9;
                $igst_pct = $is_interstate ? 18 : 0;
            }
            ?>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">CGST (<?php echo $cgst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($po['cgst_amount'], 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">SGST (<?php echo $sgst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($po['sgst_amount'], 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">IGST (<?php echo $igst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($po['igst_amount'], 2); ?></td>
            </tr>
            <tr class="totals-row" style="background: #f9f9f9; border-top: 2px solid #000;">
                <td colspan="7" style="text-align: right; padding-right: 10px; font-size: 12px; height: 30px; vertical-align: middle;">Gross Payable Amount</td>
                <td style="text-align: right; padding-right: 10px; font-size: 12px; vertical-align: middle;"><?php echo number_format($po['total_amount'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="avoid-break">
        <div style="padding: 10px; border-top: 1px solid #000;">
            <strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($po['total_amount']); ?> Only</span>
        </div>

        <div style="padding: 5px 10px; border-top: 1px solid #000; font-size: 8.5px; line-height: 1.25;">
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
                <p style="margin: 2px 0;">- 100% after printing delivery with proofs</p>
                <p style="margin: 2px 0;">- Cheque/NEFT in favor of: <strong><?php echo $v['name']; ?></strong></p>
            </div>
            <div class="footer-right">
                <div>For <strong><?php echo $company_name; ?></strong></div>
                <div style="margin-top: 15px;">
                    <img src="<?php echo BASE_URL; ?>assets/images/<?php echo htmlspecialchars($company_signature); ?>" style="height: 45px; display: block; margin: 0 auto;" onerror="this.style.display='none'">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 5px auto 0;"></div>
                    <div style="font-weight: bold; margin-top: 2px;">Authorised Signatory</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="max-width: 800px; margin: 10px auto; text-align: center; font-size: 9px; color: #888;">
    This is a computer generated Printing Purchase Order and does not require physical signature.
</div>

</body>
</html>
