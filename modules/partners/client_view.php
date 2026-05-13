<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: clients.php"); exit; }

$client = $pdo->prepare("SELECT * FROM partners WHERE id = ? AND type = 'client'");
$client->execute([$id]);
$client = $client->fetch();

if (!$client) { echo "Client not found."; exit; }

$activePage = 'clients';
$pageTitle = 'Client Profile: ' . $client['name'];
include_once __DIR__ . '/../../includes/header.php';

// Fetch Campaign History
$campaigns = $pdo->prepare("SELECT * FROM campaigns WHERE client_id = ? ORDER BY id DESC");
$campaigns->execute([$id]);
$campaigns = $campaigns->fetchAll();

// Fetch Client POs
$clientPos = $pdo->prepare("SELECT * FROM client_pos WHERE client_id = ? ORDER BY id DESC");
$clientPos->execute([$id]);
$clientPos = $clientPos->fetchAll();

// Financial Summary (Invoiced vs Paid)
$totalInvoiced = $pdo->prepare("
    SELECT SUM(i.total_amount) 
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    JOIN proposals p ON b.proposal_id = p.id
    WHERE p.client_id = ?
");
$totalInvoiced->execute([$id]);
$totalInvoiced = $totalInvoiced->fetchColumn() ?: 0;

$totalPaid = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE partner_id = ? AND type = 'credit'");
$totalPaid->execute([$id]);
$totalPaid = $totalPaid->fetchColumn() ?: 0;
?>

<div style="display: grid; grid-template-columns: 1fr 2.5fr; gap: 2rem;">
    <!-- Profile Sidebar -->
    <div>
        <div class="card">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; background: #e0f2fe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--primary); font-size: 2rem;">
                    <i class="fas fa-building"></i>
                </div>
                <h2 style="font-size: 1.25rem;"><?php echo $client['name']; ?></h2>
                <span class="badge" style="background: <?php echo $client['status'] == 'active' ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $client['status'] == 'active' ? '#166534' : '#475569'; ?>; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem;">
                    <?php echo strtoupper($client['status']); ?>
                </span>
            </div>
            
            <div style="border-top: 1px solid #f1f5f9; padding-top: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block;">Contact Person</small>
                    <strong><?php echo $client['contact_person']; ?></strong>
                </div>
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block;">Email / Phone</small>
                    <div><?php echo $client['email']; ?></div>
                    <div><?php echo $client['phone']; ?></div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block;">GSTIN / PAN</small>
                    <code><?php echo $client['gstin'] ?: 'N/A'; ?></code><br>
                    <code><?php echo $client['pan'] ?: 'N/A'; ?></code>
                </div>
                <div style="margin-bottom: 1rem;">
                    <small style="color: var(--secondary); display: block;">Primary Address</small>
                    <p style="font-size: 0.85rem; color: #475569;"><?php echo $client['address']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="card" style="background: #1e293b; color: white;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;">Billing Overview</h3>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Total Invoiced</span>
                <strong><?php echo formatCurrency($totalInvoiced); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Total Paid</span>
                <strong style="color: #4ade80;"><?php echo formatCurrency($totalPaid); ?></strong>
            </div>
            <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; margin-top: 1rem; display: flex; justify-content: space-between;">
                <span>Outstanding</span>
                <strong style="font-size: 1.25rem; color: #fb7185;"><?php echo formatCurrency($totalInvoiced - $totalPaid); ?></strong>
            </div>
        </div>
    </div>

    <!-- Details Main -->
    <div>
        <div class="card" style="padding: 0;">
            <div style="display: flex; border-bottom: 1px solid #f1f5f9;">
                <button class="tab-btn active" onclick="showTab('camps-tab')">Campaign History (<?php echo count($campaigns); ?>)</button>
                <button class="tab-btn" onclick="showTab('pos-tab')">Authorized Client POs (<?php echo count($clientPos); ?>)</button>
            </div>
            
            <div id="camps-tab" class="tab-content" style="padding: 1.5rem;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Project ID</th>
                            <th>Campaign Name</th>
                            <th>Dates</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $c): ?>
                        <tr>
                            <td><strong><?php echo $c['project_id']; ?></strong></td>
                            <td><?php echo $c['display_name']; ?></td>
                            <td style="font-size: 0.8rem;"><?php echo date('M Y', strtotime($c['from_date'])); ?></td>
                            <td><?php echo formatCurrency($c['amount']); ?></td>
                            <td><span class="status-pill status-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($campaigns)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No campaigns found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="pos-tab" class="tab-content" style="padding: 1.5rem; display: none;">
                <div style="text-align: right; margin-bottom: 1rem;">
                    <button class="btn btn-primary" onclick="openPOModal()"><i class="fas fa-plus"></i> Upload Client PO</button>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Date</th>
                            <th>Booking Ref</th>
                            <th>Approval</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientPos as $cp): ?>
                        <tr>
                            <td><strong><?php echo $cp['po_number']; ?></strong></td>
                            <td><?php echo date('d M Y', strtotime($cp['po_date'])); ?></td>
                            <td>#<?php echo $cp['booking_id']; ?></td>
                            <td>
                                <span class="badge" style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">
                                    <?php echo strtoupper($cp['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="../../uploads/client_pos/<?php echo $cp['filename']; ?>" target="_blank" class="btn-icon"><i class="fas fa-download"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($clientPos)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No POs uploaded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Upload PO Modal -->
<div id="poModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <h3>Authorize Campaign (Client PO)</h3>
        <form id="clientPOForm" style="margin-top: 1.5rem;">
            <div class="form-group">
                <label>PO Number</label>
                <input type="text" id="po_no" class="p-input" required>
            </div>
            <div class="form-group">
                <label>PO Date</label>
                <input type="date" id="po_date" class="p-input" required>
            </div>
            <div class="form-group">
                <label>Select Booking</label>
                <select id="booking_id" class="p-input" required>
                    <!-- Bookings for this client should be loaded here -->
                    <?php 
                    $bookings = $pdo->prepare("
                        SELECT b.id 
                        FROM bookings b
                        JOIN proposals p ON b.proposal_id = p.id
                        WHERE p.client_id = ? 
                        ORDER BY b.id DESC
                    ");
                    $bookings->execute([$id]);
                    while($b = $bookings->fetch()) echo "<option value='{$b['id']}'>Booking #{$b['id']}</option>";
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Upload Document (PDF/Image)</label>
                <input type="file" id="po_file" class="p-input" required>
            </div>
            <div style="margin-top: 2rem; text-align: right;">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="uploadClientPO()">Upload & Authorize</button>
            </div>
        </form>
    </div>
</div>

<style>
.tab-btn { background: none; border: none; padding: 1rem 1.5rem; font-weight: 600; color: var(--secondary); cursor: pointer; border-bottom: 2px solid transparent; }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-content { animation: fadeIn 0.3s ease; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
.p-input { width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; margin-top: 0.25rem; }
</style>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    event.currentTarget.classList.add('active');
}
function openPOModal() { document.getElementById('poModal').style.display = 'flex'; }
function closeModal() { document.getElementById('poModal').style.display = 'none'; }

function uploadClientPO() {
    const file = document.getElementById('po_file').files[0];
    if(!file) return alert('Select a file');

    const formData = new FormData();
    formData.append('client_id', '<?php echo $id; ?>');
    formData.append('booking_id', document.getElementById('booking_id').value);
    formData.append('po_no', document.getElementById('po_no').value);
    formData.append('po_date', document.getElementById('po_date').value);
    formData.append('file', file);

    fetch('../../ajax/upload_client_po.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.success) {
            Swal.fire('Success', 'Client PO authorized and uploaded.', 'success').then(() => location.reload());
        } else {
            alert('Upload failed: ' + res.message);
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
