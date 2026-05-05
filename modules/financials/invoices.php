<?php
$activePage = 'invoices';
$pageTitle = 'Financials - Invoices';
include_once __DIR__ . '/../../includes/header.php';

// Check RBAC
if (!hasRole(['admin', 'accounts'])) {
    echo "<div class='card' style='color: var(--danger);'><i class='fas fa-exclamation-triangle'></i> Access Denied. Only Admin and Accounts can view financial modules.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

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
    JOIN proposals p ON b.proposal_id = p.id
    JOIN partners c ON p.client_id = c.id
    ORDER BY i.id DESC
    LIMIT ? OFFSET ?
");
$invoices->execute([$limit, $offset]);
$invoices = $invoices->fetchAll();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;"><i class="fas fa-file-invoice-dollar"></i> Tax Invoices & Receivables</h2>
        <a href="invoice_create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Generate New Invoice
        </a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Invoice #</th>
                <th>Booking #</th>
                <th>Client</th>
                <th>Subtotal</th>
                <th>GST</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
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
                    <td><?php echo $sn++; ?></td>
                    <td><strong><?php echo $i['invoice_number']; ?></strong></td>
                    <td>#BK-<?php echo str_pad($i['booking_id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo $i['client_name']; ?></td>
                    <td><?php echo formatCurrency($i['sub_total']); ?></td>
                    <td><?php echo formatCurrency($i['cgst'] + $i['sgst'] + $i['igst']); ?></td>
                    <td><strong><?php echo formatCurrency($i['total_amount']); ?></strong></td>
                    <td>
                        <span class="pay-status pay-<?php echo $i['payment_status']; ?>">
                            <?php echo str_replace('_', ' ', ucfirst($i['payment_status'])); ?>
                        </span>
                    </td>
                    <td>
                        <a href="invoice_view.php?id=<?php echo $i['id']; ?>" class="btn-icon" title="View"><i class="fas fa-eye"></i></a>
                        <button class="btn-icon" title="Download PDF"><i class="fas fa-download"></i></button>
                        <button class="btn-icon" title="Email"><i class="fas fa-envelope"></i></button>
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

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
