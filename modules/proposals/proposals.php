<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Enforce View Permission at Page Level
requirePermission('proposals', 'view');

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    include_once __DIR__ . '/../../includes/trash_helper.php';
    requirePermission('proposals', 'delete');
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    try {
        $pdo->beginTransaction();
        $itemStmt = $pdo->prepare("SELECT id FROM proposal_items WHERE proposal_id = ?");
        $itemStmt->execute([$id]);
        while ($item = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
            move_row_to_trash($pdo, 'proposal_items', 'id', $item['id'], $_SESSION['user_id'] ?? null, 'Proposal deleted - item moved to trash');
        }
        $trashId = move_row_to_trash($pdo, 'proposals', 'id', $id, $_SESSION['user_id'] ?? null, 'Proposal deleted via UI');
        if (!$trashId) {
            throw new Exception('Failed to move proposal to trash');
        }
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
$clientFilter = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$campaignFilter = trim($_GET['campaign_name'] ?? '');

$where = 'WHERE 1=1';
$params = [];
if ($clientFilter) {
    $where .= ' AND p.client_id = ?';
    $params[] = $clientFilter;
}
if ($campaignFilter !== '') {
    $where .= ' AND p.campaign_name LIKE ?';
    $params[] = '%' . $campaignFilter . '%';
}

$totalProposals = $pdo->prepare("SELECT COUNT(*) FROM proposals p $where");
$totalProposals->execute($params);
$totalProposals = $totalProposals->fetchColumn();
$totalPages = ceil($totalProposals / $limit);

$proposals = $pdo->prepare("
    SELECT p.*, c.name as client_name, u.username as creator 
    FROM proposals p 
    JOIN partners c ON p.client_id = c.id 
    LEFT JOIN users u ON p.created_by = u.id 
    $where 
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$proposals->execute($params);
$proposals = $proposals->fetchAll();

$clients = $pdo->query("SELECT id, name FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;">Recent Proposals</h2>
        <?php if (canAdd('proposals')): ?>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Proposal
        </a>
        <?php endif; ?>
    </div>

    <form method="get" action="proposals.php" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin-bottom: 1.5rem;">
        <div style="display:flex; flex-direction:column; gap:0.35rem;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Client</label>
            <select name="client_id" style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:220px;">
                <option value="">All Clients</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>" <?php echo $clientFilter === intval($client['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.35rem; min-width:280px;">
            <label style="font-size:0.85rem; color:#475569; font-weight:600;">Campaign Name</label>
            <input type="text" name="campaign_name" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Search campaign..." style="padding:0.75rem 0.95rem; border-radius:0.75rem; border:1px solid #d1d5db; min-width:280px;">
        </div>
        <div style="display:flex; gap:0.75rem;">
            <button type="submit" class="btn btn-primary" style="padding:0.85rem 1.25rem;">Filter</button>
            <a href="proposals.php" class="btn" style="background:#f8fafc; color:#475569; border:1px solid #cbd5e1; padding:0.85rem 1.25rem; text-decoration:none;">Reset</a>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 60px;">#</th>
                <th>CAMPAIGN & PROPOSAL</th>
                <th>CLIENT / ACCOUNT</th>
                <th>TENURE</th>
                <th>VALUE (INCL. TAX)</th>
                <th>STATUS</th>
                <th style="text-align: right;">OPERATIONS</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($proposals)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--secondary); padding: 2rem;">No proposals found. Start by creating one!</td>
                </tr>
            <?php else: ?>
                <?php 
                $sn = $offset + 1;
                foreach ($proposals as $p): ?>
                <tr>
                    <td style="text-align: center; font-weight: 700; color: #94a3b8;"><?php echo $sn++; ?></td>
                    <td>
                        <a href="view.php?id=<?php echo $p['id']; ?>" style="text-decoration: none;">
                            <div style="font-weight: 800; color: var(--primary); font-size: 0.95rem; margin-bottom: 3px;"><?php echo htmlspecialchars($p['campaign_name']); ?></div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <span style="font-size: 0.65rem; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-weight: 800;">#<?php echo $p['proposal_number']; ?></span>
                                <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;"><i class="far fa-clock"></i> <?php echo date('d M Y', strtotime($p['created_at'])); ?></span>
                            </div>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo $p['client_name']; ?></div>
                        <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;">Account: <?php echo $p['creator']; ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #475569; font-size: 0.85rem;"><?php echo date('d M', strtotime($p['start_date'])); ?> - <?php echo date('d M', strtotime($p['end_date'])); ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo date('Y', strtotime($p['end_date'])); ?> • <?php 
                            $diff = date_diff(date_create($p['start_date']), date_create($p['end_date']));
                            echo $diff->format("%a days");
                        ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 800; color: #0f172a; font-size: 0.95rem;">₹<?php echo number_format($p['grand_total'], 2); ?></div>
                        <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;">Base: ₹<?php echo number_format($p['total_amount'], 2); ?></div>
                    </td>
                    <td>
                        <?php 
                            $displayStatus = $p['status'];
                            if (($p['approval_status'] ?? '') === 'approved' && $p['status'] === 'sent') {
                                $displayStatus = 'approved';
                            }
                        ?>
                        <span class="status-pill status-<?php echo $displayStatus; ?>" style="font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.65rem;">
                            <?php echo $displayStatus; ?>
                        </span>
                        <?php if (($p['approval_status'] ?? '') === 'pending_approval'): ?>
                            <div style="margin-top: 4px;">
                                <span style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; animation: pulse-approval 2s infinite;">
                                    <i class="fas fa-clock"></i> Awaiting Approval
                                </span>
                            </div>
                        <?php elseif (($p['approval_status'] ?? '') === 'rejected'): ?>
                            <div style="margin-top: 4px;">
                                <span style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.15rem 0.5rem; border-radius: 50px; font-size: 0.6rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;" title="<?php echo htmlspecialchars($p['rejection_reason'] ?? ''); ?>">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if (($p['approval_status'] ?? '') === 'approved'): ?>
                            <div class="dropdown" style="display: inline-block;">
                                <button class="btn-icon btn-view" title="Export Options"><i class="fas fa-file-export"></i></button>
                                <div class="dropdown-content">
                                    <div style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; padding: 0.5rem 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Client Documents</div>
                                    <a href="export_pdf.php?id=<?php echo $p['id']; ?>" target="_blank"><i class="fas fa-file-pdf" style="color: #ef4444;"></i> Visual Media Plan (PDF)</a>
                                    <a href="export_excel.php?id=<?php echo $p['id']; ?>"><i class="fas fa-file-excel" style="color: #10b981;"></i> Excel Rate Sheet</a>
                                    <a href="export_ppt.php?id=<?php echo $p['id']; ?>" target="_blank"><i class="fas fa-file-powerpoint" style="color: #f97316;"></i> PPT Deck / Presentation</a>
                                    <a href="javascript:void(0)" onclick="generateProforma(<?php echo $p['id']; ?>)"><i class="fas fa-file-invoice" style="color: #6366f1;"></i> Proforma Invoice (PI)</a>
                                    <div style="height: 1px; background: #f1f5f9; margin: 0.25rem 0;"></div>
                                    <div style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; padding: 0.5rem 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Visuals</div>
                                    <a href="export_ppt.php?id=<?php echo $p['id']; ?>&mode=view" target="_blank"><i class="fas fa-desktop" style="color: #6366f1;"></i> View Presentation</a>
                                    <a href="javascript:void(0)" onclick="copyPublicLink('<?php echo BASE_URL; ?>modules/proposals/export_ppt.php?id=<?php echo $p['id']; ?>')"><i class="fas fa-link" style="color: #6366f1;"></i> Copy Public Link</a>
                                    <a href="download_photos.php?id=<?php echo $p['id']; ?>"><i class="fas fa-images" style="color: #8b5cf6;"></i> Download Photos</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="btn-icon" title="Exports Locked (Awaiting Approval)" style="color: #cbd5e1; cursor: not-allowed; display: inline-block; padding: 0.25rem;"><i class="fas fa-lock"></i></span>
                        <?php endif; ?>
                        <a href="view.php?id=<?php echo $p['id']; ?>" class="btn-icon btn-view" title="Workspace" style="color: #64748b;"><i class="fas fa-layer-group"></i></a>
                        <?php if (canDelete('proposals')): ?>
                        <button class="btn-icon btn-delete" onclick="deleteProposal(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php echo renderPagination($page, $totalPages, 'proposals.php', 'page', ['client_id' => $clientFilter, 'campaign_name' => $campaignFilter]); ?>
</div>

<style>
.status-pill { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.status-confirmed { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.status-draft { background: #f1f5f9; color: #475569; }
.status-sent { background: #e0f2fe; color: #0369a1; }
.status-approved { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
@keyframes pulse-approval { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

/* Improved Dropdown styling */
.dropdown { position: relative; }
.dropdown-content { 
    display: none; 
    position: absolute; 
    right: 0; 
    top: 100%;
    background-color: white; 
    min-width: 200px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
    border-radius: 12px; 
    z-index: 9999; 
    border: 1px solid #e2e8f0; 
    padding: 0.5rem; 
    text-align: left; 
}
.dropdown-content a { 
    color: #334155; 
    padding: 0.7rem 0.9rem; 
    text-decoration: none !important; 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    font-size: 0.85rem; 
    font-weight: 600; 
    border-radius: 8px; 
    transition: all 0.2s; 
}
.dropdown-content i { font-size: 1rem; width: 20px; text-align: center; }
.dropdown-content a:hover { background: #f0fdfa; color: var(--primary); }
.dropdown:hover .dropdown-content { display: block; animation: slideIn 0.2s ease-out; }
@keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Fix table cell overflow */
.table td { position: relative; overflow: visible !important; }
.card { overflow: visible !important; }
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

function copyPublicLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Public link copied to clipboard!',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}

function generateProforma(proposalId) {
    Swal.fire({
        title: 'Generating Proforma Invoice',
        text: 'Please wait while we compile the invoice details...',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(`../../ajax/create_proforma_from_proposal.php?proposal_id=${proposalId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Generated!',
                    text: data.message,
                    confirmButtonColor: '#1CADA9',
                    confirmButtonText: 'View Proforma Invoice'
                }).then(() => {
                    window.open(`../financials/invoice_view.php?id=${data.invoice_id}`, '_blank');
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to generate proforma invoice.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'An error occurred during communication with the server.', 'error');
        });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
