<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if (!$booking_id || !$vendor_id) {
    die("Invalid request parameters.");
}

// Fetch Booking & Client Info
$stmtB = $pdo->prepare("
    SELECT b.*, c.name as client_name, p.campaign_name, p.proposal_number
    FROM bookings b
    JOIN partners c ON b.client_id = c.id
    LEFT JOIN proposals p ON b.proposal_id = p.id
    WHERE b.id = ?
");
$stmtB->execute([$booking_id]);
$b = $stmtB->fetch();

// Fetch Vendor Info
$stmtV = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$stmtV->execute([$vendor_id]);
$v = $stmtV->fetch();

if (!$b || !$v) {
    die("Booking or Vendor not found.");
}

// Fetch Items for this vendor in this booking
$vendor_gst_filter = $_GET['vendor_gst'] ?? '';
$itemSql = "
    SELECT bi.*, s.site_code, s.location, s.city, s.width, s.height, s.light_type, s.hsn_code, s.vendor_gst
    FROM booking_items bi
    JOIN sites s ON bi.site_id = s.id
    WHERE bi.booking_id = ? AND s.vendor_id = ?
";
$itemParams = [$booking_id, $vendor_id];

if ($vendor_gst_filter !== '') {
    $itemSql .= " AND (s.vendor_gst = ? OR s.vendor_gst IS NULL AND ? = '')";
    $itemParams[] = $vendor_gst_filter;
    $itemParams[] = $vendor_gst_filter;
}

$stmtItems = $pdo->prepare($itemSql);
$stmtItems->execute($itemParams);
$items = $stmtItems->fetchAll();

if (empty($items)) {
    die("No items found for this vendor in this booking.");
}

$po_number = "PO-" . date('Y', strtotime($b['start_date'])) . "-" . str_pad($b['id'], 3, '0', STR_PAD_LEFT) . "-" . str_pad($v['id'], 2, '0', STR_PAD_LEFT);
$po_date = date('d-M-Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - <?php echo $po_number; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 20px; background: #f1f5f9; color: #1e293b; }
        .po-container { background: white; max-width: 900px; margin: 0 auto; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); position: relative; min-height: 1100px; }
        
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; border-bottom: 3px solid #0f172a; padding-bottom: 1rem; }
        .header-left h1 { margin: 0; font-size: 2rem; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 2px; }
        .logo { width: 120px; height: auto; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .info-section { border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; }
        .info-title { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }
        .info-content p { margin: 0.3rem 0; font-size: 0.9rem; line-height: 1.4; }
        .info-content strong { color: #0f172a; }

        .details-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; }
        .detail-item small { display: block; font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.2rem; }
        .detail-item strong { display: block; font-size: 0.9rem; color: #0f172a; }

        .po-table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        .po-table th { background: #0f172a; color: white; text-align: left; padding: 12px; font-size: 0.75rem; text-transform: uppercase; }
        .po-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; vertical-align: top; }
        .po-table tr:last-child td { border-bottom: none; }
        
        .totals-section { display: flex; justify-content: flex-end; margin-bottom: 3rem; }
        .totals-table { width: 300px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 0.9rem; }
        .totals-row.grand { border-top: 2px solid #0f172a; margin-top: 8px; padding-top: 12px; font-weight: 800; font-size: 1.1rem; color: #0f172a; }

        .terms-section { margin-top: 2rem; font-size: 0.75rem; color: #64748b; }
        .terms-title { font-weight: 800; color: #0f172a; margin-bottom: 0.5rem; text-transform: uppercase; }
        .terms-list { padding-left: 1.5rem; margin: 0; }
        .terms-list li { margin-bottom: 0.3rem; }

        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; margin-top: 4rem; }
        .sig-box { border-top: 1px solid #0f172a; padding-top: 1rem; text-align: center; font-size: 0.8rem; font-weight: 700; color: #0f172a; }

        .print-btn { position: fixed; bottom: 30px; right: 30px; background: #0f172a; color: white; border: none; padding: 15px 30px; border-radius: 50px; font-weight: 800; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.75rem; z-index: 1000; transition: transform 0.2s; }
        .print-btn:hover { transform: scale(1.05); }

        @media print {
            body { background: white; padding: 0; }
            .po-container { box-shadow: none; width: 100%; margin: 0; padding: 0; max-width: none; min-height: auto; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> PRINT PURCHASE ORDER
    </button>

    <div class="po-container">
        <?php 
        $letterhead = getSetting('company_letterhead');
        if ($letterhead): ?>
            <div style="margin-bottom: 2rem;">
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $letterhead; ?>" style="width: 100%; height: auto; display: block; border-radius: 8px;">
            </div>
        <?php else: ?>
            <div class="header">
                <div class="header-left">
                    <h1>Purchase Order</h1>
                    <p style="font-size: 0.8rem; color: #64748b; margin-top: 0.5rem; font-weight: 600;">Original Copy</p>
                </div>
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo getSetting('company_logo', 'logo.png'); ?>" alt="Company Logo" class="logo" onerror="this.src='https://via.placeholder.com/150x50?text=SUDHA+CREATIVE'">
            </div>
        <?php endif; ?>

        <div class="details-bar">
            <div class="detail-item">
                <small>PO Number</small>
                <strong><?php echo $po_number; ?></strong>
            </div>
            <div class="detail-item">
                <small>PO Date</small>
                <strong><?php echo $po_date; ?></strong>
            </div>
            <div class="detail-item">
                <small>Campaign</small>
                <strong><?php echo $b['campaign_name']; ?></strong>
            </div>
            <div class="detail-item">
                <small>Booking Ref</small>
                <strong>#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></strong>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-section">
                <div class="info-title">Supplier Details</div>
                <div class="info-content">
                    <p><strong><?php echo $v['name']; ?></strong></p>
                    <p><?php echo $v['address']; ?></p>
                    <p><?php echo $v['city']; ?>, <?php echo $v['state']; ?></p>
                    <?php if ($vendor_gst_filter): ?>
                        <p>Branch GSTIN: <strong><?php echo $vendor_gst_filter; ?></strong></p>
                    <?php else: ?>
                        <p>GSTIN: <strong><?php echo $v['gstin']; ?></strong></p>
                    <?php endif; ?>
                    <p>Contact: <?php echo $v['contact_person']; ?> (<?php echo $v['phone']; ?>)</p>
                </div>
            </div>
            <div class="info-section" style="background: #f8fafc;">
                <div class="info-title">Buyer Details</div>
                <div class="info-content">
                    <p><strong><?php echo getSetting('company_name', COMPANY_NAME); ?></strong></p>
                    <p><?php echo getSetting('company_address', COMPANY_ADDRESS); ?></p>
                    <p><?php echo getSetting('company_city', COMPANY_CITY); ?></p>
                    <p>GSTIN: <strong><?php echo getSetting('company_gstin', COMPANY_GSTIN); ?></strong></p>
                    <p>PAN: <strong><?php echo getSetting('company_pan'); ?></strong></p>
                    <p>Email: <?php echo getSetting('company_email', COMPANY_EMAIL); ?></p>
                </div>
            </div>
        </div>

        <table class="po-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Asset & Location</th>
                    <th>HSN</th>
                    <th>Size</th>
                    <th>Lit</th>
                    <th>Period</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $net_total = 0;
                foreach ($items as $idx => $item): 
                    $net_total += $item['purchase_amount'];
                ?>
                <tr>
                    <td><?php echo $idx + 1; ?></td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;"><?php echo $item['location']; ?></div>
                        <div style="font-size: 0.7rem; color: #64748b; margin-top: 2px;"><?php echo $item['city']; ?> (<?php echo $item['site_code']; ?>)</div>
                    </td>
                    <td><?php echo $item['hsn_code'] ?: '998366'; ?></td>
                    <td><?php echo $item['width']; ?>' x <?php echo $item['height']; ?>'</td>
                    <td><?php echo $item['light_type']; ?></td>
                    <td>
                        <div style="font-size: 0.8rem;"><?php echo date('d M', strtotime($item['start_date'])); ?> - <?php echo date('d M Y', strtotime($item['end_date'])); ?></div>
                        <div style="font-size: 0.65rem; color: #64748b;"><?php echo $item['days']; ?> Days</div>
                    </td>
                    <td style="text-align: right; font-weight: 700;">₹<?php echo number_format($item['purchase_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-table">
                <div class="totals-row">
                    <span>Taxable Amount</span>
                    <strong>₹<?php echo number_format($net_total, 2); ?></strong>
                </div>
                <?php 
                $gst_total = $net_total * 0.18;
                $gross_total = $net_total + $gst_total;
                ?>
                <div class="totals-row">
                    <span>GST (18%)</span>
                    <strong>₹<?php echo number_format($gst_total, 2); ?></strong>
                </div>
                <div class="totals-row grand">
                    <span>Gross Total</span>
                    <span>₹<?php echo number_format($gross_total, 2); ?></span>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <p style="font-size: 0.85rem; font-weight: 600;">Amount in Words: <span style="text-transform: capitalize; color: #0f172a;"><?php echo amountInWords($gross_total); ?> Only</span></p>
        </div>

        <div class="terms-section">
            <div class="terms-title">Terms & Conditions</div>
            <ul class="terms-list">
                <li>Flex mounting and cleaning will be free of cost as per standard agreement.</li>
                <li>Filing of GSTR-1 within time is mandatory for acceptance of invoice and payment processing.</li>
                <li>In case of non-illumination of lit sites, display charges will be deducted on pro-rata basis.</li>
                <li>The contract period will start from the date of physical display verification.</li>
                <li>Payment will be processed within 30 days of invoice submission along with display photographs.</li>
            </ul>
        </div>

        <div class="signature-grid">
            <div class="sig-box">
                <div style="height: 60px; border: 1px dashed #cbd5e1; width: 120px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #94a3b8;">STAMP & SEAL</div>
                Supplier Acceptance (Sign & Stamp)
            </div>
            <div class="sig-box">
                <div style="height: 60px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                    <?php 
                    $sig = getSetting('company_signature');
                    if ($sig): ?>
                        <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $sig; ?>" style="height: 40px; opacity: 1;">
                    <?php else: ?>
                        <div style="height: 40px; border: 1px dashed #cbd5e1; width: 100px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #94a3b8;">STAMP HERE</div>
                    <?php endif; ?>
                </div>
                For <?php echo getSetting('company_name', COMPANY_NAME); ?> (Authorised Signatory)
            </div>
        </div>

        <div style="position: absolute; bottom: 20px; left: 40px; right: 40px; border-top: 1px solid #f1f5f9; padding-top: 10px; display: flex; justify-content: space-between; font-size: 0.65rem; color: #94a3b8;">
            <span>Generated via Sudha Creative CRM</span>
            <span>Page 1 of 1</span>
        </div>
    </div>

</body>
</html>
