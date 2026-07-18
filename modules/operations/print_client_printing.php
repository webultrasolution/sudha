<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
if (!$invoice_id) die("Invalid request: Invoice ID is required.");

// Fetch Invoice
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();
if (!$invoice) die("Invoice not found.");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
if (in_array($invoice['approval_status'] ?? '', ['pending_approval', 'rejected']) && !$isAdmin) {
    die("<div style='padding: 50px; text-align: center; font-family: Arial, sans-serif; color: #c2410c; background: #fff; min-height: 100vh; box-sizing: border-box;'>
            <h2>Awaiting Admin Approval</h2>
            <p>This Client Printing Invoice (#" . htmlspecialchars($invoice['invoice_number']) . ") requires Admin approval before it can be printed or viewed.</p>
            <a href='client_printing.php?client_id=" . intval($invoice['client_id']) . "' style='color: #0d9488; font-weight: bold; text-decoration: none;'>Back</a>
         </div>");
}

$client_id = $invoice['entity_id'] ?? $invoice['client_id'] ?? 0;

// Fetch Client Info
$stmtC = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'client'");
$stmtC->execute([$client_id]);
$c = $stmtC->fetch();

// Resolve Billing details if selected
$billing_gst = $invoice['billing_gstin'] ?? '';
$display_gstin = $c['gstin'] ?: 'N/A';
$display_address = $c['address'] ?? '';
$display_state = $c['state'] ?? '';

if (!empty($billing_gst)) {
    if ($billing_gst === $c['gstin']) {
        $display_gstin = $c['gstin'];
        $display_address = $c['address'];
        $display_state = $c['state'];
    } else {
        if (!empty($c['additional_gst'])) {
            $extra = json_decode($c['additional_gst'], true);
            if (is_array($extra)) {
                foreach ($extra as $item) {
                    if (isset($item['gstin']) && $item['gstin'] === $billing_gst) {
                        $display_gstin = $billing_gst;
                        $display_address = $item['address'] ?? '';
                        if (!empty($item['city'])) {
                            $display_address .= (!empty($display_address) ? ", " : "") . $item['city'];
                        }
                        if (!empty($item['district'])) {
                            $display_address .= (!empty($display_address) ? ", " : "") . $item['district'];
                        }
                        $display_state = $item['state'] ?? '';
                        break;
                    }
                }
            }
        }
    }
}

// Fetch Items (Decoded JSON from description)
$stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmtItems->execute([$invoice_id]);
$dbItems = $stmtItems->fetchAll();

$rates = [];
foreach ($dbItems as $dbItem) {
    $rates[] = json_decode($dbItem['description'], true);
}

$po_remark = $invoice['remarks'] ?? '';
$po_number = $invoice['invoice_number'];
$po_date = date('d-m-Y', strtotime($invoice['invoice_date']));
$is_final = false; // Based on original code logic, client_printing was mainly PO or Tax Invoice based on a flag. We can assume standard printing invoice here.

// Company Settings — uses active session entity
$co                 = resolveCompanyDetails();
$company_name       = $co['name'];
$company_gstin      = $co['gstin'];
$company_pan        = $co['pan'];
$company_address    = $co['address'];
$company_phone      = $co['phone'];
$company_email      = $co['email'];
$company_letterhead = $co['letterhead'];
$company_signature  = $co['signature'];
$company_msme       = $co['msme_number'];
$company_cin        = $co['cin'] ?? '';
$company_tan        = $co['tan'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Printing Invoice - <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8', false); ?></title>
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
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; color: #000; font-size: 11px; line-height: 1.3; background: #f1f5f9; }
        .po-wrapper {
            border: 1px solid #d1d5db;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            position: relative;
            background: #fff;
            padding: 12mm 14mm;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            box-sizing: border-box;
            display: block;
        }

        .header-top { border-bottom: 1px solid #000; padding: 5px 10px; }
        .header-top p { margin: 0; }

        .main-info { display: flex; border-bottom: 1px solid #000; }
        .info-col { flex: 1; padding: 6px; }
        .info-col:first-child { border-right: 1px solid #000; }

        .info-row { display: flex; margin-bottom: 3px; }
        .info-label { width: 110px; font-weight: normal; }
        .info-sep { width: 15px; }
        .info-value { flex: 1; font-weight: normal; }

        .section-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; font-style: italic; }
        .table-title { background: #f0f0f0; border-bottom: 1px solid #000; text-align: center; font-weight: bold; padding: 4px; letter-spacing: 2px; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        th { border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 4px; text-align: center; font-weight: bold; background: #fafafa; }
        th:last-child { border-right: none; }
        td { border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 4px 5px; vertical-align: top; text-align: center; }
        td:last-child { border-right: none; }

        .totals-row td { border-bottom: none; border-top: 1px solid #000; font-weight: bold; }
        .footer { display: flex; border: 1px solid #000; }
        .footer-left { flex: 2; padding: 10px; border-right: 1px solid #000; min-height: 80px; }
        .footer-right { flex: 1; padding: 10px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; }

        .btn-print { position: fixed; bottom: 30px; right: 30px; background: #000; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .btn-back { position: fixed; bottom: 30px; right: 180px; background: #6366f1; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; text-decoration: none; font-size: 12px; }
        @media print {
            .btn-print, .btn-back { display: none; }
            body { padding: 0; background: #fff; }
            .po-wrapper {
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

<button class="btn-print" onclick="window.print()">PRINT CLIENT PO</button>
<a class="btn-back" href="client_printing.php?client_id=<?php echo $client_id; ?>">← BACK TO SELECTION</a>

<div class="po-wrapper">
    <!-- Header with Letterhead or Manual Info -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 10px 2px;">
        <div style="flex: 1.4; text-align: left;">
            <?php if ($company_letterhead): ?>
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



    <!-- PO Info -->
    <div class="main-info">
        <div class="info-col">
            <div style="margin-bottom: 15px;">
                 <!-- Removed PO Ref as per user request -->
                <?php if (!empty($rates[0]['customer_po_no'])): ?>
                <div class="info-row">
                    <span class="info-label">Client PO</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($rates[0]['customer_po_no']); ?></span>
                </div>
                <?php if (!empty($rates[0]['customer_po_date']) && $rates[0]['customer_po_date'] !== '0000-00-00'): ?>
                <div class="info-row">
                    <span class="info-label">PO Date</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo date('d-m-Y', strtotime($rates[0]['customer_po_date'])); ?></span>
                </div>
                <?php endif; ?>
                <?php elseif (!empty($rates[0]['email_date']) && $rates[0]['email_date'] !== '0000-00-00'): ?>
                <div class="info-row">
                    <span class="info-label">Email Date</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo date('d-m-Y', strtotime($rates[0]['email_date'])); ?></span>
                </div>
                <?php endif; ?>

                <?php
                $camp_brand = [];
                if (!empty($rates[0]['campaign_name'])) $camp_brand[] = trim($rates[0]['campaign_name']);
                if (!empty($rates[0]['brand_name'])) $camp_brand[] = trim($rates[0]['brand_name']);
                $display_camp_brand = implode(' / ', $camp_brand);
                if (!empty($display_camp_brand)): ?>
                <div class="info-row">
                    <span class="info-label">Campaign / Brand</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo htmlspecialchars($display_camp_brand); ?></strong></span>
                </div>
                <?php endif; ?>

                <div class="section-title" style="margin-top: 10px;">Client / Customer:</div>
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 2px;"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8', false); ?></div>
                <div style="width: 250px;"><?php echo htmlspecialchars($display_address, ENT_QUOTES, 'UTF-8', false); ?></div>
                
                <div class="info-row" style="margin-top: 5px;">
                    <span class="info-label">GSTIN / UIN</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo htmlspecialchars($display_gstin); ?></strong></span>
                </div>
                <?php if (!empty($display_state)): ?>
                <div class="info-row">
                    <span class="info-label">State / Code</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($display_state); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Contact Person</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($c['contact_person'], ENT_QUOTES, 'UTF-8', false); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($c['phone'], ENT_QUOTES, 'UTF-8', false); ?></span>
                </div>
            </div>
        </div>

        <div class="info-col">
            <div class="info-row">
                <span class="info-label">Invoice Number</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo $po_number; ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Invoice Date</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $po_date; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Type</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong>TAX INVOICE (FINAL)</strong></span>
            </div>
            <?php if ($po_remark): ?>
            <div class="info-row">
                <span class="info-label">Remark</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($po_remark); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row" style="margin-top: 5px;">
                <span class="info-label">PAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_pan; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">GSTIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $company_gstin; ?></span>
            </div>
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

    <div class="table-title">TAX INVOICE DETAILS:</div>

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
                $item_total = $sqft * floatval($item['rate_per_sqft']);
                $net_total += $item_total;
            ?>
            <tr>
                <td><?php echo $idx + 1; ?></td>
                <td style="text-align: left; padding-left: 10px;">
                    <?php if ($item['site_id']): ?>
                        <div style="font-weight: bold;"><?php echo $item['site_name']; ?></div>
                        <div style="font-size: 9px; color: #555;"><?php echo $item['site_code']; ?> • <?php echo $item['city'] ?? ''; ?></div>
                    <?php else: ?>
                        <div style="font-weight: bold;">Generic Printing</div>
                        <div style="font-size: 9px; color: #555;"><?php echo $item['media_type']; ?></div>
                    <?php endif; ?>
                </td>
                <td><?php echo $item['hsn_code'] ?: ''; ?></td>
                <td><?php echo ($item['width'] && $item['height']) ? $item['width'] . "'x" . $item['height'] . "'" : '-'; ?></td>
                <td><?php echo $sqft > 0 ? number_format($sqft) : '-'; ?></td>
                <td style="font-size: 9px;"><?php echo $item['media_type_site']; ?></td>
                <td>₹<?php echo number_format($item['rate_per_sqft'], 2); ?></td>
                <td style="text-align: right; padding-right: 10px; font-weight: bold;"><?php echo number_format($item_total, 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">Taxable Amount (Total Cost)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($invoice['sub_total'], 2); ?></td>
            </tr>
            <?php
            $is_interstate = (strcasecmp($display_state, 'West Bengal') !== 0 && substr($display_gstin, 0, 2) !== '19');
            $cgst_pct = $is_interstate ? 0 : 9;
            $sgst_pct = $is_interstate ? 0 : 9;
            $igst_pct = $is_interstate ? 18 : 0;
            ?>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">CGST (<?php echo $cgst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($invoice['cgst'], 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">SGST (<?php echo $sgst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($invoice['sgst'], 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">IGST (<?php echo $igst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($invoice['igst'], 2); ?></td>
            </tr>
            <tr class="totals-row" style="background: #f9f9f9; border-top: 2px solid #000;">
                <td colspan="7" style="text-align: right; padding-right: 10px; font-size: 12px; height: 30px; vertical-align: middle;">Gross Payable Amount</td>
                <td style="text-align: right; padding-right: 10px; font-size: 12px; vertical-align: middle;"><?php echo number_format($invoice['total_amount'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- HSN/SAC Breakdown Table -->
    <?php
    $hsnGroups = [];
    foreach ($rates as $item) {
        $hsn = $item['hsn_code'] ?: '998366';
        $sqft = ($item['width'] && $item['height']) ? floatval($item['width']) * floatval($item['height']) : 0;
        $amount = $sqft * floatval($item['rate_per_sqft']);
        $hsnGroups[$hsn] = ($hsnGroups[$hsn] ?? 0) + $amount;
    }
    ?>
    <table style="border-top: 1.5px solid #000; border-bottom: 1px solid #000; margin-top: 6px; margin-bottom: 10px;">
        <thead>
            <tr style="background: #f2f2f2;">
                <th style="text-align: left; padding-left: 8px; width: 90px; border-top: none;">HSN/SAC</th>
                <th style="width: 70px; border-top: none;">Tax Rate</th>
                <th style="text-align: right; padding-right: 8px; border-top: none;">Taxable Amt.</th>
                <th style="text-align: right; padding-right: 8px; border-top: none;">CGST Amt.</th>
                <th style="text-align: right; padding-right: 8px; border-top: none;">SGST Amt.</th>
                <th style="text-align: right; padding-right: 8px; border-top: none; border-right: none;">IGST Amt.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($hsnGroups as $hsn => $taxableAmt): 
                $hsnCgst = $is_interstate ? 0 : $taxableAmt * 0.09;
                $hsnSgst = $is_interstate ? 0 : $taxableAmt * 0.09;
                $hsnIgst = $is_interstate ? $taxableAmt * 0.18 : 0;
            ?>
            <tr>
                <td style="text-align: left; padding-left: 8px; border-left: none;"><?php echo htmlspecialchars($hsn); ?></td>
                <td style="text-align: center;">18%</td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($taxableAmt, 2); ?></td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($hsnCgst, 2); ?></td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($hsnSgst, 2); ?></td>
                <td style="text-align: right; padding-right: 8px; border-right: none;"><?php echo number_format($hsnIgst, 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="avoid-break">
        <div style="padding: 10px; border-top: 1px solid #000;">
            <strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($invoice['total_amount']); ?> Only</span>
        </div>

        <div style="padding: 5px 10px; border-top: 1px solid #000; font-size: 8.5px; line-height: 1.25;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <div style="font-weight: bold; text-decoration: underline; font-size: 10px;">Terms & Conditions</div>
            </div>
            <div style="margin: 0; line-height: 1.2; white-space: pre-wrap;">
                <?php echo nl2br(htmlspecialchars($co['invoice_terms'])); ?>
            </div>
        </div>

        <div class="footer">
            <div class="footer-left">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 5px;">Bank Details:</div>
                <p style="margin: 2px 0;">Bank Name: <strong><?php echo getSetting('bank_name', 'Your Bank Name'); ?></strong></p>
                <p style="margin: 2px 0;">Account No: <strong><?php echo getSetting('bank_account', 'XXXX-XXXX-XXXX'); ?></strong></p>
                <p style="margin: 2px 0;">IFSC Code: <strong><?php echo getSetting('bank_ifsc', 'XXXX0000000'); ?></strong></p>
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
    This is a computer generated Invoice and does not require physical signature.
</div>

</body>
</html>
