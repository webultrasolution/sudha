<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

requirePermission('clients', 'view');

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$po_number = isset($_GET['po_number']) ? clean($_GET['po_number']) : null;
$rate_ids  = isset($_GET['rate_ids']) && is_array($_GET['rate_ids']) ? $_GET['rate_ids'] : [];
$isFinal   = isset($_GET['final']) && $_GET['final'] == '1';

try {
    if ($po_number) {
        $stmt = $pdo->prepare("
            SELECT r.*, s.name as site_name, s.site_code, s.width, s.height, s.location, s.city, s.state,
                   s.hsn_code, s.mounting_hsn, c.name as client_name, c.city as client_city,
                   c.address as client_address, c.gstin as client_gstin, c.state as client_state,
                   c.pan as client_pan, c.phone as client_phone, c.additional_gst
            FROM client_mounting_rates r
            LEFT JOIN sites s ON r.site_id = s.id
            JOIN partners c ON r.client_id = c.id
            WHERE r.po_number = ? AND r.client_id = ?
        ");
        $stmt->execute([$po_number, $client_id]);
    } elseif (!empty($rate_ids)) {
        $in   = str_repeat('?,', count($rate_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT r.*, s.name as site_name, s.site_code, s.width, s.height, s.location, s.city, s.state,
                   s.hsn_code, s.mounting_hsn, c.name as client_name, c.city as client_city,
                   c.address as client_address, c.gstin as client_gstin, c.state as client_state,
                   c.pan as client_pan, c.phone as client_phone, c.additional_gst
            FROM client_mounting_rates r
            LEFT JOIN sites s ON r.site_id = s.id
            JOIN partners c ON r.client_id = c.id
            WHERE r.id IN ($in)
        ");
        $stmt->execute($rate_ids);
    } else {
        die("Invalid request.");
    }
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'mounting_hsn') !== false) {
        try {
            $pdo->exec("ALTER TABLE sites ADD COLUMN mounting_hsn VARCHAR(50) DEFAULT NULL AFTER hsn_code");
            if ($po_number) {
                $stmt = $pdo->prepare("
                    SELECT r.*, s.name as site_name, s.site_code, s.width, s.height, s.location, s.city, s.state,
                           s.hsn_code, s.mounting_hsn, c.name as client_name, c.city as client_city,
                           c.address as client_address, c.gstin as client_gstin, c.state as client_state,
                           c.pan as client_pan, c.phone as client_phone, c.additional_gst
                    FROM client_mounting_rates r
                    LEFT JOIN sites s ON r.site_id = s.id
                    JOIN partners c ON r.client_id = c.id
                    WHERE r.po_number = ? AND r.client_id = ?
                ");
                $stmt->execute([$po_number, $client_id]);
            } elseif (!empty($rate_ids)) {
                $in   = str_repeat('?,', count($rate_ids) - 1) . '?';
                $stmt = $pdo->prepare("
                    SELECT r.*, s.name as site_name, s.site_code, s.width, s.height, s.location, s.city, s.state,
                           s.hsn_code, s.mounting_hsn, c.name as client_name, c.city as client_city,
                           c.address as client_address, c.gstin as client_gstin, c.state as client_state,
                           c.pan as client_pan, c.phone as client_phone, c.additional_gst
                    FROM client_mounting_rates r
                    LEFT JOIN sites s ON r.site_id = s.id
                    JOIN partners c ON r.client_id = c.id
                    WHERE r.id IN ($in)
                ");
                $stmt->execute($rate_ids);
            }
            $rows = $stmt->fetchAll();
        } catch (Exception $ex) {
            throw $e;
        }
    } else {
        throw $e;
    }
}
if (empty($rows)) die("No records found.");

$first      = $rows[0];

// Block view if not approved and user is not admin
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
if (!$isAdmin && $first['approval_status'] !== 'approved') {
    die("<div style='padding: 50px; text-align: center; font-family: sans-serif;'><h1 style='color: #ef4444;'>Access Denied</h1><p>This Client Mounting Invoice is awaiting admin approval and cannot be viewed yet.</p></div>");
}

$invoiceNo  = $first['custom_invoice_number'] ?: ($first['po_number'] ?? 'DRAFT');
$invoiceDate = $first['invoice_date'] ?: $first['created_at'];
$isFinalInv = $first['is_final_invoice'] ?? 0;

$billing_gst = $first['billing_gstin'] ?? '';
$client_gstin = $first['client_gstin'] ?: 'N/A';
$clientAddress = !empty($first['client_address']) ? $first['client_address'] : ($first['client_city'] ?: 'N/A');
$clientState = $first['client_state'] ?? '';

if (!empty($billing_gst)) {
    if ($billing_gst === $first['client_gstin']) {
        $client_gstin = $first['client_gstin'];
        $clientAddress = !empty($first['client_address']) ? $first['client_address'] : ($first['client_city'] ?: 'N/A');
        $clientState = $first['client_state'] ?? '';
    } else {
        if (!empty($first['additional_gst'])) {
            $extra = json_decode($first['additional_gst'], true);
            if (is_array($extra)) {
                foreach ($extra as $item) {
                    if (isset($item['gstin']) && $item['gstin'] === $billing_gst) {
                        $client_gstin = $billing_gst;
                        $clientAddress = $item['address'] ?? '';
                        if (!empty($item['city'])) {
                            $clientAddress .= (!empty($clientAddress) ? ", " : "") . $item['city'];
                        }
                        if (!empty($item['district'])) {
                            $clientAddress .= (!empty($clientAddress) ? ", " : "") . $item['district'];
                        }
                        $clientState = $item['state'] ?? '';
                        break;
                    }
                }
            }
        }
    }
}
$isInterState = (strtolower(trim($clientState)) !== 'west bengal' && substr($client_gstin, 0, 2) !== '19');

// Company settings — uses active session entity
$co                 = resolveCompanyDetails();
$company_name       = $co['name'];
$company_gstin      = $co['gstin'];
$company_pan        = $co['pan'];
$company_address    = $co['address'];
$company_phone      = $co['phone'];
$company_email      = $co['email'];
$company_logo       = $co['logo'];
$company_letterhead = $co['letterhead'];
$company_signature  = $co['signature'];
$company_bank_details    = $co['bank_details'];
$company_terms           = $isFinalInv ? $co['invoice_terms'] : $co['terms_conditions'];
$company_msme            = $co['msme_number'];
$company_cin             = $co['cin'] ?? '';
$company_tan             = $co['tan'] ?? '';

// Derive short name (first 2 words) for large header text
$name_words       = explode(' ', trim($company_name));
$company_short    = strtoupper(implode(' ', array_slice($name_words, 0, 2)));
$company_full_uc  = strtoupper($company_name);

// Calculate totals
$subTotal = 0;
foreach ($rows as $row) {
    $sqft = ($row['width'] ?? 0) * ($row['height'] ?? 0);
    $subTotal += $sqft * $row['rate_per_sqft'];
}

if ($isFinalInv) {
    $cgst = floatval($first['cgst'] ?? 0);
    $sgst = floatval($first['sgst'] ?? 0);
    $igst = floatval($first['igst'] ?? 0);
    $totalAmt = floatval($first['total_amount'] ?? 0);
} else {
    if ($isInterState) {
        $igst = round($subTotal * 0.18, 2);
        $cgst = 0;
        $sgst = 0;
    } else {
        $cgst = round($subTotal * 0.09, 2);
        $sgst = round($subTotal * 0.09, 2);
        $igst = 0;
    }
    $totalAmt = $subTotal + $cgst + $sgst + $igst;
}

$cgst_pct = $isInterState ? 0 : 9;
$sgst_pct = $isInterState ? 0 : 9;
$igst_pct = $isInterState ? 18 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Mounting Invoice - <?php echo htmlspecialchars($first['client_name'], ENT_QUOTES, 'UTF-8', false); ?></title>
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

<button class="btn-print" onclick="window.print()">PRINT MOUNTING PO</button>
<a class="btn-back" href="mounting.php?client_id=<?php echo $client_id; ?>">← BACK TO SELECTION</a>

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
                <?php if (!empty($first['customer_po_no'])): ?>
                <div class="info-row">
                    <span class="info-label">Client PO</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($first['customer_po_no']); ?></span>
                </div>
                <?php if (!empty($first['customer_po_date']) && $first['customer_po_date'] !== '0000-00-00'): ?>
                <div class="info-row">
                    <span class="info-label">PO Date</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo date('d-m-Y', strtotime($first['customer_po_date'])); ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php
                $camp_brand = [];
                if (!empty($first['campaign_name'])) $camp_brand[] = trim($first['campaign_name']);
                if (!empty($first['brand_name'])) $camp_brand[] = trim($first['brand_name']);
                $display_camp_brand = implode(' / ', $camp_brand);
                if (!empty($display_camp_brand)): ?>
                <div class="info-row">
                    <span class="info-label">Campaign / Brand</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo htmlspecialchars($display_camp_brand); ?></strong></span>
                </div>
                <?php endif; ?>

                <div class="section-title" style="margin-top: 10px;">Client / Customer:</div>
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 2px;"><?php echo htmlspecialchars($first['client_name'], ENT_QUOTES, 'UTF-8', false); ?></div>
                <div style="width: 250px;"><?php echo htmlspecialchars($clientAddress, ENT_QUOTES, 'UTF-8', false); ?></div>
                
                <div class="info-row" style="margin-top: 5px;">
                    <span class="info-label">GSTIN / UIN</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo htmlspecialchars($client_gstin); ?></strong></span>
                </div>
                <?php if (!empty($clientState)): ?>
                <div class="info-row">
                    <span class="info-label">State / Code</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($clientState); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Contact Person</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($first['contact_person'] ?? '', ENT_QUOTES, 'UTF-8', false); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo htmlspecialchars($first['client_phone'] ?? '', ENT_QUOTES, 'UTF-8', false); ?></span>
                </div>
            </div>
        </div>

        <div class="info-col">
            <div class="info-row">
                <span class="info-label">Invoice Number</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo htmlspecialchars($invoiceNo); ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Invoice Date</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo date('d-m-Y', strtotime($invoiceDate)); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Type</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong>TAX INVOICE (FINAL)</strong></span>
            </div>
            <?php if (!empty($first['remarks'])): ?>
            <div class="info-row">
                <span class="info-label">Remark</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($first['remarks']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row" style="margin-top: 5px;">
                <span class="info-label">PAN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_pan); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">GSTIN</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($company_gstin); ?></span>
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
            <?php $sn=1; foreach ($rows as $row):
                $sqft   = ($row['width'] ?? 0) * ($row['height'] ?? 0);
                $amount = $sqft * $row['rate_per_sqft'];
                $hsn    = $row['mounting_hsn'] ?: $row['hsn_code'] ?: '998366';
            ?>
            <tr>
                <td><?php echo $sn++; ?></td>
                <td style="text-align: left; padding-left: 10px;">
                    <strong><?php echo htmlspecialchars($row['site_name'] ?? 'Generic'); ?></strong>
                    <?php if ($row['site_code']): ?><br><span style="font-size: 9px; color: #555;"><?php echo $row['site_code']; ?> • <?php echo htmlspecialchars($row['city'] ?? ''); ?></span><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($hsn); ?></td>
                <td><?php echo ($row['width'] && $row['height']) ? $row['width'] . "x" . $row['height'] : '-'; ?></td>
                <td><?php echo $sqft > 0 ? number_format($sqft) : '-'; ?></td>
                <td style="font-size: 9px;"><?php echo htmlspecialchars($row['mounting_type'] ?? ''); ?></td>
                <td>₹<?php echo number_format($row['rate_per_sqft'], 2); ?></td>
                <td style="text-align: right; padding-right: 10px; font-weight: bold;"><?php echo number_format($amount, 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">Taxable Amount (Total Cost)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($subTotal, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">CGST (<?php echo $cgst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($cgst, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">SGST (<?php echo $sgst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($sgst, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">IGST (<?php echo $igst_pct; ?>%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($igst, 2); ?></td>
            </tr>
            <tr class="totals-row" style="background: #f9f9f9; border-top: 2px solid #000;">
                <td colspan="7" style="text-align: right; padding-right: 10px; font-size: 12px; height: 30px; vertical-align: middle;">Gross Payable Amount</td>
                <td style="text-align: right; padding-right: 10px; font-size: 12px; vertical-align: middle;"><?php echo number_format($totalAmt, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- HSN/SAC Breakdown Table -->
    <?php
    // Group items by HSN code
    $hsnGroups = [];
    foreach ($rows as $row) {
        $hsn = $row['mounting_hsn'] ?: $row['hsn_code'] ?: '998366';
        $sqft = ($row['width'] ?? 0) * ($row['height'] ?? 0);
        $amount = $sqft * $row['rate_per_sqft'];
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
                $hsnGst = calculateGST($taxableAmt, $isInterState);
            ?>
            <tr>
                <td style="text-align: left; padding-left: 8px; border-left: none;"><?php echo htmlspecialchars($hsn); ?></td>
                <td style="text-align: center;">18%</td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($taxableAmt, 2); ?></td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($hsnGst['cgst'], 2); ?></td>
                <td style="text-align: right; padding-right: 8px;"><?php echo number_format($hsnGst['sgst'], 2); ?></td>
                <td style="text-align: right; padding-right: 8px; border-right: none;"><?php echo number_format($hsnGst['igst'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="avoid-break">
        <div style="padding: 10px; border-top: 1px solid #000; font-size: 11px;">
            <strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($totalAmt); ?> Only</span>
        </div>

        <div style="padding: 5px 10px; border-top: 1px solid #000; font-size: 8.5px; line-height: 1.25;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <div style="font-weight: bold; text-decoration: underline; font-size: 10px;">Terms & Conditions</div>
            </div>
            <div style="margin: 0; line-height: 1.2; white-space: pre-wrap;">
                <?php echo nl2br(htmlspecialchars($company_terms)); ?>
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
                <div>For <strong><?php echo htmlspecialchars($company_name); ?></strong></div>
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
