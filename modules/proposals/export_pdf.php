<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.name as client_name, c.address as client_address, c.city as client_city, c.email as client_email, c.phone as client_phone,
    c.gstin as client_gstin, c.contact_person,
    u.full_name as creator_name
    FROM proposals p
    JOIN partners c ON p.client_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal) {
    die("Proposal not found.");
}

$items = $pdo->prepare("
    SELECT pi.*, s.site_code, s.location, s.city as site_city, s.type as site_type, s.width, s.height, s.light_type
    FROM proposal_items pi
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
");
$items->execute([$id]);
$items = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Proposal - <?php echo $proposal['proposal_number']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #0d9488; --dark: #0f172a; --slate: #64748b; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body { font-family: 'Outfit', sans-serif; color: var(--dark); margin: 0; padding: 40px; line-height: 1.5; font-size: 14px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 50px; border-bottom: 4px solid var(--primary); padding-bottom: 20px; }
        .logo-area h1 { margin: 0; color: var(--primary); font-size: 28px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; }
        .logo-area p { margin: 5px 0 0; color: var(--slate); font-weight: 600; }
        .proposal-info { text-align: right; }
        .proposal-info h2 { margin: 0; font-size: 20px; color: var(--slate); }
        .proposal-info p { margin: 5px 0 0; font-weight: 600; }

        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        .detail-box h3 { font-size: 12px; text-transform: uppercase; color: var(--slate); margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
        .detail-box p { margin: 2px 0; font-weight: 500; }
        .detail-box strong { color: var(--dark); font-weight: 700; font-size: 16px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8fafc; padding: 12px 10px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--slate); border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        
        .total-section { margin-left: auto; width: 300px; }
        .total-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .total-row.grand { border-bottom: none; color: var(--primary); font-size: 20px; font-weight: 800; margin-top: 10px; }

        .footer { margin-top: 80px; font-size: 12px; color: var(--slate); border-top: 1px solid #e2e8f0; padding-top: 20px; text-align: center; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; background: #fff; padding: 10px; border-radius: 8px; shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; z-index: 100;">
        <button onclick="window.print()" style="background: var(--primary); color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 700;">Print to PDF</button>
    </div>

    <div class="header">
        <div class="logo-area">
            <img src="../../assets/img/LOGO.png" style="height: 50px; width: auto;" alt="Easy Outdoor Logo">
        </div>
        <div class="proposal-info">
            <h2>PROPOSAL</h2>
            <p>#<?php echo $proposal['proposal_number']; ?></p>
            <p>Date: <?php echo date('d M Y', strtotime($proposal['created_at'])); ?></p>
        </div>
    </div>

    <div class="details-grid">
        <div class="detail-box">
            <h3>From: Easy Outdoor</h3>
            <strong><?php echo COMPANY_NAME; ?></strong>
            <p><?php echo COMPANY_ADDRESS; ?></p>
            <p><?php echo COMPANY_CITY; ?></p>
            <p style="margin-top: 5px; font-weight: 700;">GSTIN: <?php echo COMPANY_GSTIN; ?></p>
            <p>Ph: <?php echo COMPANY_PHONE; ?></p>
        </div>
        <div class="detail-box">
            <h3>Prepared For</h3>
            <strong><?php echo $proposal['client_name']; ?></strong>
            <p><?php echo $proposal['client_address']; ?></p>
            <p><?php echo $proposal['client_city']; ?></p>
            <?php if ($proposal['client_gstin']): ?>
                <p style="margin-top: 5px; font-weight: 700;">GSTIN: <?php echo $proposal['client_gstin']; ?></p>
            <?php endif; ?>
            <p>Attn: <?php echo $proposal['contact_person'] ?? 'Proprietor'; ?></p>
        </div>
    </div>

    <div class="details-grid" style="grid-template-columns: 1fr; margin-bottom: 20px;">
        <div class="detail-box" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <h3 style="border: none; margin-bottom: 5px;">Campaign Summary</h3>
            <div style="display: flex; gap: 40px;">
                <div>
                    <span style="font-size: 11px; color: var(--slate); text-transform: uppercase;">Campaign Name</span>
                    <div style="font-weight: 700; font-size: 16px;"><?php echo $proposal['campaign_name']; ?></div>
                </div>
                <div>
                    <span style="font-size: 11px; color: var(--slate); text-transform: uppercase;">Duration</span>
                    <div style="font-weight: 600;"><?php echo date('d M Y', strtotime($proposal['start_date'])); ?> - <?php echo date('d M Y', strtotime($proposal['end_date'])); ?></div>
                </div>
                <div style="margin-left: auto; text-align: right;">
                    <span style="font-size: 11px; color: var(--slate); text-transform: uppercase;">Account Manager</span>
                    <div style="font-weight: 600;"><?php echo $proposal['creator_name']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Asset Details</th>
                <th>Location / City</th>
                <th>Size</th>
                <th>Days</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $sn=1; foreach ($items as $item): ?>
            <tr>
                <td><?php echo $sn++; ?></td>
                <td>
                    <div style="font-weight: 700; color: var(--dark);"><?php echo $item['site_type']; ?></div>
                    <div style="font-size: 11px; color: var(--slate);">Code: <?php echo $item['site_code']; ?></div>
                </td>
                <td>
                    <div style="font-weight: 600;"><?php echo $item['location']; ?></div>
                    <div style="font-size: 11px; color: var(--slate);"><?php echo $item['site_city']; ?></div>
                </td>
                <td><?php echo $item['width']; ?>' x <?php echo $item['height']; ?>'</td>
                <td><?php echo $item['days']; ?></td>
                <td style="text-align: right; font-weight: 700;">₹<?php echo number_format($item['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span>Subtotal</span>
            <strong>₹<?php echo number_format($proposal['total_amount'], 2); ?></strong>
        </div>
        <div class="total-row">
            <span>Tax (GST)</span>
            <strong>₹<?php echo number_format($proposal['tax_amount'], 2); ?></strong>
        </div>
        <div class="total-row grand">
            <span>Grand Total</span>
            <span>₹<?php echo number_format($proposal['grand_total'], 2); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>This is a computer generated proposal. For any queries, please contact our support team.</p>
        <p>Thank you for your business!</p>
    </div>

    <script>
        window.onload = function() {
            // setTimeout(() => { window.print(); }, 500);
        }
    </script>
</body>
</html>
