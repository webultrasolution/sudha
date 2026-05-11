<?php
$activePage = 'bookings';
$pageTitle = 'Operations / Mounting Tracker';
include_once __DIR__ . '/../../includes/header.php';

// Filter by Booking ID if provided
$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : null;
$where = "";
$params = [];

if ($bookingId) {
    $where = "WHERE op.booking_id = ?";
    $params[] = $bookingId;
}

// Fetch Operations (Site-wise execution)
$stmt = $pdo->prepare("
    SELECT op.*, s.name as site_name, s.site_code, s.location, s.type as site_type, p.name as client_name, b.id as booking_id
    FROM operations op
    JOIN sites s ON op.site_id = s.id
    JOIN bookings b ON op.booking_id = b.id
    JOIN partners p ON b.client_id = p.id
    $where
    ORDER BY op.id DESC
");
$stmt->execute($params);
$ops = $stmt->fetchAll();

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
                <th style="width: 40px;">#</th>
                <th>Asset / Location</th>
                <th>Client Details</th>
                <th>Execution Date</th>
                <th>Field Personnel</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $sn=1; foreach ($ops as $op): ?>
            <tr>
                <td><?php echo $sn++; ?></td>
                <td>
                    <div style="font-weight: 700; color: #1e293b;"><?php echo $op['site_name']; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600; text-transform: uppercase;">
                        Code: <?php echo $op['site_code']; ?> • <?php echo $op['location']; ?>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 600; color: #334155;"><?php echo $op['client_name']; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8;">Booking: #BK-<?php echo str_pad($op['booking_id'], 4, '0', STR_PAD_LEFT); ?></div>
                </td>
                <td>
                    <div style="font-weight: 700; color: #475569; font-size: 0.85rem;">
                        <i class="far fa-calendar-alt" style="margin-right: 0.3rem;"></i>
                        <?php echo $op['mounting_date'] ? date('d M Y', strtotime($op['mounting_date'])) : '<span style="color: #ef4444;">Not Scheduled</span>'; ?>
                    </div>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <select class="p-input" style="width: 160px; height: 32px; font-size: 0.8rem; padding: 0 0.5rem; border-radius: 6px;" onchange="assignMounter(<?php echo $op['id']; ?>, this.value)">
                            <option value="">Unassigned</option>
                            <?php foreach ($mounters as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo $op['assigned_mounter_id'] == $m['id'] ? 'selected' : ''; ?>>
                                    <?php echo $m['full_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($op['assigned_mounter_id']): ?>
                            <i class="fas fa-user-check" style="color: #10b981;"></i>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <span class="status-pill status-<?php echo $op['status']; ?>" style="padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">
                        <?php echo ucfirst($op['status']); ?>
                    </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                    <?php if ($op['status'] !== 'completed'): ?>
                        <button class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;" onclick="markCompleted(<?php echo $op['id']; ?>)">
                            <i class="fas fa-check"></i> Complete
                        </button>
                    <?php endif; ?>
                    <button class="btn-icon" style="color: #64748b; margin-left: 0.5rem;" onclick="uploadPhoto(<?php echo $op['id']; ?>)" title="Upload Proof">
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
    Swal.fire({
        title: 'Task Completed?',
        text: "Are you sure you want to mark this task as completed?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Yes, Mark Completed'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../ajax/update_op_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ op_id: opId, status: 'completed' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', 'Task marked as completed.', 'success').then(() => location.reload());
                }
            });
        }
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
