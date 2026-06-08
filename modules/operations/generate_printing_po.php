<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
if (!$vendor_id) die("Invalid request: Vendor ID is required.");

// Fetch Vendor Info
$stmtV = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'vendor'");
$stmtV->execute([$vendor_id]);
$v = $stmtV->fetch();
if (!$v) die("Vendor not found.");

// Fetch Printing POs for this vendor (with site details)
$stmtR = $pdo->prepare("
    SELECT r.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.type as media_type_site, s.hsn_code, s.vendor_gst
    FROM vendor_printing_rates r
    LEFT JOIN sites s ON r.site_id = s.id
    WHERE r.vendor_id = ?
    ORDER BY s.site_code ASC
");
$stmtR->execute([$vendor_id]);
$rates = $stmtR->fetchAll();

// Check if we are in preview mode (form submitted)
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';
$selected_rate_ids = isset($_GET['rate_ids']) ? array_map('intval', $_GET['rate_ids']) : [];
$po_remark = $_GET['remark'] ?? '';
$po_number_filter = $_GET['po_number'] ?? '';

// If preview mode, filter only selected rates or by po_number
if ($preview) {
    if (!empty($selected_rate_ids)) {
        $rates = array_filter($rates, function($r) use ($selected_rate_ids) {
            return in_array($r['id'], $selected_rate_ids);
        });
        $rates = array_values($rates);
    } elseif (!empty($po_number_filter)) {
        $rates = array_filter($rates, function($r) use ($po_number_filter) {
            return $r['po_number'] === $po_number_filter;
        });
        $rates = array_values($rates);
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

$po_number = "PPO/" . date('y') . "-" . date('y', strtotime('+1 year')) . "/" . str_pad($vendor_id, 3, '0', STR_PAD_LEFT) . "-" . date('dHi');
$po_date = date('d-m-Y');

// ============================
// SELECTION FORM (Not Preview)
// ============================
if (!$preview):

$activePage = 'printing_rates';
$pageTitle = 'Printing PO - Generate PO';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">
                <i class="fas fa-print" style="color: var(--primary); margin-right: 0.5rem;"></i>
                Generate Printing PO
            </h2>
            <div style="font-size: 0.8rem; color: #64748b;">
                Vendor: <strong style="color: #1e293b;"><?php echo htmlspecialchars($v['name']); ?></strong>
                <?php if($v['gstin']): ?> • GSTIN: <code style="font-size: 0.75rem;"><?php echo $v['gstin']; ?></code><?php endif; ?>
            </div>
        </div>
        <a href="../partners/printing_rates.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Printing PO
        </a>
    </div>

    <?php if (empty($rates)): ?>
        <div style="text-align: center; padding: 3rem; color: #94a3b8;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
            <p>No Printing POs found for this vendor. Please add rates first in <a href="../partners/printing_rates.php?vendor_id=<?php echo $vendor_id; ?>">Printing PO</a>.</p>
        </div>
    <?php else: ?>
        <form method="GET" action="generate_printing_po.php" id="poForm">
            <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
            <input type="hidden" name="preview" value="1">

            <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Remark / Reference (Optional)</label>
                <input type="text" name="remark" placeholder="e.g. Campaign Name, Client Name..." style="width: 100%; padding: 0.65rem; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                        </th>
                        <th>Site / Code</th>
                        <th>Location</th>
                        <th>Size</th>
                        <th>Media</th>
                        <th>Rate/SQFT</th>
                        <th>Total SQFT</th>
                        <th style="text-align: right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rates as $r): ?>
                    <?php
                        $sqft = ($r['width'] && $r['height']) ? floatval($r['width']) * floatval($r['height']) : 0;
                        $total = $sqft * floatval($r['rate_per_sqft']);
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="rate_ids[]" value="<?php echo $r['id']; ?>" class="rate-chk" data-total="<?php echo $total; ?>"
                                   onchange="updateSummary()" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                        </td>
                        <td>
                            <?php if ($r['site_id']): ?>
                                <strong><?php echo htmlspecialchars($r['site_name']); ?></strong>
                                <div style="font-size: 0.7rem; color: #f97316; font-weight: 700;"><?php echo $r['site_code']; ?></div>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">Generic Rate</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['site_id']): ?>
                                <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($r['location'] ?? $r['city'] ?? '-'); ?></div>
                                <div style="font-size: 0.7rem; color: #64748b;"><?php echo $r['city']; ?></div>
                            <?php else: ?>
                                <span style="color: #94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['width'] && $r['height']): ?>
                                <strong><?php echo $r['width']; ?>'x<?php echo $r['height']; ?>'</strong>
                            <?php else: ?>
                                <span style="color: #94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge" style="background: #f1f5f9; color: #475569;"><?php echo $r['media_type']; ?></span></td>
                        <td><strong style="color: var(--primary);">₹<?php echo number_format($r['rate_per_sqft'], 2); ?></strong></td>
                        <td><?php echo $sqft > 0 ? number_format($sqft) . ' SQFT' : '-'; ?></td>
                        <td style="text-align: right;">
                            <?php if ($total > 0): ?>
                                <strong style="color: #059669;">₹<?php echo number_format($total, 2); ?></strong>
                            <?php else: ?>
                                <span style="color: #94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Summary Bar -->
            <div id="summary-bar" style="display: none; margin-top: 1.5rem; padding: 1rem 1.5rem; background: linear-gradient(135deg, #0d9488, #0f766e); border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(13, 148, 136, 0.3);">
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
    document.querySelectorAll('.rate-chk').forEach(chk => chk.checked = el.checked);
    updateSummary();
}

function updateSummary() {
    const checked = document.querySelectorAll('.rate-chk:checked');
    const bar = document.getElementById('summary-bar');

    if (checked.length === 0) {
        bar.style.display = 'none';
        return;
    }

    bar.style.display = 'block';
    let net = 0;
    checked.forEach(chk => net += parseFloat(chk.dataset.total) || 0);
    const gst = net * 0.18;
    const grand = net + gst;

    document.getElementById('summary-count').innerText = checked.length;
    document.getElementById('summary-net').innerText = '₹' + net.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summary-gst').innerText = '₹' + gst.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summary-grand').innerText = '₹' + grand.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

document.getElementById('poForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const checked = document.querySelectorAll('.rate-chk:checked');
    if (checked.length === 0) {
        Swal.fire('Error', 'Please select at least one site/rate to generate a PO.', 'error');
        return;
    }

    const rateIds = Array.from(checked).map(chk => chk.value);
    const vendorId = document.querySelector('input[name="vendor_id"]').value;
    const remark = document.querySelector('input[name="remark"]').value;

    fetch('../../ajax/save_printing_po_direct.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rate_ids: rateIds, vendor_id: vendorId, remark: remark })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'print_printing_po.php?po_id=' + data.po_id;
        } else {
            Swal.fire('Error', data.message || 'Failed to save PO', 'error');
        }
    })
    .catch(err => Swal.fire('Error', 'Network error', 'error'));
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
// ============================
// PREVIEW / PRINT MODE
// ============================
else:

if (empty($rates)) die("No rates selected for this PO.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Printing PO - <?php echo htmlspecialchars($v['name']); ?></title>
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
<a class="btn-back" href="generate_printing_po.php?vendor_id=<?php echo $vendor_id; ?>">← BACK TO SELECTION</a>

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
                <div class="section-title">Printing Vendor / Supplier:</div>
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

    <div class="table-title">Printing Order Details:</div>

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
                        <div style="font-size: 9px; color: #555;"><?php echo $item['site_code']; ?> • <?php echo $item['location'] ?? $item['city'] ?? ''; ?></div>
                    <?php else: ?>
                        <div style="font-weight: bold;">Generic Printing</div>
                        <div style="font-size: 9px; color: #555;"><?php echo $item['media_type']; ?></div>
                    <?php endif; ?>
                </td>
                <td><?php echo $item['hsn_code'] ?: ''; ?></td>
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
            <p style="margin: 2px 0;">- 100% after printing delivery with proofs</p>
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
    This is a computer generated Printing Purchase Order and does not require physical signature.
</div>

</body>
</html>
<?php endif; ?>
