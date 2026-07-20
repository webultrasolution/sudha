<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.name as client_name, c.address as client_address, c.city as client_city, c.email as client_email, c.phone as client_phone,
    c.gstin as client_gstin, c.contact_person,
    COALESCE(u.name, u.full_name) as creator_name
    FROM proposals p
    JOIN partners c ON p.client_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal) {
    die("Proposal/Quotation not found.");
}

// Fetch Items for the table
$items = $pdo->prepare("
    SELECT pi.*, s.name as site_name, s.site_code, s.location, s.city as site_city, s.type as site_type, s.width, s.height, s.light_type
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
    <title>Quotation - <?php echo $proposal['proposal_number']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0d9488;
            --accent: #dc2626;
            --dark: #0f172a;
            --slate: #64748b;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }

        body {
            font-family: 'Outfit', sans-serif;
            color: var(--dark);
            margin: 0;
            padding: 40px;
            line-height: 1.5;
            font-size: 14px;
            background: white;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 4px solid var(--primary);
            padding-bottom: 10px;
        }

        .logo-area img {
            height: 60px;
            width: auto;
        }

        .proposal-info {
            text-align: right;
        }

        .proposal-info h2 {
            margin: 0;
            font-size: 24px;
            color: var(--primary);
            font-weight: 800;
        }

        .proposal-info p {
            margin: 5px 0 0;
            font-weight: 600;
            color: var(--slate);
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 20px;
        }

        .detail-box h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--slate);
            margin-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }

        .detail-box p {
            margin: 2px 0;
            font-weight: 500;
        }

        .detail-box strong {
            color: var(--dark);
            font-weight: 800;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th {
            background: #f8fafc;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--slate);
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 10px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .total-section {
            margin-left: auto;
            width: 300px;
            margin-top: 15px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .total-row.grand {
            border-bottom: none;
            color: var(--primary);
            font-size: 22px;
            font-weight: 900;
            margin-top: 10px;
        }

        .footer {
            margin-top: 40px;
            font-size: 12px;
            color: var(--slate);
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            text-align: center;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print"
        style="position: fixed; top: 20px; right: 20px; background: #fff; padding: 10px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; z-index: 100;">
        <button onclick="window.print()"
            style="background: var(--primary); color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 800; font-family: inherit;">PRINT
            QUOTATION</button>
    </div>

    <?php
    $company = resolveCompanyDetails();
    $logoUrl = (!empty($company['logo']) && file_exists(__DIR__ . '/../../assets/images/' . $company['logo'])) ? BASE_URL . 'assets/images/' . $company['logo'] : BASE_URL . 'assets/img/LOGO.png';
    ?>
    <!-- Cover Page Info -->
    <div class="header">
        <div class="logo-area">
            <img src="<?php echo $logoUrl; ?>" alt="Logo" style="max-height: 60px; object-fit: contain;">
        </div>
        <div class="proposal-info">
            <h2>QUOTATION</h2>
            <p>#<?php echo $proposal['proposal_number']; ?></p>
            <p><?php echo date('d F Y'); ?></p>
        </div>
    </div>

    <div class="details-grid">
        <div class="detail-box">
            <h3>From: <?php echo htmlspecialchars($company['name']); ?></h3>
            <strong><?php echo htmlspecialchars($company['name']); ?></strong>
            <p><?php echo nl2br(htmlspecialchars($company['address'])); ?></p>
            <p style="margin-top: 8px; font-weight: 800; color: var(--primary);">GSTIN: <?php echo htmlspecialchars($company['gstin']); ?></p>
            <?php if (!empty($company['phone'])): ?>
                <p>Ph: <?php echo htmlspecialchars($company['phone']); ?></p>
            <?php endif; ?>
        </div>
        <div class="detail-box">
            <h3>Prepared For</h3>
            <strong><?php echo $proposal['client_name']; ?></strong>
            <p><?php echo $proposal['client_address']; ?></p>
            <p><?php echo $proposal['client_city']; ?></p>
            <?php if ($proposal['client_gstin']): ?>
                <p style="margin-top: 8px; font-weight: 800; color: var(--primary);">GSTIN:
                    <?php echo $proposal['client_gstin']; ?></p>
            <?php endif; ?>
            <p>Attn: <?php echo $proposal['contact_person'] ?? 'Concerned Person'; ?></p>
        </div>
    </div>

    <div
        style="background: #f8fafc; padding: 15px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span
                    style="font-size: 11px; color: var(--slate); text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">Campaign
                    Name</span>
                <div style="font-weight: 900; font-size: 24px; color: var(--dark);">
                    <?php echo $proposal['campaign_name']; ?></div>
            </div>
            <div style="text-align: right;">
                <span
                    style="font-size: 11px; color: var(--slate); text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">Duration</span>
                <div style="font-weight: 700; font-size: 18px;">
                    <?php 
                    if (!empty($proposal['start_date']) && $proposal['start_date'] !== '0000-00-00' && !empty($proposal['end_date']) && $proposal['end_date'] !== '0000-00-00') {
                        echo date('d M Y', strtotime($proposal['start_date'])) . ' - ' . date('d M Y', strtotime($proposal['end_date']));
                    } else {
                        echo 'N/A';
                    }
                    ?></div>
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
                <th style="text-align: right;">Total (INR)</th>
            </tr>
        </thead>
        <tbody>
            <?php $sn = 1;
            foreach ($items as $item): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td>
                        <div style="font-weight: 800; color: var(--dark); font-size: 15px;">
                            <?php echo $item['site_type']; ?></div>
                        <div style="font-size: 11px; color: var(--slate); font-weight: 700;">CODE:
                            <?php echo $item['site_code']; ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 700;"><?php echo htmlspecialchars($item['site_name'] ?? ''); ?></div>
                        <div style="font-size: 11px; color: var(--slate); font-weight: 600;">
                            <?php echo htmlspecialchars($item['site_city'] ?? ''); ?></div>
                    </td>
                    <td style="font-weight: 600;"><?php echo (int) $item['width']; ?>' x <?php echo (int) $item['height']; ?>'
                    </td>
                    <td style="font-weight: 600;"><?php echo $item['days']; ?></td>
                    <td style="text-align: right; font-weight: 800; color: var(--dark);">
                        ₹<?php echo number_format($item['amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span style="font-weight: 600; color: var(--slate);">Subtotal</span>
            <strong style="font-size: 16px;">₹<?php echo number_format($proposal['total_amount'], 2); ?></strong>
        </div>
        <div class="total-row">
            <span style="font-weight: 600; color: var(--slate);">Tax (GST 18%)</span>
            <strong style="font-size: 16px;">₹<?php echo number_format($proposal['tax_amount'], 2); ?></strong>
        </div>
        <div class="total-row grand">
            <span>Grand Total</span>
            <span>₹<?php echo number_format($proposal['grand_total'], 2); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>This is a computer generated quotation. For any queries, please contact our support team.</p>
        <p>Thank you for choosing Sudha Creative!</p>
    </div>

    <script>
        window.onload = function () {
            setTimeout(() => { window.print(); }, 800);
        }
    </script>
</body>

</html>
