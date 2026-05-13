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
        
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'submit') {
                $stmt->execute([$value, $key]);
            }
        }

        // Handle File Uploads (Logo & Signature)
        $uploadDir = __DIR__ . '/../../assets/images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (!empty($_FILES['company_logo']['name'])) {
            $logoName = 'logo_' . time() . '_' . $_FILES['company_logo']['name'];
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $uploadDir . $logoName)) {
                $stmt->execute([$logoName, 'company_logo']);
            }
        }

        if (!empty($_FILES['company_signature']['name'])) {
            $sigName = 'sig_' . time() . '_' . $_FILES['company_signature']['name'];
            if (move_uploaded_file($_FILES['company_signature']['tmp_name'], $uploadDir . $sigName)) {
                $stmt->execute([$sigName, 'company_signature']);
            }
        }

        if (!empty($_FILES['company_letterhead']['name'])) {
            $lhName = 'lh_' . time() . '_' . $_FILES['company_letterhead']['name'];
            if (move_uploaded_file($_FILES['company_letterhead']['tmp_name'], $uploadDir . $lhName)) {
                $stmt->execute([$lhName, 'company_letterhead']);
            }
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

$activePage = 'settings';
$pageTitle = 'Global Admin Settings';
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
                        <textarea name="po_terms" rows="10"><?php echo htmlspecialchars($settings['po_terms'] ?? ''); ?></textarea>
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
                        $lh = getSetting('company_letterhead');
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
