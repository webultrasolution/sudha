<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM proposal_items WHERE proposal_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM proposals WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$activePage = 'proposals';
$pageTitle = 'Campaign Proposals';
include_once __DIR__ . '/../../includes/header.php';

// Pagination Logic
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalProposals = $pdo->query("SELECT COUNT(*) FROM proposals")->fetchColumn();
$totalPages = ceil($totalProposals / $limit);

$proposals = $pdo->prepare("
    SELECT p.*, c.name as client_name, u.username as creator 
    FROM proposals p 
    JOIN partners c ON p.client_id = c.id 
    LEFT JOIN users u ON p.created_by = u.id 
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
");
$proposals->execute([$limit, $offset]);
$proposals = $proposals->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Recent Proposals</h2>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Proposal
        </a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Proposal #</th>
                <th>Client</th>
                <th>Duration</th>
                <th>Grand Total</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($proposals)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--secondary); padding: 2rem;">No proposals found. Start by creating one!</td>
                </tr>
            <?php else: ?>
                <?php 
                $sn = $offset + 1;
                foreach ($proposals as $p): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td>
                        <a href="view.php?id=<?php echo $p['id']; ?>" style="color: var(--primary); text-decoration: none; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                            <strong><?php echo $p['proposal_number'] ?: '#PR-' . str_pad($p['id'], 5, '0', STR_PAD_LEFT); ?></strong>
                        </a>
                    </td>
                    <td><?php echo $p['client_name']; ?></td>
                    <td style="font-size: 0.875rem;">
                        <?php echo date('d M Y', strtotime($p['start_date'])); ?> to <br>
                        <?php echo date('d M Y', strtotime($p['end_date'])); ?>
                    </td>
                    <td><strong><?php echo formatCurrency($p['grand_total']); ?></strong></td>
                    <td>
                        <span class="status-pill status-<?php echo $p['status']; ?>">
                            <?php echo ucfirst($p['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="dropdown" style="display: inline-block; margin-right: 0.5rem;">
                            <button class="btn-icon" style="color: var(--primary);" title="Export"><i class="fas fa-download"></i></button>
                            <div class="dropdown-content" style="right: 0; min-width: 160px;">
                                <a href="#"><i class="fas fa-file-pdf" style="color: #ef4444; width: 18px;"></i> PDF Proposal</a>
                                <a href="#"><i class="fas fa-file-excel" style="color: #10b981; width: 18px;"></i> Excel Rate Sheet</a>
                                <a href="#"><i class="fas fa-file-powerpoint" style="color: #f97316; width: 18px;"></i> PPT Presentation</a>
                            </div>
                        </div>
                        <a href="view.php?id=<?php echo $p['id']; ?>" class="btn-icon" title="View Workspace"><i class="fas fa-external-link-alt"></i></a>
                        <button class="btn-icon" style="color: var(--danger);" onclick="deleteProposal(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php echo renderPagination($page, $totalPages, 'proposals.php'); ?>
</div>

<style>
.status-pill { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.status-draft { background: #f1f5f9; color: #475569; }
.status-sent { background: #e0f2fe; color: #0369a1; }
.status-confirmed { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.btn-icon { color: var(--secondary); background: none; border: none; cursor: pointer; text-decoration: none; margin-right: 0.25rem; transition: color 0.2s; padding: 0.25rem; display: inline-flex; align-items: center; justify-content: center; }
.btn-icon:hover { color: var(--primary); }

/* Dropdown styling */
.dropdown { position: relative; display: inline-block; }
.dropdown-content { display: none; position: absolute; right: 0; background-color: white; min-width: 180px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); border-radius: 12px; z-index: 50; border: 1px solid #f1f5f9; overflow: hidden; padding: 0.5rem; text-align: left; }
.dropdown-content a { color: #334155; padding: 0.6rem 0.8rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; border-radius: 6px; transition: all 0.2s; }
.dropdown-content a:hover { background: #f8fafc; color: var(--primary); transform: translateX(2px); }
.dropdown:hover .dropdown-content { display: block; animation: slideDown 0.2s ease-out forwards; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script>
function deleteProposal(id) {
    Swal.fire({
        title: 'Delete Proposal?',
        text: "This will delete the proposal and all its line items. Continue?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1CADA9',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete everything!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('proposals.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(() => {
                Swal.fire('Deleted!', 'Proposal removed successfully.', 'success').then(() => location.reload());
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
