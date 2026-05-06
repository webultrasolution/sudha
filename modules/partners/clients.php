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
    $pan = clean($_POST['pan']);
    $billing_address = clean($_POST['billing_address']);
    $status = clean($_POST['status'] ?? 'active');

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO partners (name, contact_person, phone, email, address, city, state, gstin, pan, billing_address, status, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'client')");
        $stmt->execute([$name, $contact, $phone, $email, $address, $city, $state, $gstin, $pan, $billing_address, $status]);
        header("Location: clients.php?msg=added"); exit;
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("UPDATE partners SET name=?, contact_person=?, phone=?, email=?, address=?, city=?, state=?, gstin=?, pan=?, billing_address=?, status=? WHERE id=?");
        $stmt->execute([$name, $contact, $phone, $email, $address, $city, $state, $gstin, $pan, $billing_address, $status, $id]);
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
                    <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo $c['state']; ?></div>
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
                    <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" id="f_contact" required></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" id="f_phone" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="f_email" required></div>
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
                    <div class="form-group"><label>GSTIN</label><input type="text" name="gstin" id="f_gstin"></div>
                    <div class="form-group"><label>PAN</label><input type="text" name="pan" id="f_pan"></div>
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
    document.getElementById('clientModal').style.display = 'block'; 
}
function closeModal() { document.getElementById('clientModal').style.display = 'none'; }
function editClient(c) {
    const form = document.getElementById('clientForm');
    form.classList.remove('was-validated');
    document.getElementById('formAction').value = 'edit';
    document.getElementById('clientId').value = c.id;
    document.getElementById('f_name').value = c.name;
    document.getElementById('f_contact').value = c.contact_person;
    document.getElementById('f_phone').value = c.phone;
    document.getElementById('f_email').value = c.email;
    document.getElementById('f_address').value = c.address;
    document.getElementById('f_city').value = c.city;
    document.getElementById('f_state').value = c.state;
    document.getElementById('f_gstin').value = c.gstin;
    document.getElementById('f_pan').value = c.pan;
    document.getElementById('f_billing').value = c.billing_address;
    document.getElementById('f_status').value = c.status || 'active';
    document.getElementById('clientModal').style.display = 'block';
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
