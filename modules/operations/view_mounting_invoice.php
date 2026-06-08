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
                   s.hsn_code, s.mounting_hsn, c.name as client_name, c.city as client_city
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
                   s.hsn_code, s.mounting_hsn, c.name as client_name, c.city as client_city
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
                           s.hsn_code, s.mounting_hsn, c.name as client_name, c.city as client_city
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
                           s.hsn_code, s.mounting_hsn, c.name as client_name, c.city as client_city
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
$invoiceNo  = $first['custom_invoice_number'] ?: ($first['po_number'] ?? 'DRAFT');
$invoiceDate = $first['invoice_date'] ?: $first['created_at'];
$gstType    = $first['gst_type'] ?? 'igst';
$isFinalInv = $first['is_final_invoice'] ?? 0;

// Company settings — uses active session entity
$co                 = resolveCompanyDetails();
$company_name       = $co['name'];
$company_gstin      = $co['gstin'];
$company_pan        = $co['pan'];
$company_address    = $co['address'];
$company_phone      = $co['phone'];
$company_email      = $co['email'];
$company_letterhead = $co['letterhead'];
$company_signature  = $co['signature'];
$company_logo       = $co['logo'];

// Calculate totals
$subTotal = 0;
foreach ($rows as $row) {
    $sqft = ($row['width'] ?? 0) * ($row['height'] ?? 0);
    $subTotal += $sqft * $row['rate_per_sqft'];
}
$cgst = $sgst = $igst = 0;
if ($gstType === 'igst') {
    $igst = round($subTotal * 0.18, 2);
} else {
    $cgst = $sgst = round($subTotal * 0.09, 2);
}
$totalAmt = $subTotal + $cgst + $sgst + $igst;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mounting Invoice - <?php echo htmlspecialchars($invoiceNo); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Arial', sans-serif; font-size: 12px; color: #1e293b; background: #f8fafc; }
        .invoice-wrapper { max-width: 900px; margin: 20px auto; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        .inv-header { background: #0d9488; color: white; padding: 24px 32px; display: flex; justify-content: space-between; align-items: flex-start; }
        .inv-header h1 { font-size: 22px; font-weight: 900; letter-spacing: 1px; margin-bottom: 4px; }
        .inv-header .inv-type { background: rgba(255,255,255,0.2); display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .inv-meta { text-align: right; font-size: 11px; line-height: 1.8; }
        .inv-meta strong { font-size: 13px; }
        .inv-body { padding: 24px 32px; }
        .party-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .party-box { background: #f8fafc; border-radius: 8px; padding: 16px; border-left: 3px solid #0d9488; }
        .party-box h4 { font-size: 9px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .party-box .name { font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .party-box p { font-size: 11px; color: #64748b; line-height: 1.6; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items th { background: #0d9488; color: white; padding: 10px 12px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.items td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 11px; vertical-align: middle; }
        table.items tr:nth-child(even) td { background: #f8fafc; }
        .hsn-badge { background: #f0fdfa; color: #0d9488; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 800; font-family: monospace; }
        .totals-section { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .totals-box { width: 300px; }
        .tot-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
        .tot-row.grand { background: #0d9488; color: white; padding: 10px 12px; border-radius: 6px; margin-top: 8px; font-weight: 900; font-size: 14px; }
        .footer-notes { background: #f8fafc; border-radius: 8px; padding: 16px; margin-top: 20px; font-size: 11px; color: #64748b; }
        .sign-section { display: flex; justify-content: space-between; margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
        .sign-box { text-align: center; }
        .sign-box .sign-line { width: 150px; border-bottom: 1px solid #475569; margin: 40px auto 6px; }
        .sign-box p { font-size: 10px; font-weight: 700; color: #475569; }
        .print-bar { background: white; border-bottom: 1px solid #e2e8f0; padding: 12px 32px; display: flex; gap: 1rem; justify-content: flex-end; }
        .btn-print { background: #0d9488; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 12px; display: flex; align-items: center; gap: 6px; }
        .btn-back  { background: #f1f5f9; color: #475569; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 12px; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        @media print {
            .print-bar { display: none !important; }
            body { background: white; }
            .invoice-wrapper { box-shadow: none; margin: 0; border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="print-bar">
    <a href="mounting.php" class="btn-back">← Back to List</a>
    <button class="btn-print" onclick="window.print()">🖨 Print Invoice</button>
</div>

<div class="invoice-wrapper">

    <!-- Header -->
    <div class="inv-header">
        <div>
            <?php if ($company_logo): ?>
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_logo; ?>" style="height:40px; margin-bottom:8px; filter:brightness(10); display:block;">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($company_name); ?></h1>
            <p style="font-size:11px; opacity:0.85; margin-top:4px;"><?php echo htmlspecialchars($company_address); ?></p>
            <p style="font-size:11px; opacity:0.85;">📞 <?php echo $company_phone; ?> &nbsp;|&nbsp; ✉ <?php echo $company_email; ?></p>
            <p style="font-size:11px; opacity:0.85; margin-top:4px;">GSTIN: <strong><?php echo $company_gstin; ?></strong> &nbsp;|&nbsp; PAN: <?php echo $company_pan; ?></p>
        </div>
        <div class="inv-meta">
            <div class="inv-type"><?php echo $isFinalInv ? 'Final Tax Invoice' : 'Proforma Invoice'; ?> — Mounting</div>
            <p style="margin-top:10px;"><strong style="font-size:15px;"><?php echo htmlspecialchars($invoiceNo); ?></strong></p>
            <p>Date: <?php echo date('d M Y', strtotime($invoiceDate)); ?></p>
            <?php if ($first['po_number']): ?>
            <p>PO Ref: <?php echo htmlspecialchars($first['po_number']); ?></p>
            <?php endif; ?>
            <?php if ($first['customer_po_no']): ?>
            <p>Client PO: <?php echo htmlspecialchars($first['customer_po_no']); ?><?php if($first['customer_po_date']) echo ' (' . date('d M Y', strtotime($first['customer_po_date'])) . ')'; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="inv-body">

        <!-- Bill To / From -->
        <div class="party-grid">
            <div class="party-box">
                <h4>Bill From</h4>
                <div class="name"><?php echo htmlspecialchars($company_name); ?></div>
                <p><?php echo htmlspecialchars($company_address); ?></p>
                <p>GSTIN: <?php echo $company_gstin; ?></p>
            </div>
            <div class="party-box">
                <h4>Bill To</h4>
                <div class="name"><?php echo htmlspecialchars($first['client_name']); ?></div>
                <?php if ($first['client_city']): ?><p><?php echo htmlspecialchars($first['client_city']); ?></p><?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items">
            <thead>
                <tr>
                    <th style="width:35px;">#</th>
                    <th>Site / Location</th>
                    <th style="width:90px;">Mounting HSN</th>
                    <th style="width:90px;">Size (ft)</th>
                    <th style="width:70px;">SQFT</th>
                    <th style="width:80px;">Type</th>
                    <th style="width:90px; text-align:right;">Rate/SQFT</th>
                    <th style="width:100px; text-align:right;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
            <?php $sn=1; foreach ($rows as $row):
                $sqft   = ($row['width'] ?? 0) * ($row['height'] ?? 0);
                $amount = $sqft * $row['rate_per_sqft'];
                $hsn    = $row['mounting_hsn'] ?: $row['hsn_code'] ?: '';
            ?>
            <tr>
                <td><?php echo $sn++; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($row['site_name'] ?? 'Generic'); ?></strong>
                    <?php if ($row['site_code']): ?><br><span style="font-size:10px;color:#f97316;font-weight:700;"><?php echo $row['site_code']; ?></span><?php endif; ?>
                    <?php if ($row['location']): ?><br><span style="font-size:10px;color:#94a3b8;"><?php echo htmlspecialchars($row['location']); ?>, <?php echo htmlspecialchars($row['city'] ?? ''); ?></span><?php endif; ?>
                </td>
                <td><?php echo $hsn ? '<span class="hsn-badge">' . htmlspecialchars($hsn) . '</span>' : '—'; ?></td>
                <td><?php echo $row['width'] ?? 0; ?>' × <?php echo $row['height'] ?? 0; ?>'</td>
                <td><?php echo number_format($sqft); ?></td>
                <td><?php echo htmlspecialchars($row['mounting_type'] ?? ''); ?></td>
                <td style="text-align:right;">₹<?php echo number_format($row['rate_per_sqft'], 2); ?></td>
                <td style="text-align:right; font-weight:700;">₹<?php echo number_format($amount, 2); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-box">
                <div class="tot-row"><span>Sub Total</span><span>₹<?php echo number_format($subTotal, 2); ?></span></div>
                <?php if ($gstType === 'igst'): ?>
                <div class="tot-row"><span>IGST (18%)</span><span>₹<?php echo number_format($igst, 2); ?></span></div>
                <?php else: ?>
                <div class="tot-row"><span>CGST (9%)</span><span>₹<?php echo number_format($cgst, 2); ?></span></div>
                <div class="tot-row"><span>SGST (9%)</span><span>₹<?php echo number_format($sgst, 2); ?></span></div>
                <?php endif; ?>
                <div class="tot-row grand"><span>Grand Total</span><span>₹<?php echo number_format($totalAmt, 2); ?></span></div>
            </div>
        </div>

        <!-- Signature -->
        <div class="sign-section">
            <div class="sign-box">
                <div class="sign-line"></div>
                <p>Authorised Signatory</p>
                <p style="margin-top:2px; color:#0d9488;"><?php echo htmlspecialchars($company_name); ?></p>
            </div>
            <div style="text-align:right; font-size:11px; color:#64748b;">
                <p>This is a computer-generated invoice.</p>
                <p>Printed on: <?php echo date('d M Y, h:i A'); ?></p>
            </div>
        </div>

        <!-- Notes -->
        <div class="footer-notes">
            <strong>Terms &amp; Conditions:</strong>
            <p style="margin-top:4px;">Payment due within 30 days. All disputes subject to local jurisdiction.</p>
        </div>

    </div><!-- /inv-body -->
</div><!-- /invoice-wrapper -->
</body>
</html>
