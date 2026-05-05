<?php
$activePage = 'bookings';
$pageTitle = 'Operations / Mounting Tracker';
include_once __DIR__ . '/../../includes/header.php';

// Fetch Operations (Site-wise execution)
$ops = $pdo->query("
    SELECT op.*, s.name as site_name, s.site_code, s.location, s.type as site_type, p.name as client_name, b.id as booking_id
    FROM operations op
    JOIN sites s ON op.site_id = s.id
    JOIN bookings b ON op.booking_id = b.id
    JOIN proposals prop ON b.proposal_id = prop.id
    JOIN partners p ON prop.client_id = p.id
    ORDER BY op.id DESC
")->fetchAll();

// Fetch Mounters (Staff with operations/field role - using 'operations' role for now)
$mounters = $pdo->query("SELECT id, full_name FROM users WHERE role = 'operations' OR role = 'admin' ORDER BY full_name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Field Mounting Tasks</h2>
        <div class="search-box">
            <input type="text" placeholder="Search tasks..." id="op-search" class="p-input" onkeyup="filterOps()">
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Site Code</th>
                <th>Site Name & Location</th>
                <th>Client</th>
                <th>Planned Date</th>
                <th>Assigned To</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ops as $op): ?>
            <tr>
                <td><strong><?php echo $op['site_code']; ?></strong></td>
                <td>
                    <div><?php echo $op['site_name']; ?></div>
                    <div style="font-size: 0.75rem; color: var(--secondary);"><?php echo $op['location']; ?></div>
                </td>
                <td><?php echo $op['client_name']; ?></td>
                <td><?php echo $op['mounting_date'] ? date('d M Y', strtotime($op['mounting_date'])) : 'Not Set'; ?></td>
                <td>
                    <select class="p-input" style="width: 150px; font-size: 0.8rem; padding: 0.25rem;" onchange="assignMounter(<?php echo $op['id']; ?>, this.value)">
                        <option value="">Unassigned</option>
                        <?php foreach ($mounters as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $op['assigned_mounter_id'] == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo $m['full_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <span class="status-pill status-<?php echo $op['status']; ?>">
                        <?php echo ucfirst($op['status']); ?>
                    </span>
                </td>
                <td style="white-space: nowrap;">
                    <?php if ($op['status'] !== 'completed'): ?>
                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="markCompleted(<?php echo $op['id']; ?>)">
                            <i class="fas fa-check"></i> Complete
                        </button>
                    <?php endif; ?>
                    <button class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border: 1px solid #ddd;" onclick="uploadPhoto(<?php echo $op['id']; ?>)">
                        <i class="fas fa-camera"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.status-pill { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.status-pending { background: #fef9c3; color: #854d0e; }
.status-mounting { background: #e0f2fe; color: #0369a1; }
.status-completed { background: #dcfce7; color: #166534; }
.p-input { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; }
</style>

<script>
function assignMounter(opId, mounterId) {
    fetch('../../ajax/update_op_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ op_id: opId, assigned_mounter_id: mounterId, status: 'mounting' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ title: 'Assigned', text: 'Mounter assigned successfully!', icon: 'success', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false });
        }
    });
}

function markCompleted(opId) {
    if(!confirm('Mark this task as completed?')) return;
    
    fetch('../../ajax/update_op_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ op_id: opId, status: 'completed' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

function uploadPhoto(opId) {
    Swal.fire({
        title: 'Upload Proof of Work',
        input: 'file',
        inputAttributes: { 'accept': 'image/*', 'aria-label': 'Upload site photo' },
        showCancelButton: true,
        confirmButtonText: 'Upload',
        showLoaderOnConfirm: true,
        preConfirm: (file) => {
            // In a real app, use FormData to upload
            return true; 
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Success', 'Photo uploaded and tagged to site.', 'success');
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
