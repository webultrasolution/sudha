<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Prevent timezone warnings
date_default_timezone_set('Asia/Kolkata');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    die("Booking ID is required.");
}

// Check Invoice Approval Status
$stmtInvCheck = $pdo->prepare("SELECT * FROM invoices WHERE booking_id = ? AND type = 'tax' LIMIT 1");
$stmtInvCheck->execute([$booking_id]);
$invoiceData = $stmtInvCheck->fetch();

if (session_status() === PHP_SESSION_NONE)
    session_start();
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if ($invoiceData && ($invoiceData['approval_status'] ?? '') === 'pending_approval' && !$isAdmin) {
    die("<div style='font-family: sans-serif; padding: 2rem; text-align: center; color: #64748b;'><h3>Access Denied</h3><p>This invoice is pending admin approval and cannot be viewed or printed yet.</p></div>");
}

// Fetch Booking & Client Details
$stmt = $pdo->prepare("
    SELECT b.*, c.name as client_name, c.address as client_address, c.gstin as client_gstin, c.state as client_state, c.additional_gst, c.contact_person, c.phone, c.pan as client_pan
    FROM bookings b
    JOIN partners c ON b.client_id = c.id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$b = $stmt->fetch();

if (!$b) {
    die("Booking not found.");
}

// Override with selected Billing GSTIN details if applicable
if (!empty($b['billing_gstin']) && $b['billing_gstin'] !== $b['client_gstin'] && !empty($b['additional_gst'])) {
    $addGsts = json_decode($b['additional_gst'], true);
    if (is_array($addGsts)) {
        foreach ($addGsts as $g) {
            if ($g['gstin'] === $b['billing_gstin']) {
                $b['client_gstin'] = $g['gstin'];
                $b['client_address'] = $g['address'];
                $b['client_state'] = $g['state'];
                break;
            }
        }
    }
}

// Fetch Items
$stmtItems = $pdo->prepare("
    SELECT bi.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.light_type, s.hsn_code, s.type as media_type
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    WHERE bi.booking_id = ?
");
$stmtItems->execute([$booking_id]);
$items = $stmtItems->fetchAll();

// Company Settings
$company_name = getSetting('company_name', 'Sudha Creative & Advertising');
$company_gstin = getSetting('company_gstin', '19AHRPT4740Q1Z6');
$company_pan = getSetting('company_pan', 'AHRPT4740Q');
$company_address = getSetting('company_address', 'Deshbandhu Para, P.O - Jhaljhalia, Dist - Malda - 732102, West Bengal');
$company_phone = getSetting('company_phone', '8158854313');
$company_email = getSetting('company_email', 'sudhacreativemalda@gmail.com');
$company_letterhead = getSetting('company_letterhead');
$company_signature = getSetting('company_signature', 'signature.png');

// Tax Calculation Logic
$subtotal = $b['total_amount'];
$isInterState = (strtolower(trim($b['client_state'])) !== 'west bengal');
$gst = calculateGST($subtotal, $isInterState);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            color: #000;
            font-size: 11px;
            line-height: 1.3;
        }

        .invoice-wrapper {
            border: 1px solid #000;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
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
        }

        .info-col {
            flex: 1;
            padding: 10px;
        }

        .info-col:first-child {
            border-right: 1px solid #000;
        }

        .info-row {
            display: flex;
            margin-bottom: 3px;
        }

        .info-label {
            width: 80px;
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

        .table-title {
            background: #f0f0f0;
            border-bottom: 1px solid #000;
            text-align: center;
            font-weight: bold;
            padding: 3px;
            letter-spacing: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-weight: bold;
        }

        th:last-child {
            border-right: none;
        }

        td {
            border-bottom: 1px solid #d0d0d0;
            border-right: 1px solid #000;
            padding: 8px 5px;
            vertical-align: top;
            text-align: center;
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
            border-top: 1px solid #000;
        }

        .footer-left {
            flex: 2;
            padding: 10px;
            border-right: 1px solid #000;
            min-height: 100px;
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
        }

        @media print {
            .btn-print {
                display: none;
            }

            body {
                padding: 0;
            }

            .invoice-wrapper {
                border: none;
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <button class="btn-print" onclick="window.print()">PRINT INVOICE</button>

    <div class="invoice-wrapper">
        <!-- Header with Letterhead or Manual Info -->
        <?php if ($company_letterhead): ?>
            <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_letterhead; ?>"
                style="width: 100%; height: auto; display: block; border-bottom: 1px solid #000;">
        <?php else: ?>
            <div class="header-top" style="text-align: center;">
                <h2 style="margin: 0; text-transform: uppercase;"><?php echo $company_name; ?></h2>
                <p><?php echo $company_address; ?></p>
                <p>Ph: <?php echo $company_phone; ?> Email: <?php echo $company_email; ?></p>
            </div>
        <?php endif; ?>

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
                    <span
                        class="info-value"><?php echo $b['customer_po_date'] ? date('d.m.Y', strtotime($b['customer_po_date'])) : 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Campaign</span>
                    <span class="info-sep">:</span>
                    <span
                        class="info-value"><strong><?php echo htmlspecialchars($b['campaign_name'] ?? 'N/A'); ?></strong></span>
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
                    <span
                        class="info-value"><?php echo !empty($invoiceData['invoice_number']) ? $invoiceData['invoice_number'] : ('SCR/' . date('y', strtotime($b['created_at'])) . '-' . date('y', strtotime($b['created_at'] . ' +1 year')) . '/' . str_pad($b['id'], 3, '0', STR_PAD_LEFT)); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Invoice Date</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><?php echo (!empty($invoiceData['invoice_date']) && $invoiceData['invoice_date'] !== '0000-00-00') ? date('d-m-Y', strtotime($invoiceData['invoice_date'])) : date('d-m-Y'); ?></span>
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
            </div>
        </div>

        <div class="table-title">DISPLAY:</div>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">S.N.</th>
                    <th>LOCATION</th>
                    <th style="width: 70px;">HSN/SAC<br>Code</th>
                    <th style="width: 70px;">SIZE</th>
                    <th style="width: 100px;">PERIOD</th>
                    <th style="width: 90px;">Amount(₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item):
                    // Safe Date Handling
                    $sDate = (!empty($item['start_date']) && $item['start_date'] != '0000-00-00') ? $item['start_date'] : $b['start_date'];
                    $eDate = (!empty($item['end_date']) && $item['end_date'] != '0000-00-00') ? $item['end_date'] : $b['end_date'];
                    ?>
                    <tr>
                        <td><?php echo $idx + 1; ?></td>
                        <td style="text-align: left; padding-left: 10px;">
                            <div style="font-weight: bold;">
                                <?php echo htmlspecialchars($item['city'] ?? ''); ?>,
                                <?php echo htmlspecialchars($item['site_name'] ?? ''); ?>,
                                <?php echo htmlspecialchars($item['location'] ?? ''); ?>,
                                <?php echo htmlspecialchars($item['media_type'] ?? ''); ?>
                            </div>
                        </td>
                        <td><?php echo $item['hsn_code'] ?: '998366'; ?></td>
                        <td><?php echo $item['width']; ?>'x<?php echo $item['height']; ?>'</td>
                        <td style="font-size: 9px;">
                            <?php echo date('d.m.Y', strtotime($sDate)); ?> to<br>
                            <?php echo date('d.m.Y', strtotime($eDate)); ?>
                        </td>
                        <td style="text-align: right; padding-right: 10px; font-weight: bold;">
                            <?php echo number_format($item['amount'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr class="gst-row">
                    <td colspan="5" style="text-align: right; padding-right: 10px;">Taxable Amount</td>
                    <td style="text-align: right; padding-right: 10px;"><?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <?php if ($isInterState): ?>
                    <tr class="gst-row">
                        <td colspan="5" style="text-align: right; padding-right: 10px;">IGST (18%)</td>
                        <td style="text-align: right; padding-right: 10px;"><?php echo number_format($gst['igst'], 2); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <tr class="gst-row">
                        <td colspan="5" style="text-align: right; padding-right: 10px;">CGST (9%)</td>
                        <td style="text-align: right; padding-right: 10px;"><?php echo number_format($gst['cgst'], 2); ?>
                        </td>
                    </tr>
                    <tr class="gst-row">
                        <td colspan="5" style="text-align: right; padding-right: 10px;">SGST (9%)</td>
                        <td style="text-align: right; padding-right: 10px;"><?php echo number_format($gst['sgst'], 2); ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr class="totals-row">
                    <td colspan="5" style="text-align: right; padding-right: 10px; font-size: 12px;">Total Invoice Value
                    </td>
                    <td style="text-align: right; padding-right: 10px; font-size: 12px;">
                        <?php echo number_format($b['grand_total'], 2); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div style="padding: 10px; border-top: 1px solid #000;">
            <strong>Amount in Words:</strong> <span
                style="text-transform: capitalize;"><?php echo amountInWords($b['grand_total']); ?> Only</span>
        </div>

        <div class="footer">
            <div class="footer-left">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 5px;">Bank Details:</div>
                <div style="line-height: 1.4;">
                    <?php echo nl2br(getSetting('company_bank_details', "A/C Name: $company_name\nBank: STATE BANK OF INDIA\nA/C No: 1234567890123\nIFSC: SBIN0000123")); ?>
                </div>
            </div>
            <div class="footer-right">
                <div>For <strong><?php echo $company_name; ?></strong></div>
                <div style="margin-top: 30px;">
                    <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_signature; ?>"
                        style="height: 40px; display: block; margin: 0 auto;" onerror="this.style.display='none'">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 5px auto 0;"></div>
                    <div style="font-weight: bold; margin-top: 2px;">Authorised Signatory</div>
                </div>
            </div>
        </div>
    </div>

    <div style="max-width: 800px; margin: 10px auto; text-align: center; font-size: 9px; color: #888;">
        This is a computer generated invoice and does not require physical signature.
    </div>

</body>

</html>