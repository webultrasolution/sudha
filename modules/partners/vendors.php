<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = clean($_POST['name']);
    $contact = clean($_POST['contact_person']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    $city = clean($_POST['city']);
    $state = clean($_POST['state']);
    $district = clean($_POST['district'] ?? '');
    $pincode = clean($_POST['pincode'] ?? '');
    $gstin = clean($_POST['gstin']);
    $pan = clean($_POST['pan']);
    $billing_address = clean($_POST['billing_address']);
    $payment_terms = clean($_POST['payment_terms']);
    $business_type = clean($_POST['business_type'] ?? '');
    $status = clean($_POST['status'] ?? 'active');
    $additional_gst = clean($_POST['additional_gst'] ?? '');

    // Handle Multi-GST JSON if present (overrides simple additional_gst)
    if (isset($_POST['gst_rows'])) {
        $additional_gst = json_encode($_POST['gst_rows']);
    }

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO partners (name, business_type, contact_person, phone, email, address, city, state, district, pincode, gstin, additional_gst, pan, billing_address, payment_terms, status, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'vendor')");
        $stmt->execute([$name, $business_type, $contact, $phone, $email, $address, $city, $state, $district, $pincode, $gstin, $additional_gst, $pan, $billing_address, $payment_terms, $status]);
        header("Location: vendors.php?msg=added"); exit;
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("UPDATE partners SET name=?, business_type=?, contact_person=?, phone=?, email=?, address=?, city=?, state=?, district=?, pincode=?, gstin=?, additional_gst=?, pan=?, billing_address=?, payment_terms=?, status=? WHERE id=?");
        $stmt->execute([$name, $business_type, $contact, $phone, $email, $address, $city, $state, $district, $pincode, $gstin, $additional_gst, $pan, $billing_address, $payment_terms, $status, $id]);
        header("Location: vendors.php?msg=updated"); exit;
    } elseif ($_POST['action'] === 'delete') {
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'vendors';
$pageTitle = 'Vendor Management';
include_once __DIR__ . '/../../includes/header.php';

// Search Logic
$search = $_GET['search'] ?? '';
$query = "
    SELECT v.*, (SELECT COUNT(*) FROM sites WHERE vendor_id = v.id) as site_count 
    FROM partners v 
    WHERE type = 'vendor' 
";
$params = [];
if ($search) {
    $query .= " AND (name LIKE ? OR contact_person LIKE ? OR city LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$query .= " ORDER BY name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

$indian_states = ["Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal", "Delhi"];
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Manage Vendors</h2>
        <div style="display: flex; gap: 1rem;">
            <form method="GET" style="display: flex; gap: 0.5rem;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search vendor..." style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <button type="submit" class="btn" style="padding: 0.5rem 1rem;"><i class="fas fa-search"></i></button>
            </form>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Create Vendor
            </button>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Vendor Name</th>
                <th>Contact</th>
                <th>GSTIN / PAN</th>
                <th>Owned Sites</th>
                <th>Location</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vendors as $v): ?>
            <tr>
                <td>
                    <a href="vendor_view.php?id=<?php echo $v['id']; ?>" style="text-decoration: none; color: inherit;">
                        <strong><?php echo $v['name']; ?></strong>
                        <div style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-top: 2px;"><?php echo $v['business_type'] ?: 'N/A'; ?></div>
                        <i class="fas fa-external-link-alt" style="font-size: 0.7rem; color: var(--primary); margin-left: 0.3rem;"></i>
                    </a>
                </td>
                <td>
                    <div><?php echo $v['contact_person']; ?></div>
                    <div style="font-size: 0.75rem; color: var(--secondary);"><?php echo $v['phone']; ?></div>
                </td>
                <td>
                    <div style="font-family: monospace; font-size: 0.8rem;">G: <?php echo $v['gstin']; ?></div>
                    <div style="font-family: monospace; font-size: 0.8rem;">P: <?php echo $v['pan']; ?></div>
                </td>
                <td><span class="badge" style="background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;"><?php echo $v['site_count']; ?> Sites</span></td>
                <td><?php echo $v['city']; ?>, <?php echo $v['state']; ?></td>
                <td>
                    <a href="../operations/direct_po.php?vendor_id=<?php echo $v['id']; ?>" class="btn-icon btn-view" title="Direct PO (No Proposal)"><i class="fas fa-file-signature"></i></a>
                    <button class="btn-icon btn-edit" onclick="editVendor(<?php echo htmlspecialchars(json_encode($v)); ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn-icon btn-delete" onclick="deleteVendor(<?php echo $v['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="vendorModal" class="modal">
    <div class="modal-content" style="max-width: 850px;">
        <div class="modal-header">
            <h2 id="modalTitle">Vendor Profile</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="vendorForm" class="was-validated">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="vendorId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <div class="form-group"><label>Vendor/Company Name</label><input type="text" name="name" id="f_name" required></div>
                    <div class="form-group">
                        <label>Business Type</label>
                        <select name="business_type" id="f_business_type" onchange="showTypeHelp(this.value)">
                            <option value="">Select Type</option>
                            <option value="Proprietorship">Proprietorship (Single Owner)</option>
                            <option value="Partnership Firm">Partnership Firm (Multiple Owners)</option>
                            <option value="Private Limited">Private Limited (Pvt. Ltd.)</option>
                            <option value="Public Limited">Public Limited (Public Investment)</option>
                            <option value="LLP">LLP (Limited Liability Partnership)</option>
                            <option value="Group of Companies">Group of Companies (Multiple GSTs)</option>
                            <option value="Individual">Individual (Freelancer/Personal)</option>
                            <option value="Trust/NGO">Trust/NGO (Non-Profit)</option>
                            <option value="Government Body">Government Body (Dept/Authority)</option>
                        </select>
                        <div id="type-help" style="font-size: 0.7rem; color: #64748b; margin-top: 0.3rem; font-style: italic;"></div>
                    </div>
                    <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" id="f_contact"></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" id="f_phone"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="f_email"></div>
                    <div class="form-group"><label>Address</label><textarea name="address" id="f_address" rows="3" required></textarea></div>
                </div>
                <div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group"><label>City</label><input type="text" name="city" id="f_city" required></div>
                        <div class="form-group"><label>District</label><input type="text" name="district" id="f_district"></div>
                    </div>
                    <div class="form-group"><label>Pincode</label><input type="text" name="pincode" id="f_pincode"></div>
                    <div class="form-group">
                        <label>State</label>
                        <select name="state" id="f_state" required>
                            <option value="">Select State</option>
                            <?php foreach ($indian_states as $s): ?>
                                <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label id="f_gstin_label">Primary GSTIN</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="gstin" id="f_gstin" placeholder="Enter GSTIN" style="flex: 1;">
                            <button type="button" class="btn btn-secondary" onclick="fetchGSTDetails()" style="padding: 0 1rem; background: #64748b; color: white;">
                                <i class="fas fa-sync-alt" id="gst_loader"></i> Fetch
                            </button>
                        </div>
                    </div>
                    <div id="additional_gst_container" style="display: none; margin-top: 1rem; border-top: 1px solid #f1f5f9; padding-top: 1rem;" class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <label style="font-weight: 700; color: var(--primary); margin-bottom: 0;">Branch GSTs & Locations</label>
                            <button type="button" class="btn btn-sm" onclick="addGstRow()" style="padding: 4px 12px; font-size: 0.75rem; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <i class="fas fa-plus"></i> Add Branch
                            </button>
                        </div>
                        <div id="gst_rows_list" style="display: flex; flex-direction: column; gap: 1rem; max-height: 300px; overflow-y: auto; overflow-x: hidden; padding: 4px;">
                            <!-- Dynamic rows will be injected here -->
                        </div>
                        <textarea name="additional_gst" id="f_additional_gst" style="display: none;"></textarea>
                    </div>
                    <div class="form-group"><label id="f_pan_label">PAN</label><input type="text" name="pan" id="f_pan"></div>
                    <div class="form-group"><label>Payment Terms</label><input type="text" name="payment_terms" id="f_terms" placeholder="e.g. 30 Days Net"></div>
                    <div class="form-group"><label>Billing/Payment Address</label><textarea name="billing_address" id="f_billing" rows="2"></textarea></div>
                </div>
            </div>
            
            <div style="margin-top: 2rem; text-align: right;">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Vendor Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Import Modal -->
<div id="importModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Bulk Import Vendors</h2>
            <span class="close" onclick="closeImportModal()">&times;</span>
        </div>
        <form id="importForm" action="../../ajax/import_partners.php" method="POST" enctype="multipart/form-data" style="padding: 1rem 0;">
            <input type="hidden" name="type" value="vendor">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Select CSV File</label>
                <input type="file" name="file" accept=".csv" required style="padding: 1rem; border: 2px dashed #e2e8f0; background: #f8fafc; text-align: center;">
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Use the same <a href="../../templates/client_template.csv" download style="color: var(--primary); font-weight: 600;">Standard Template</a> for vendors.
                </div>
            </div>
            <div style="text-align: right;">
                <button type="button" class="btn" onclick="closeImportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Start Upload</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
.modal-content { background: white; margin: 2% auto; padding: 2rem; border-radius: 12px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; color: #475569; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.65rem; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 0.9rem; }
.close { cursor: pointer; float: right; font-size: 1.5rem; }
</style>

<script>
function openModal() { 
    const form = document.getElementById('vendorForm');
    form.reset(); 
    form.classList.remove('was-validated');
    document.getElementById('formAction').value = 'add'; 
    renderGstRows(''); // Clear dynamic rows
    document.getElementById('vendorModal').style.display = 'block'; 
}
function closeModal() { document.getElementById('vendorModal').style.display = 'none'; }

function openImportModal() { document.getElementById('importModal').style.display = 'block'; }
function closeImportModal() { document.getElementById('importModal').style.display = 'none'; }
function editVendor(v) {
    const form = document.getElementById('vendorForm');
    form.classList.remove('was-validated');
    document.getElementById('formAction').value = 'edit';
    document.getElementById('vendorId').value = v.id;
    document.getElementById('f_name').value = v.name;
    document.getElementById('f_contact').value = v.contact_person;
    document.getElementById('f_phone').value = v.phone;
    document.getElementById('f_email').value = v.email;
    document.getElementById('f_address').value = v.address;
    document.getElementById('f_city').value = v.city;
    document.getElementById('f_state').value = v.state;
    document.getElementById('f_district').value = v.district || '';
    document.getElementById('f_pincode').value = v.pincode || '';
    document.getElementById('f_gstin').value = v.gstin;
    document.getElementById('f_pan').value = v.pan;
    document.getElementById('f_billing').value = v.billing_address;
    document.getElementById('f_terms').value = v.payment_terms;
    document.getElementById('f_business_type').value = v.business_type || '';
    
    // Populate Multi-GST Rows
    renderGstRows(v.additional_gst);
    
    showTypeHelp(v.business_type || '');
    document.getElementById('vendorModal').style.display = 'block';
}

function addGstRow(data = {gstin: '', city: '', district: '', state: '', pan: '', address: ''}) {
    const container = document.getElementById('gst_rows_list');
    const rowId = 'row_' + Date.now() + Math.random().toString(36).substr(2, 5);
    
    const rowHtml = `
        <div id="${rowId}" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 10px; background: white; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 2px;">
            <button type="button" onclick="removeGstRow('${rowId}')" style="position: absolute; top: -10px; right: -10px; width: 24px; height: 24px; border-radius: 50%; background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 2; transition: all 0.2s;">
                <i class="fas fa-times"></i>
            </button>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">GSTIN</label>
                <div style="display: flex; gap: 4px;">
                    <input type="text" name="gst_rows[${rowId}][gstin]" id="gstin_${rowId}" value="${data.gstin || ''}" placeholder="GSTIN" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc; flex: 1;">
                    <button type="button" onclick="fetchBranchGSTDetails('${rowId}')" style="padding: 0 8px; background: #64748b; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-sync-alt" id="loader_${rowId}"></i>
                    </button>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">State</label>
                <select name="gst_rows[${rowId}][state]" id="state_${rowId}" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc;">
                    <option value="">Select State</option>
                    <?php foreach ($indian_states as $s): ?>
                        <option value="<?php echo $s; ?>" ${data.state === '<?php echo $s; ?>' ? 'selected' : ''}><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">City</label>
                <input type="text" name="gst_rows[${rowId}][city]" id="city_${rowId}" value="${data.city || ''}" placeholder="City" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">District</label>
                <input type="text" name="gst_rows[${rowId}][district]" id="district_${rowId}" value="${data.district || ''}" placeholder="District" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc;">
            </div>
            <div class="form-group" style="margin-bottom: 0; grid-column: span 2;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">Full Address</label>
                <textarea name="gst_rows[${rowId}][address]" id="address_${rowId}" rows="2" placeholder="Branch Address" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc; resize: vertical;">${data.address || ''}</textarea>
            </div>
            <div class="form-group" style="margin-bottom: 0; grid-column: span 2;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">PAN Number</label>
                <input type="text" name="gst_rows[${rowId}][pan]" id="pan_${rowId}" value="${data.pan || ''}" placeholder="PAN (Extracted from GSTIN)" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc; font-family: monospace;">
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', rowHtml);
    container.scrollTop = container.scrollHeight;
}

function removeGstRow(id) {
    const row = document.getElementById(id);
    if (row) row.remove();
}

function renderGstRows(jsonStr) {
    const container = document.getElementById('gst_rows_list');
    container.innerHTML = '';
    
    if (!jsonStr) return;
    
    try {
        const rows = JSON.parse(jsonStr);
        if (Array.isArray(rows)) {
            rows.forEach(row => addGstRow(row));
        } else if (typeof rows === 'object') {
            Object.values(rows).forEach(row => addGstRow(row));
        }
    } catch (e) {
        // Legacy data
        if (jsonStr.trim()) {
            const gsts = jsonStr.split(',');
            gsts.forEach(g => {
                if (g.trim()) addGstRow({gstin: g.trim(), city: '', district: '', state: ''});
            });
        }
    }
}

function showTypeHelp(val) {
    const help = document.getElementById('type-help');
    const tips = {
        'Proprietorship': 'Single owner business, common for small shops.',
        'Partnership Firm': '2+ partners, common for small family businesses.',
        'Private Limited': 'Registered company (Pvt Ltd), most common for startups.',
        'Public Limited': 'Large company listed on stock market.',
        'LLP': 'Partnership with limited liability, common for professional firms.',
        'Group of Companies': 'Multiple sister concerns with state-wise GSTs.',
        'Individual': 'Personal account or freelancer.',
        'Trust/NGO': 'Non-profit organization for charity or education.',
        'Government Body': 'Govt department, municipal corp, or authority.'
    };

    // Dynamic Labeling logic
    if (val === 'Individual') {
        document.getElementById('f_gstin_label').innerText = 'GSTIN (Optional)';
        document.getElementById('f_pan_label').innerText = 'Personal PAN';
    } else if (val === 'Proprietorship') {
        document.getElementById('f_gstin_label').innerText = 'GSTIN (If Registered)';
        document.getElementById('f_pan_label').innerText = 'Owner PAN';
    } else {
        document.getElementById('f_gstin_label').innerText = 'GSTIN';
        document.getElementById('f_pan_label').innerText = 'Company PAN';
    }

    help.innerText = tips[val] || '';

    // Show/Hide Additional GST for Groups or Large Companies
    const addGst = document.getElementById('additional_gst_container');
    if (val === 'Group of Companies' || val === 'Public Limited' || val === 'Private Limited') {
        addGst.style.display = 'block';
    } else {
        addGst.style.display = 'none';
    }
}

function deleteVendor(id) {
    Swal.fire({
        title: 'Delete Vendor?',
        text: "Are you sure you want to remove this vendor? This cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('vendors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Vendor has been removed.', 'success').then(() => location.reload());
            });
        }
    });
}

async function fetchGSTDetails() {
    const gstin = document.getElementById('f_gstin').value.trim();
    if (gstin.length !== 15) {
        Swal.fire('Invalid GSTIN', 'Please enter a valid 15-digit GSTIN.', 'error');
        return;
    }

    const loader = document.getElementById('gst_loader');
    loader.classList.add('fa-spin');
    
    try {
        const response = await fetch(`../../ajax/fetch_gst.php?gstin=${gstin}`);
        const result = await response.json();
        
        if (result.success) {
            if (result.data.name) document.getElementById('f_name').value = result.data.name;
            if (result.data.pan) document.getElementById('f_pan').value = result.data.pan;
            if (result.data.address) document.getElementById('f_address').value = result.data.address;
            if (result.data.city) document.getElementById('f_city').value = result.data.city;
            if (result.data.state) document.getElementById('f_state').value = result.data.state;
            if (result.data.district) document.getElementById('f_district').value = result.data.district;
            if (result.data.pincode) document.getElementById('f_pincode').value = result.data.pincode;
            if (result.data.phone) document.getElementById('f_phone').value = result.data.phone;
            if (result.data.email) document.getElementById('f_email').value = result.data.email;
            
            if (!result.data.pan && gstin.length >= 12) {
                document.getElementById('f_pan').value = gstin.substring(2, 12);
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Data Fetched',
                text: 'Vendor details populated from GST record.',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            document.getElementById('f_pan').value = gstin.substring(2, 12);
            Swal.fire('Info', result.message || 'Could not fetch full details, but PAN has been extracted.', 'info');
        }
    } catch (error) {
        console.error('GST Fetch Error:', error);
        document.getElementById('f_pan').value = gstin.substring(2, 12);
        Swal.fire('Error', 'Failed to connect to GST API. PAN extracted from GSTIN.', 'warning');
    } finally {
        loader.classList.remove('fa-spin');
    }
}

async function fetchBranchGSTDetails(rowId) {
    const gstin = document.getElementById(`gstin_${rowId}`).value.trim();
    if (gstin.length !== 15) {
        Swal.fire('Invalid GSTIN', 'Please enter a valid 15-digit GSTIN for this branch.', 'error');
        return;
    }

    const loader = document.getElementById(`loader_${rowId}`);
    loader.classList.add('fa-spin');
    
    try {
        const response = await fetch(`../../ajax/fetch_gst.php?gstin=${gstin}`);
        const result = await response.json();
        
        // Auto-extract PAN from GSTIN regardless of API success
        if (gstin.length >= 12) {
            document.getElementById(`pan_${rowId}`).value = gstin.substring(2, 12);
        }

        if (result.success) {
            if (result.data.city) document.getElementById(`city_${rowId}`).value = result.data.city;
            if (result.data.state) document.getElementById(`state_${rowId}`).value = result.data.state;
            if (result.data.district) document.getElementById(`district_${rowId}`).value = result.data.district;
            if (result.data.address) document.getElementById(`address_${rowId}`).value = result.data.address;
            
            Swal.fire({
                icon: 'success',
                title: 'Branch Data Fetched',
                text: 'Location details, address, and PAN populated.',
                timer: 1000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Info', result.message || 'Could not fetch full branch details, but PAN has been extracted.', 'info');
        }
    } catch (error) {
        console.error('Branch GST Fetch Error:', error);
        Swal.fire('Error', 'Failed to connect to GST API. PAN extracted from GSTIN.', 'warning');
    } finally {
        loader.classList.remove('fa-spin');
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
