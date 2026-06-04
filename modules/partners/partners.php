<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle Form Submissions (AJAX & POST) - MUST BE BEFORE HEADER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_partner') {
        $type = clean($_POST['type']);
        requirePermission($type === 'client' ? 'clients' : 'vendors', 'add');
        
        $name = clean($_POST['name']);
        $gstin = clean($_POST['gstin']);
        $pan = clean($_POST['pan']);
        $contact = clean($_POST['contact_person']);
        $phone = clean($_POST['phone']);
        $email = clean($_POST['email']);
        $address = clean($_POST['address']);

        $stmt = $pdo->prepare("INSERT INTO partners (type, name, gstin, pan, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $name, $gstin, $pan, $contact, $phone, $email, $address]);
        header("Location: partners.php?msg=added");
        exit;
    } elseif ($_POST['action'] === 'edit_partner') {
        $id = intval($_POST['id']);
        
        // Fetch partner type for permission enforcement
        $p_type = $pdo->prepare("SELECT type FROM partners WHERE id = ?");
        $p_type->execute([$id]);
        $type = $p_type->fetchColumn();
        if ($type) {
            requirePermission($type === 'client' ? 'clients' : 'vendors', 'edit');
        } else {
            die("Invalid partner ID.");
        }

        $name = clean($_POST['name']);
        $gstin = clean($_POST['gstin']);
        $pan = clean($_POST['pan']);
        $contact = clean($_POST['contact_person']);
        $phone = clean($_POST['phone']);
        $email = clean($_POST['email']);
        $address = clean($_POST['address']);

        $stmt = $pdo->prepare("UPDATE partners SET name=?, gstin=?, pan=?, contact_person=?, phone=?, email=?, address=? WHERE id=?");
        $stmt->execute([$name, $gstin, $pan, $contact, $phone, $email, $address, $id]);
        header("Location: partners.php?msg=updated");
        exit;
    } elseif ($_POST['action'] === 'delete_partner') {
        header('Content-Type: application/json');
        $id = intval($_POST['id']);
        
        // Fetch partner type for permission enforcement
        $p_type = $pdo->prepare("SELECT type FROM partners WHERE id = ?");
        $p_type->execute([$id]);
        $type = $p_type->fetchColumn();
        if ($type) {
            requirePermission($type === 'client' ? 'clients' : 'vendors', 'delete');
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid partner ID.']);
            exit;
        }

        try {
            include_once __DIR__ . '/../../includes/trash_helper.php';
            $trashId = move_row_to_trash($pdo, 'partners', 'id', $id, $_SESSION['user_id'] ?? null, ucfirst($type) . ' deleted via UI');
            if ($trashId) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to move partner to trash.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'This partner cannot be deleted because they are associated with existing sites, proposals, or invoices.']);
        }
        exit;
    }
}

$activePage = 'partners';
$pageTitle = 'Partner Management';
include_once __DIR__ . '/../../includes/header.php';

// Pagination for Clients
$limit = 10;
$c_page = isset($_GET['c_page']) ? (int)$_GET['c_page'] : 1;
$c_offset = ($c_page - 1) * $limit;
$totalClients = $pdo->query("SELECT COUNT(*) FROM partners WHERE type = 'client'")->fetchColumn();
$totalClientPages = ceil($totalClients / $limit);

$clients = $pdo->prepare("SELECT * FROM partners WHERE type = 'client' ORDER BY name ASC LIMIT ? OFFSET ?");
$clients->execute([$limit, $c_offset]);
$clients = $clients->fetchAll();

// Pagination for Vendors
$v_page = isset($_GET['v_page']) ? (int)$_GET['v_page'] : 1;
$v_offset = ($v_page - 1) * $limit;
$totalVendors = $pdo->query("SELECT COUNT(*) FROM partners WHERE type = 'vendor'")->fetchColumn();
$totalVendorPages = ceil($totalVendors / $limit);

$vendors = $pdo->prepare("SELECT * FROM partners WHERE type = 'vendor' ORDER BY name ASC LIMIT ? OFFSET ?");
$vendors->execute([$limit, $v_offset]);
$vendors = $vendors->fetchAll();
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Clients Section -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem;"><i class="fas fa-briefcase" style="color: var(--primary);"></i> Clients</h2>
            <?php if (canAdd('clients')): ?>
            <button class="btn btn-primary" onclick="openModal('client')">
                <i class="fas fa-plus"></i> Add Client
            </button>
            <?php endif; ?>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Name</th>
                    <th>GSTIN</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sn = $c_offset + 1;
                foreach ($clients as $p): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td>
                        <div style="font-weight: 600;"><?php echo $p['name']; ?></div>
                        <div style="font-size: 0.75rem; color: var(--secondary);"><?php echo $p['email']; ?></div>
                    </td>
                    <td><code style="font-size: 0.75rem;"><?php echo $p['gstin'] ?: 'N/A'; ?></code></td>
                    <td><?php echo $p['contact_person']; ?></td>
                    <td>
                        <?php if (canEdit('clients')): ?>
                        <button type="button" class="btn" title="Edit" onclick="editPartner(<?php echo htmlspecialchars(json_encode($p)); ?>)"><i class="fas fa-edit"></i></button>
                        <?php endif; ?>
                        <?php if (canDelete('clients')): ?>
                        <button type="button" class="btn" style="color: var(--danger);" title="Delete" onclick="deletePartner(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php echo renderPagination($c_page, $totalClientPages, 'partners.php', 'c_page'); ?>
    </div>

    <!-- Vendors Section -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem;"><i class="fas fa-truck-loading" style="color: var(--warning);"></i> Vendors</h2>
            <?php if (canAdd('vendors')): ?>
            <button class="btn btn-primary" onclick="openModal('vendor')">
                <i class="fas fa-plus"></i> Add Vendor
            </button>
            <?php endif; ?>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>GSTIN</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sn = $v_offset + 1;
                foreach ($vendors as $p): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td>
                        <div style="font-weight: 600;"><?php echo $p['name']; ?></div>
                        <div style="font-size: 0.75rem; color: var(--secondary);"><?php echo $p['email']; ?></div>
                    </td>
                    <td><code style="font-size: 0.75rem;"><?php echo $p['gstin'] ?: 'N/A'; ?></code></td>
                    <td><?php echo $p['contact_person']; ?></td>
                    <td>
                        <?php if (canEdit('vendors')): ?>
                        <button type="button" class="btn" title="Edit" onclick="editPartner(<?php echo htmlspecialchars(json_encode($p)); ?>)"><i class="fas fa-edit"></i></button>
                        <?php endif; ?>
                        <?php if (canDelete('vendors')): ?>
                        <button type="button" class="btn" style="color: var(--danger);" title="Delete" onclick="deletePartner(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php echo renderPagination($v_page, $totalVendorPages, 'partners.php', 'v_page'); ?>
    </div>
</div>

<!-- Add/Edit Partner Modal -->
<div id="partnerModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="modalTitle">Add Partner</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="partnerForm">
            <input type="hidden" name="action" id="formAction" value="add_partner">
            <input type="hidden" name="type" id="partnerType">
            <input type="hidden" name="id" id="partnerId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label>Company Name</label>
                    <input type="text" name="name" id="f_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label>GSTIN</label>
                    <input type="text" name="gstin" id="f_gstin" maxlength="15" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label>PAN</label>
                    <input type="text" name="pan" id="f_pan" maxlength="10" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" id="f_contact" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="f_phone" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Email</label>
                    <input type="email" name="email" id="f_email" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Address</label>
                    <textarea name="address" id="f_address" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Partner</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.modal-content { background: white; margin: 10% auto; padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); position: relative; }
.modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; }
.close { cursor: pointer; font-size: 1.5rem; color: var(--secondary); }
.form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--secondary); }
</style>

<script>
function openModal(type) {
    document.getElementById('partnerForm').reset();
    document.getElementById('partnerId').value = '';
    document.getElementById('formAction').value = 'add_partner';
    document.getElementById('partnerType').value = type;
    document.getElementById('modalTitle').innerText = 'Add New ' + type.charAt(0).toUpperCase() + type.slice(1);
    document.getElementById('partnerModal').style.display = 'block';
}
function closeModal() {
    document.getElementById('partnerModal').style.display = 'none';
}

function editPartner(p) {
    document.getElementById('formAction').value = 'edit_partner';
    document.getElementById('partnerId').value = p.id;
    document.getElementById('partnerType').value = p.type;
    document.getElementById('modalTitle').innerText = 'Edit ' + p.type.charAt(0).toUpperCase() + p.type.slice(1);
    
    document.getElementById('f_name').value = p.name;
    document.getElementById('f_gstin').value = p.gstin || '';
    document.getElementById('f_pan').value = p.pan || '';
    document.getElementById('f_contact').value = p.contact_person || '';
    document.getElementById('f_phone').value = p.phone || '';
    document.getElementById('f_email').value = p.email || '';
    document.getElementById('f_address').value = p.address || '';
    
    document.getElementById('partnerModal').style.display = 'block';
}

function deletePartner(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This partner will be permanently removed!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('partners.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_partner&id=${id}`
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'The partner has been removed.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
