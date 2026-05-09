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
    $additional_gst = clean($_POST['additional_gst'] ?? '');
    $pan = clean($_POST['pan']);
    $billing_address = clean($_POST['billing_address']);
    $business_type = clean($_POST['business_type'] ?? '');
    $status = clean($_POST['status'] ?? 'active');

    // Handle Multi-GST JSON if present (overrides simple additional_gst)
    if (isset($_POST['gst_rows'])) {
        $additional_gst = json_encode($_POST['gst_rows']);
    }

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO partners (name, business_type, contact_person, phone, email, address, city, state, district, pincode, gstin, additional_gst, pan, billing_address, status, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'client')");
        $stmt->execute([$name, $business_type, $contact, $phone, $email, $address, $city, $state, $district, $pincode, $gstin, $additional_gst, $pan, $billing_address, $status]);
        header("Location: clients.php?msg=added"); exit;
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("UPDATE partners SET name=?, business_type=?, contact_person=?, phone=?, email=?, address=?, city=?, state=?, district=?, pincode=?, gstin=?, additional_gst=?, pan=?, billing_address=?, status=? WHERE id=?");
        $stmt->execute([$name, $business_type, $contact, $phone, $email, $address, $city, $state, $district, $pincode, $gstin, $additional_gst, $pan, $billing_address, $status, $id]);
        header("Location: clients.php?msg=updated"); exit;
    } elseif ($_POST['action'] === 'delete') {
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]); exit;
    }
}

$activePage = 'clients';
$pageTitle = 'Client Management';
include_once __DIR__ . '/../../includes/header.php';

// Search Logic
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM partners WHERE type = 'client'";
$params = [];

if ($search) {
    $query .= " AND (name LIKE ? OR contact_person LIKE ? OR city LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$indian_states = ["Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal", "Delhi"];
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Corporate Clients</h2>
        <div style="display: flex; gap: 1rem;">
            <form method="GET" style="display: flex; gap: 0.5rem;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search company..." style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <select name="status" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="btn" style="padding: 0.5rem 1rem;"><i class="fas fa-search"></i></button>
            </form>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Add New Company
            </button>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
            <tr>
                <th>Client Details</th>
                <th>Primary Contact</th>
                <th>Tax Credentials</th>
                <th>Location</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $c): ?>
            <tr style="<?php echo $c['status'] == 'inactive' ? 'opacity: 0.6;' : ''; ?>">
                <td>
                    <a href="client_view.php?id=<?php echo $c['id']; ?>" style="text-decoration: none;">
                        <div style="font-weight: 700; color: var(--primary);"><?php echo $c['name']; ?></div>
                        <div style="font-size: 0.7rem; color: #64748b; font-weight: 700; margin-bottom: 2px;"><?php echo $c['business_type'] ?: 'N/A'; ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;">ID: CL-<?php echo str_pad($c['id'], 4, '0', STR_PAD_LEFT); ?></div>
                    </a>
                </td>
                <td>
                    <div style="font-weight: 600; color: #334155;"><?php echo $c['contact_person']; ?></div>
                    <div style="font-size: 0.75rem; color: #94a3b8;"><?php echo $c['phone']; ?></div>
                </td>
                <td>
                    <div style="font-family: monospace; font-size: 0.8rem; color: #475569;">GST: <strong><?php echo $c['gstin'] ?: 'N/A'; ?></strong></div>
                    <div style="font-family: monospace; font-size: 0.8rem; color: #475569;">PAN: <strong><?php echo $c['pan'] ?: 'N/A'; ?></strong></div>
                </td>
                <td>
                    <div style="font-weight: 600; color: #475569;"><?php echo $c['city']; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo ($c['district'] ? $c['district'] . ', ' : '') . $c['state']; ?></div>
                </td>
                <td>
                    <span class="status-pill <?php echo $c['status']; ?>" style="padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; background: <?php echo $c['status'] == 'active' ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $c['status'] == 'active' ? '#166534' : '#475569'; ?>;">
                        <?php echo strtoupper($c['status']); ?>
                    </span>
                </td>
                <td style="text-align: right;">
                    <button class="btn-icon" onclick="editClient(<?php echo htmlspecialchars(json_encode($c)); ?>)" style="color: var(--primary);"><i class="fas fa-edit"></i></button>
                    <button class="btn-icon" style="color: #ef4444;" onclick="deleteClient(<?php echo $c['id']; ?>)"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="clientModal" class="modal">
    <div class="modal-content" style="max-width: 850px;">
        <div class="modal-header">
            <h2 id="modalTitle">Company Profile</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="clientForm" class="was-validated">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="clientId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <div class="form-group"><label>Company Name</label><input type="text" name="name" id="f_name" required></div>
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
                    <div class="form-group"><label>Status</label>
                        <select name="status" id="f_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Billing Address (If different)</label><textarea name="billing_address" id="f_billing" rows="2"></textarea></div>
                </div>
            </div>
            
            <div style="margin-top: 2rem; text-align: right;">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Company Profile</button>
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
    const form = document.getElementById('clientForm');
    form.reset(); 
    form.classList.remove('was-validated');
    document.getElementById('formAction').value = 'add'; 
    renderGstRows(''); // Clear dynamic rows
    document.getElementById('clientModal').style.display = 'block'; 
}
function closeModal() { document.getElementById('clientModal').style.display = 'none'; }
function editClient(c) {
    const form = document.getElementById('clientForm');
    form.classList.remove('was-validated');
    document.getElementById('formAction').value = 'edit';
    document.getElementById('clientId').value = c.id;
    document.getElementById('f_name').value = c.name;
    document.getElementById('f_business_type').value = c.business_type || '';
    document.getElementById('f_contact').value = c.contact_person;
    document.getElementById('f_phone').value = c.phone;
    document.getElementById('f_email').value = c.email;
    document.getElementById('f_address').value = c.address;
    document.getElementById('f_city').value = c.city;
    document.getElementById('f_state').value = c.state;
    document.getElementById('f_district').value = c.district || '';
    document.getElementById('f_pincode').value = c.pincode || '';
    document.getElementById('f_gstin').value = c.gstin;
    document.getElementById('f_pan').value = c.pan;
    document.getElementById('f_additional_gst').value = c.additional_gst || '';
    document.getElementById('f_billing').value = c.billing_address;
    document.getElementById('f_status').value = c.status || 'active';
    
    // Populate Multi-GST Rows
    renderGstRows(c.additional_gst);
    
    showTypeHelp(c.business_type || '');
    document.getElementById('clientModal').style.display = 'block';
}

function addGstRow(data = {gstin: '', city: '', district: '', state: ''}) {
    const container = document.getElementById('gst_rows_list');
    const rowId = 'row_' + Date.now() + Math.random().toString(36).substr(2, 5);
    
    const rowHtml = `
        <div id="${rowId}" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 10px; background: white; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 2px;">
            <button type="button" onclick="removeGstRow('${rowId}')" style="position: absolute; top: -10px; right: -10px; width: 24px; height: 24px; border-radius: 50%; background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 2; transition: all 0.2s;">
                <i class="fas fa-times"></i>
            </button>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">GSTIN</label>
                <input type="text" name="gst_rows[${rowId}][gstin]" value="${data.gstin}" placeholder="GSTIN" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">State</label>
                <select name="gst_rows[${rowId}][state]" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc;">
                    <option value="">Select State</option>
                    <?php foreach ($indian_states as $s): ?>
                        <option value="<?php echo $s; ?>" ${data.state === '<?php echo $s; ?>' ? 'selected' : ''}><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">City</label>
                <input type="text" name="gst_rows[${rowId}][city]" value="${data.city}" placeholder="City" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">District</label>
                <input type="text" name="gst_rows[${rowId}][district]" value="${data.district}" placeholder="District" style="padding: 0.5rem; font-size: 0.85rem; border-color: #f1f5f9; background: #f8fafc;">
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
            // Handle associative array format if it was saved that way
            Object.values(rows).forEach(row => addGstRow(row));
        }
    } catch (e) {
        // Legacy data (comma separated string)
        if (jsonStr.trim()) {
            const gsts = jsonStr.split(',');
            gsts.forEach(g => {
                if (g.trim()) addGstRow({gstin: g.trim(), city: '', district: '', state: ''});
            });
        }
    }
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
            
            // Auto-extract PAN if API didn't provide it
            if (!result.data.pan && gstin.length >= 12) {
                document.getElementById('f_pan').value = gstin.substring(2, 12);
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Data Fetched',
                text: 'Client details populated from GST record.',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            // Even if API fails, we can still extract PAN
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

function showTypeHelp(val) {
    const help = document.getElementById('type-help');
    const gstLabel = document.querySelector('label[for="f_gstin"]') || { innerText: '' };
    const panLabel = document.querySelector('label[for="f_pan"]') || { innerText: '' };
    
    const tips = {
        'Proprietorship': 'Single owner business, common for small shops.',
        'Partnership Firm': '2+ partners, common for small family businesses.',
        'Private Limited': 'Registered company (Pvt Ltd), most common for startups.',
        'Public Limited': 'Large company listed on stock market.',
        'LLP': 'Partnership with limited liability, common for professional firms.',
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

function deleteClient(id) {
    Swal.fire({
        title: 'Delete Client?',
        text: "This company and all associated records will be removed!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('clients.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Company has been removed.', 'success').then(() => location.reload());
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
