<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $vendor_id = intval($_POST['vendor_id']);
        $media_type = clean($_POST['media_type']);
        $rate = floatval($_POST['rate_per_sqft']);

        if ($_POST['action'] === 'add') {
            $site_ids = !empty($_POST['site_ids']) ? $_POST['site_ids'] : [null];
            $individual_rates = $_POST['individual_rates'] ?? [];
            $po_number = "PPO-" . date('ymd') . "-" . rand(100, 999);
            
            foreach ($site_ids as $site_id) {
                $site_id = !empty($site_id) ? intval($site_id) : null;
                $this_rate = (isset($individual_rates[$site_id]) && $individual_rates[$site_id] !== '') ? floatval($individual_rates[$site_id]) : $rate;
                
                // Insert new rate with PO number to preserve historical PO data
                $insertStmt = $pdo->prepare("INSERT INTO vendor_printing_rates (vendor_id, site_id, media_type, rate_per_sqft, po_number) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->execute([$vendor_id, $site_id, $media_type, $this_rate, $po_number]);
            }
            header("Location: printing_rates.php?msg=added"); exit;
        } else {
            $id = intval($_POST['id']);
            $site_id = !empty($_POST['site_id']) ? intval($_POST['site_id']) : null;
            $stmt = $pdo->prepare("UPDATE vendor_printing_rates SET vendor_id=?, site_id=?, media_type=?, rate_per_sqft=? WHERE id=?");
            $stmt->execute([$vendor_id, $site_id, $media_type, $rate, $id]);
            header("Location: printing_rates.php?msg=updated"); exit;
        }
    } elseif ($_POST['action'] === 'delete') {
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM vendor_printing_rates WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'printing_rates';
$pageTitle = 'Printing PO';
include_once __DIR__ . '/../../includes/header.php';

$selectedVendorId = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$queryWhere = $selectedVendorId ? "WHERE r.vendor_id = $selectedVendorId" : "";

// Fetch Rates Grouped by PO Number
$rates = $pdo->query("
    SELECT 
        r.po_number,
        r.vendor_id,
        v.name as vendor_name,
        GROUP_CONCAT(r.id SEPARATOR '||') as rate_ids,
        GROUP_CONCAT(COALESCE(s.name, 'Generic') SEPARATOR '||') as site_names,
        GROUP_CONCAT(COALESCE(s.site_code, '-') SEPARATOR '||') as site_codes,
        GROUP_CONCAT(COALESCE(s.width, 0) SEPARATOR '||') as widths,
        GROUP_CONCAT(COALESCE(s.height, 0) SEPARATOR '||') as heights,
        GROUP_CONCAT(r.media_type SEPARATOR '||') as media_types,
        GROUP_CONCAT(r.rate_per_sqft SEPARATOR '||') as rates
    FROM vendor_printing_rates r
    JOIN partners v ON r.vendor_id = v.id
    LEFT JOIN sites s ON r.site_id = s.id
    $queryWhere
    GROUP BY r.po_number, r.vendor_id, v.name, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY r.id DESC
")->fetchAll();

$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$sites = $pdo->query("SELECT id, name, site_code, width, height, vendor_id, city, state, type, light_type, owner_type, status FROM sites ORDER BY site_code ASC")->fetchAll();

// Fetch filter values for advanced search criteria in the modal
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL AND width != '' AND height != '' ORDER BY width, height")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Printing PO</h2>
        <div style="display: flex; gap: 0.75rem;">
            <a href="create_printing_po.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <i class="fas fa-plus"></i> Add New Printing PO 
            </a>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 40px;"></th>
                <th>Vendor</th>
                <th>Site / Dimension</th>
                <th>Media Type</th>
                <th>Rate (per SQFT)</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rates as $r): ?>
            <?php 
                $ids = explode('||', $r['rate_ids']);
                $sNames = explode('||', $r['site_names']);
                $sCodes = explode('||', $r['site_codes']);
                $widths = explode('||', $r['widths']);
                $heights = explode('||', $r['heights']);
                $mediaTypes = explode('||', $r['media_types']);
                $unitRates = explode('||', $r['rates']);
                
                $totalGroupSqft = 0;
                $totalGroupAmount = 0;
                foreach($ids as $i => $id) {
                    $sqft = floatval($widths[$i]) * floatval($heights[$i]);
                    $totalGroupSqft += $sqft;
                    $totalGroupAmount += ($sqft * floatval($unitRates[$i]));
                }
            ?>
            <tr class="rate-row" data-vendor-id="<?php echo $r['vendor_id']; ?>">
                <td style="text-align: center;">
                    <?php 
                        $pdfUrl = "../operations/generate_printing_po.php?vendor_id=" . $r['vendor_id'] . "&preview=1";
                        foreach($ids as $id) $pdfUrl .= "&rate_ids[]=" . $id;
                    ?>
                    <a href="<?php echo $pdfUrl; ?>" target="_blank" title="Download Group PO" style="color: #ef4444; font-size: 1.1rem;">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($r['vendor_name']); ?></strong>
                    <?php if($r['po_number']): ?>
                        <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 2px;">#<?php echo $r['po_number']; ?></div>
                    <?php endif; ?>
                </td>
                <?php 
                $has_multiple = count($ids) > 1;
                $groupId = $r['po_number'] ? $r['po_number'] : 'rate-' . $ids[0];
                ?>
                <td>
                    <!-- First site (always visible) -->
                    <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($sNames[0]); ?></div>
                        <small style="color: #64748b;"><?php echo $sCodes[0]; ?> (<?php echo $widths[0]; ?>x<?php echo $heights[0]; ?> = <strong><?php echo floatval($widths[0]) * floatval($heights[0]); ?> SQFT</strong>)</small>
                    </div>
                    
                    <!-- Collapsible sites -->
                    <?php if ($has_multiple): ?>
                        <div class="collapsible-po-<?php echo $groupId; ?>" style="display: none;">
                            <?php for($i = 1; $i < count($ids); $i++): ?>
                                <div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9;">
                                    <div style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($sNames[$i]); ?></div>
                                    <small style="color: #64748b;"><?php echo $sCodes[$i]; ?> (<?php echo $widths[$i]; ?>x<?php echo $heights[$i]; ?> = <strong><?php echo floatval($widths[$i]) * floatval($heights[$i]); ?> SQFT</strong>)</small>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <a href="javascript:void(0);" onclick="togglePODetails('<?php echo $groupId; ?>')" id="toggle-btn-<?php echo $groupId; ?>" data-count="<?php echo (count($ids) - 1); ?>" style="font-size: 0.72rem; color: var(--primary); font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; background: #f0fdfa; padding: 4px 8px; border-radius: 6px; border: 1px solid #ccfbf1;">
                            <i class="fas fa-chevron-down"></i> + <?php echo (count($ids) - 1); ?> more site(s)
                        </a>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- First media type -->
                    <div style="height: 38px; display: flex; align-items: center;">
                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.7rem;"><?php echo htmlspecialchars($mediaTypes[0]); ?></span>
                    </div>
                    
                    <!-- Collapsible media types -->
                    <?php if ($has_multiple): ?>
                        <div class="collapsible-po-<?php echo $groupId; ?>" style="display: none;">
                            <?php for($i = 1; $i < count($ids); $i++): ?>
                                <div style="height: 38px; display: flex; align-items: center;">
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.7rem;"><?php echo htmlspecialchars($mediaTypes[$i]); ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- First rate -->
                    <div style="height: 38px; display: flex; flex-direction: column; justify-content: center;">
                        <strong style="color: var(--primary);">₹<?php echo number_format(floatval($unitRates[0]), 2); ?></strong>
                        <div style="font-size: 0.65rem; color: #059669; font-weight: 700;">₹<?php echo number_format(floatval($unitRates[0]) * floatval($widths[0]) * floatval($heights[0]), 2); ?></div>
                    </div>
                    
                    <!-- Collapsible rates -->
                    <?php if ($has_multiple): ?>
                        <div class="collapsible-po-<?php echo $groupId; ?>" style="display: none;">
                            <?php for($i = 1; $i < count($ids); $i++): ?>
                                <div style="height: 38px; display: flex; flex-direction: column; justify-content: center;">
                                    <strong style="color: var(--primary);">₹<?php echo number_format(floatval($unitRates[$i]), 2); ?></strong>
                                    <div style="font-size: 0.65rem; color: #059669; font-weight: 700;">₹<?php echo number_format(floatval($unitRates[$i]) * floatval($widths[$i]) * floatval($heights[$i]), 2); ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(count($ids) > 1): ?>
                        <div style="margin-top: 10px; padding-top: 5px; border-top: 2px solid #e2e8f0;">
                            <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Total Amount</div>
                            <strong style="color: #0f172a; font-size: 0.9rem;">₹<?php echo number_format($totalGroupAmount, 2); ?></strong>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-end;">
                        <!-- First Action -->
                        <div style="height: 38px; display: flex; align-items: center; gap: 8px;">
                            <a href="create_printing_po.php?action=edit&id=<?php echo $ids[0]; ?>" class="btn-icon" style="color: #0284c7; background: #e0f2fe; padding: 6px; border-radius: 8px; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; text-decoration: none;" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn-icon btn-delete" onclick="deleteRate(<?php echo $ids[0]; ?>)" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        
                        <!-- Collapsible Actions -->
                        <?php if ($has_multiple): ?>
                            <div class="collapsible-po-<?php echo $groupId; ?>" style="display: none;">
                                <?php for($i = 1; $i < count($ids); $i++): ?>
                                    <div style="height: 38px; display: flex; align-items: center; gap: 8px;">
                                        <a href="create_printing_po.php?action=edit&id=<?php echo $ids[$i]; ?>" class="btn-icon" style="color: #0284c7; background: #e0f2fe; padding: 6px; border-radius: 8px; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; text-decoration: none;" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn-icon btn-delete" onclick="deleteRate(<?php echo $ids[$i]; ?>)" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rates)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 2rem; color: #94a3b8;">No Printing POs found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Check for parameters on load
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('vendor_id')) {
        const vId = urlParams.get('vendor_id');
        window.location.href = `create_printing_po.php?vendor_id=${vId}`;
    }
    
    // Show success message if present in URL
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        let title = 'Success';
        let text = '';
        if (msg === 'added') text = 'Printing PO created successfully.';
        if (msg === 'updated') text = 'Printing PO updated successfully.';
        
        if (text) {
            Swal.fire({
                title: title,
                text: text,
                icon: 'success',
                confirmButtonColor: '#0d9488',
                timer: 2500,
                showConfirmButton: false
            });
            // Clean URL without reloading
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});

function deleteRate(id) {
    Swal.fire({
        title: 'Delete Rate?',
        text: "Are you sure you want to remove this Printing PO?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('printing_rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Rate has been removed.', 'success').then(() => location.reload());
            });
        }
    });
}

function togglePODetails(groupId) {
    const elements = document.querySelectorAll('.collapsible-po-' + groupId);
    const btn = document.getElementById('toggle-btn-' + groupId);
    if (elements.length > 0) {
        const isHidden = elements[0].style.display === 'none';
        elements.forEach(el => {
            el.style.display = isHidden ? 'block' : 'none';
        });
        const count = btn.getAttribute('data-count');
        if (isHidden) {
            btn.innerHTML = `<i class="fas fa-chevron-up"></i> Show less`;
        } else {
            btn.innerHTML = `<i class="fas fa-chevron-down"></i> + ${count} more site(s)`;
        }
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
