<?php
$activePage = 'ledger';
$pageTitle = 'Financials - Account Ledgers';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/header.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'client';

// Fetch Partners based on type
$stmt = $pdo->prepare("SELECT id, name, city, gstin FROM partners WHERE type = ? ORDER BY name ASC");
$stmt->execute([$type]);
$partners = $stmt->fetchAll();
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin: 0;">Account Ledgers</h1>
    <p style="color: #64748b; margin-top: 0.25rem;">Comprehensive financial statement tracking for all partners.</p>
</div>

<div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
    <a href="?type=client" class="btn" style="flex: 1; text-align: center; padding: 1rem; border-radius: 12px; font-weight: 700; text-decoration: none; border: 2px solid <?php echo $type == 'client' ? 'var(--primary)' : '#e2e8f0'; ?>; background: <?php echo $type == 'client' ? '#f0fdfa' : 'white'; ?>; color: <?php echo $type == 'client' ? 'var(--primary)' : '#64748b'; ?>;">
        <i class="fas fa-user-tie"></i> CLIENT ACCOUNTS (RECEIVABLES)
    </a>
    <a href="?type=vendor" class="btn" style="flex: 1; text-align: center; padding: 1rem; border-radius: 12px; font-weight: 700; text-decoration: none; border: 2px solid <?php echo $type == 'vendor' ? 'var(--primary)' : '#e2e8f0'; ?>; background: <?php echo $type == 'vendor' ? '#f0fdfa' : 'white'; ?>; color: <?php echo $type == 'vendor' ? 'var(--primary)' : '#64748b'; ?>;">
        <i class="fas fa-truck-loading"></i> VENDOR ACCOUNTS (PAYABLES)
    </a>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Name</th>
                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Location</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Total Billed</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Total Paid</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Net Balance</th>
                <th style="padding: 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 800;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($partners as $p): 
                if ($type == 'client') {
                    // Total Billed to Client (Invoices)
                    $stmtBilled = $pdo->prepare("SELECT SUM(i.total_amount) FROM invoices i JOIN bookings b ON i.booking_id = b.id WHERE b.client_id = ?");
                    $stmtBilled->execute([$p['id']]);
                    $totalBilled = $stmtBilled->fetchColumn() ?: 0;

                    // Total Paid by Client (Receivables)
                    $stmtPaid = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE entity_id = ? AND type = 'receivable'");
                    $stmtPaid->execute([$p['id']]);
                    $totalPaid = $stmtPaid->fetchColumn() ?: 0;
                } else {
                    // Total Billed by Vendor (Purchase Orders)
                    $stmtBilled = $pdo->prepare("SELECT SUM(total_amount) FROM purchase_orders WHERE vendor_id = ?");
                    $stmtBilled->execute([$p['id']]);
                    $totalBilled = $stmtBilled->fetchColumn() ?: 0;

                    // Total Paid to Vendor (Payables)
                    $stmtPaid = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE entity_id = ? AND type = 'payable'");
                    $stmtPaid->execute([$p['id']]);
                    $totalPaid = $stmtPaid->fetchColumn() ?: 0;
                }

                $balance = $totalBilled - $totalPaid;
            ?>
            <tr style="background: #fff; border-bottom: 1px solid #f1f5f9; hover: background: #f8fafc;">
                <td style="padding: 1rem;">
                    <div style="font-weight: 700; color: #0f172a;"><?php echo $p['name']; ?></div>
                    <div style="font-size: 0.7rem; color: #94a3b8; font-family: monospace;"><?php echo $p['gstin'] ?: 'NO-GST'; ?></div>
                </td>
                <td style="padding: 1rem; color: #64748b; font-weight: 500;"><?php echo $p['city']; ?></td>
                <td style="padding: 1rem; text-align: right; font-weight: 600; color: #475569;"><?php echo formatCurrency($totalBilled); ?></td>
                <td style="padding: 1rem; text-align: right; font-weight: 600; color: #10b981;"><?php echo formatCurrency($totalPaid); ?></td>
                <td style="padding: 1rem; text-align: right; font-weight: 800; color: <?php echo $balance > 0 ? '#e11d48' : '#059669'; ?>;">
                    <?php echo formatCurrency(abs($balance)); ?> <?php echo $balance > 0 ? ($type == 'client' ? 'DUE' : 'PAYABLE') : 'ADV'; ?>
                </td>
                <td style="padding: 1rem; text-align: right;">
                    <a href="client_ledger.php?client_id=<?php echo $p['id']; ?>" class="btn-sm" style="text-decoration: none; background: #0f172a; color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.7rem; font-weight: 700;">
                        OPEN STATEMENT
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($partners)): ?>
                <tr>
                    <td colspan="6" style="padding: 3rem; text-align: center; color: #94a3b8; font-weight: 500;">No <?php echo $type; ?> records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
