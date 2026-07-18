<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

checkAuth();
requireRole('admin');

// Self-healing database schema: ensure status column exists in production DB
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM contact_leads LIKE 'status'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE contact_leads ADD COLUMN status VARCHAR(50) DEFAULT 'pending' AFTER message");
    }
} catch (Exception $e) {
    error_log("Failed to self-heal schema for contact_leads status: " . $e->getMessage());
}

$activePage = 'enquiries';
$pageTitle = 'Enquiries / Leads';

$message = '';
$error = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $leadId = intval($_POST['lead_id']);
        $newStatus = clean($_POST['status'] ?? '');
        
        $validStatuses = ['pending', 'contacted', 'closed'];
        if (in_array($newStatus, $validStatuses)) {
            try {
                $stmt = $pdo->prepare("UPDATE contact_leads SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $leadId]);
                logActivity('update_enquiry_status', 'contact_leads', $leadId, "Status updated to " . ucwords($newStatus));
                $_SESSION['flash_msg'] = "Enquiry status updated successfully!";
                header("Location: enquiries.php" . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
                exit;
            } catch (Exception $e) {
                $error = "Failed to update status: " . $e->getMessage();
            }
        } else {
            $error = "Invalid status specified.";
        }
    }
    
    // Handle Delete
    if ($_POST['action'] === 'delete_lead' && hasRole('admin')) {
        $leadId = intval($_POST['lead_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM contact_leads WHERE id = ?");
            $stmt->execute([$leadId]);
            logActivity('delete_enquiry', 'contact_leads', $leadId, "Deleted enquiry ID: $leadId");
            $_SESSION['flash_msg'] = "Enquiry deleted permanently!";
            header("Location: enquiries.php" . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
            exit;
        } catch (Exception $e) {
            $error = "Failed to delete lead: " . $e->getMessage();
        }
    }
}

// Retrieve flash message if exists
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// Stats summary counts
$totalLeads = $pdo->query("SELECT COUNT(*) FROM contact_leads")->fetchColumn();
$pendingLeads = $pdo->query("SELECT COUNT(*) FROM contact_leads WHERE status = 'pending'")->fetchColumn();
$contactedLeads = $pdo->query("SELECT COUNT(*) FROM contact_leads WHERE status = 'contacted'")->fetchColumn();
$closedLeads = $pdo->query("SELECT COUNT(*) FROM contact_leads WHERE status = 'closed'")->fetchColumn();

// Handle Filter and Pagination
$filter = isset($_GET['filter']) ? clean($_GET['filter']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$params = [];
$countSql = "SELECT COUNT(*) FROM contact_leads";
$selectSql = "SELECT * FROM contact_leads";

if ($filter !== 'all' && in_array($filter, ['pending', 'contacted', 'closed'])) {
    $countSql .= " WHERE status = ?";
    $selectSql .= " WHERE status = ?";
    $params[] = $filter;
}

$selectSql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

// Get total count for page calculations
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$filteredCount = $stmtCount->fetchColumn();
$totalPages = ceil($filteredCount / $limit);

// Fetch leads
$stmtLeads = $pdo->prepare($selectSql);
$stmtLeads->execute($params);
$leads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

include_once __DIR__ . '/../../includes/header.php';
?>

<div style="max-width: 1200px; margin: 0 auto;">
    
    <!-- Top Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">Website Enquiries & Leads</h1>
            <p style="color: #64748b; margin-top: 0.25rem;">Monitor and manage prospective client enquiries received from the main website.</p>
        </div>
        
        <?php if ($message): ?>
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; border: 1px solid #bbf7d0;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; border: 1px solid #fca5a5;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
        <div class="stat-card" style="border-left: 5px solid #64748b;">
            <div class="stat-icon" style="background: #f1f5f9; color: #64748b;"><i class="fas fa-list"></i></div>
            <div class="stat-details">
                <h3><?php echo $totalLeads; ?></h3>
                <p>Total Enquiries</p>
            </div>
        </div>
        <div class="stat-card" style="border-left: 5px solid #f59e0b;">
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;"><i class="fas fa-clock"></i></div>
            <div class="stat-details">
                <h3><?php echo $pendingLeads; ?></h3>
                <p>Pending Leads</p>
            </div>
        </div>
        <div class="stat-card" style="border-left: 5px solid #3b82f6;">
            <div class="stat-icon" style="background: #dbeafe; color: #2563eb;"><i class="fas fa-phone"></i></div>
            <div class="stat-details">
                <h3><?php echo $contactedLeads; ?></h3>
                <p>Contacted Leads</p>
            </div>
        </div>
        <div class="stat-card" style="border-left: 5px solid #10b981;">
            <div class="stat-icon" style="background: #d1fae5; color: #059669;"><i class="fas fa-check-double"></i></div>
            <div class="stat-details">
                <h3><?php echo $closedLeads; ?></h3>
                <p>Closed Leads</p>
            </div>
        </div>
    </div>

    <!-- Table & Filters Section -->
    <div class="card" style="padding: 2rem; border-radius: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1.25rem;">
            
            <!-- Filters -->
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?filter=pending" class="filter-tab filter-pending <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?filter=contacted" class="filter-tab filter-contacted <?php echo $filter === 'contacted' ? 'active' : ''; ?>">Contacted</a>
                <a href="?filter=closed" class="filter-tab filter-closed <?php echo $filter === 'closed' ? 'active' : ''; ?>">Closed</a>
            </div>
            
            <span style="font-size: 0.9rem; color: #64748b; font-weight: 600;">Showing <?php echo count($leads); ?> of <?php echo $filteredCount; ?> Entries</span>
        </div>

        <?php if (empty($leads)): ?>
            <div style="text-align: center; padding: 4rem 2rem; color: #94a3b8;">
                <i class="fas fa-envelope-open" style="font-size: 3rem; margin-bottom: 1rem; color: #cbd5e1;"></i>
                <h4 style="margin: 0; color: #64748b;">No enquiries found</h4>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem;">Leads submitted from the website form will appear here.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr>
                            <th style="padding: 1rem; font-weight: 700; color: #475569; border-bottom: 2px solid #f1f5f9;">Contact Name</th>
                            <th style="padding: 1rem; font-weight: 700; color: #475569; border-bottom: 2px solid #f1f5f9;">Subject</th>
                            <th style="padding: 1rem; font-weight: 700; color: #475569; border-bottom: 2px solid #f1f5f9;">Message</th>
                            <th style="padding: 1rem; font-weight: 700; color: #475569; border-bottom: 2px solid #f1f5f9;">Received Date</th>
                            <th style="padding: 1rem; font-weight: 700; color: #475569; border-bottom: 2px solid #f1f5f9;">Status</th>
                            <th style="padding: 1rem; font-weight: 700; color: #475569; border-bottom: 2px solid #f1f5f9; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr class="lead-row" style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 1.25rem 1rem; vertical-align: middle;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.98rem;"><?php echo htmlspecialchars($lead['name']); ?></div>
                                    <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.2rem;">
                                        <i class="fas fa-envelope" style="width: 16px;"></i> <?php echo htmlspecialchars($lead['email']); ?>
                                    </div>
                                    <?php if (!empty($lead['phone'])): ?>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.1rem;">
                                            <i class="fas fa-phone" style="width: 16px;"></i> <?php echo htmlspecialchars($lead['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="padding: 1.25rem 1rem; vertical-align: middle; max-width: 200px; font-weight: 600; color: #334155;">
                                    <?php echo htmlspecialchars($lead['subject'] ?: '(No Subject)'); ?>
                                </td>
                                
                                <td style="padding: 1.25rem 1rem; vertical-align: middle; max-width: 250px; color: #475569; font-size: 0.92rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                    <?php echo htmlspecialchars($lead['message']); ?>
                                </td>
                                
                                <td style="padding: 1.25rem 1rem; vertical-align: middle; color: #64748b; font-size: 0.9rem;">
                                    <?php echo date('M d, Y h:i A', strtotime($lead['created_at'])); ?>
                                </td>
                                
                                <td style="padding: 1.25rem 1rem; vertical-align: middle;">
                                    <form method="POST" style="margin: 0; display: inline-flex; align-items: center; gap: 0.5rem;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                                        
                                        <!-- Inline status select -->
                                        <select name="status" onchange="this.form.submit()" class="status-select status-<?php echo $lead['status']; ?>">
                                            <option value="pending" <?php echo $lead['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                            <option value="closed" <?php echo $lead['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                    </form>
                                </td>
                                
                                <td style="padding: 1.25rem 1rem; vertical-align: middle; text-align: center;">
                                    <div style="display: inline-flex; gap: 0.5rem;">
                                        <button class="btn-action-view" onclick="viewLeadDetails(<?php echo htmlspecialchars(json_encode($lead)); ?>)" title="View Full Message">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <?php if (hasRole('admin')): ?>
                                            <button class="btn-action-delete" onclick="deleteLead(<?php echo $lead['id']; ?>)" title="Delete Lead">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; margin-top: 2rem;">
                    <?php echo renderPagination($page, $totalPages, 'enquiries.php', 'page', ['filter' => $filter]); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_lead">
    <input type="hidden" name="lead_id" id="deleteLeadId" value="">
</form>

<script>
// SweetAlert details pop-up
function viewLeadDetails(lead) {
    let dateObj = new Date(lead.created_at);
    let dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    Swal.fire({
        title: '<div style="font-family: \'Outfit\', sans-serif; font-weight: 800; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem; text-align: left; font-size: 1.4rem; color: #0f172a;"><i class="fas fa-envelope-open" style="color: #7b161c; margin-right: 8px;"></i> Enquiry Details</div>',
        html: `
            <div style="text-align: left; font-family: 'Inter', sans-serif; font-size: 0.95rem; line-height: 1.6; color: #334155; margin-top: 1rem;">
                <div style="display: grid; grid-template-columns: 80px 1fr; gap: 0.5rem; margin-bottom: 1.25rem; background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <strong>Name:</strong> <div>\${escapeHtml(lead.name)}</div>
                    <strong>Email:</strong> <div><a href="mailto:\${lead.email}" style="color: #7b161c; font-weight: 600; text-decoration: none;">\${escapeHtml(lead.email)}</a></div>
                    \${lead.phone ? `<strong>Phone:</strong> <div><a href="tel:\${lead.phone}" style="color: #7b161c; font-weight: 600; text-decoration: none;">\${escapeHtml(lead.phone)}</a></div>` : ''}
                    <strong>Date:</strong> <div>\${dateStr}</div>
                    <strong>Status:</strong> <div style="text-transform: capitalize;">\${lead.status}</div>
                </div>
                
                <div style="margin-bottom: 0.5rem; font-weight: 800; color: #0f172a;">Subject:</div>
                <div style="background: #f1f5f9; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 600; margin-bottom: 1.25rem;">
                    \${escapeHtml(lead.subject || '(No Subject)')}
                </div>
                
                <div style="margin-bottom: 0.5rem; font-weight: 800; color: #0f172a;">Message:</div>
                <div style="background: #f8fafc; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; max-height: 250px; overflow-y: auto; white-space: pre-wrap; font-style: italic;">
                    \${escapeHtml(lead.message)}
                </div>
            </div>
        `,
        width: '600px',
        confirmButtonColor: '#7b161c',
        confirmButtonText: 'Close Window',
        customClass: {
            popup: 'lead-details-modal'
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function deleteLead(id) {
    Swal.fire({
        title: 'Delete Enquiry?',
        text: 'This enquiry will be permanently removed from the records. This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete permanently',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteLeadId').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}
</script>

<style>
/* Stat Cards styling */
.stat-card {
    background: #fff;
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 1.25rem;
    border: 1px solid #f1f5f9;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.stat-details h3 {
    font-size: 1.5rem;
    margin: 0;
    font-weight: 800;
    color: #0f172a;
}
.stat-details p {
    margin: 0;
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filters Tabs styling */
.filter-tab {
    padding: 0.5rem 1.25rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.88rem;
    color: #64748b;
    background: #f1f5f9;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}
.filter-tab:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.filter-tab.active {
    background: #0f172a;
    color: #fff;
}
.filter-pending.active {
    background: #fef3c7;
    color: #d97706;
    border-color: #fde68a;
}
.filter-contacted.active {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.filter-closed.active {
    background: #d1fae5;
    color: #047857;
    border-color: #a7f3d0;
}

/* Status selection box styling */
.status-select {
    padding: 0.4rem 0.8rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 700;
    border: 1px solid #cbd5e1;
    cursor: pointer;
    outline: none;
    transition: all 0.2s;
    background-position: right 8px center;
}
.status-select.status-pending {
    background-color: #fffbeb;
    color: #d97706;
    border-color: #fde68a;
}
.status-select.status-contacted {
    background-color: #eff6ff;
    color: #2563eb;
    border-color: #bfdbfe;
}
.status-select.status-closed {
    background-color: #ecfdf5;
    color: #059669;
    border-color: #a7f3d0;
}

/* Action button styles */
.btn-action-view {
    padding: 0.45rem 1rem;
    border-radius: 8px;
    background: #f8fafc;
    color: #334155;
    border: 1px solid #e2e8f0;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}
.btn-action-view:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}
.btn-action-delete {
    padding: 0.45rem 0.6rem;
    border-radius: 8px;
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fee2e2;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-action-delete:hover {
    background: #fee2e2;
    border-color: #fca5a5;
}

/* Hover effects */
.lead-row {
    transition: background-color 0.15s;
}
.lead-row:hover {
    background-color: #fafbfc;
}
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
