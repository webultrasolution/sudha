<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS client_mounting_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    site_id INT DEFAULT NULL,
    po_number VARCHAR(50) DEFAULT NULL,
    mounting_type VARCHAR(50) DEFAULT 'Standard',
    rate_per_sqft DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attachments VARCHAR(255) DEFAULT NULL,
    vendor_invoice_no VARCHAR(100) DEFAULT NULL,
    vendor_invoice_date DATE DEFAULT NULL,
    invoice_number VARCHAR(100) DEFAULT NULL,
    is_final_invoice TINYINT(1) DEFAULT 0,
    approval_status ENUM('draft','pending_approval','approved','rejected') DEFAULT 'draft',
    gst_type ENUM('igst','cgst_sgst') DEFAULT 'igst',
    customer_po_no VARCHAR(100) DEFAULT NULL,
    customer_po_date DATE DEFAULT NULL,
    custom_invoice_number VARCHAR(100) DEFAULT NULL,
    custom_invoice_date DATE DEFAULT NULL,
    invoice_date DATE DEFAULT NULL,
    sub_total DECIMAL(15,2) DEFAULT 0,
    cgst DECIMAL(15,2) DEFAULT 0,
    sgst DECIMAL(15,2) DEFAULT 0,
    igst DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) DEFAULT 0
)");

requirePermission('clients', 'view');

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        requirePermission('clients', 'delete');
        header('Content-Type: application/json');
        include_once __DIR__ . '/../../includes/trash_helper.php';

        $po_number = $_POST['po_number'] ?? '';
        $rate_ids_str = $_POST['rate_ids'] ?? '';

        if (!empty($po_number)) {
            $stmt = $pdo->prepare("SELECT id FROM client_mounting_rates WHERE po_number = ?");
            $stmt->execute([$po_number]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ids)) {
                move_multiple_rows_to_trash($pdo, 'client_mounting_rates', 'id', $ids, $_SESSION['user_id'] ?? null, "Client Mounting PO #$po_number deleted");
            }
        } elseif (!empty($rate_ids_str)) {
            $ids = explode(',', $rate_ids_str);
            $ids = array_map('intval', $ids);
            if (!empty($ids)) {
                move_multiple_rows_to_trash($pdo, 'client_mounting_rates', 'id', $ids, $_SESSION['user_id'] ?? null, "Client mounting rates deleted");
            }
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                move_multiple_rows_to_trash($pdo, 'client_mounting_rates', 'id', [$id], $_SESSION['user_id'] ?? null, "Client mounting rate ID $id deleted");
            }
        }
        echo json_encode(['success' => true]); exit;
    }

    // Handle finalize invoice
    if ($_POST['action'] === 'finalize') {
        header('Content-Type: application/json');
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
            if (!canView('clients')) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to finalize client mounting tax invoices.']);
                exit;
            }
            
            $approvalStatus = $isAdmin ? 'approved' : 'pending_approval';
            $isFinalInvoice = $isAdmin ? 1 : 0;

            $po = clean($_POST['po_number']);
            $cid = intval($_POST['client_id']);
            $customNo   = !empty($_POST['custom_invoice_number']) ? clean($_POST['custom_invoice_number']) : null;
            
            $raw_custom_date = !empty($_POST['custom_invoice_date']) ? clean($_POST['custom_invoice_date']) : '';
            $customDate = !empty($raw_custom_date) ? date('Y-m-d', strtotime(str_replace('/', '-', $raw_custom_date))) : date('Y-m-d');
            
            $gstType    = in_array($_POST['gst_type'] ?? 'igst', ['igst','cgst_sgst']) ? $_POST['gst_type'] : 'igst';
            $custPONo   = clean($_POST['customer_po_no'] ?? '');
            
            $raw_cust_po_date = !empty($_POST['customer_po_date']) ? clean($_POST['customer_po_date']) : '';
            $custPODate = !empty($raw_cust_po_date) ? date('Y-m-d', strtotime(str_replace('/', '-', $raw_cust_po_date))) : null;

            $file_path = null;
            if (isset($_FILES['customer_po_file']) && $_FILES['customer_po_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/customer_pos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $extension = pathinfo($_FILES['customer_po_file']['name'], PATHINFO_EXTENSION);
                $filename = 'MOUNT_PO_' . $cid . '_' . time() . '.' . $extension;
                $target = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['customer_po_file']['tmp_name'], $target)) {
                    $file_path = 'uploads/customer_pos/' . $filename;
                }
            }

            if (!$file_path) {
                echo json_encode(['success' => false, 'message' => 'Please upload the PO attachment']);
                exit;
            }

            // Generate invoice number if not provided
            if (!$customNo) {
                $customNo = generateSequenceNumber($pdo, 'client_mounting_invoice');
            } else {
                $preview = getPreviewSequenceNumber($pdo, 'client_mounting_invoice');
                if ($customNo === $preview) {
                    generateSequenceNumber($pdo, 'client_mounting_invoice');
                }
            }

            // Calculate totals for this PO
            $stmt = $pdo->prepare("SELECT r.rate_per_sqft, s.width, s.height FROM client_mounting_rates r LEFT JOIN sites s ON r.site_id = s.id WHERE r.po_number = ? AND r.client_id = ?");
            $stmt->execute([$po, $cid]);
            $rows = $stmt->fetchAll();
            $subTotal = 0;
            foreach ($rows as $row) $subTotal += $row['rate_per_sqft'] * ($row['width'] ?? 0) * ($row['height'] ?? 0);
            $cgst = $sgst = $igst = 0;
            if ($gstType === 'igst') $igst = round($subTotal * 0.18, 2);
            else { $cgst = $sgst = round($subTotal * 0.09, 2); }
            $totalAmt = $subTotal + $cgst + $sgst + $igst;

            $pdo->prepare("UPDATE client_mounting_rates SET is_final_invoice=?, approval_status=?,
                custom_invoice_number=?, invoice_date=?, gst_type=?, customer_po_no=?, customer_po_date=?,
                sub_total=?, cgst=?, sgst=?, igst=?, total_amount=?, attachments=?
                WHERE po_number=? AND client_id=?")
                ->execute([$isFinalInvoice, $approvalStatus, $customNo, $customDate, $gstType, $custPONo, $custPODate,
                           $subTotal, $cgst, $sgst, $igst, $totalAmt, $file_path, $po, $cid]);

            if (!$isAdmin) {
                $stmtId = $pdo->prepare("SELECT id FROM client_mounting_rates WHERE po_number = ? LIMIT 1");
                $stmtId->execute([$po]);
                $rateId = $stmtId->fetchColumn();
                if ($rateId) {
                    // Delete old pending request if any
                    $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'client_mounting' AND entity_id = ? AND status = 'pending'")->execute([$rateId]);
                    $stmtAR = $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('client_mounting', ?, ?, ?, 'pending')");
                    $stmtAR->execute([$rateId, $po, $_SESSION['user_id'] ?? 0]);
                }
            }

            echo json_encode(['success' => true, 'invoice_number' => $customNo, 'approval_status' => $approvalStatus]); exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'PHP Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()]); exit;
        }
    }
}

$activePage = 'mounting';
$pageTitle  = 'Client Mounting Invoice';
include_once __DIR__ . '/../../includes/header.php';

$selectedClientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');

$queryWhere = "WHERE 1=1";
$params = [];
if ($selectedClientId) {
    $queryWhere .= " AND r.client_id = ?";
    $params[] = $selectedClientId;
}
if ($campaignFilter !== '') {
    $queryWhere .= " AND r.campaign_name LIKE ?";
    $params[] = '%' . $campaignFilter . '%';
}

$ratesStmt = $pdo->prepare("
    SELECT
        r.po_number,
        r.client_id,
        c.name as client_name,
        GROUP_CONCAT(r.id SEPARATOR '||') as rate_ids,
        GROUP_CONCAT(COALESCE(s.site_code, '-') SEPARATOR '||') as site_codes,
        GROUP_CONCAT(COALESCE(s.width,  0) SEPARATOR '||') as widths,
        GROUP_CONCAT(COALESCE(s.height, 0) SEPARATOR '||') as heights,
        GROUP_CONCAT(r.rate_per_sqft SEPARATOR '||') as rates,
        GROUP_CONCAT(r.mounting_type SEPARATOR '||') as mounting_types,
        MIN(r.created_at) as created_at,
        MAX(r.is_final_invoice) as is_final_invoice,
        MAX(r.approval_status) as approval_status,
        MAX(r.custom_invoice_number) as invoice_number,
        MAX(r.invoice_date) as invoice_date,
        MAX(r.campaign_name) as campaign_name,
        MAX(r.brand_name) as brand_name,
        MAX(r.billing_gstin) as billing_gstin,
        MAX(c.state) as client_state,
        MAX(c.gstin) as client_gstin,
        MAX(c.additional_gst) as additional_gst,
        (SELECT remarks FROM approval_requests WHERE entity_type = 'client_mounting' AND entity_ref COLLATE utf8mb4_unicode_ci = r.po_number COLLATE utf8mb4_unicode_ci ORDER BY id DESC LIMIT 1) as rejection_reason
    FROM client_mounting_rates r
    JOIN partners c ON r.client_id = c.id
    LEFT JOIN sites s ON r.site_id = s.id
    $queryWhere
    GROUP BY r.po_number, r.client_id, c.name, (CASE WHEN r.po_number IS NULL THEN r.id ELSE 0 END)
    ORDER BY MIN(r.id) DESC
");
$ratesStmt->execute($params);
$rates = $ratesStmt->fetchAll();

$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <div>
            <h2 style="font-size:1.25rem; margin:0;">Client Mounting Invoice</h2>
            <p style="margin:0; font-size:0.8rem; color:#64748b; margin-top:2px;">Manage mounting charges billed to clients</p>
        </div>
        <div style="display:flex; gap:0.75rem;">
            <?php if (canAdd('clients')): ?>
            <a href="create_mounting_po.php" class="btn btn-primary"
               style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; background:#0d9488; border-color:#0d9488;">
                <i class="fas fa-plus"></i> Add New Mounting Invoice
            </a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" action="mounting.php" style="display:flex; flex-wrap: wrap; gap:1rem; align-items:flex-end; margin-bottom:1.5rem;">
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Client</label>
            <select name="client_id" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:220px;">
                <option value="">All Clients</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>" <?php echo $selectedClientId === intval($client['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.35rem; min-width:280px;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Campaign Name</label>
            <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..." style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:280px;">
        </div>
        <div style="display:flex; gap:0.75rem;">
            <button type="submit" class="btn btn-primary" style="padding:0.85rem 1.25rem;">Filter</button>
            <a href="mounting.php" class="btn" style="background:#f8fafc; color:#475569; border:1px solid #cbd5e1; padding:0.85rem 1.25rem; text-decoration:none;">Reset</a>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Invoice / PO #</th>
                <th>Campaign / Brand</th>
                <th>Client</th>
                <th>Sites</th>
                <th>Mounting Type</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rates)): ?>
                <tr><td colspan="9" style="text-align:center; color:#94a3b8; padding:2rem;">No Client Mounting Invoices found.</td></tr>
            <?php else: ?>
                <?php foreach ($rates as $r):
                    $ids       = explode('||', $r['rate_ids']);
                    $widths    = explode('||', $r['widths']);
                    $heights   = explode('||', $r['heights']);
                    $unitRates = explode('||', $r['rates']);
                    $siteCodes = explode('||', $r['site_codes']);
                    $mtypes    = array_unique(explode('||', $r['mounting_types']));
                    $subTotal  = 0;
                    foreach ($ids as $i => $id)
                        $subTotal += floatval($widths[$i]) * floatval($heights[$i]) * floatval($unitRates[$i]);
                    $gst      = $subTotal * 0.18;
                    $grandTotal = $subTotal + $gst;
                ?>
                <tr>
                    <td>
                        <?php if ($r['invoice_number']): ?>
                            <strong style="color:#0d9488;"><?php echo htmlspecialchars($r['invoice_number']); ?></strong><br>
                            <span style="font-size:0.65rem; color:#94a3b8;"><?php echo htmlspecialchars($r['po_number'] ?? ''); ?></span>
                        <?php elseif ($r['po_number']): ?>
                            <strong style="color:#0d9488;">#<?php echo htmlspecialchars($r['po_number']); ?></strong>
                        <?php else: ?>
                            <span style="color:#cbd5e1;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $camp_brand = [];
                        if (!empty($r['campaign_name'])) $camp_brand[] = trim($r['campaign_name']);
                        if (!empty($r['brand_name'])) $camp_brand[] = trim($r['brand_name']);
                        $display_camp_brand = implode(' / ', $camp_brand);
                        if (!empty($display_camp_brand)): ?>
                            <div style="font-size: 0.72rem; color: #2563eb; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; padding: 2px 6px; border-radius: 4px;">
                                <i class="fas fa-bullhorn" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($display_camp_brand); ?>
                            </div>
                        <?php else: ?>
                            <span style="color:#cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><div style="font-weight:700; color:#1e293b;"><?php echo htmlspecialchars($r['client_name']); ?></div></td>
                    <td>
                        <div style="font-size:0.8rem; color:#475569; font-weight:600;"><?php echo count($ids); ?> site<?php echo count($ids)>1?'s':''; ?></div>
                        <div style="font-size:0.7rem; color:#94a3b8;">
                            <?php echo htmlspecialchars(implode(', ', array_slice($siteCodes, 0, 3))); ?>
                            <?php if (count($siteCodes) > 3) echo ' +' . (count($siteCodes)-3) . ' more'; ?>
                        </div>
                    </td>
                    <td>
                        <?php foreach ($mtypes as $mt): ?>
                        <span style="background:#f0fdfa;color:#0d9488;padding:0.1rem 0.5rem;border-radius:4px;font-size:0.65rem;font-weight:800;display:inline-block;margin:1px;"><?php echo htmlspecialchars($mt); ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td style="font-size:0.85rem; color:#475569;">
                        <?php echo $r['invoice_date'] ? date('d M Y', strtotime($r['invoice_date'])) : date('d M Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td>
                        <div style="font-weight:800; color:#059669;">₹<?php echo number_format($subTotal, 2); ?></div>
                        <div style="font-size:0.65rem; color:#94a3b8;">+GST: ₹<?php echo number_format($gst, 2); ?></div>
                        <div style="font-size:0.7rem; font-weight:800; color:#0f172a;">Total: ₹<?php echo number_format($grandTotal, 2); ?></div>
                    </td>
                    <td>
                        <?php if ($r['approval_status'] === 'pending_approval'): ?>
                            <span style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;" title="Pending Admin Approval">
                                <i class="fas fa-clock"></i> Awaiting Approval
                            </span>
                        <?php elseif ($r['approval_status'] === 'rejected'): ?>
                            <span style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;" title="Rejected by Admin">
                                <i class="fas fa-times-circle"></i> Rejected
                            </span>
                            <?php if (!empty($r['rejection_reason'])): ?>
                                <div style="font-size: 0.65rem; color: #ef4444; margin-top: 4px; font-weight: 600;" title="<?php echo htmlspecialchars($r['rejection_reason']); ?>">
                                    Reason: <?php echo htmlspecialchars($r['rejection_reason']); ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($r['is_final_invoice']): ?>
                            <span style="background:#f0fdf4;color:#15803d;border:1px solid #dcfce7;padding:0.15rem 0.5rem;border-radius:50px;font-size:0.6rem;font-weight:800;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-check-circle"></i> Final Invoice
                            </span>
                        <?php else: ?>
                            <span style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:0.15rem 0.5rem;border-radius:50px;font-size:0.6rem;font-weight:800;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-file-invoice"></i> Draft
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php
                        // Resolve Billing State for default GST type
                        $billingGst = $r['billing_gstin'] ?? '';
                        $clientState = $r['client_state'] ?? '';
                        $clientGst = $r['client_gstin'] ?? '';
                        
                        $finalState = $clientState;
                        $finalGst = $clientGst;
                        
                        if (!empty($billingGst) && $billingGst !== $clientGst) {
                            if (!empty($r['additional_gst'])) {
                                $extra = json_decode($r['additional_gst'], true);
                                if (is_array($extra)) {
                                    foreach ($extra as $item) {
                                        if (isset($item['gstin']) && $item['gstin'] === $billingGst) {
                                            $finalState = $item['state'] ?? '';
                                            $finalGst = $billingGst;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        
                        $isInterstate = (strcasecmp($finalState, 'West Bengal') !== 0 && substr($finalGst, 0, 2) !== '19');
                        $defaultGstType = $isInterstate ? 'igst' : 'cgst_sgst';

                        $viewUrl = "view_mounting_invoice.php?client_id={$r['client_id']}";
                        if ($r['po_number']) $viewUrl .= "&po_number=" . urlencode($r['po_number']);
                        else foreach ($ids as $id) $viewUrl .= "&rate_ids[]=$id";
                        ?>
                        
                        <?php if (hasRole('admin') || $r['approval_status'] === 'approved'): ?>
                        <a href="<?php echo $viewUrl; ?>" target="_blank" class="btn-icon" style="color:#0d9488;" title="View / Print Invoice"><i class="fas fa-eye"></i></a>
                        <?php endif; ?>
 
                        <?php if (!$r['is_final_invoice'] && $r['approval_status'] !== 'pending_approval'): ?>
                        <button class="btn-icon" onclick="openFinalizePopup('<?php echo htmlspecialchars($r['po_number']??'',ENT_QUOTES); ?>',<?php echo $r['client_id']; ?>,'<?php echo implode(',',$ids); ?>', '<?php echo $defaultGstType; ?>')"
                                style="color:#0f172a;" title="Generate Final Invoice">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </button>
                        <?php endif; ?>

                        <?php if ($r['is_final_invoice']): ?>
                        <a href="<?php echo $viewUrl; ?>&final=1" target="_blank" class="btn-icon" style="color:#10b981;" title="Print Final Invoice"><i class="fas fa-print"></i></a>
                        <?php endif; ?>

                        <?php if (hasRole('admin') || ($r['approval_status'] !== 'approved')): ?>
                        <?php $editUrl = "create_mounting_po.php?action=edit&client_id={$r['client_id']}";
                              if ($r['po_number']) $editUrl .= "&po_number=".urlencode($r['po_number']);
                              else foreach($ids as $id) $editUrl .= "&rate_ids[]=$id"; ?>
                        <a href="<?php echo $editUrl; ?>" class="btn-icon" style="color:#0284c7;" title="Edit"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>

                        <?php if (canDelete('clients')): ?>
                        <button class="btn-icon btn-delete" onclick="deletePO('<?php echo htmlspecialchars($r['po_number'] ?? '', ENT_QUOTES); ?>', '<?php echo implode(',', $ids); ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function deletePO(poNumber, rateIds) {
    Swal.fire({ 
        title: 'Delete Client Mounting PO / Invoice?', 
        text: "Are you sure you want to remove this Client Mounting Invoice and all its sites?", 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#ef4444', 
        confirmButtonText: 'Yes, delete' 
    })
    .then(r => { 
        if(r.isConfirmed) {
            fetch('mounting.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete&po_number=' + encodeURIComponent(poNumber) + '&rate_ids=' + encodeURIComponent(rateIds)
            }).then(() => Swal.fire('Deleted!', 'Client Mounting Invoice has been removed.', 'success').then(() => location.reload())); 
        }
    });
}

function openFinalizePopup(poNumber, clientId, rateIdsStr, defaultGstType) {
    Swal.fire({
        title: 'Generate Final Mounting Invoice',
        html: `
            <div style="text-align:left;">
                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">INVOICE NUMBER (Last Used: <?php echo getLastSequenceNumber($pdo, 'client_mounting_invoice'); ?>) <span style="color:red;">*</span></label>
                <input id="custom_invoice_number" class="swal2-input" value="<?php echo getPreviewSequenceNumber($pdo, 'client_mounting_invoice'); ?>" placeholder="e.g. SCRM/26-27/0001" style="margin:0 0 1rem 0;width:100%;box-sizing:border-box;">

                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">INVOICE DATE <span style="color:red;">*</span></label>
                <input id="custom_invoice_date" type="date" class="swal2-input" value="${new Date().toISOString().split('T')[0]}" style="margin:0 0 1rem 0;width:100%;box-sizing:border-box;">

                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">CUSTOMER PO NUMBER <span style="color:red;">*</span></label>
                <input id="customer_po_no" class="swal2-input" placeholder="Customer PO reference" style="margin:0 0 1rem 0;width:100%;box-sizing:border-box;">

                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">CUSTOMER PO DATE <span style="color:red;">*</span></label>
                <input id="customer_po_date" type="date" class="swal2-input" style="margin:0 0 1rem 0;width:100%;box-sizing:border-box;">
                
                <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:5px;">UPLOAD ATTACHMENT (PDF/IMAGE) <span style="color:red;">*</span></label>
                <input id="customer_po_file" type="file" accept=".pdf,image/*" class="swal2-file" style="margin:0;width:100%;box-sizing:border-box;border:1px solid #e2e8f0;padding:10px;border-radius:6px;">
            </div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-file-invoice-dollar"></i> Generate Invoice',
        confirmButtonColor: '#0d9488',
        preConfirm: () => {
            const custom_invoice_number = document.getElementById('custom_invoice_number').value;
            const custom_invoice_date = document.getElementById('custom_invoice_date').value;
            const customer_po_no = document.getElementById('customer_po_no').value;
            const customer_po_date = document.getElementById('customer_po_date').value;
            const po_file = document.getElementById('customer_po_file').files[0];
            
            if (!custom_invoice_number) { Swal.showValidationMessage('Invoice Number is mandatory'); return false; }
            if (!custom_invoice_date) { Swal.showValidationMessage('Invoice Date is mandatory'); return false; }
            if (!customer_po_no) { Swal.showValidationMessage('Customer PO Number is mandatory'); return false; }
            if (!customer_po_date) { Swal.showValidationMessage('Customer PO Date is mandatory'); return false; }
            if (!po_file) { Swal.showValidationMessage('Please upload the PO attachment (PDF/Image)'); return false; }
            
            let formData = new FormData();
            formData.append('action', 'finalize');
            formData.append('po_number', poNumber);
            formData.append('client_id', clientId);
            formData.append('custom_invoice_number', custom_invoice_number);
            formData.append('custom_invoice_date', custom_invoice_date);
            formData.append('gst_type', defaultGstType);
            formData.append('customer_po_no', customer_po_no);
            formData.append('customer_po_date', customer_po_date);
            formData.append('customer_po_file', po_file);
            
            return fetch('mounting.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json())
            .then(res => {
                if (!res.success) {
                    throw new Error(res.message || 'Generation failed');
                }
                return res;
            }).catch(err => {
                Swal.showValidationMessage(`Request failed: ${err}`);
            });
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        const res = result.value;
        if (res.approval_status === 'pending_approval') {
            Swal.fire({ title:'Approval Sent!', text:`Invoice ${res.invoice_number} submitted for admin approval.`, icon:'success', confirmButtonColor:'#0d9488' })
            .then(() => location.reload());
        } else {
            Swal.fire({ title:'Invoice Generated!', text:`Invoice ${res.invoice_number} created.`, icon:'success', confirmButtonColor:'#0d9488' })
            .then(() => location.reload());
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const p = new URLSearchParams(window.location.search);
    if (p.has('msg')) {
        const msgs = { added:'Client Mounting Invoice created.', updated:'Client Mounting Invoice updated.' };
        const text = msgs[p.get('msg')];
        if (text) { Swal.fire({title:'Success',text,icon:'success',confirmButtonColor:'#0d9488',timer:2500,showConfirmButton:false}); window.history.replaceState({},document.title,window.location.pathname); }
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
