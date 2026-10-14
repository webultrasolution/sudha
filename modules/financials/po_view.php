<?php
$activePage = 'pos';
$pageTitle = 'View Purchase Order';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

$poId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$po = $pdo->prepare("
    SELECT po.*, v.name as vendor_name, v.address as v_address, v.gstin as v_gstin, v.phone as v_phone,
           COALESCE(c.display_name, po.campaign_name) as camp_name, 
           COALESCE(c.project_id, 'Direct') as proj_id,
           e.name as entity_name, e.logo as entity_logo, e.address as entity_address, e.gstin as entity_gstin, e.pan as entity_pan, e.letterhead as entity_letterhead, e.signature as entity_signature
    FROM purchase_orders po
    JOIN partners v ON po.vendor_id = v.id
    LEFT JOIN campaigns c ON po.campaign_id = c.id
    LEFT JOIN entities e ON po.entity_id = e.id
    WHERE po.id = ?
");
$po->execute([$poId]);
$poData = $po->fetch();

if (!$poData) {
    echo "<div class='card'>PO not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$items = $pdo->prepare("
    SELECT pi.*, 
           COALESCE(bi.custom_site_name, s.name) as site_name, s.site_code, 
           COALESCE(bi.custom_location, s.location) as location, s.city, s.width, s.height, s.light_type, s.hsn_code
    FROM po_items pi
    JOIN sites s ON pi.site_id = s.id
    LEFT JOIN purchase_orders po ON pi.po_id = po.id
    LEFT JOIN booking_items bi ON po.campaign_id = bi.booking_id AND pi.site_id = bi.site_id
    WHERE pi.po_id = ?
");
$items->execute([$poId]);
$poItems = $items->fetchAll();

$attachments = $pdo->prepare("SELECT * FROM po_attachments WHERE po_id = ?");
$attachments->execute([$poId]);
$poAttachments = $attachments->fetchAll();
?>

<div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;" class="no-print">
    <button type="button" onclick="sendPoEmail(<?php echo $poId; ?>, '<?php echo htmlspecialchars($poData['po_number'] ?: ('PO-' . $poId)); ?>', '<?php echo number_format($poData['total_amount'], 2); ?>', '<?php echo date('d M Y', strtotime($poData['po_date'] ?? $poData['created_at'])); ?>', '', '', '<?php echo addslashes($poData['vendor_email'] ?? ''); ?>', '<?php echo addslashes($poData['vendor_name'] ?? 'Vendor'); ?>')" class="btn" style="background: #ea4335; color: #ffffff; border-radius: 8px; padding: 0.75rem 1.25rem; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
        <i class="fas fa-paper-plane"></i> Email PO (SMTP)
    </button>
    <button type="button" onclick="sendPoWhatsApp(<?php echo $poId; ?>, '<?php echo htmlspecialchars($poData['po_number'] ?: ('PO-' . $poId)); ?>', '<?php echo number_format($poData['total_amount'], 2); ?>', '<?php echo date('d M Y', strtotime($poData['po_date'] ?? $poData['created_at'])); ?>', '', '', '<?php echo addslashes($poData['vendor_phone'] ?? ''); ?>', '<?php echo addslashes($poData['vendor_name'] ?? 'Vendor'); ?>')" class="btn" style="background: #25D366; color: #ffffff; border-radius: 8px; padding: 0.75rem 1.25rem; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
        <i class="fab fa-whatsapp"></i> WhatsApp PO
    </button>
    <?php if (($poData['approval_status'] ?? '') === 'approved'): ?>
        <a href="../operations/generate_po.php?po_id=<?php echo $poId; ?>" target="_blank" class="btn btn-primary" style="background: #0d9488; border-radius: 8px; padding: 0.75rem 1.5rem; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-file-pdf"></i> Professional PDF
        </a>
    <?php else: ?>
        <div class="btn" style="background: #e2e8f0; color: #64748b; border-radius: 8px; padding: 0.75rem 1.5rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; cursor: not-allowed;" title="Awaiting admin approval">
            <i class="fas fa-lock"></i> PDF Locked
        </div>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-primary" style="background: #0f172a; border-radius: 8px; padding: 0.75rem 1.5rem; font-weight: 800;">
        <i class="fas fa-print"></i> PRINT PAGE
    </button>
</div>

<div style="display: grid; grid-template-columns: 3fr 1fr; gap: 2rem;">
    <div class="card" id="po-print-area" style="padding: 0; border: 1px solid #000; overflow: hidden; border-radius: 0;">
        <!-- Header / Letterhead -->
        <?php 
        $co_details = resolveCompanyDetails($poData['entity_id'] ?? null);
        $header_logo = !empty($co_details['letterhead']) ? $co_details['letterhead'] : (!empty($co_details['logo']) ? $co_details['logo'] : '');
        $header_name = $co_details['name'];
        $company_address = $co_details['address'];
        $company_phone = $co_details['phone'];
        $company_email = $co_details['email'];
        ?>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 10px 2px;">
            <div style="flex: 1.4; text-align: left;">
                <?php if ($header_logo): ?>
                    <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $header_logo; ?>" style="max-height: 80px; width: auto; display: block; margin-bottom: 5px;">
                <?php else: ?>
                    <h2 style="margin: 0; text-transform: uppercase; font-size: 18px; color: #8B1A1A;"><?php echo htmlspecialchars($header_name); ?></h2>
                <?php endif; ?>
            </div>
            <div style="flex: 1.2; text-align: center; padding-top: 15px;">
                <div style="font-size: 16px; font-weight: bold; text-decoration: underline; letter-spacing: 1.5px; text-transform: uppercase;">PURCHASE ORDER</div>
            </div>
            <div style="flex: 0.8; text-align: right; font-style: italic; font-size: 10px; padding-top: 15px; color: #555;">
                Original Copy
            </div>
        </div>
        <div style="padding: 0 10px 10px; font-size: 10px; line-height: 1.4; color: #000; border-bottom: 1px solid #000; margin-bottom: 10px;">
            <?php echo nl2br(htmlspecialchars($company_address)); ?><br>
            Ph : <?php echo htmlspecialchars($company_phone); ?> &nbsp;|&nbsp; Email : <?php echo htmlspecialchars($company_email); ?>
        </div>



        <div style="padding: 1.5rem;">
            <!-- Info Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; border-bottom: 1px solid #000; padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <h4 style="text-decoration: underline; font-style: italic; font-size: 0.9rem; margin-bottom: 0.5rem;">Supplier / Vendor:</h4>
                    <div style="font-weight: 800; font-size: 1.1rem; color: #000;"><?php echo $poData['vendor_name']; ?></div>
                    <div style="color: #475569; margin-top: 0.25rem; font-size: 0.85rem;"><?php echo $poData['v_address']; ?></div>
                    <div style="margin-top: 0.5rem; font-size: 0.85rem;">
                        <strong>GSTIN:</strong> <?php echo $poData['v_gstin']; ?><br>
                        <strong>Phone:</strong> <?php echo $poData['v_phone']; ?>
                    </div>
                </div>
                <div style="border-left: 1px solid #e2e8f0; padding-left: 2rem;">
                    <div style="display: flex; margin-bottom: 4px;">
                        <span style="width: 100px; font-weight: 600;">PO Number</span><span style="margin: 0 10px;">:</span>
                        <span style="font-weight: 800; color: #000;"><?php echo $poData['po_number']; ?></span>
                    </div>
                    <div style="display: flex; margin-bottom: 4px;">
                        <span style="width: 100px; font-weight: 600;">PO Date</span><span style="margin: 0 10px;">:</span>
                        <span><?php echo date('d M Y', strtotime($poData['po_date'])); ?></span>
                    </div>
                    <div style="display: flex; margin-bottom: 4px;">
                        <span style="width: 100px; font-weight: 600;">Campaign</span><span style="margin: 0 10px;">:</span>
                        <span style="font-weight: 700;"><?php echo $poData['camp_name']; ?></span>
                    </div>
                    <div style="display: flex; margin-bottom: 4px;">
                        <span style="width: 100px; font-weight: 600;">Project ID</span><span style="margin: 0 10px;">:</span>
                        <span style="color: var(--secondary);"><?php echo $poData['proj_id']; ?></span>
                    </div>
                    <div style="display: flex; margin-top: 10px;">
                        <span style="width: 100px; font-weight: 600;">Status</span><span style="margin: 0 10px;">:</span>
                        <?php 
                        $currentInvoiceStatus = $poData['vendor_invoice_approval_status'] ?? '';
                        if ($currentInvoiceStatus === 'approved') {
                            echo '<span class="status-badge approved">APPROVED</span>';
                        } elseif ($currentInvoiceStatus === 'pending') {
                            echo '<span class="status-badge pending">PENDING APPROVAL</span>';
                        } elseif ($currentInvoiceStatus === 'rejected') {
                            echo '<span class="status-badge rejected">REJECTED</span>';
                        } else {
                            echo '<span class="status-badge pending">PENDING</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div style="background: #f1f5f9; padding: 0.5rem; text-align: center; font-weight: 800; letter-spacing: 2px; margin-bottom: 1rem; border: 1px solid #e2e8f0;">PURCHASE ORDER DETAILS</div>
            <table class="table" style="border: 1px solid #e2e8f0; font-size: 0.82rem; border-collapse: collapse; width: 100%;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 45px; text-align: center; padding: 0.5rem 0.25rem;">SI No</th>
                        <th style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 75px; padding: 0.5rem 0.25rem;">HSN/SAC</th>
                        <th style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 70px; padding: 0.5rem 0.25rem;">City</th>
                        <th style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 0.5rem 0.25rem;">Location</th>
                        <th style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 80px; padding: 0.5rem 0.25rem;">Size</th>
                        <th style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 70px; padding: 0.5rem 0.25rem;">Lit Type</th>
                        <th style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 130px; padding: 0.5rem 0.25rem;">Period</th>
                        <th style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 90px; padding: 0.5rem 0.25rem;">Rate</th>
                        <th style="text-align: right; border-bottom: 1px solid #e2e8f0; width: 90px; padding: 0.5rem 0.25rem;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $si = 1;
                    foreach ($poItems as $item): 
                    ?>
                    <tr>
                        <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; text-align: center; font-weight: 600; padding: 0.5rem 0.25rem;"><?php echo $si++; ?></td>
                        <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #475569; padding: 0.5rem 0.25rem;"><?php echo htmlspecialchars($item['hsn_code'] ?: '998366'); ?></td>
                        <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #475569; padding: 0.5rem 0.25rem;"><?php echo htmlspecialchars($item['city'] ?: '—'); ?></td>
                        <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 0.5rem 0.25rem;">
                            <div style="font-weight: 700; color: #000;"><?php echo htmlspecialchars($item['site_name'] ?? $item['site_code'] ?? '—'); ?></div>
                            <?php if (!empty($item['location'])): ?>
                                <div style="font-size: 0.72rem; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars($item['location']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 0.78rem; color: #475569; padding: 0.5rem 0.25rem;">
                            <?php 
                            if (!empty($item['width']) && !empty($item['height'])) {
                                echo htmlspecialchars($item['width']) . ' x ' . htmlspecialchars($item['height']);
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 0.78rem; color: #475569; text-transform: uppercase; padding: 0.5rem 0.25rem;">
                            <?php echo htmlspecialchars($item['light_type'] ?: '—'); ?>
                        </td>
                        <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 0.78rem; color: #475569; padding: 0.5rem 0.25rem;">
                            <?php 
                            $sD = (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00') ? date('d/m/y', strtotime($item['start_date'])) : 'N/A';
                            $eD = (!empty($item['end_date']) && $item['end_date'] !== '0000-00-00') ? date('d/m/y', strtotime($item['end_date'])) : 'N/A';
                            echo "$sD - $eD";
                            ?>
                        </td>
                        <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #475569; padding: 0.5rem 0.25rem;"><?php echo formatCurrency($item['monthly_rate']); ?></td>
                        <td style="text-align: right; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #000; padding: 0.5rem 0.25rem;"><?php echo formatCurrency($item['cost']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td colspan="8" style="text-align: right; padding: 0.75rem; border-right: 1px solid #e2e8f0;">Subtotal (Taxable):</td>
                        <td style="text-align: right; padding: 0.75rem 0.25rem;"><?php echo formatCurrency($poData['po_amount']); ?></td>
                    </tr>
                    <?php
                    $cgst_val = floatval($poData['cgst_amount'] ?? 0);
                    $sgst_val = floatval($poData['sgst_amount'] ?? 0);
                    $igst_val = floatval($poData['igst_amount'] ?? 0);
                    $total_tax = $cgst_val + $sgst_val + $igst_val;
                    
                    if ($total_tax > 0) {
                        if ($igst_val > 0) {
                            $tax_label = 'IGST (18%):';
                        } else {
                            $tax_label = 'CGST + SGST (9%+9%):';
                        }
                    } else {
                        $tax_label = 'GST (0%):';
                    }
                    ?>
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td colspan="8" style="text-align: right; padding: 0.75rem; border-right: 1px solid #e2e8f0;"><?php echo $tax_label; ?></td>
                        <td style="text-align: right; padding: 0.75rem 0.25rem;"><?php echo formatCurrency($total_tax); ?></td>
                    </tr>
                    <tr style="background: #eff6ff; font-weight: 900; font-size: 1rem; color: #1e3a8a;">
                        <td colspan="8" style="text-align: right; padding: 1rem 0.75rem; border-right: 1px solid #e2e8f0; border-top: 2px solid #1e3a8a;">GRAND TOTAL:</td>
                        <td style="text-align: right; border-top: 2px solid #1e3a8a; padding: 1rem 0.25rem;"><?php echo formatCurrency($poData['total_amount']); ?></td>
                    </tr>
                </tfoot>
            </table>

            <?php if(!empty($poData['remarks'])): ?>
                <div class="avoid-break" style="margin-top: 1.5rem; padding: 1rem; background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 8px;">
                    <div style="font-weight: 800; font-size: 0.75rem; text-transform: uppercase; color: #92400e; margin-bottom: 0.5rem;">Remarks / Special Instructions</div>
                    <div style="font-style: italic; color: #000;"><?php echo nl2br(htmlspecialchars($poData['remarks'])); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="no-print">
        <div class="card">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="fas fa-file-invoice"></i> Vendor Invoice</h3>
            
            <?php if (($poData['status'] ?? '') === 'cancelled'): ?>
                <div style="background: #fdf2f8; padding: 1.25rem; border-radius: 12px; border: 1px solid #fbcfe8; text-align: center;">
                    <i class="fas fa-times-circle" style="font-size: 2rem; color: #db2777; margin-bottom: 0.75rem; display: block;"></i>
                    <span style="font-size: 0.85rem; color: #9d174d; font-weight: 600;">Purchase Order Cancelled</span>
                </div>
            <?php else: ?>
                <?php 
                $invoiceStatus = $poData['vendor_invoice_approval_status'] ?? '';
                if ($invoiceStatus === 'approved'): 
                ?>
                    <div style="background: #ecfdf5; padding: 1.25rem; border-radius: 12px; border: 1px solid #a7f3d0; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; color: #065f46;">
                                <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
                                <strong style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Invoice Approved</strong>
                            </div>
                            <?php if (canEdit('financials')): ?>
                                <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" style="background: #ffffff; border: 1px solid #a7f3d0; color: #065f46; border-radius: 6px; padding: 2px 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer;" title="Edit Invoice Details & Files">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 2px;"><?php echo htmlspecialchars($poData['vendor_invoice_no']); ?></div>
                        <div style="font-size: 0.75rem; color: #475569; margin-bottom: 6px;">Date: <?php echo date('d M Y', strtotime($poData['vendor_invoice_date'])); ?></div>
                        <div style="font-size: 0.95rem; font-weight: 900; color: #059669; margin-bottom: 0.5rem;">Amount: <?php echo formatCurrency($poData['vendor_invoice_amount']); ?></div>
                        
                        <?php if (canEdit('financials')): ?>
                            <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" class="btn" style="background: #0d9488; color: white; border-radius: 8px; font-weight: 700; width: 100%; padding: 0.5rem 0.75rem; font-size: 0.8rem; margin-top: 0.5rem; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fas fa-edit"></i> Edit Invoice & Attachments
                            </button>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; margin-bottom: 0.5rem;">
                        <h3 style="font-size: 0.85rem; margin: 0; color: #475569;"><i class="fas fa-paperclip"></i> Invoice Attachments (<?php echo count($poAttachments); ?>)</h3>
                        <?php if (canEdit('financials')): ?>
                            <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-plus"></i> Add Files
                            </button>
                        <?php endif; ?>
                    </div>
                    <div id="attachments-list-approved">
                        <?php foreach ($poAttachments as $att): ?>
                            <div id="att-item-<?php echo $att['id']; ?>" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                                <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                                <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; font-size: 0.75rem;"><?php echo $att['filename']; ?></span>
                                <a href="../../uploads/pos/<?php echo $att['filename']; ?>" target="_blank" class="btn-icon" style="color: #0284c7; background: #e0f2fe; border-radius: 6px; padding: 4px 8px; margin-right: 2px;" title="View PDF"><i class="fas fa-eye"></i></a>
                                <a href="../../uploads/pos/<?php echo $att['filename']; ?>" download="<?php echo $att['filename']; ?>" class="btn-icon" style="color: #059669; background: #d1fae5; border-radius: 6px; padding: 4px 8px; margin-right: 2px;" title="Download PDF"><i class="fas fa-download"></i></a>
                                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                    <button type="button" class="btn-icon" style="color: #ef4444; background: #fee2e2; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer;" onclick="deletePoAttachmentInsideModal(<?php echo $poId; ?>, <?php echo $att['id']; ?>)" title="Remove Attachment (Admin Only)"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($poAttachments)): ?>
                            <div style="text-align: center; padding: 0.85rem; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 0.25rem;">
                                <p style="font-size: 0.8rem; color: #94a3b8; font-style: italic; margin: 0 0 6px 0;">No documents attached.</p>
                                <?php if (canEdit('financials')): ?>
                                    <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer;">
                                        <i class="fas fa-paperclip"></i> Upload Attachment(s)
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($invoiceStatus === 'pending'): ?>
                    <div style="background: #fffbeb; padding: 1.25rem; border-radius: 12px; border: 1px solid #fde68a; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; color: #92400e;">
                                <i class="fas fa-hourglass-half" style="font-size: 1.1rem;"></i>
                                <strong style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Awaiting Admin Approval</strong>
                            </div>
                            <?php if (canEdit('financials')): ?>
                                <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" style="background: #ffffff; border: 1px solid #fde68a; color: #92400e; border-radius: 6px; padding: 2px 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer;" title="Edit Invoice Details & Files">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 2px;"><?php echo htmlspecialchars($poData['vendor_invoice_no']); ?></div>
                        <div style="font-size: 0.75rem; color: #475569; margin-bottom: 6px;">Date: <?php echo date('d M Y', strtotime($poData['vendor_invoice_date'])); ?></div>
                        <div style="font-size: 0.95rem; font-weight: 900; color: #d97706; margin-bottom: 0.5rem;">Amount: <?php echo formatCurrency($poData['vendor_invoice_amount']); ?></div>

                        <?php if (canEdit('financials')): ?>
                            <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" class="btn" style="background: #d97706; color: white; border-radius: 8px; font-weight: 700; width: 100%; padding: 0.5rem 0.75rem; font-size: 0.8rem; margin-top: 0.5rem; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fas fa-edit"></i> Edit Invoice & Attachments
                            </button>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; margin-bottom: 0.5rem;">
                        <h3 style="font-size: 0.85rem; margin: 0; color: #475569;"><i class="fas fa-paperclip"></i> Invoice Attachments (<?php echo count($poAttachments); ?>)</h3>
                        <?php if (canEdit('financials')): ?>
                            <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-plus"></i> Add Files
                            </button>
                        <?php endif; ?>
                    </div>
                    <div id="attachments-list-pending">
                        <?php foreach ($poAttachments as $att): ?>
                            <div id="att-item-<?php echo $att['id']; ?>" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                                <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                                <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; font-size: 0.75rem;"><?php echo $att['filename']; ?></span>
                                <a href="../../uploads/pos/<?php echo $att['filename']; ?>" target="_blank" class="btn-icon" style="color: #0284c7; background: #e0f2fe; border-radius: 6px; padding: 4px 8px; margin-right: 2px;" title="View PDF"><i class="fas fa-eye"></i></a>
                                <a href="../../uploads/pos/<?php echo $att['filename']; ?>" download="<?php echo $att['filename']; ?>" class="btn-icon" style="color: #059669; background: #d1fae5; border-radius: 6px; padding: 4px 8px; margin-right: 2px;" title="Download PDF"><i class="fas fa-download"></i></a>
                                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                    <button type="button" class="btn-icon" style="color: #ef4444; background: #fee2e2; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer;" onclick="deletePoAttachmentInsideModal(<?php echo $poId; ?>, <?php echo $att['id']; ?>)" title="Remove Attachment (Admin Only)"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($poAttachments)): ?>
                            <div style="text-align: center; padding: 0.85rem; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 0.25rem;">
                                <p style="font-size: 0.8rem; color: #94a3b8; font-style: italic; margin: 0 0 6px 0;">No documents attached.</p>
                                <?php if (canEdit('financials')): ?>
                                    <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer;">
                                        <i class="fas fa-paperclip"></i> Upload Attachment(s)
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if ($invoiceStatus === 'rejected'): ?>
                        <div style="background: #fef2f2; padding: 1rem; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 1rem; font-size: 0.8rem; color: #991b1b;">
                            <i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i> <strong>Invoice Rejected</strong>
                            <?php if (!empty($poData['rejection_reason'])): ?>
                                <p style="margin: 4px 0 0 0; font-style: italic;"><?php echo htmlspecialchars($poData['rejection_reason']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (canEdit('financials')): ?>
                    <form id="invoiceForm" style="margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">Inv Number <span style="color:red;">*</span></label>
                            <input type="text" id="v_inv_no" class="p-input" value="<?php echo htmlspecialchars($poData['vendor_invoice_no'] ?? ''); ?>" placeholder="e.g. INV-12345" required>
                        </div>
                        <div class="form-group" style="margin-top: 0.75rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">Inv Date <span style="color:red;">*</span></label>
                            <input type="date" id="v_inv_date" class="p-input" value="<?php echo htmlspecialchars($poData['vendor_invoice_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="form-group" style="margin-top: 0.75rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: #475569;">Invoice Amount (₹) <span style="color:red;">*</span></label>
                            <input type="number" id="v_inv_amount" step="0.01" class="p-input" value="<?php echo htmlspecialchars($poData['vendor_invoice_amount'] ?? $poData['total_amount'] ?? ''); ?>" placeholder="e.g. 50000.00" required>
                        </div>

                        <!-- Attachments inside the Form -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.25rem; margin-bottom: 0.5rem;">
                            <h3 style="font-size: 0.85rem; margin: 0; color: #475569;"><i class="fas fa-paperclip"></i> Upload Attachment(s) <span style="color:red;">*</span></h3>
                            <button type="button" onclick="openVendorInvoiceModal(<?php echo $poId; ?>)" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-cog"></i> Modal Mode
                            </button>
                        </div>
                        <div id="attachments-list" style="margin-bottom: 0.75rem;">
                            <?php foreach ($poAttachments as $att): ?>
                                <div id="att-item-<?php echo $att['id']; ?>" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                                    <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                                    <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; font-size: 0.75rem;"><?php echo $att['filename']; ?></span>
                                    <a href="../../uploads/pos/<?php echo $att['filename']; ?>" target="_blank" class="btn-icon" style="color: #0284c7; background: #e0f2fe; border-radius: 6px; padding: 4px 8px; margin-right: 2px;" title="View PDF"><i class="fas fa-eye"></i></a>
                                    <a href="../../uploads/pos/<?php echo $att['filename']; ?>" download="<?php echo $att['filename']; ?>" class="btn-icon" style="color: #059669; background: #d1fae5; border-radius: 6px; padding: 4px 8px; margin-right: 2px;" title="Download PDF"><i class="fas fa-download"></i></a>
                                    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                        <button type="button" class="btn-icon" style="color: #ef4444; background: #fee2e2; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer;" onclick="deletePoAttachmentInsideModal(<?php echo $poId; ?>, <?php echo $att['id']; ?>)" title="Remove Attachment (Admin Only)"><i class="fas fa-trash-alt"></i></button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($poAttachments)): ?>
                                <p id="no-attachments-msg" style="font-size: 0.8rem; color: #94a3b8; font-style: italic; margin-bottom: 0.5rem;">No files uploaded yet.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Upload dashed button -->
                        <div style="margin-bottom: 1.5rem;">
                            <input type="file" id="po_file" style="display: none;" multiple onchange="uploadPOFile()">
                            <button type="button" class="btn" style="width: 100%; border: 1px dashed var(--primary); color: var(--primary); font-size: 0.8rem; padding: 0.5rem;" onclick="document.getElementById('po_file').click()">
                                <i class="fas fa-upload"></i> Upload Attachment(s)
                            </button>
                        </div>

                        <!-- Submit button at the very bottom -->
                        <button type="button" class="btn btn-primary" style="width: 100%; background: var(--primary); padding: 0.75rem; font-weight: 700; border-radius: 8px;" onclick="updateVendorInvoice()">
                            <i class="fas fa-paper-plane"></i> <?php echo (($_SESSION['user_role'] ?? '') === 'admin') ? 'Save & Approve Invoice' : 'Submit for Approval'; ?>
                        </button>
                    </form>
                    <?php else: ?>
                        <p style="font-size: 0.85rem; color: #94a3b8; font-style: italic;">No vendor invoice details recorded.</p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.status-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; }
.status-badge.approved { background: #dcfce7; color: #166534; }
.status-badge.pending { background: #fef3c7; color: #d97706; }
.status-badge.rejected { background: #fee2e2; color: #dc2626; }
.status-badge.paid { background: #dcfce7; color: #166534; }
.status-badge.draft { background: #f1f5f9; color: #475569; }
.p-input { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; margin-top: 0.25rem; font-size: 0.85rem; }

@page {
    size: A4;
    margin: 12mm 14mm;
    @bottom-right {
        content: counter(page) "/" counter(pages);
        font-family: Arial, sans-serif;
        font-size: 9px;
        color: #555;
    }
}
.avoid-break {
    page-break-inside: avoid;
    break-inside: avoid;
}

@media print {
    .sidebar, .header, .btn-primary, .no-print { display: none !important; }
    .main-content { margin: 0; padding: 0; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    body { background: white; }
    #po-print-area { width: 100% !important; margin: 0 !important; border: none !important; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; break-inside: avoid; }
}
</style>

<script>
let attachmentCount = <?php echo count($poAttachments); ?>;

function updateVendorInvoice() {
    const no = document.getElementById('v_inv_no').value;
    const dt = document.getElementById('v_inv_date').value;
    const amt = document.getElementById('v_inv_amount').value;
    
    if(!no || !dt || !amt) {
        return Swal.fire('Error', 'Please fill all invoice details (Number, Date, Amount).', 'error');
    }
    if(attachmentCount === 0) {
        return Swal.fire('Attachment Required', 'Please upload at least one invoice scan/file before submitting.', 'warning');
    }

    Swal.showLoading();
    fetch('../../ajax/update_po_invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=<?php echo $poId; ?>&no=${encodeURIComponent(no)}&date=${encodeURIComponent(dt)}&amount=${encodeURIComponent(amt)}`
    }).then(r => r.json()).then(res => {
        if(res.success) {
            const msg = res.auto_approved ? 'Vendor invoice updated and approved.' : 'Vendor invoice submitted for admin approval.';
            Swal.fire('Success!', msg, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Failed to submit invoice.', 'error');
        }
    });
}

function uploadPOFile() {
    const fileInput = document.getElementById('po_file');
    const file = fileInput.files[0];
    if(!file) return;

    Swal.showLoading();

    const formData = new FormData();
    formData.append('po_id', '<?php echo $poId; ?>');
    formData.append('file', file);

    fetch('../../ajax/upload_po_attachment.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.success) {
            attachmentCount++;
            
            // Remove empty list placeholder if present
            const noMsg = document.getElementById('no-attachments-msg');
            if (noMsg) noMsg.remove();
            
            // Dynamically append the newly uploaded attachment link
            const list = document.getElementById('attachments-list');
            const newFileDiv = document.createElement('div');
            const attId = res.attachment_id || Date.now();
            newFileDiv.id = 'att-item-' + attId;
            newFileDiv.style = "display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;";
            const safeName = res.filename.replace(/'/g, "\\'");
            const isAdminUser = <?php echo (($_SESSION['user_role'] ?? '') === 'admin') ? 'true' : 'false'; ?>;
            const delBtnHtml = isAdminUser ? `<button type="button" class="btn-icon" style="color: #ef4444; background: #fee2e2; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer;" onclick="deletePOAttachment(${attId}, '${safeName}')" title="Remove Attachment (Admin Only)"><i class="fas fa-trash-alt"></i></button>` : '';
            newFileDiv.innerHTML = `
                <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; font-size: 0.75rem;">${res.filename}</span>
                <a href="../../uploads/pos/${res.filename}" target="_blank" class="btn-icon" style="color: #0284c7; background: #e0f2fe; border-radius: 6px; padding: 4px 8px; margin-right: 2px;" title="View PDF"><i class="fas fa-eye"></i></a>
                <a href="../../uploads/pos/${res.filename}" download="${res.filename}" class="btn-icon" style="color: #059669; background: #d1fae5; border-radius: 6px; padding: 4px 8px; margin-right: 2px;" title="Download PDF"><i class="fas fa-download"></i></a>
                ${delBtnHtml}
            `;
            list.appendChild(newFileDiv);
            
            fileInput.value = ''; // Clear file input
            Swal.fire('Uploaded', 'File attached successfully.', 'success');
        } else {
            Swal.fire('Upload Failed', res.message || 'Error occurred.', 'error');
        }
    });
}

function deletePOAttachment(attId, filename) {
    Swal.fire({
        title: 'Remove Attachment?',
        text: `Are you sure you want to remove ${filename}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, remove it'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const formData = new FormData();
            formData.append('id', attId);

            fetch('../../ajax/delete_po_attachment.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    attachmentCount = Math.max(0, attachmentCount - 1);
                    const itemElem = document.getElementById('att-item-' + attId);
                    if (itemElem) itemElem.remove();
                    
                    if (attachmentCount === 0) {
                        const attList = document.getElementById('attachments-list');
                        if (attList) {
                            attList.innerHTML = '<p id="no-attachments-msg" style="font-size: 0.8rem; color: #94a3b8; font-style: italic; margin-bottom: 0.5rem;">No files uploaded yet.</p>';
                        }
                    }
                    Swal.fire('Removed!', 'Attachment has been removed.', 'success');
                } else {
                    Swal.fire('Error', res.message || 'Failed to remove attachment.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        }
    });
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<iframe id="po-pdf-render-iframe" style="position: fixed; left: -9999px; top: 0; width: 794px; height: 1200px; border: none; opacity: 0; pointer-events: none; z-index: -1;"></iframe>

<script>
// Helper function to render official Purchase Order to PDF Blob
async function generatePoPdfBlob(poId) {
    try {
        const poResp = await fetch(`../../modules/operations/generate_po.php?po_id=${poId}`);
        if (!poResp.ok) return null;
        const poHtml = await poResp.text();

        let iframe = document.getElementById('po-pdf-render-iframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'po-pdf-render-iframe';
            iframe.style.cssText = 'position: fixed; left: -9999px; top: 0; width: 794px; height: 1200px; border: none; opacity: 0; pointer-events: none; z-index: -1;';
            document.body.appendChild(iframe);
        }

        const iframeDoc = iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(poHtml);
        iframeDoc.close();

        // Inject overrides for perfect A4 rendering
        const styleElem = iframeDoc.createElement('style');
        styleElem.innerHTML = `
            * { box-sizing: border-box !important; }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                font-family: Arial, sans-serif !important;
                width: 794px !important;
            }
            .po-wrapper {
                width: 794px !important;
                max-width: 794px !important;
                min-height: auto !important;
                margin: 0 auto !important;
                padding: 6mm 8mm !important;
                box-shadow: none !important;
                border: none !important;
                box-sizing: border-box !important;
                background: #ffffff !important;
            }
            .btn-print, .no-print { display: none !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            img { max-width: 100% !important; }
        `;
        iframeDoc.head.appendChild(styleElem);

        // Wait for all images inside iframe to load (letterhead logo, signature, stamps)
        const images = Array.from(iframeDoc.images);
        if (images.length > 0) {
            await Promise.all(images.map(img => {
                if (img.complete && img.naturalHeight !== 0) return Promise.resolve();
                return new Promise(resolve => {
                    img.onload = resolve;
                    img.onerror = resolve;
                    setTimeout(resolve, 2000);
                });
            }));
        }

        // Stabilization pause
        await new Promise(resolve => setTimeout(resolve, 300));

        const targetElem = iframeDoc.querySelector('.po-wrapper') || iframeDoc.body;
        const canvas = await html2canvas(targetElem, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: 794,
            windowWidth: 794
        });

        const JsPdfConstructor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : ((typeof jsPDF !== 'undefined') ? jsPDF : (window.jsPDF || null));
        if (!JsPdfConstructor) {
            console.error('jsPDF not loaded');
            return null;
        }

        const pdf = new JsPdfConstructor({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4',
            compress: true
        });

        const pageWidth = 210; // mm
        const pageHeight = 297; // mm
        const imgWidth = pageWidth;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const imgData = canvas.toDataURL('image/jpeg', 0.95);

        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
        heightLeft -= pageHeight;

        while (heightLeft > 5) {
            position = heightLeft - imgHeight;
            pdf.addPage('a4', 'portrait');
            pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
            heightLeft -= pageHeight;
        }

        return pdf.output('blob');
    } catch (e) {
        console.error('Error generating PO PDF blob:', e);
        return null;
    }
}

// Helper function to render official Purchase Order to Base64 PDF
async function generatePoPdfBase64(poId) {
    const blob = await generatePoPdfBlob(poId);
    if (!blob) return null;
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            const dataUrl = reader.result;
            resolve(dataUrl.split(',')[1] || null);
        };
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(blob);
    });
}

// Send Single PO via Email (SMTP)
async function sendPoEmail(poId, poNumber, totalAmt, poDate, poStartDate = '', poEndDate = '', vendorEmail = '', vendorName = 'Vendor') {
    const defaultSubject = `Purchase Order: ${poNumber} - Sudha Creative`;
    const periodText = (poStartDate && poEndDate) ? `for the period from **${poStartDate} to ${poEndDate}** ` : '';
    const defaultTemplateText = `Dear Sir/Madam,

Please be informed that the Purchase Order **${poNumber}** has been issued ${periodText}at a rate of **₹${totalAmt}**.

Please find the official Purchase Order PDF and booking details attached for your reference and records.

Thank you.

Regards,

Sudha Creative`;

    const { value: formValues } = await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#ea4335;"><i class="fas fa-paper-plane"></i> Send Purchase Order via Email</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Recipient Email Address (Vendor) <span style="color:#ef4444;">*</span></label>
                    <input id="smtp_po_recipient_email" type="text" value="${vendorEmail}" placeholder="vendor@example.com" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Email Subject</label>
                    <input id="smtp_po_subject" type="text" value="${defaultSubject}" style="width: 100%; box-sizing: border-box; font-size: 0.9rem; padding: 0.55rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Message Body</label>
                    <textarea id="smtp_po_message" style="width: 100%; box-sizing: border-box; height: 130px; font-size: 0.86rem; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; resize: vertical; line-height: 1.45; font-family: inherit;">${defaultTemplateText}</textarea>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; color: #166534; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.3rem;"></i>
                    <span>Official <b>${poNumber}.pdf</b> document will be rendered and attached directly to the email.</span>
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Email (SMTP)',
        confirmButtonColor: '#0d9488',
        cancelButtonText: '<i class="fab fa-google"></i> Open in Gmail Web',
        cancelButtonColor: '#ea4335',
        showCloseButton: true,
        preConfirm: () => {
            const emailVal = document.getElementById('smtp_po_recipient_email').value.trim();
            const subjectVal = document.getElementById('smtp_po_subject').value.trim();
            const msgVal = document.getElementById('smtp_po_message').value.trim();
            if (!emailVal) {
                Swal.showValidationMessage('Please enter a valid recipient email address.');
                return false;
            }
            return { email: emailVal, subject: subjectVal, message: msgVal };
        }
    });

    if (formValues) {
        Swal.fire({
            title: 'Preparing & Sending PO...',
            html: `
                <div style="padding: 10px 0; text-align: center;">
                    <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #0d9488; margin-bottom: 12px;"></i><br>
                    Rendering official <b>${poNumber}.pdf</b> & dispatching via SMTP to <b>${formValues.email}</b>...
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const poPdfBase64 = await generatePoPdfBase64(poId);

            const formData = new FormData();
            formData.append('po_id', poId);
            formData.append('email', formValues.email);
            formData.append('subject', formValues.subject);
            formData.append('message', formValues.message);
            if (poPdfBase64) {
                const cleanPoNum = poNumber.replace(/[\/\\?%*:|"<>]/g, '_');
                formData.append('po_pdf_base64', poPdfBase64);
                formData.append('po_pdf_name', `Purchase_Order_${cleanPoNum}.pdf`);
            }

            const resp = await fetch('../../ajax/send_po_email.php', {
                method: 'POST',
                body: formData
            });
            const rawText = await resp.text();
            let res;
            try {
                res = JSON.parse(rawText);
            } catch(jsonErr) {
                const cleanError = rawText.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(cleanError || 'Invalid response from server.');
            }

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'PO Sent Successfully!',
                    html: `<b>${res.message || 'Purchase Order sent successfully!'}</b><br><br><span style="color:#059669;font-size:0.88rem;"><i class="fas fa-paperclip"></i> Official PDF Attached (${poNumber}.pdf)</span><br><span style="color:#64748b;font-size:0.82rem;">Sender: sudhacreativemalda@gmail.com</span>`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Sending Failed',
                    text: res.message || 'Could not send PO email via SMTP server.'
                });
            }
        } catch (err) {
            Swal.fire('SMTP Error', err.message || 'Network error occurred while communicating with SMTP server.', 'error');
        }
    } else if (Swal.getDismissReason() === Swal.DismissReason.cancel) {
        const emailInput = document.getElementById('smtp_po_recipient_email')?.value.trim() || vendorEmail;
        const subjectInput = document.getElementById('smtp_po_subject')?.value.trim() || defaultSubject;
        const msgInput = document.getElementById('smtp_po_message')?.value.trim() || defaultTemplateText;
        const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailInput)}&su=${encodeURIComponent(subjectInput)}&body=${encodeURIComponent(msgInput)}`;
        window.open(gmailUrl, '_blank');
    }
}

// Send Single PO via WhatsApp with Attached Official PDF
async function sendPoWhatsApp(poId, poNumber, totalAmt, poDate, poStartDate = '', poEndDate = '', vendorPhone = '', vendorName = 'Vendor') {
    const cleanPoNum = poNumber.replace(/[\/\\?%*:|"<>]/g, '_');
    const pdfFileName = `Purchase_Order_${cleanPoNum}.pdf`;

    await Swal.fire({
        title: '<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#25D366;"><i class="fab fa-whatsapp"></i> Share PO PDF via WhatsApp</div>',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-top: 0.75rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 0.35rem; font-size: 0.85rem;">Vendor WhatsApp Number</label>
                    <div style="position: relative;">
                        <i class="fab fa-whatsapp" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #25D366; font-size: 1.1rem;"></i>
                        <input id="wa_po_phone" type="text" value="${vendorPhone}" placeholder="e.g. 9876543210 (or leave blank for chat selection)" style="width: 100%; box-sizing: border-box; font-size: 0.95rem; font-weight: 600; padding: 0.6rem 0.75rem 0.6rem 2.4rem; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none;">
                    </div>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 1.8rem; flex-shrink: 0;"></i>
                    <div style="flex: 1; overflow: hidden;">
                        <div style="font-weight: 800; color: #0f172a; font-size: 0.9rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${pdfFileName}</div>
                        <div style="font-size: 0.75rem; color: #15803d; margin-top: 2px;">PO #${poNumber} ${totalAmt ? '• ₹' + totalAmt : ''} • Ready for WhatsApp</div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                    <button type="button" id="btnShareWaWeb" style="width: 100%; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.92rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(37,211,102,0.35); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                        <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Open & Share in WhatsApp Web
                    </button>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" id="btnShareWaApp" style="flex: 1; background: #0284c7; color: white; border: none; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fas fa-share-alt"></i> WhatsApp App / Mobile
                        </button>
                        <button type="button" id="btnDownloadPoPdf" style="flex: 1; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 0.65rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <i class="fas fa-download"></i> Download PDF Only
                        </button>
                    </div>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times"></i> Close',
        cancelButtonColor: '#94a3b8',
        showCloseButton: true,
        didOpen: () => {
            document.getElementById('btnShareWaWeb')?.addEventListener('click', () => {
                executePoShare('web');
            });
            document.getElementById('btnShareWaApp')?.addEventListener('click', () => {
                executePoShare('app');
            });
            document.getElementById('btnDownloadPoPdf')?.addEventListener('click', () => {
                executePoShare('download');
            });
        }
    });

    async function executePoShare(mode) {
        const phoneInput = document.getElementById('wa_po_phone')?.value.trim().replace(/[^0-9]/g, '') || '';
        let phoneFormatted = phoneInput;
        if (phoneFormatted && phoneFormatted.length === 10) phoneFormatted = '91' + phoneFormatted;

        if (mode === 'download') {
            Swal.fire({
                title: 'Generating PDF...',
                text: 'Please wait...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });
            const pdfBlob = await generatePoPdfBlob(poId);
            if (pdfBlob) {
                const blobUrl = URL.createObjectURL(pdfBlob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = pdfFileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);
                Swal.fire({
                    icon: 'success',
                    title: 'PO PDF Downloaded!',
                    text: `${pdfFileName} has been saved to your downloads.`,
                    confirmButtonColor: '#0d9488'
                });
            } else {
                Swal.fire('Error', 'Failed to generate PDF.', 'error');
            }
            return;
        }

        Swal.fire({
            title: 'Preparing PO PDF...',
            html: `
                <div style="padding: 10px 0; text-align: center;">
                    <i class="fas fa-file-pdf fa-spin" style="font-size: 2.2rem; color: #25D366; margin-bottom: 12px;"></i><br>
                    Rendering official <b>${pdfFileName}</b> for WhatsApp...
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const pdfBlob = await generatePoPdfBlob(poId);
            if (!pdfBlob) throw new Error('Could not generate PO PDF document.');

            const pdfFile = new File([pdfBlob], pdfFileName, { type: 'application/pdf' });

            if (mode === 'app') {
                if (navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
                    Swal.close();
                    try {
                        await navigator.share({
                            files: [pdfFile]
                        });
                    } catch(shareErr) {
                        if (shareErr.name !== 'AbortError') {
                            triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                        }
                    }
                } else {
                    triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'app');
                }
            } else {
                // WhatsApp Web mode
                triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, 'web');
            }
        } catch(err) {
            Swal.fire('Error', err.message || 'Failed to prepare PO PDF for WhatsApp.', 'error');
        }
    }
}

function triggerDesktopWhatsAppShare(pdfBlob, pdfFileName, phoneFormatted, target = 'web') {
    // 1. Download PDF to user's device
    const blobUrl = URL.createObjectURL(pdfBlob);
    const a = document.createElement('a');
    a.href = blobUrl;
    a.download = pdfFileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(blobUrl);

    // 2. Open WhatsApp Web or Desktop App (no prefilled message)
    let waUrl = '';
    if (target === 'app') {
        waUrl = phoneFormatted ? `whatsapp://send?phone=${phoneFormatted}` : `whatsapp://send`;
    } else {
        waUrl = phoneFormatted 
            ? `https://web.whatsapp.com/send?phone=${phoneFormatted}`
            : `https://web.whatsapp.com/`;
    }
    window.open(waUrl, '_blank');

    Swal.fire({
        icon: 'success',
        title: 'PDF Downloaded & WhatsApp Web Opened!',
        html: `
            <div style="text-align: left; font-size: 0.9rem; line-height: 1.5;">
                <p style="margin: 0 0 8px 0;">📄 <b>${pdfFileName}</b> has been downloaded to your computer.</p>
                <p style="margin: 0 0 8px 0;">💬 <b>${target === 'app' ? 'WhatsApp App' : 'WhatsApp Web'}</b> has been opened in a new window/tab.</p>
                <div style="color: #065f46; font-weight: 600; background: #ecfdf5; padding: 10px 14px; border-radius: 8px; border: 1px solid #a7f3d0; margin-top: 10px;">
                    👉 Simply drag & drop the downloaded PDF into WhatsApp chat or click 📎 to attach and send!
                </div>
            </div>
        `,
        confirmButtonText: 'Got It!',
        confirmButtonColor: '#25D366'
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
