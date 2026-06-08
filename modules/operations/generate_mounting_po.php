<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
if (!$booking_id) die("Invalid request: Booking ID is required.");

// Fetch Booking Info
$stmtB = $pdo->prepare("SELECT b.*, c.name as client_name FROM bookings b JOIN partners c ON b.client_id = c.id WHERE b.id = ?");
$stmtB->execute([$booking_id]);
$b = $stmtB->fetch();
if (!$b) die("Booking not found.");

// Fetch Vendors
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name")->fetchAll();

// Fetch Sites for this booking
try {
    $stmtS = $pdo->prepare("
        SELECT bi.*, COALESCE(bi.custom_site_name, s.name) as site_name, s.site_code, COALESCE(bi.custom_location, s.location) as location, s.city, s.width, s.height, s.type as media_type, s.hsn_code, s.mounting_hsn, s.vendor_gst
        FROM booking_items bi
        JOIN sites s ON bi.site_id = s.id
        WHERE bi.booking_id = ?
        ORDER BY s.site_code ASC
    ");
    $stmtS->execute([$booking_id]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'mounting_hsn') !== false) {
        try {
            $pdo->exec("ALTER TABLE sites ADD COLUMN mounting_hsn VARCHAR(50) DEFAULT NULL AFTER hsn_code");
            $stmtS = $pdo->prepare("
                SELECT bi.*, COALESCE(bi.custom_site_name, s.name) as site_name, s.site_code, COALESCE(bi.custom_location, s.location) as location, s.city, s.width, s.height, s.type as media_type, s.hsn_code, s.mounting_hsn, s.vendor_gst
                FROM booking_items bi
                JOIN sites s ON bi.site_id = s.id
                WHERE bi.booking_id = ?
                ORDER BY s.site_code ASC
            ");
            $stmtS->execute([$booking_id]);
        } catch (Exception $ex) {
            throw $e;
        }
    } else {
        throw $e;
    }
}
$sites = $stmtS->fetchAll();

// Check if we are in preview mode
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$po_remark = $_GET['remark'] ?? '';

// If preview mode, get selected rates
$selected_rates = [];
if ($preview && $vendor_id) {
    // Fetch selected vendor
    $stmtV = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
    $stmtV->execute([$vendor_id]);
    $v = $stmtV->fetch();
    if (!$v) die("Vendor not found.");

    // Process rates
    foreach ($sites as $s) {
        $sid = $s['site_id'];
        if (isset($_GET['rate_'.$sid]) && $_GET['rate_'.$sid] !== '') {
            $s['rate_per_sqft'] = floatval($_GET['rate_'.$sid]);
            $selected_rates[] = $s;
        }
    }
}

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

$po_number = "MPO/" . date('y') . "-" . date('y', strtotime('+1 year')) . "/" . str_pad($vendor_id, 3, '0', STR_PAD_LEFT) . "-" . date('dHi');
$po_date = date('d-m-Y');

// ============================
// SELECTION FORM (Not Preview)
// ============================
if (!$preview):

$activePage = 'bookings';
$pageTitle = 'Mounting PO - Generate';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">
                <i class="fas fa-tools" style="color: var(--primary); margin-right: 0.5rem;"></i>
                Generate Mounting PO
            </h2>
            <div style="font-size: 0.8rem; color: #64748b;">
                Campaign: <strong style="color: #1e293b;"><?php echo htmlspecialchars($b['campaign_name']); ?></strong>
            </div>
        </div>
        <a href="view_booking.php?id=<?php echo $booking_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Booking
        </a>
    </div>

    <?php if (empty($sites)): ?>
        <div style="text-align: center; padding: 3rem; color: #94a3b8;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
            <p>No sites found in this booking.</p>
        </div>
    <?php else: ?>
        <form method="GET" action="generate_mounting_po.php" id="poForm">
            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
            <input type="hidden" name="preview" value="1">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div style="padding: 1rem; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Select Vendor <span style="color:red;">*</span></label>
                    <select name="vendor_id" required style="width: 100%; padding: 0.65rem; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                        <option value="">-- Select Vendor --</option>
                        <?php foreach($vendors as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="padding: 1rem; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Remark / Reference (Optional)</label>
                    <input type="text" name="remark" value="<?php echo htmlspecialchars($b['campaign_name']); ?>" placeholder="e.g. Campaign Name, Client Name..." style="width: 100%; padding: 0.65rem; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)" checked style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                        </th>
                        <th>Site / Code</th>
                        <th>Location</th>
                        <th>Size</th>
                        <th>Total SQFT</th>
                        <th style="width: 150px;">Rate/SQFT (₹)</th>
                        <th style="text-align: right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $r): ?>
                    <?php
                        $sqft = ($r['width'] && $r['height']) ? floatval($r['width']) * floatval($r['height']) : 0;
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="rate-chk" checked style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;" onchange="updateSummary()">
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($r['site_name']); ?></strong>
                            <div style="font-size: 0.7rem; color: #f97316; font-weight: 700;"><?php echo $r['site_code']; ?></div>
                        </td>
                        <td>
                            <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($r['location'] ?? $r['city'] ?? '-'); ?></div>
                            <div style="font-size: 0.7rem; color: #64748b;"><?php echo $r['city']; ?></div>
                        </td>
                        <td>
                            <?php if ($r['width'] && $r['height']): ?>
                                <strong><?php echo $r['width']; ?>'x<?php echo $r['height']; ?>'</strong>
                            <?php else: ?>
                                <span style="color: #94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $sqft > 0 ? number_format($sqft) . ' SQFT' : '-'; ?></td>
                        <td>
                            <input type="number" step="0.01" name="rate_<?php echo $r['site_id']; ?>" class="rate-input" data-sqft="<?php echo $sqft; ?>" oninput="updateSummary()" value="" placeholder="0.00" style="width: 100%; padding: 0.4rem; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;">
                        </td>
                        <td style="text-align: right;">
                            <strong class="row-total" style="color: #059669;">₹0.00</strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Summary Bar -->
            <div id="summary-bar" style="margin-top: 1.5rem; padding: 1rem 1.5rem; background: linear-gradient(135deg, #0d9488, #0f766e); border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(13, 148, 136, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">Selected Sites</span>
                        <div style="font-size: 1.25rem; font-weight: 900;" id="summary-count">0</div>
                    </div>
                    <div style="text-align: center;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">Net Amount</span>
                        <div style="font-size: 1.25rem; font-weight: 900;" id="summary-net">₹0.00</div>
                    </div>
                    <div style="text-align: center;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">GST (18%)</span>
                        <div style="font-size: 1.25rem; font-weight: 900;" id="summary-gst">₹0.00</div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">Grand Total</span>
                        <div style="font-size: 1.5rem; font-weight: 900;" id="summary-grand">₹0.00</div>
                    </div>
                    <button type="submit" class="btn" style="background: white; color: #0d9488; font-weight: 800; padding: 0.75rem 2rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 0.9rem;">
                        <i class="fas fa-file-alt"></i> Generate PO
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function toggleAll(el) {
    document.querySelectorAll('.rate-chk').forEach(chk => {
        chk.checked = el.checked;
        const input = chk.closest('tr').querySelector('.rate-input');
        if (!el.checked) {
            input.value = '';
            input.disabled = true;
        } else {
            input.disabled = false;
        }
    });
    updateSummary();
}

document.querySelectorAll('.rate-chk').forEach(chk => {
    chk.addEventListener('change', function() {
        const input = this.closest('tr').querySelector('.rate-input');
        if (!this.checked) {
            input.value = '';
            input.disabled = true;
        } else {
            input.disabled = false;
        }
        updateSummary();
    });
});

function updateSummary() {
    let net = 0;
    let count = 0;
    
    document.querySelectorAll('tbody tr').forEach(row => {
        const chk = row.querySelector('.rate-chk');
        const input = row.querySelector('.rate-input');
        const totalEl = row.querySelector('.row-total');
        
        if (chk.checked && input.value !== '') {
            const sqft = parseFloat(input.dataset.sqft) || 0;
            const rate = parseFloat(input.value) || 0;
            const total = sqft * rate;
            totalEl.innerText = '₹' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            net += total;
            count++;
        } else {
            totalEl.innerText = '₹0.00';
            if (chk.checked) count++;
        }
    });

    const gst = net * 0.18;
    const grand = net + gst;

    document.getElementById('summary-count').innerText = count;
    document.getElementById('summary-net').innerText = '₹' + net.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summary-gst').innerText = '₹' + gst.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summary-grand').innerText = '₹' + grand.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

document.getElementById('poForm')?.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.rate-chk:checked');
    let hasRate = false;
    checked.forEach(chk => {
        if (chk.closest('tr').querySelector('.rate-input').value !== '') hasRate = true;
    });
    if (checked.length === 0 || !hasRate) {
        e.preventDefault();
        Swal.fire('Error', 'Please select at least one site and enter a rate to generate a PO.', 'error');
    }
});

updateSummary();
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
// ============================
// PREVIEW / PRINT MODE
// ============================
else:

if (empty($selected_rates)) die("No rates selected for this PO.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mounting PO - <?php echo htmlspecialchars($v['name']); ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; color: #000; font-size: 11px; line-height: 1.3; }
        .po-wrapper { border: 1px solid #000; max-width: 800px; margin: 0 auto; position: relative; }

        .header-top { border-bottom: 1px solid #000; padding: 5px 10px; }
        .header-top p { margin: 0; }

        .main-info { display: flex; border-bottom: 1px solid #000; }
        .info-col { flex: 1; padding: 10px; }
        .info-col:first-child { border-right: 1px solid #000; }

        .info-row { display: flex; margin-bottom: 3px; }
        .info-label { width: 90px; font-weight: normal; }
        .info-sep { width: 15px; }
        .info-value { flex: 1; font-weight: normal; }

        .section-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; font-style: italic; }
        .table-title { background: #f0f0f0; border-bottom: 1px solid #000; text-align: center; font-weight: bold; padding: 4px; letter-spacing: 2px; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; }
        th { border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 6px; text-align: center; font-weight: bold; background: #fafafa; }
        th:last-child { border-right: none; }
        td { border-bottom: 1px solid #d0d0d0; border-right: 1px solid #000; padding: 8px 5px; vertical-align: top; text-align: center; }
        td:last-child { border-right: none; }

        .totals-row td { border-bottom: none; border-top: 1px solid #000; font-weight: bold; }
        .footer { display: flex; border-top: 1px solid #000; }
        .footer-left { flex: 2; padding: 10px; border-right: 1px solid #000; min-height: 120px; }
        .footer-right { flex: 1; padding: 10px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; }

        .btn-print { position: fixed; bottom: 30px; right: 30px; background: #000; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .btn-back { position: fixed; bottom: 30px; right: 180px; background: #6366f1; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; text-decoration: none; font-size: 12px; }
        @media print { .btn-print, .btn-back { display: none; } body { padding: 0; } .po-wrapper { border: none; width: 100%; } }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">PRINT PURCHASE ORDER</button>
<a class="btn-back" href="generate_mounting_po.php?booking_id=<?php echo $booking_id; ?>">← BACK TO SELECTION</a>

<div class="po-wrapper">
    <!-- Header -->
    <?php if ($company_letterhead): ?>
        <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_letterhead; ?>" style="width: 100%; height: auto; display: block; border-bottom: 1px solid #000;">
    <?php else: ?>
        <div class="header-top" style="text-align: center;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo $company_name; ?></h2>
            <p><?php echo $company_address; ?></p>
            <p>Ph: <?php echo $company_phone; ?> | Email: <?php echo $company_email; ?></p>
            <?php if ($company_msme): ?><p>MSME: <?php echo htmlspecialchars($company_msme); ?></p><?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- PO Info -->
    <div class="main-info">
        <div class="info-col">
            <div style="margin-bottom: 15px;">
                <div class="section-title">Mounting Vendor / Supplier:</div>
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 2px;"><?php echo $v['name']; ?></div>
                <div style="width: 250px;"><?php echo $v['address']; ?></div>
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
                <span class="info-value"><strong>MOUNTING ORDER</strong></span>
            </div>
            <div class="info-row" style="margin-top: 2px;">
                <span class="info-label">Campaign</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong style="text-transform: uppercase;"><?php echo htmlspecialchars($b['campaign_name']); ?></strong></span>
            </div>
            <?php if ($po_remark): ?>
            <div class="info-row">
                <span class="info-label">Remark</span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo htmlspecialchars($po_remark); ?></span>
            </div>
            <?php endif; ?>
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
        </div>
    </div>

    <div class="table-title">Mounting Order Details:</div>

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
            foreach ($selected_rates as $idx => $item):
                $sqft = ($item['width'] && $item['height']) ? floatval($item['width']) * floatval($item['height']) : 0;
                $item_total = $sqft * floatval($item['rate_per_sqft']);
                $net_total += $item_total;
            ?>
            <tr>
                <td><?php echo $idx + 1; ?></td>
                <td style="text-align: left; padding-left: 10px;">
                    <div style="font-weight: bold;"><?php echo $item['site_name']; ?></div>
                    <div style="font-size: 9px; color: #555;"><?php echo $item['site_code']; ?> • <?php echo $item['location'] ?? $item['city'] ?? ''; ?></div>
                </td>
                <td><?php echo $item['mounting_hsn'] ?: $item['hsn_code'] ?: '998366'; ?></td>
                <td><?php echo ($item['width'] && $item['height']) ? $item['width'] . "'x" . $item['height'] . "'" : '-'; ?></td>
                <td><?php echo $sqft > 0 ? number_format($sqft) : '-'; ?></td>
                <td style="font-size: 9px;"><?php echo $item['media_type']; ?></td>
                <td>₹<?php echo number_format($item['rate_per_sqft'], 2); ?></td>
                <td style="text-align: right; padding-right: 10px; font-weight: bold;"><?php echo number_format($item_total, 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <?php
            $gst_amount = $net_total * 0.18;
            $grand_total = $net_total + $gst_amount;
            ?>

            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">Taxable Amount (Total Cost)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($net_total, 2); ?></td>
            </tr>
            <tr class="totals-row">
                <td colspan="7" style="text-align: right; padding-right: 10px;">GST (18%)</td>
                <td style="text-align: right; padding-right: 10px;"><?php echo number_format($gst_amount, 2); ?></td>
            </tr>
            <tr class="totals-row" style="background: #f9f9f9; border-top: 2px solid #000;">
                <td colspan="7" style="text-align: right; padding-right: 10px; font-size: 12px; height: 30px; vertical-align: middle;">Gross Payable Amount</td>
                <td style="text-align: right; padding-right: 10px; font-size: 12px; vertical-align: middle;"><?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div style="padding: 10px; border-top: 1px solid #000;">
        <strong>Amount in Words:</strong> <span style="text-transform: capitalize;"><?php echo amountInWords($grand_total); ?> Only</span>
    </div>

    <div style="padding: 10px; border-top: 1px solid #000; font-size: 9px;">
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
            <p style="margin: 2px 0;">- 100% after mounting completion with proofs</p>
            <p style="margin: 2px 0;">- Cheque/NEFT in favor of: <strong><?php echo $v['name']; ?></strong></p>
        </div>
        <div class="footer-right">
            <div>For <strong><?php echo $company_name; ?></strong></div>
            <div style="margin-top: 30px;">
                <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_signature; ?>" style="height: 40px; display: block; margin: 0 auto;" onerror="this.style.display='none'">
                <div style="border-top: 1px solid #000; width: 150px; margin: 5px auto 0;"></div>
                <div style="font-weight: bold; margin-top: 2px;">Authorised Signatory</div>
            </div>
        </div>
    </div>
</div>

<div style="max-width: 800px; margin: 10px auto; text-align: center; font-size: 9px; color: #888;">
    This is a computer generated Mounting Purchase Order and does not require physical signature.
</div>

</body>
</html>
<?php endif; ?>
