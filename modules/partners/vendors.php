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
    $gstin = clean($_POST['gstin']);
    $additional_gst = clean($_POST['additional_gst'] ?? '');
    $pan = clean($_POST['pan']);
    $billing_address = clean($_POST['billing_address']);
    $payment_terms = clean($_POST['payment_terms']);
    $business_type = clean($_POST['business_type'] ?? '');
    $status = clean($_POST['status'] ?? 'active');

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO partners (name, business_type, contact_person, phone, email, address, city, state, gstin, additional_gst, pan, billing_address, payment_terms, status, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'vendor')");
        $stmt->execute([$name, $business_type, $contact, $phone, $email, $address, $city, $state, $gstin, $additional_gst, $pan, $billing_address, $payment_terms, $status]);
        header("Location: vendors.php?msg=added"); exit;
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("UPDATE partners SET name=?, business_type=?, contact_person=?, phone=?, email=?, address=?, city=?, state=?, gstin=?, additional_gst=?, pan=?, billing_address=?, payment_terms=?, status=? WHERE id=?");
        $stmt->execute([$name, $business_type, $contact, $phone, $email, $address, $city, $state, $gstin, $additional_gst, $pan, $billing_address, $payment_terms, $status, $id]);
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
                    <button class="btn-icon" onclick="editVendor(<?php echo htmlspecialchars(json_encode($v)); ?>)"><i class="fas fa-edit"></i></button>
                    <button class="btn-icon" style="color: var(--danger);" onclick="deleteVendor(<?php echo $v['id']; ?>)"><i class="fas fa-trash"></i></button>
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
                        <div class="form-group">
                            <label>State</label>
                            <select name="state" id="f_state" required>
                                <option value="">Select State</option>
                                <?php foreach ($indian_states as $s): ?>
                                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label id="f_gstin_label">Primary GSTIN</label><input type="text" name="gstin" id="f_gstin"></div>
                    <div id="additional_gst_container" style="display: none;" class="form-group">
                        <label>Additional GSTINs (Branch/State-wise)</label>
                        <textarea name="additional_gst" id="f_additional_gst" rows="2" placeholder="Enter multiple GSTs separated by comma..."></textarea>
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
    document.getElementById('vendorModal').style.display = 'block'; 
}
function closeModal() { document.getElementById('vendorModal').style.display = 'none'; }
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
    document.getElementById('f_gstin').value = v.gstin;
    document.getElementById('f_additional_gst').value = v.additional_gst || '';
    document.getElementById('f_pan').value = v.pan;
    document.getElementById('f_billing').value = v.billing_address;
    document.getElementById('f_terms').value = v.payment_terms;
    document.getElementById('f_business_type').value = v.business_type || '';
    showTypeHelp(v.business_type || '');
    document.getElementById('vendorModal').style.display = 'block';
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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
