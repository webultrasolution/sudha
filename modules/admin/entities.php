<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

checkAuth();

$message = '';
$error = '';

// Handle Entity Deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    include_once __DIR__ . '/../../includes/trash_helper.php';
    if (move_row_to_trash($pdo, 'entities', 'id', $id, $_SESSION['user_id'] ?? null, 'Entity deleted via admin UI')) {
        $message = "Entity moved to trash successfully!";
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = $_POST['name'];
    $gstin = $_POST['gstin'];
    $pan = $_POST['pan'];
    $address = $_POST['address'];
    $bank_details      = $_POST['bank_details'];
    $terms_conditions  = $_POST['terms_conditions'] ?? '';

    $uploadDir = __DIR__ . '/../../assets/images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $logo = $_POST['existing_logo'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        $logoName = 'entity_' . time() . '_' . $_FILES['logo']['name'];
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $logoName)) {
            $logo = $logoName;
        }
    }
    
    $letterhead = $_POST['existing_letterhead'] ?? '';
    if (!empty($_FILES['letterhead']['name'])) {
        $letterheadName = 'entity_lh_' . time() . '_' . $_FILES['letterhead']['name'];
        if (move_uploaded_file($_FILES['letterhead']['tmp_name'], $uploadDir . $letterheadName)) {
            $letterhead = $letterheadName;
        }
    }

    $signature = $_POST['existing_signature'] ?? '';
    if (!empty($_FILES['signature']['name'])) {
        $signatureName = 'entity_sig_' . time() . '_' . $_FILES['signature']['name'];
        if (move_uploaded_file($_FILES['signature']['tmp_name'], $uploadDir . $signatureName)) {
            $signature = $signatureName;
        }
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE entities SET name=?, gstin=?, pan=?, address=?, bank_details=?, terms_conditions=?, logo=?, letterhead=?, signature=? WHERE id=?");
            $stmt->execute([$name, $gstin, $pan, $address, $bank_details, $terms_conditions, $logo, $letterhead, $signature, $id]);
            $message = "Entity updated successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO entities (name, gstin, pan, address, bank_details, terms_conditions, logo, letterhead, signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $gstin, $pan, $address, $bank_details, $terms_conditions, $logo, $letterhead, $signature]);
            $message = "New Entity added successfully!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$entities = $pdo->query("SELECT * FROM entities ORDER BY name ASC")->fetchAll();

$activePage = 'entities';
$pageTitle = 'Multi Content Management';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid" style="padding: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">Multi Content Management</h1>
            <p style="color: #64748b; margin-top: 0.25rem;">Manage multiple business entities, brands, or regional content centers.</p>
        </div>
        <button onclick="openModal()" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);">
            <i class="fas fa-plus"></i> Add New Content
        </button>
    </div>

    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 12px; font-weight: 700; margin-bottom: 1.5rem; border-left: 5px solid #10b981;">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
        <?php foreach ($entities as $entity): ?>
            <div class="entity-card" style="background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; transition: transform 0.2s;">
                <div style="padding: 1.5rem; display: flex; align-items: flex-start; gap: 1rem;">
                    <div style="width: 80px; height: 80px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0; flex-shrink: 0;">
                        <?php if ($entity['logo']): ?>
                            <img src="<?php echo BASE_URL; ?>assets/images/<?php echo $entity['logo']; ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <i class="fas fa-building" style="font-size: 1.5rem; color: #94a3b8;"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex-grow: 1;">
                        <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0;"><?php echo htmlspecialchars($entity['name']); ?></h3>
                        <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <span style="font-size: 0.65rem; background: #eff6ff; color: #3b82f6; padding: 2px 8px; border-radius: 50px; font-weight: 700;">GST: <?php echo htmlspecialchars($entity['gstin'] ?: 'N/A'); ?></span>
                            <span style="font-size: 0.65rem; background: #fef2f2; color: #ef4444; padding: 2px 8px; border-radius: 50px; font-weight: 700;">PAN: <?php echo htmlspecialchars($entity['pan'] ?: 'N/A'); ?></span>
                        </div>
                        <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.75rem; line-height: 1.4;">
                            <i class="fas fa-map-marker-alt" style="width: 15px;"></i> <?php echo htmlspecialchars($entity['address'] ?: 'No address specified'); ?>
                        </p>
                    </div>
                </div>
                <div style="background: #f8fafc; padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;">Added: <?php echo date('M d, Y', strtotime($entity['created_at'])); ?></span>
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick='editEntity(<?php echo json_encode($entity); ?>)' class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <a href="?delete=<?php echo $entity['id']; ?>" onclick="return confirm('Are you sure you want to delete this content?')" class="btn-icon delete" title="Delete"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal -->
<div id="entityModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; width: 100%; max-width: 600px; border-radius: 24px; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 id="modalTitle" style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Add New Content</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;"><i class="fas fa-times"></i></button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" style="max-height:80vh; overflow-y:auto; padding-right:4px;">
            <input type="hidden" name="id" id="entityId">
            <input type="hidden" name="existing_logo" id="existingLogo">
            <input type="hidden" name="existing_letterhead" id="existingLetterhead">
            <input type="hidden" name="existing_signature" id="existingSignature">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label>Content / Company Name</label>
                    <input type="text" name="name" id="entityName" required placeholder="e.g. Sudha Creative Mumbai">
                </div>
                <div class="form-group">
                    <label>GSTIN</label>
                    <input type="text" name="gstin" id="entityGstin" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label>PAN</label>
                    <input type="text" name="pan" id="entityPan" placeholder="Optional">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Address</label>
                    <textarea name="address" id="entityAddress" rows="2" placeholder="Full address..."></textarea>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Bank Details</label>
                    <textarea name="bank_details" id="entityBank" rows="3" placeholder="Bank Name&#10;A/C No: XXXX&#10;IFSC: XXXX"></textarea>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Terms &amp; Conditions <span style="color:#94a3b8;font-weight:400;">(shown on invoices)</span></label>
                    <textarea name="terms_conditions" id="entityTerms" rows="4" placeholder="1. Payment due within 30 days.&#10;2. Subject to local jurisdiction only."></textarea>
                </div>

                <!-- Logo -->
                <div class="form-group">
                    <label>Brand Logo <span style="color:#94a3b8;font-weight:400;">(used in invoices)</span></label>
                    <div id="logoPreviewBox" class="img-preview-box" style="display:none;">
                        <img id="logoPreview" src="" alt="Logo">
                        <span class="img-preview-label">Current Logo</span>
                    </div>
                    <input type="file" name="logo" accept="image/*" onchange="previewImg(this,'logoPreview','logoPreviewBox')">
                </div>

                <!-- Signature -->
                <div class="form-group">
                    <label>Digital Signature <span style="color:#94a3b8;font-weight:400;">(invoice footer)</span></label>
                    <div id="sigPreviewBox" class="img-preview-box" style="display:none;">
                        <img id="sigPreview" src="" alt="Signature">
                        <span class="img-preview-label">Current Signature</span>
                    </div>
                    <input type="file" name="signature" accept="image/*" onchange="previewImg(this,'sigPreview','sigPreviewBox')">
                </div>

            </div>

            <div style="display: flex; gap: 1rem; margin-top: 0.5rem; position:sticky; bottom:0; background:white; padding-top:0.75rem;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1; padding: 0.75rem;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 2; padding: 0.75rem;">Save Entity</button>
            </div>
        </form>
    </div>
</div>

<style>
.entity-card:hover { transform: translateY(-5px); border-color: #3b82f6; }
.btn-icon { width: 35px; height: 35px; border-radius: 8px; border: none; background: white; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s; }
.btn-icon:hover { background: #eff6ff; color: #3b82f6; }
.btn-icon.delete:hover { background: #fef2f2; color: #ef4444; }
.form-group label { display: block; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.025em; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; font-family: inherit; transition: all 0.2s; box-sizing: border-box; }
.form-group input:focus, .form-group textarea:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
.form-group input[type="file"] { padding: 0.5rem; background: #f8fafc; cursor: pointer; }
.img-preview-box { position: relative; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 10px; padding: 8px; text-align: center; margin-bottom: 8px; }
.img-preview-box img { max-height: 60px; max-width: 100%; object-fit: contain; display: block; margin: 0 auto; }
.img-preview-label { font-size: 0.65rem; color: #94a3b8; font-weight: 600; display: block; margin-top: 4px; }
</style>

<script>
const BASE = '<?php echo BASE_URL; ?>assets/images/';

function showPreview(imgId, boxId, filename) {
    const box = document.getElementById(boxId);
    const img = document.getElementById(imgId);
    if (filename) {
        img.src = BASE + filename;
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

function previewImg(input, imgId, boxId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById(imgId).src = e.target.result;
            document.getElementById(boxId).style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function resetModal() {
    document.getElementById('entityId').value = '';
    document.getElementById('entityName').value = '';
    document.getElementById('entityGstin').value = '';
    document.getElementById('entityPan').value = '';
    document.getElementById('entityAddress').value = '';
    document.getElementById('entityBank').value  = '';
    document.getElementById('entityTerms').value = '';
    document.getElementById('existingLogo').value = '';
    document.getElementById('existingLetterhead').value = '';
    document.getElementById('existingSignature').value = '';
    showPreview('logoPreview', 'logoPreviewBox', null);
    showPreview('sigPreview',  'sigPreviewBox',  null);
}

function openModal() {
    resetModal();
    document.getElementById('modalTitle').innerText = 'Add New Entity';
    document.getElementById('entityModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('entityModal').style.display = 'none';
}

function editEntity(entity) {
    resetModal();
    document.getElementById('modalTitle').innerText = 'Edit Entity';
    document.getElementById('entityId').value       = entity.id;
    document.getElementById('entityName').value     = entity.name;
    document.getElementById('entityGstin').value    = entity.gstin || '';
    document.getElementById('entityPan').value      = entity.pan || '';
    document.getElementById('entityAddress').value  = entity.address || '';
    document.getElementById('entityBank').value     = entity.bank_details || '';
    document.getElementById('entityTerms').value    = entity.terms_conditions || '';
    document.getElementById('existingLogo').value        = entity.logo || '';
    document.getElementById('existingLetterhead').value  = entity.letterhead || '';
    document.getElementById('existingSignature').value   = entity.signature || '';
    showPreview('logoPreview', 'logoPreviewBox', entity.logo);
    showPreview('sigPreview',  'sigPreviewBox',  entity.signature);
    document.getElementById('entityModal').style.display = 'flex';
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
