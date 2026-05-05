<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM operations WHERE booking_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$activePage = 'bookings';
$pageTitle = 'Campaign Execution';
include_once __DIR__ . '/../../includes/header.php';

// Fetch Bookings
$bookings = $pdo->query("
    SELECT b.*, p.proposal_number, c.name as client_name, p.start_date, p.end_date
    FROM bookings b 
    JOIN proposals p ON b.proposal_id = p.id 
    JOIN partners c ON p.client_id = c.id 
    ORDER BY b.id DESC
")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Active Bookings</h2>
        <a href="mounting.php" class="btn btn-primary" style="background: var(--dark);">
            <i class="fas fa-tools"></i> Go to Mounting Tracker
        </a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Booking ID</th>
                <th>Proposal #</th>
                <th>Client</th>
                <th>Period</th>
                <th>Execution Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--secondary); padding: 2rem;">No active bookings found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><strong>#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                    <td><?php echo $b['proposal_number'] ?: 'PR-'.$b['proposal_id']; ?></td>
                    <td><?php echo $b['client_name']; ?></td>
                    <td style="font-size: 0.875rem;">
                        <?php echo date('d M', strtotime($b['start_date'])); ?> - <?php echo date('d M Y', strtotime($b['end_date'])); ?>
                    </td>
                    <td>
                        <span class="exec-status status-<?php echo $b['status']; ?>">
                            <?php echo ucfirst($b['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="mounting.php?booking_id=<?php echo $b['id']; ?>" class="btn-icon" title="View Operations"><i class="fas fa-clipboard-list"></i></a>
                        <button class="btn-icon" style="color: var(--danger);" onclick="deleteBooking(<?php echo $b['id']; ?>)"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.exec-status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-pending { background: #fef9c3; color: #854d0e; }
.status-mounting { background: #dcfce7; color: #166534; }
.status-active { background: #e0f2fe; color: #0369a1; }
.status-completed { background: #f1f5f9; color: #475569; }
.btn-icon { background: none; border: none; cursor: pointer; color: var(--secondary); text-decoration: none; margin-right: 0.5rem; }
.btn-icon:hover { color: var(--primary); }
</style>

<script>
function deleteBooking(id) {
    Swal.fire({
        title: 'Cancel Booking?',
        text: "This will delete the booking and all related operational tasks. Proceed?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete booking'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('bookings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Booking has been cancelled and removed.', 'success').then(() => location.reload());
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
