<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

checkAuth();

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $activeEntity = getActiveEntity();
        
        // Fetch current settings for fallback
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Handle File Uploads (Logo, Signature, Letterhead)
        $uploadDir = __DIR__ . '/../../assets/images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $logoVal = $activeEntity ? $activeEntity['logo'] : ($settings['company_logo'] ?? '');
        if (!empty($_FILES['company_logo']['name'])) {
            $logoVal = ($activeEntity ? 'entity_' : 'logo_') . time() . '_' . $_FILES['company_logo']['name'];
            move_uploaded_file($_FILES['company_logo']['tmp_name'], $uploadDir . $logoVal);
        }

        $sigVal = $activeEntity ? $activeEntity['signature'] : ($settings['company_signature'] ?? '');
        if (!empty($_FILES['company_signature']['name'])) {
            $sigVal = ($activeEntity ? 'entity_sig_' : 'sig_') . time() . '_' . $_FILES['company_signature']['name'];
            move_uploaded_file($_FILES['company_signature']['tmp_name'], $uploadDir . $sigVal);
        }

        $lhVal = $activeEntity ? $activeEntity['letterhead'] : ($settings['company_letterhead'] ?? '');
        if (!empty($_FILES['company_letterhead']['name'])) {
            $lhVal = ($activeEntity ? 'entity_lh_' : 'lh_') . time() . '_' . $_FILES['company_letterhead']['name'];
            move_uploaded_file($_FILES['company_letterhead']['tmp_name'], $uploadDir . $lhVal);
        }

        if ($activeEntity) {
            // Update Entity details
            $stmt = $pdo->prepare("UPDATE entities SET name = ?, gstin = ?, pan = ?, address = ?, bank_details = ?, terms_conditions = ?, invoice_terms = ?, msme_number = ?, logo = ?, letterhead = ?, signature = ?, cin = ?, tan = ? WHERE id = ?");
            $stmt->execute([
                $_POST['company_name'] ?? '',
                $_POST['company_gstin'] ?? '',
                $_POST['company_pan'] ?? '',
                $_POST['company_address'] ?? '',
                $_POST['company_bank_details'] ?? '',
                $_POST['po_terms'] ?? '',
                $_POST['invoice_terms'] ?? '',
                $_POST['company_msme_number'] ?? '',
                $logoVal,
                $lhVal,
                $sigVal,
                $_POST['company_cin'] ?? '',
                $_POST['company_tan'] ?? '',
                $activeEntity['id']
            ]);

            // Update other global settings in settings table
            $stmtGlobal = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $globalKeys = ['company_city', 'company_phone', 'company_email', 'po_important_note'];
            foreach ($globalKeys as $key) {
                if (isset($_POST[$key])) {
                    $stmtGlobal->execute([$_POST[$key], $key]);
                }
            }
        } else {
            // Update everything in settings table
            $stmtGlobal = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            foreach ($_POST as $key => $value) {
                if ($key !== 'submit') {
                    $stmtGlobal->execute([$value, $key]);
                }
            }
            // Execute file updates in settings
            $stmtGlobal->execute([$logoVal, 'company_logo']);
            $stmtGlobal->execute([$lhVal, 'company_letterhead']);
            $stmtGlobal->execute([$sigVal, 'company_signature']);
        }

        $pdo->commit();
        $message = "Settings updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// If active entity is set, override relevant fields
$activeEntity = getActiveEntity();
if ($activeEntity) {
    $settings['company_name'] = $activeEntity['name'];
    $settings['company_gstin'] = $activeEntity['gstin'];
    $settings['company_pan'] = $activeEntity['pan'];
    $settings['company_address'] = $activeEntity['address'];
    $settings['company_msme_number'] = $activeEntity['msme_number'];
    $settings['company_bank_details'] = $activeEntity['bank_details'];
    $settings['po_terms'] = $activeEntity['terms_conditions'];
    $settings['invoice_terms'] = $activeEntity['invoice_terms'];
    $settings['company_logo'] = $activeEntity['logo'];
    $settings['company_letterhead'] = $activeEntity['letterhead'];
    $settings['company_signature'] = $activeEntity['signature'];
    $settings['company_cin'] = $activeEntity['cin'];
    $settings['company_tan'] = $activeEntity['tan'];
}

$activePage = 'settings';
$pageTitle = $activeEntity ? 'Admin Settings - ' . htmlspecialchars($activeEntity['name']) : 'Global Admin Settings';
include_once __DIR__ . '/../../includes/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">Admin Settings</h1>
            <p style="color: #64748b; margin-top: 0.25rem;">Manage company branding, contact details, and document defaults.</p>
        </div>
        <?php if ($message): ?>
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; border: 1px solid #bbf7d0;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            
            <!-- Company Information -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <div class="card" style="padding: 2rem; border-radius: 20px;">
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                        <i class="fas fa-building" style="color: var(--primary);"></i> Company Profile
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label>Company Display Name</label>
                            <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>GSTIN Number</label>
                            <input type="text" name="company_gstin" value="<?php echo htmlspecialchars($settings['company_gstin'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>PAN Number</label>
                            <input type="text" name="company_pan" value="<?php echo htmlspecialchars($settings['company_pan'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Registered Address</label>
                            <textarea name="company_address" rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>City & State</label>
                            <input type="text" name="company_city" value="<?php echo htmlspecialchars($settings['company_city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Contact Phone</label>
                            <input type="text" name="company_phone" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Official Email</label>
                            <input type="email" name="company_email" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>MSME Number</label>
                            <input type="text" name="company_msme_number" value="<?php echo htmlspecialchars($settings['company_msme_number'] ?? ''); ?>" placeholder="e.g. UDYAM-WB-00-0000000">
                        </div>
                        <div class="form-group">
                            <label>CIN Number</label>
                            <input type="text" name="company_cin" value="<?php echo htmlspecialchars($settings['company_cin'] ?? ''); ?>" placeholder="e.g. U12345WB2026PTC123456">
                        </div>
                        <div class="form-group">
                            <label>TAN Number</label>
                            <input type="text" name="company_tan" value="<?php echo htmlspecialchars($settings['company_tan'] ?? ''); ?>" placeholder="e.g. CALS01234A">
                        </div>
                    </div>
                </div>

                <div class="card" style="padding: 2rem; border-radius: 20px;">
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                        <i class="fas fa-file-contract" style="color: var(--primary);"></i> Document Preferences
                    </h3>
                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;">These details will appear at the bottom of POs and Invoices.</p>
                    
                    <div class="form-group">
                        <label>Bank Account Details (for Invoices)</label>
                        <textarea name="company_bank_details" rows="3" placeholder="Account Name, Bank Name, A/c No, IFSC..."><?php echo htmlspecialchars($settings['company_bank_details'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label style="color: #ef4444;">PO Important Note (Red Box)</label>
                        <textarea name="po_important_note" rows="2"><?php echo htmlspecialchars($settings['po_important_note'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Purchase Order Terms & Conditions</label>
                        <textarea name="po_terms" rows="8"><?php echo htmlspecialchars($settings['po_terms'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Tax Invoice Terms & Conditions</label>
                        <textarea name="invoice_terms" rows="8"><?php echo htmlspecialchars($settings['invoice_terms'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Branding & Assets -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <div class="card" style="padding: 2rem; border-radius: 20px; text-align: center;">
                    <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem; text-align: left;">
                        <i class="fas fa-palette" style="color: var(--primary);"></i> Logo Branding
                    </h3>
                    
                    <div style="margin-bottom: 1.5rem; padding: 1.5rem; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px;">
                        <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $settings['company_logo'] ?: 'placeholder.png'; ?>" 
                             style="max-width: 100%; max-height: 80px; object-fit: contain; margin-bottom: 1rem;" 
                             onerror="this.src='https://via.placeholder.com/200x80?text=NO+LOGO'">
                        <input type="file" name="company_logo" id="logo_input" style="display: none;" accept="image/*">
                        <button type="button" onclick="document.getElementById('logo_input').click()" class="btn" style="width: 100%; background: #f1f5f9; color: #475569; font-weight: 700; border: 1px solid #e2e8f0;">
                            Change Logo
                        </button>
                    </div>

                    <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem; text-align: left; border-top: 1px solid #f1f5f9; padding-top: 1.5rem;">
                        <i class="fas fa-file-image" style="color: var(--primary);"></i> Full Letterhead
                    </h3>
                    
                    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px;">
                        <?php 
                        $lh = $settings['company_letterhead'] ?? '';
                        if ($lh): ?>
                            <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $lh; ?>" 
                                 style="max-width: 100%; max-height: 120px; object-fit: contain; margin-bottom: 1rem; border-radius: 4px;">
                        <?php else: ?>
                            <div style="height: 60px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.8rem; margin-bottom: 1rem;">
                                No letterhead uploaded
                            </div>
                        <?php endif; ?>
                        <input type="file" name="company_letterhead" id="lh_input" style="display: none;" accept="image/*">
                        <button type="button" onclick="document.getElementById('lh_input').click()" class="btn" style="width: 100%; background: #f1f5f9; color: #475569; font-weight: 700; border: 1px solid #e2e8f0;">
                            Change Letterhead
                        </button>
                    </div>

                    <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem; text-align: left; border-top: 1px solid #f1f5f9; padding-top: 1.5rem;">
                        <i class="fas fa-signature" style="color: var(--primary);"></i> Digital Signature
                    </h3>
                    
                    <div style="margin-bottom: 1.5rem; padding: 1.5rem; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px;">
                        <?php if (!empty($settings['company_signature'])): ?>
                            <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $settings['company_signature']; ?>" 
                                 style="max-width: 100%; max-height: 60px; object-fit: contain; margin-bottom: 1rem;">
                        <?php else: ?>
                            <div style="height: 60px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.8rem; margin-bottom: 1rem;">
                                No signature uploaded
                            </div>
                        <?php endif; ?>
                        <input type="file" name="company_signature" id="sig_input" style="display: none;" accept="image/*">
                        <button type="button" onclick="document.getElementById('sig_input').click()" class="btn" style="width: 100%; background: #f1f5f9; color: #475569; font-weight: 700; border: 1px solid #e2e8f0;">
                            Upload Signature
                        </button>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(13, 148, 136, 0.2);">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
            </div>

        </div>
    </form>
</div>

<style>
.card { background: white; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.5rem; }
.form-group input, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; font-family: inherit; transition: all 0.2s; }
.form-group input:focus, .form-group textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
