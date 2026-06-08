<?php
$activePage = 'invoices';
$pageTitle = 'Generate Tax Invoice';
include_once __DIR__ . '/../../includes/header.php';

// Enforce Add Permission at Page Level
requirePermission('financials', 'add');

// Fetch Confirmed Bookings that don't have a final tax invoice yet
$bookings = $pdo->query("
    SELECT b.id, p.proposal_number, c.name as client_name, p.grand_total, p.total_amount, p.tax_amount
    FROM bookings b
    JOIN proposals p ON b.proposal_id = p.id
    JOIN partners c ON p.client_id = c.id
    WHERE b.status != 'completed' 
    ORDER BY b.id DESC
")->fetchAll();

$entities = $pdo->query("SELECT id, name FROM entities ORDER BY name ASC")->fetchAll();
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <h2 style="font-size: 1.25rem; margin-bottom: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;">Invoice Generation Wizard</h2>
    
    <form id="invoiceForm">
        <div class="form-group">
            <label>1. Select Booking / Campaign</label>
            <select id="booking_id" class="form-control" onchange="updateDetails(this)" required>
                <option value="">-- Select Active Booking --</option>
                <?php foreach ($bookings as $b): ?>
                    <option value="<?php echo $b['id']; ?>" 
                            data-subtotal="<?php echo $b['total_amount']; ?>" 
                            data-tax="<?php echo $b['tax_amount']; ?>" 
                            data-total="<?php echo $b['grand_total']; ?>">
                        #BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?> | <?php echo $b['client_name']; ?> (<?php echo $b['proposal_number']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
            <div class="form-group">
                <label>2. Invoice Type</label>
                <select id="invoice_type" class="form-control">
                    <option value="tax">Tax Invoice (Final)</option>
                    <option value="proforma">Proforma Invoice</option>
                    <option value="estimate">Estimate / Quote</option>
                </select>
            </div>
            <div class="form-group">
                <label>3. Invoice Date</label>
                <input type="date" id="invoice_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>4. Billing Entity</label>
                <select id="entity_id" class="form-control">
                    <option value="">-- Default Company Settings --</option>
                    <?php foreach ($entities as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo (($_SESSION['active_entity_id'] ?? 0) == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="card" style="background: #f8fafc; margin-top: 1.5rem; border: 1px dashed #cbd5e1;">
            <h3 style="font-size: 0.875rem; margin-bottom: 1rem; color: var(--secondary);">Financial Summary (Auto-Populated)</h3>
            <div style="display: grid; gap: 0.75rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Subtotal:</span>
                    <strong id="disp-subtotal">₹0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Tax (GST 18%):</span>
                    <strong id="disp-tax">₹0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.125rem; border-top: 1px solid #e2e8f0; padding-top: 0.75rem;">
                    <span>Grand Total:</span>
                    <strong id="disp-total" style="color: var(--primary);">₹0.00</strong>
                </div>
            </div>
        </div>

        <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem;">
            <a href="invoices.php" class="btn">Cancel</a>
            <button type="button" class="btn btn-primary" onclick="saveInvoice()">
                <i class="fas fa-file-signature"></i> Generate & Save Invoice
            </button>
        </div>
    </form>
</div>

<script>
function updateDetails(select) {
    const option = select.options[select.selectedIndex];
    if (!option.value) {
        reset();
        return;
    }
    document.getElementById('disp-subtotal').innerText = '₹' + parseFloat(option.dataset.subtotal).toLocaleString();
    document.getElementById('disp-tax').innerText = '₹' + parseFloat(option.dataset.tax).toLocaleString();
    document.getElementById('disp-total').innerText = '₹' + parseFloat(option.dataset.total).toLocaleString();
}

function reset() {
    document.getElementById('disp-subtotal').innerText = '₹0.00';
    document.getElementById('disp-tax').innerText = '₹0.00';
    document.getElementById('disp-total').innerText = '₹0.00';
}

function saveInvoice() {
    const bookingId = document.getElementById('booking_id').value;
    const type = document.getElementById('invoice_type').value;
    
    if (!bookingId) {
        alert('Please select a booking.');
        return;
    }

    const data = {
        booking_id: bookingId,
        type: type,
        date: document.getElementById('invoice_date').value,
        entity_id: document.getElementById('entity_id').value
    };

    fetch('../../ajax/save_invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Invoice #' + result.invoice_number + ' generated successfully!');
            window.location.href = '<?php echo BASE_URL; ?>modules/financials/invoices.php';
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred.');
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
