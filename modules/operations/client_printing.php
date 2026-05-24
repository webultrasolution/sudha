<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Fetch Client Info if ID provided
$c = null;
if ($client_id > 0) {
    $stmtC = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'client'");
    $stmtC->execute([$client_id]);
    $c = $stmtC->fetch();
}

// Fetch all clients for client dropdown
$clientsList = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();

// Fetch all vendors for filtering rates
$vendorsList = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();

// Fetch Printing Rates (optionally filtered by vendor)
$selectedFilterVendorId = isset($_GET['filter_vendor_id']) ? intval($_GET['filter_vendor_id']) : 0;
$rateParams = [];
$rateWhere = "";
if ($client_id > 0) {
    $rateWhere = "WHERE r.client_id = ?";
    $rateParams[] = $client_id;
    if ($selectedFilterVendorId > 0) {
        $rateWhere .= " AND s.vendor_id = ?";
        $rateParams[] = $selectedFilterVendorId;
    }
}

$rates = [];
if ($client_id > 0) {
    $stmtR = $pdo->prepare("
        SELECT r.*, s.name as site_name, s.site_code, s.location, s.city, s.width, s.height, s.type as media_type_site, s.hsn_code, s.vendor_gst, v.name as vendor_name
        FROM client_printing_rates r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN partners v ON s.vendor_id = v.id
        $rateWhere
        ORDER BY s.site_code ASC
    ");
    $stmtR->execute($rateParams);
    $rates = $stmtR->fetchAll();
}

// Check if we are in preview mode (form submitted)
$preview = isset($_GET['preview']) && $_GET['preview'] === '1' && $client_id > 0;
$selected_rate_ids = isset($_GET['rate_ids']) ? array_map('intval', $_GET['rate_ids']) : [];
$po_remark = $_GET['remark'] ?? '';

// If preview mode, filter only selected rates
if ($preview && !empty($selected_rate_ids)) {
    $rates = array_filter($rates, function($r) use ($selected_rate_ids) {
        return in_array($r['id'], $selected_rate_ids);
    });
    $rates = array_values($rates);
}

// Company Settings
$company_name = getSetting('company_name', 'Sudha Creative & Advertising');
$company_gstin = getSetting('company_gstin', '19AHRPT4740Q1Z6');
$company_pan = getSetting('company_pan', 'AHRPT4740Q');
$company_address = getSetting('company_address', 'Deshbandhu Para, P.O - Jhaljhalia, Dist - Malda - 732102, West Bengal');
$company_phone = getSetting('company_phone', '8158854313');
$company_email = getSetting('company_email', 'sudhacreativemalda@gmail.com');
$company_letterhead = getSetting('company_letterhead');
$company_signature = getSetting('company_signature', 'signature.png');

$po_number = "CPPO/" . date('y') . "-" . date('y', strtotime('+1 year')) . "/" . str_pad($client_id, 3, '0', STR_PAD_LEFT) . "-" . date('dHi');
$po_date = date('d-m-Y');

// ============================
// SELECTION FORM (Not Preview)
// ============================
if (!$preview):

$activePage = 'client_printing';
$pageTitle = 'Client Printing Invoice - Generate Print';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">
                <i class="fas fa-file-invoice" style="color: var(--primary); margin-right: 0.5rem;"></i>
                Generate Client Printing Invoice
            </h2>
            <div style="font-size: 0.8rem; color: #64748b;">
                Select Client and choose printing rates to include.
            </div>
        </div>
        <a href="../partners/client_printing_rates.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Printing PO
        </a>
    </div>

    <!-- Client and Vendor Selection Row -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="client_printing.php" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin: 0;">
            <div style="flex: 1.5; min-width: 250px;">
                <label style="display: block; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">1. Select Client (Required)</label>
                <select name="client_id" required style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; font-weight: 600; background: white; outline: none;">
                    <option value="">Choose Client...</option>
                    <?php foreach ($clientsList as $cl): ?>
                        <option value="<?php echo $cl['id']; ?>" <?php echo $client_id == $cl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cl['name'], ENT_QUOTES, 'UTF-8', false); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Filter Rates by Vendor</label>
                <select name="filter_vendor_id" style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; font-weight: 600; background: white; outline: none;">
                    <option value="">All Printing Vendors</option>
                    <?php foreach ($vendorsList as $vl): ?>
                        <option value="<?php echo $vl['id']; ?>" <?php echo $selectedFilterVendorId == $vl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vl['name'], ENT_QUOTES, 'UTF-8', false); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.5rem; font-weight: 800; font-size: 0.85rem; border-radius: 10px;">
                    Apply Client & Filter
                </button>
            </div>
        </form>
    </div>

    <?php if ($client_id <= 0): ?>
        <div style="text-align: center; padding: 3rem; color: #94a3b8; background: #fafafa; border-radius: 12px;">
            <i class="fas fa-user-circle" style="font-size: 3rem; margin-bottom: 1rem; display: block; color: #cbd5e1;"></i>
            <p style="font-weight: 600; font-size: 1rem; margin-bottom: 0.25rem;">Please select a Client to proceed</p>
            <p style="font-size: 0.8rem;">Use the client selector dropdown above to start generating a client-facing PO.</p>
        </div>
    <?php elseif (empty($rates)): ?>
        <div style="text-align: center; padding: 3rem; color: #94a3b8;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
            <p>No Printing rates found in the system. Add rates first in Printing PO module.</p>
        </div>
    <?php else: ?>
        <form method="GET" action="client_printing.php" id="poForm" enctype="multipart/form-data">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <input type="hidden" name="filter_vendor_id" value="<?php echo $selectedFilterVendorId; ?>">
            <input type="hidden" name="preview" value="1">

            <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Remark / Reference (Optional)</label>
                        <input type="text" name="remark" placeholder="e.g. Campaign Name, Brand name..." style="width: 100%; padding: 0.65rem; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                    </div>
                    
                    <div style="flex: 1; min-width: 200px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Ownership Type</label>
                        <div style="display: flex; gap: 1rem; padding-top: 0.4rem;">
                            <label style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.9rem; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="ownership" value="Self" checked style="accent-color: var(--primary); width: 16px; height: 16px;"> Self
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.9rem; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="ownership" value="TA" style="accent-color: var(--primary); width: 16px; height: 16px;"> TA (Agency)
                            </label>
                        </div>
                    </div>
                    
                    <div style="flex: 1; min-width: 250px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Attach POS / Tax Invoice</label>
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" style="width: 100%; padding: 0.5rem 0; font-size: 0.85rem;">
                        <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.2rem;">Optional. JPG, PNG, or PDF allowed.</div>
                    </div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                        </th>
                        <th>Vendor</th>
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
                        <td><strong><?php echo htmlspecialchars($r['vendor_name'], ENT_QUOTES, 'UTF-8', false); ?></strong></td>
                        <td>
                            <?php if ($r['site_id']): ?>
                                <strong><?php echo htmlspecialchars($r['site_name'], ENT_QUOTES, 'UTF-8', false); ?></strong>
                                <div style="font-size: 0.7rem; color: #f97316; font-weight: 700;"><?php echo $r['site_code']; ?></div>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">Generic Rate</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['site_id']): ?>
                                <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($r['location'] ?? $r['city'] ?? '-', ENT_QUOTES, 'UTF-8', false); ?></div>
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
                        <i class="fas fa-file-alt"></i> Generate Client Print
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
        Swal.fire('Error', 'Please select at least one site/rate to generate an Invoice.', 'error');
        return;
    }

    const formData = new FormData(this);
    
    // FormData handles checked inputs naturally if they have name="rate_ids[]"
    // Just ensure they are included based on current selection
    formData.delete('rate_ids[]');
    Array.from(checked).forEach(chk => {
        formData.append('rate_ids[]', chk.value);
    });

    fetch('../../ajax/save_client_printing_invoice.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'print_client_printing.php?invoice_id=' + data.invoice_id;
        } else {
            Swal.fire('Error', data.message || 'Failed to save Invoice', 'error');
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
$is_final = isset($_GET['is_final']) && $_GET['is_final'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $is_final ? 'Tax Invoice' : 'Client Printing Invoice'; ?> - <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8', false); ?></title>
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

<button class="btn-print" onclick="window.print()"><?php echo $is_final ? 'PRINT TAX INVOICE' : 'PRINT CLIENT PO'; ?></button>
<a class="btn-back" href="client_printing.php?client_id=<?php echo $client_id; ?>&filter_vendor_id=<?php echo $selectedFilterVendorId; ?>">← BACK TO SELECTION</a>

<div class="po-wrapper">
    <!-- Header -->
    <?php if ($company_letterhead): ?>
        <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $company_letterhead; ?>" style="width: 100%; height: auto; display: block; border-bottom: 1px solid #000;">
    <?php else: ?>
        <div class="header-top" style="text-align: center;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo $company_name; ?></h2>
            <p><?php echo $company_address; ?></p>
            <p>Ph: <?php echo $company_phone; ?> Email: <?php echo $company_email; ?></p>
        </div>
    <?php endif; ?>

    <!-- PO Info -->
    <div class="main-info">
        <div class="info-col">
            <div style="margin-bottom: 15px;">
                <div class="section-title">Client / Customer:</div>
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 2px;"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8', false); ?></div>
                <div style="width: 250px;"><?php echo htmlspecialchars($c['address'], ENT_QUOTES, 'UTF-8', false); ?></div>
                <div class="info-row" style="margin-top: 5px;">
                    <span class="info-label">GSTIN / UIN</span>
                    <span class="info-sep">:</span>
                    <span class="info-value"><strong><?php echo $c['gstin'] ?: 'N/A'; ?></strong></span>
                </div>
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
                <span class="info-label"><?php echo $is_final ? 'Invoice Number' : 'PO Number'; ?></span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo $po_number; ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label"><?php echo $is_final ? 'Invoice Date' : 'PO Date'; ?></span>
                <span class="info-sep">:</span>
                <span class="info-value"><?php echo $po_date; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Type</span>
                <span class="info-sep">:</span>
                <span class="info-value"><strong><?php echo $is_final ? 'TAX INVOICE (FINAL)' : 'CLIENT PRINTING ORDER'; ?></strong></span>
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
        </div>
    </div>

    <div class="table-title"><?php echo $is_final ? 'Tax Invoice Details' : 'Printing Order Details'; ?>:</div>

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
            <p style="margin: 2px 0;">- Cheque/NEFT in favor of: <strong><?php echo $company_name; ?></strong></p>
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
    This is a computer generated Client Printing Invoice and does not require physical signature.
</div>

</body>
</html>
<?php endif; ?>
