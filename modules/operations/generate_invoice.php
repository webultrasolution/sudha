<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Prevent timezone warnings
date_default_timezone_set('Asia/Kolkata');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    die("Booking ID is required.");
}

// Fetch Booking & Client Details
$stmt = $pdo->prepare("
    SELECT b.*, c.name as client_name, c.address as client_address, c.gstin as client_gstin, c.state as client_state, c.contact_person, c.phone
    FROM bookings b
    JOIN partners c ON b.client_id = c.id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$b = $stmt->fetch();

if (!$b) {
    die("Booking not found.");
}

// Fetch Items
$stmtItems = $pdo->prepare("
    SELECT bi.*, s.site_code, s.location, s.city, s.width, s.height, s.light_type, s.hsn_code, s.type as media_type
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    WHERE bi.booking_id = ?
");
$stmtItems->execute([$booking_id]);
$items = $stmtItems->fetchAll();

// Company Settings
$company_name = getSetting('company_name', 'Sudha Creative & Advertising');
$company_gstin = getSetting('company_gstin', '27ABCDE1234F1Z5'); // Placeholder if not set
$company_pan = getSetting('company_pan', '');
$company_address = getSetting('company_address', 'Deshbandhu Para, P.O - Jhaljhalia, Dist - Malda - 732102, West Bengal');
$company_logo = getSetting('company_logo', 'logo.png');
$company_letterhead = getSetting('company_letterhead');
$company_signature = getSetting('company_signature', 'signature.png');

// Tax Calculation Logic
$subtotal = $b['total_amount'];
$isInterState = (strtolower(trim($b['client_state'])) !== 'west bengal'); // Default to WB based on company address
$gst = calculateGST($subtotal, $isInterState);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #0f172a; --secondary: #64748b; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #1e293b; }
        .invoice-container { background: white; max-width: 900px; margin: 0 auto; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); position: relative; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; }
        .logo { max-height: 70px; }
        .title-block { text-align: right; }
        .title-block h1 { margin: 0; font-size: 2rem; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; }
        .title-block p { margin: 5px 0 0; color: var(--secondary); font-weight: 600; }
        
        .address-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; }
        .address-block h3 { font-size: 0.85rem; text-transform: uppercase; color: var(--secondary); margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; }
        .address-block p { margin: 3px 0; font-size: 0.9rem; line-height: 1.4; }
        
        .info-bar { display: flex; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 30px; justify-content: space-between; }
        .info-item { text-align: center; flex: 1; border-right: 1px solid #e2e8f0; }
        .info-item:last-child { border-right: none; }
        .info-item label { display: block; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--secondary); margin-bottom: 5px; }
        .info-item span { font-weight: 700; font-size: 0.95rem; color: var(--primary); }

        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8fafc; text-align: left; padding: 12px 15px; font-size: 0.75rem; text-transform: uppercase; color: var(--secondary); border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; }
        
        .totals-section { display: flex; justify-content: flex-end; }
        .totals-table { width: 300px; }
        .totals-table tr td { padding: 8px 0; border-bottom: none; }
        .totals-table tr.grand-total td { border-top: 2px solid #0f172a; padding-top: 15px; font-weight: 800; font-size: 1.1rem; color: var(--primary); }
        
        .footer { margin-top: 50px; border-top: 1px solid #f1f5f9; padding-top: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
        .footer-note { font-size: 0.75rem; color: var(--secondary); line-height: 1.5; max-width: 60%; }
        .signature-block { text-align: center; }
        .signature-img { max-height: 60px; margin-bottom: 5px; }
        .signature-block p { margin: 0; font-size: 0.8rem; font-weight: 800; }

        .btn-print { position: fixed; bottom: 30px; right: 30px; background: #3b82f6; color: white; border: none; padding: 12px 25px; border-radius: 50px; font-weight: 800; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.5); z-index: 100; }
        @media print { .btn-print { display: none; } body { background: white; padding: 0; } .invoice-container { box-shadow: none; padding: 0; } }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">PRINT INVOICE</button>

    <div class="invoice-container">
        <?php if ($company_letterhead): ?>
            <div style="margin-bottom: 2rem;">
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_letterhead; ?>" style="width: 100%; height: auto; display: block; border-radius: 8px;">
            </div>
        <?php else: ?>
            <div class="header">
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_logo; ?>" alt="Logo" class="logo">
                <div class="title-block">
                    <h1>Tax Invoice</h1>
                    <p>GST Compliant Document</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="info-bar">
            <div class="info-item">
                <label>Invoice Number</label>
                <span>INV/<?php echo date('Y'); ?>/<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-item">
                <label>Date of Issue</label>
                <span><?php echo date('d M Y'); ?></span>
            </div>
            <div class="info-item">
                <label>Booking ID</label>
                <span>#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-item">
                <label>Client GSTIN</label>
                <span><?php echo $b['client_gstin'] ?: 'N/A'; ?></span>
            </div>
        </div>

        <div class="address-grid">
            <div class="address-block">
                <h3>Billed To</h3>
                <p><strong><?php echo $b['client_name']; ?></strong></p>
                <p><?php echo $b['client_address']; ?></p>
                <p><?php echo $b['client_state']; ?></p>
                <p>Contact: <?php echo $b['contact_person']; ?> (<?php echo $b['phone']; ?>)</p>
            </div>
            <div class="address-block">
                <h3>Our Details</h3>
                <p><strong><?php echo $company_name; ?></strong></p>
                <p><?php echo $company_address; ?></p>
                <p>GSTIN: <strong><?php echo $company_gstin; ?></strong></p>
                <p>PAN: <strong><?php echo $company_pan; ?></strong></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Asset Description</th>
                    <th>HSN</th>
                    <th>Tenure</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td>
                        <div style="font-weight: 700;"><?php echo $item['location']; ?></div>
                        <div style="font-size: 0.75rem; color: var(--secondary);"><?php echo $item['media_type']; ?> • <?php echo $item['city']; ?> (<?php echo $item['site_code']; ?>)</div>
                    </td>
                    <td><?php echo $item['hsn_code'] ?: '998366'; ?></td>
                    <td><?php echo date('d/m', strtotime($item['start_date'])); ?> - <?php echo date('d/m/y', strtotime($item['end_date'])); ?></td>
                    <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($item['amount']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-table">
                <tr>
                    <td style="color: var(--secondary);">Taxable Value</td>
                    <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($subtotal); ?></td>
                </tr>
                <?php if ($isInterState): ?>
                <tr>
                    <td style="color: var(--secondary);">IGST (18%)</td>
                    <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($gst['igst']); ?></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td style="color: var(--secondary);">CGST (9%)</td>
                    <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($gst['cgst']); ?></td>
                </tr>
                <tr>
                    <td style="color: var(--secondary);">SGST (9%)</td>
                    <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($gst['sgst']); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td>Invoice Total</td>
                    <td style="text-align: right;"><?php echo formatCurrency($b['grand_total']); ?></td>
                </tr>
            </div>
        </div>

        <div style="margin-top: 20px; font-size: 0.85rem;">
            <p><strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($b['grand_total']); ?> Only</span></p>
        </div>

        <div class="footer">
            <div class="footer-note">
                <p><strong>Terms & Conditions:</strong></p>
                <ol style="padding-left: 15px; margin: 0;">
                    <li>Please make payment within 7 days of invoice date.</li>
                    <li>Payment should be made in favor of "<?php echo $company_name; ?>".</li>
                    <li>This is a computer-generated document and requires a digital signature.</li>
                </ol>
            </div>
            <div class="signature-block">
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_signature; ?>" class="signature-img" onerror="this.style.display='none'">
                <p>Authorized Signatory</p>
                <small>For <?php echo $company_name; ?></small>
            </div>
        </div>
    </div>

</body>
</html>
