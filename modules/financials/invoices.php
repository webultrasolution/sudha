<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    include_once __DIR__ . '/../../config/db.php';
    include_once __DIR__ . '/../../includes/functions.php';
    include_once __DIR__ . '/../../includes/trash_helper.php';
    checkAuth();
    requirePermission('financials', 'delete');
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    try {
        $pdo->beginTransaction();
        
        // Find the booking ID and invoice number associated with this invoice
        $stmt = $pdo->prepare("SELECT booking_id, invoice_number FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $invoiceData = $stmt->fetch();
        
        if ($invoiceData) {
            $bookingId = $invoiceData['booking_id'];
            $invoiceNumber = $invoiceData['invoice_number'];

            // Move invoice items to trash first so they can be restored with the invoice
            $itemStmt = $pdo->prepare("SELECT id FROM invoice_items WHERE invoice_id = ?");
            $itemStmt->execute([$id]);
            while ($item = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
                move_row_to_trash($pdo, 'invoice_items', 'id', $item['id'], $_SESSION['user_id'] ?? null, 'Invoice deleted - item moved to trash');
            }
            
            // Move the invoice itself to trash
            move_row_to_trash($pdo, 'invoices', 'id', $id, $_SESSION['user_id'] ?? null, 'Invoice deleted via invoice UI');
            
            logActivity('deleted invoice', 'financials', $id, "Invoice $invoiceNumber was deleted. Booking #$bookingId reverted to editable state.");
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$activePage = 'invoices';
$pageTitle = 'Financials - Invoices';
include_once __DIR__ . '/../../includes/header.php';

// Enforce View Permission at Page Level
requirePermission('financials', 'view');

// Pagination Logic
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalInvoices = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
$totalPages = ceil($totalInvoices / $limit);

$invoices = $pdo->prepare("
    SELECT i.*, b.id as booking_id, c.name as client_name 
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN proposals p ON b.proposal_id = p.id
    JOIN partners c ON b.client_id = c.id
    ORDER BY i.id DESC
    LIMIT ? OFFSET ?
");
$invoices->execute([$limit, $offset]);
$invoices = $invoices->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;"><i class="fas fa-file-invoice-dollar"></i> Tax Invoices & Receivables</h2>
        <?php if (canView('inventory')): ?>
        <a href="../admin/trash.php" class="btn btn-warning" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; text-decoration: none;">Trash</a>
        <?php endif; ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Invoice Details</th>
                <th>Client / Booking</th>
                <th>Subtotal / Tax</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--secondary); padding: 2rem;">No invoices generated yet.</td>
                </tr>
            <?php else: ?>
                <?php 
                $sn = $offset + 1;
                foreach ($invoices as $i): ?>
                <tr>
                    <td>
                        <div style="font-weight: 700; color: var(--primary);"><?php echo $i['invoice_number']; ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo date('d M Y', strtotime($i['created_at'] ?? date('Y-m-d'))); ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #334155;"><?php echo $i['client_name']; ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">Booking: #BK-<?php echo str_pad($i['booking_id'], 4, '0', STR_PAD_LEFT); ?></div>
                    </td>
                    <td>
                        <div style="font-size: 0.85rem; color: #475569;">Sub: <?php echo formatCurrency($i['sub_total']); ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">GST (18%): <?php echo formatCurrency($i['cgst'] + $i['sgst'] + $i['igst']); ?></div>
                    </td>
                    <td><strong style="font-size: 1rem; color: #1e293b;"><?php echo formatCurrency($i['total_amount']); ?></strong></td>
                    <td>
                        <span class="pay-status pay-<?php echo $i['payment_status']; ?>" style="padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 800;">
                            <?php echo str_replace('_', ' ', ucfirst($i['payment_status'])); ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <a href="../operations/generate_invoice.php?booking_id=<?php echo $i['booking_id']; ?>" target="_blank" class="btn-icon" title="View & Print" style="color: #64748b;"><i class="fas fa-file-invoice"></i></a>
                        <button class="btn-icon" title="Email Invoice" style="color: var(--primary);"><i class="fas fa-paper-plane"></i></button>
                        <?php if (canDelete('financials')): ?>
                        <button class="btn-icon" title="Delete Invoice" onclick="deleteInvoice(<?php echo $i['id']; ?>)" style="color: #ef4444;"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php echo renderPagination($page, $totalPages, 'invoices.php'); ?>
</div>

<style>
.pay-status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.pay-unpaid { background: #fee2e2; color: #991b1b; }
.pay-partially_paid { background: #fef9c3; color: #854d0e; }
.pay-paid { background: #dcfce7; color: #166534; }
.btn-icon { color: var(--secondary); border: none; background: none; cursor: pointer; margin-right: 0.5rem; }
.btn-icon:hover { color: var(--primary); }
</style>

<script>
function deleteInvoice(id) {
    Swal.fire({
        title: 'Delete Invoice?',
        text: "This will remove the invoice and its ledger entry, but the Booking will remain and can be edited/re-invoiced.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('invoices.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    Swal.fire('Deleted!', 'Invoice has been deleted and Booking reverted.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
