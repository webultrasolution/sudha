<?php
$activePage = 'activity_logs';
$pageTitle = 'System Activity Logs';
include_once __DIR__ . '/../../includes/header.php';

// Enforce Admin Role
requireRole('admin');

// Handle Clear Logs action (restricted to Admin)
if (isset($_POST['clear_logs'])) {
    $pdo->exec("TRUNCATE TABLE activity_log");
    logActivity('cleared all activity logs', 'system', 0, 'Admin cleared the entire activity log history.');
    header("Location: activity_logs.php?msg=cleared");
    exit;
}

// Fetch Filters
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$selectedUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$selectedEntity = isset($_GET['entity_type']) ? clean($_GET['entity_type']) : '';
$selectedDate = isset($_GET['date_filter']) ? clean($_GET['date_filter']) : '';

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(al.action LIKE ? OR al.description LIKE ? OR al.entity_type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($selectedUserId > 0) {
    $whereClauses[] = "al.user_id = ?";
    $params[] = $selectedUserId;
}

if ($selectedEntity !== '') {
    $whereClauses[] = "al.entity_type = ?";
    $params[] = $selectedEntity;
}

if ($selectedDate !== '') {
    $whereClauses[] = "DATE(al.created_at) = ?";
    $params[] = $selectedDate;
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Pagination logic
$limit = 50;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log al $whereSql");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch logs
$stmt = $pdo->prepare("
    SELECT al.*, u.username, COALESCE(u.name, u.full_name) as full_name, u.role
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    $whereSql
    ORDER BY al.id DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for filter dropdown
$usersList = $pdo->query("SELECT id, username, COALESCE(name, full_name) as full_name, role FROM users ORDER BY username ASC")->fetchAll();

// Fetch unique entities for filter dropdown
$entitiesList = $pdo->query("SELECT DISTINCT entity_type FROM activity_log WHERE entity_type IS NOT NULL AND entity_type != '' ORDER BY entity_type ASC")->fetchAll(PDO::FETCH_COLUMN);

// Stat Summary Queries
$totalLogsCount = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$todayLogsCount = $pdo->query("SELECT COUNT(*) FROM activity_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$uniqueUsersCount = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM activity_log")->fetchColumn();
?>

<!-- Premium Stats Summary Bar -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <div style="background: #e0f2fe; color: #0284c7; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
            <i class="fas fa-history"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Total Logs Recorded</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?php echo number_format($totalLogsCount); ?></div>
        </div>
    </div>
    
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <div style="background: #ecfdf5; color: #059669; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Today's Operations</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?php echo number_format($todayLogsCount); ?></div>
        </div>
    </div>
    
    <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <div style="background: #fdf2f8; color: #db2777; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
            <i class="fas fa-users"></i>
        </div>
        <div>
            <h4 style="margin: 0; color: #64748b; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Active Operatives</h4>
            <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?php echo number_format($uniqueUsersCount); ?></div>
        </div>
    </div>
</div>

<!-- Main Logs workspace Card -->
<div class="card" style="border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 2rem;">
    
    <!-- Title Area -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="color: #0f172a; margin: 0; display: inline-flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.5rem;">
                <i class="fas fa-fingerprint" style="color: var(--primary);"></i> Security & Activity Audits
            </h2>
            <p style="color: #64748b; margin: 0.25rem 0 0 0; font-size: 0.9rem;">Track adds, edits, approvals, and deletion logs system-wide.</p>
        </div>
        <form method="POST" id="clearLogsForm">
            <button type="button" onclick="confirmClearLogs()" class="btn btn-secondary" style="background: #fee2e2; color: #991b1b; border-color: #fca5a5; font-weight: 800; font-size: 0.85rem; border-radius: 8px; padding: 0.6rem 1.25rem;">
                <i class="fas fa-trash-alt"></i> Purge Logs Table
            </button>
            <input type="hidden" name="clear_logs" value="1">
        </form>
    </div>

    <!-- Filter Bar -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end; margin: 0;">
            <div>
                <label class="filter-lbl">Search Description / Action</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. approved, deleted..." class="filter-input">
            </div>
            <div>
                <label class="filter-lbl">Filter by User</label>
                <select name="user_id" class="filter-input">
                    <option value="">All Users</option>
                    <?php foreach ($usersList as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $selectedUserId === $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['username']); ?> (<?php echo htmlspecialchars($u['full_name'] ?: ucfirst($u['role'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="filter-lbl">Filter by Module/Entity</label>
                <select name="entity_type" class="filter-input">
                    <option value="">All Entities</option>
                    <?php foreach ($entitiesList as $ent): ?>
                        <option value="<?php echo $ent; ?>" <?php echo $selectedEntity === $ent ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $ent)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="filter-lbl">Operation Date</label>
                <input type="date" name="date_filter" value="<?php echo htmlspecialchars($selectedDate); ?>" class="filter-input">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="height: 40px; flex: 1; font-weight: 800; font-size: 0.85rem; border-radius: 8px; justify-content: center; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search !== '' || $selectedUserId > 0 || $selectedEntity !== '' || $selectedDate !== ''): ?>
                    <a href="activity_logs.php" class="btn btn-secondary" style="height: 40px; font-weight: 800; font-size: 0.85rem; border-radius: 8px; background: #e2e8f0; color: #475569; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                        <i class="fas fa-undo"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x: auto;">
        <table class="table matrix-table" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <thead>
                <tr>
                    <th style="width: 80px; text-align: center;">ID</th>
                    <th style="width: 220px; text-align: left;">Operative</th>
                    <th style="width: 180px; text-align: center;">Action Code</th>
                    <th style="width: 180px; text-align: center;">Reference Context</th>
                    <th style="text-align: left;">Detailed Audit Trail</th>
                    <th style="width: 180px; text-align: center;">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): 
                    // Set up high-contrast badges for operations
                    $actionType = strtolower($l['action']);
                    $actionBadge = 'badge-secondary';
                    if (strpos($actionType, 'delete') !== false || strpos($actionType, 'remove') !== false || strpos($actionType, 'cancel') !== false || strpos($actionType, 'purge') !== false) {
                        $actionBadge = 'badge-danger';
                    } elseif (strpos($actionType, 'add') !== false || strpos($actionType, 'create') !== false || strpos($actionType, 'insert') !== false) {
                        $actionBadge = 'badge-success';
                    } elseif (strpos($actionType, 'edit') !== false || strpos($actionType, 'update') !== false || strpos($actionType, 'modify') !== false) {
                        $actionBadge = 'badge-warning';
                    } elseif (strpos($actionType, 'approve') !== false || strpos($actionType, 'confirm') !== false || strpos($actionType, 'pay') !== false || strpos($actionType, 'paid') !== false) {
                        $actionBadge = 'badge-success-emerald';
                    }

                    // Render entity link context if applicable
                    $entityContext = '—';
                    if (!empty($l['entity_type'])) {
                        $entName = ucfirst(str_replace('_', ' ', $l['entity_type']));
                        if (!empty($l['entity_id'])) {
                            $targetUrl = '#';
                            if ($l['entity_type'] === 'purchase_orders') {
                                $targetUrl = "../financials/po_view.php?id=" . $l['entity_id'];
                            } elseif ($l['entity_type'] === 'bookings') {
                                $targetUrl = "../operations/view_booking.php?id=" . $l['entity_id'];
                            } elseif ($l['entity_type'] === 'proposals') {
                                $targetUrl = "../proposals/view.php?id=" . $l['entity_id'];
                            } elseif ($l['entity_type'] === 'partners') {
                                $targetUrl = "../partners/vendor_view.php?id=" . $l['entity_id'];
                            }

                            if ($targetUrl !== '#') {
                                $entityContext = "<a href='$targetUrl' class='entity-link' title='Click to View'><span class='ent-badge'>$entName #{$l['entity_id']}</span></a>";
                            } else {
                                $entityContext = "<span class='ent-badge'>$entName #{$l['entity_id']}</span>";
                            }
                        } else {
                            $entityContext = "<span class='ent-badge'>$entName</span>";
                        }
                    }
                ?>
                <tr>
                    <td style="text-align: center; color: #94a3b8; font-weight: 700;">#<?php echo $l['id']; ?></td>
                    <td style="text-align: left;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($l['username'] ?? 'S', 0, 2)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($l['full_name'] ?: ($l['username'] ?? 'System')); ?></div>
                                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8;"><?php echo htmlspecialchars($l['role'] ?? 'automator'); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="act-badge <?php echo $actionBadge; ?>"><?php echo strtoupper($l['action']); ?></span>
                    </td>
                    <td><?php echo $entityContext; ?></td>
                    <td style="text-align: left; color: #475569; font-weight: 500; font-size: 0.85rem; line-height: 1.4; word-break: break-word;">
                        <?php echo htmlspecialchars($l['description'] ?: 'No details recorded.'); ?>
                    </td>
                    <td style="color: #64748b; font-size: 0.8rem; font-weight: 600;">
                        <?php echo date('d M Y, h:i A', strtotime($l['created_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 3rem; color: #94a3b8;">
                        <i class="fas fa-search" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: #cbd5e1;"></i>
                        No audit trail entries matched the selected filters.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Premium Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; border-top: 1px solid #f1f5f9; padding-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">
            Showing records <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $totalRecords); ?></strong> out of <strong><?php echo $totalRecords; ?></strong> total entries.
        </div>
        <div class="pagination-bar" style="display: flex; gap: 0.25rem;">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&user_id=<?php echo $selectedUserId; ?>&entity_type=<?php echo urlencode($selectedEntity); ?>&date_filter=<?php echo urlencode($selectedDate); ?>" class="pag-btn"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($p = $startPage; $p <= $endPage; $p++): 
            ?>
                <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&user_id=<?php echo $selectedUserId; ?>&entity_type=<?php echo urlencode($selectedEntity); ?>&date_filter=<?php echo urlencode($selectedDate); ?>" class="pag-btn <?php echo $page === $p ? 'active' : ''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&user_id=<?php echo $selectedUserId; ?>&entity_type=<?php echo urlencode($selectedEntity); ?>&date_filter=<?php echo urlencode($selectedDate); ?>" class="pag-btn"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* Filters Labels & Inputs */
.filter-lbl { display: block; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.filter-input { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; font-weight: 600; background: white; outline: none; transition: border-color 0.2s; height: 40px; }
.filter-input:focus { border-color: var(--primary); }

/* Matrix Table override */
.matrix-table { width: 100%; border-collapse: collapse; }
.matrix-table th { background: #f8fafc; color: #475569; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem; border-bottom: 1.5px solid #e2e8f0; }
.matrix-table td { padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; background: #fff; text-align: center; }
.matrix-table tr:hover td { background: #f8fafc; }

/* User Initial Avatar Badge */
.user-avatar { width: 36px; height: 36px; border-radius: 50%; background: #e0f2fe; color: #0284c7; font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 2px solid #fff; box-shadow: 0 0 0 2px #e0f2fe; }

/* Dynamic Badge Colors */
.act-badge { padding: 0.25rem 0.625rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; }
.badge-secondary { background: #f1f5f9; color: #475569; }
.badge-success { background: #dcfce7; color: #15803d; }
.badge-warning { background: #fef3c7; color: #b45309; }
.badge-danger { background: #fee2e2; color: #b91c1c; }
.badge-success-emerald { background: #ecfdf5; color: #047857; }

/* Context Ent Link */
.ent-badge { padding: 0.25rem 0.5rem; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; font-weight: 700; color: #475569; }
.entity-link { text-decoration: none; }
.entity-link:hover .ent-badge { background: #e2e8f0; color: var(--primary); border-color: #cbd5e1; }

/* Pagination buttons styling */
.pag-btn { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #475569; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: all 0.2s; }
.pag-btn:hover { background: #f1f5f9; border-color: #cbd5e1; color: var(--primary); }
.pag-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }
</style>

<script>
function confirmClearLogs() {
    Swal.fire({
        title: 'Purge Activity Logs?',
        text: "Are you sure you want to delete all historical logs? This action is highly destructive and cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Purge everything!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('clearLogsForm').submit();
        }
    });
}

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'cleared'): ?>
    Swal.fire({
        icon: 'success',
        title: 'Logs Purged!',
        text: 'Activity history table has been cleared.',
        timer: 1800,
        showConfirmButton: false
    });
<?php endif; ?>
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
