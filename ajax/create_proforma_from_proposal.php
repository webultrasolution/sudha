<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Enforce permissions for financials/proposals
if (!canAdd('financials') && !canAdd('proposals')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
    exit;
}

$proposalId = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;

if (!$proposalId) {
    echo json_encode(['success' => false, 'message' => 'Invalid Proposal ID.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch Proposal Data
    $stmtP = $pdo->prepare("SELECT * FROM proposals WHERE id = ?");
    $stmtP->execute([$proposalId]);
    $proposal = $stmtP->fetch();

    if (!$proposal) {
        throw new Exception("Proposal not found.");
    }

    // 2. Check if a Booking already exists for this proposal
    $stmtB = $pdo->prepare("SELECT id FROM bookings WHERE proposal_id = ?");
    $stmtB->execute([$proposalId]);
    $bookingId = $stmtB->fetchColumn();

    if (!$bookingId) {
        // Auto-confirm the proposal and create booking
        $stmtUpdate = $pdo->prepare("UPDATE proposals SET status = 'confirmed' WHERE id = ?");
        $stmtUpdate->execute([$proposalId]);

        $stmtBooking = $pdo->prepare("
            INSERT INTO bookings (proposal_id, client_id, billing_gstin, start_date, end_date, total_amount, tax_amount, grand_total, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmtBooking->execute([
            $proposalId,
            $proposal['client_id'],
            $proposal['billing_gstin'],
            $proposal['start_date'],
            $proposal['end_date'],
            $proposal['total_amount'],
            $proposal['tax_amount'],
            $proposal['grand_total']
        ]);
        $bookingId = $pdo->lastInsertId();

        // Fetch all proposal items and copy to booking items
        $stmtItems = $pdo->prepare("SELECT * FROM proposal_items WHERE proposal_id = ?");
        $stmtItems->execute([$proposalId]);
        $items = $stmtItems->fetchAll();

        $stmtBI = $pdo->prepare("
            INSERT INTO booking_items (booking_id, proposal_item_id, site_id, purchase_rate, sale_rate, start_date, end_date, days, purchase_amount, amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");

        foreach ($items as $item) {
            $itemStartDate = (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00') ? $item['start_date'] : $proposal['start_date'];
            $itemEndDate = (!empty($item['end_date']) && $item['end_date'] !== '0000-00-00') ? $item['end_date'] : $proposal['end_date'];

            $stmtBI->execute([
                $bookingId,
                $item['id'],
                $item['site_id'],
                $item['purchase_rate'],
                $item['sale_rate'],
                $itemStartDate,
                $itemEndDate,
                $item['days'],
                0,
                $item['amount']
            ]);

            $stmtOps->execute([$bookingId, $item['site_id']]);
        }

        logActivity('converted proposal to booking', 'bookings', $bookingId, "Booking Auto-Created for Proforma Invoice from Proposal ID: $proposalId");
    }

    // 3. Check if a Proforma Invoice already exists for this Booking
    $stmtCheckInv = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ? AND type = 'proforma'");
    $stmtCheckInv->execute([$bookingId]);
    $invoiceId = $stmtCheckInv->fetchColumn();

    if (!$invoiceId) {
        // Generate a new Proforma Invoice
        $invNum = 'PI-' . date('Ymd') . '-' . rand(100, 999);
        
        $cgst = $proposal['tax_amount'] / 2;
        $sgst = $proposal['tax_amount'] / 2;
        $igst = 0;

        $stmtInv = $pdo->prepare("
            INSERT INTO invoices (invoice_number, booking_id, type, sub_total, cgst, sgst, igst, total_amount, payment_status) 
            VALUES (?, ?, 'proforma', ?, ?, ?, ?, ?, 'unpaid')
        ");
        $stmtInv->execute([
            $invNum,
            $bookingId,
            $proposal['total_amount'],
            $cgst,
            $sgst,
            $igst,
            $proposal['grand_total']
        ]);
        $invoiceId = $pdo->lastInsertId();

        logActivity('generated proforma invoice', 'invoices', $invoiceId, "Proforma Invoice #$invNum generated for Proposal ID: $proposalId");
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'invoice_id' => $invoiceId,
        'message' => 'Proforma Invoice generated successfully!'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
