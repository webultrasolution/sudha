<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('bookings', 'view');

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    requirePermission('bookings', 'delete');
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
    SELECT b.*, p.proposal_number, c.name as client_name
    FROM bookings b 
    LEFT JOIN proposals p ON b.proposal_id = p.id 
    LEFT JOIN partners c ON b.client_id = c.id 
    ORDER BY b.id DESC
")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Active Bookings</h2>
        <?php if (canAdd('bookings')): ?>
        <a href="direct_booking.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Direct Booking
        </a>
        <?php endif; ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Booking ID</th>
                <th style="width: 100px;">Type</th>
                <th>Proposal #</th>
                <th>Campaign / Brand</th>
                <th>Client</th>
                <th>Period</th>
                <th>Amount</th>
                <th>Execution Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--secondary); padding: 2rem;">No active bookings found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td>
                        <a href="view_booking.php?id=<?php echo $b['id']; ?>" style="text-decoration: none; color: inherit; transition: color 0.2s;" onmouseover="this.style.color='var(--primary)';" onmouseout="this.style.color='inherit';">
                            <strong>#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                        </a>
                    </td>
                    <td>
                        <?php if (empty($b['proposal_id'])): ?>
                            <span class="badge" style="background: #fff1f2; color: #be123c; font-size: 0.65rem; font-weight: 800; border: 1px solid #fecdd3; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase;">Direct</span>
                        <?php else: ?>
                            <span class="badge" style="background: #f0fdf4; color: #15803d; font-size: 0.65rem; font-weight: 800; border: 1px solid #dcfce7; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase;">System</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight: 600; color: #64748b;">
                        <?php if (!empty($b['proposal_number'])): ?>
                            <?php echo $b['proposal_number']; ?>
                        <?php elseif (!empty($b['external_po'])): ?>
                            <span style="color: #0d9488; font-size: 0.75rem;"><i class="fas fa-tag"></i> <?php echo $b['external_po']; ?></span>
                        <?php else: ?>
                            <span style="color: #cbd5e1; font-weight: 400;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo $b['campaign_name']; ?></div>
                        <?php if (!empty($b['brand_name'])): ?>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php echo $b['brand_name']; ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $b['client_name']; ?></td>
                    <td style="font-size: 0.875rem; white-space: nowrap;">
                        <?php echo date('d M', strtotime($b['start_date'])); ?> - <?php echo date('d M Y', strtotime($b['end_date'])); ?>
                    </td>
                    <td style="font-weight: 800; color: #059669; white-space: nowrap;">
                        <?php echo formatCurrency($b['grand_total']); ?>
                    </td>
                    <td>
                        <span class="exec-status status-<?php echo $b['status']; ?>">
                            <?php echo ucfirst($b['status']); ?>
                        </span>
                        <?php if (($b['approval_status'] ?? '') === 'pending_approval'): ?>
                            <div style="margin-top: 4px;">
                                <span style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;">
                                    <i class="fas fa-clock"></i> Awaiting Approval
                                </span>
                            </div>
                        <?php elseif (($b['approval_status'] ?? '') === 'rejected'): ?>
                            <div style="margin-top: 4px;">
                                <span style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;" title="<?php echo htmlspecialchars($b['rejection_reason'] ?? ''); ?>">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="mounting.php?booking_id=<?php echo $b['id']; ?>" class="btn-icon btn-view" title="View Operations"><i class="fas fa-clipboard-list"></i></a>
                        <?php if (canDelete('bookings')): ?>
                        <button class="btn-icon btn-delete" onclick="deleteBooking(<?php echo $b['id']; ?>)"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
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
.status-cancelled { background: #fee2e2; color: #991b1b; }
@keyframes pulse-approval { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
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
