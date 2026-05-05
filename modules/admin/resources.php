<?php
$activePage = 'resources';
$pageTitle = 'Resources & Tax Configuration';
include_once __DIR__ . '/../../includes/header.php';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real app, save these to a 'settings' table. 
    // For now, we simulate and show success.
    $msg = "Settings updated successfully.";
}
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2 style="font-size: 1.25rem;"><i class="fas fa-cogs"></i> System Resources & Templates</h2>
        <span class="badge-running" style="padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.7rem; background: #dcfce7; color: #166534; font-weight: 700;">PRO CONFIG ACTIVE</span>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem;">
        <!-- Left: Templates -->
        <div class="res-section">
            <h3 class="res-title">Document Templates</h3>
            <div class="res-item">
                <div class="res-info">
                    <i class="fas fa-file-pdf"></i>
                    <div>
                        <strong>Proposal PDF Template</strong>
                        <p>Standard OOH Proposal with 4-Panel stats.</p>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm">Edit HTML</button>
            </div>
            <div class="res-item">
                <div class="res-info">
                    <i class="fas fa-file-invoice"></i>
                    <div>
                        <strong>Tax Invoice Template</strong>
                        <p>GST compliant layout (CGST/SGST/IGST).</p>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm">Edit HTML</button>
            </div>
            <div class="res-item">
                <div class="res-info" style="color: #f59e0b;">
                    <i class="fas fa-file-powerpoint"></i>
                    <div>
                        <strong>PPT Deck Generator</strong>
                        <p>Site photo auto-export (16:9 format).</p>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm">Upload PPTX</button>
            </div>
        </div>

        <!-- Right: Tax & Global -->
        <div class="res-section">
            <h3 class="res-title">Tax & Legal Setup</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Standard GST Rate (%)</label>
                    <input type="number" name="gst_rate" class="p-input" value="18" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label>SAC Code (Rentals)</label>
                        <input type="text" name="sac_rent" class="p-input" value="997331">
                    </div>
                    <div class="form-group">
                        <label>SAC Code (Printing)</label>
                        <input type="text" name="sac_print" class="p-input" value="998394">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label>HSN Code (Hardware)</label>
                    <input type="text" name="hsn_code" class="p-input" value="7308">
                </div>
                
                <h3 class="res-title" style="margin-top: 2rem;">Company Branding</h3>
                <div class="form-group">
                    <label>Company Display Name</label>
                    <input type="text" class="p-input" value="Easy Outdoor Advertising Pvt Ltd">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem; width: 100%;">Save Global Configuration</button>
            </form>
        </div>
    </div>
</div>

<style>
.res-title { font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-bottom: 1.25rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; }
.res-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 1rem; }
.res-info { display: flex; align-items: center; gap: 1rem; }
.res-info i { font-size: 1.5rem; color: var(--primary); }
.res-info p { font-size: 0.75rem; color: var(--secondary); margin-top: 0.2rem; }
.res-info strong { font-size: 0.9rem; }
.btn-sm { padding: 0.4rem 0.75rem; font-size: 0.75rem; }
.p-input { width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 6px; margin-top: 0.25rem; }
.form-group label { font-size: 0.85rem; font-weight: 600; color: #475569; }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
