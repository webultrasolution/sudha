<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

    if (!$isAdmin && !canEdit('bookings') && !canAdd('financials')) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to edit bookings.']);
        exit;
    }
    
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $billing_gstin = strtoupper(trim($_POST['billing_gstin'] ?? ''));
    
    if (!$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID provided.']);
        exit;
    }

    // Determine state and tax type based on GSTIN
    $isWestBengal = true;
    if (!empty($billing_gstin)) {
        $stateCode = substr($billing_gstin, 0, 2);
        $isWestBengal = ($stateCode === '19');
    } else {
        // Fallback to client state
        $stmtClient = $pdo->prepare("SELECT c.state FROM bookings b JOIN partners c ON b.client_id = c.id WHERE b.id = ?");
        $stmtClient->execute([$booking_id]);
        $cState = strtolower(trim($stmtClient->fetchColumn() ?? ''));
        $isWestBengal = empty($cState) || ($cState === 'west bengal') || ($cState === 'wb');
    }

    $taxType = $isWestBengal ? 'cgst_sgst' : 'igst';

    $stmt = $pdo->prepare("UPDATE bookings SET billing_gstin = ?, tax_type = ? WHERE id = ?");
    if ($stmt->execute([$billing_gstin, $taxType, $booking_id])) {
        // Also update any linked invoices with proper tax split (CGST+SGST vs IGST)
        if ($taxType === 'cgst_sgst') {
            $stmtInv = $pdo->prepare("
                UPDATE invoices 
                SET billing_gstin = ?, 
                    cgst = ROUND(sub_total * 0.09, 2), 
                    sgst = ROUND(sub_total * 0.09, 2), 
                    igst = 0, 
                    total_amount = ROUND(sub_total * 1.18, 2) 
                WHERE booking_id = ?
            ");
        } else {
            $stmtInv = $pdo->prepare("
                UPDATE invoices 
                SET billing_gstin = ?, 
                    cgst = 0, 
                    sgst = 0, 
                    igst = ROUND(sub_total * 0.18, 2), 
                    total_amount = ROUND(sub_total * 1.18, 2) 
                WHERE booking_id = ?
            ");
        }
        $stmtInv->execute([$billing_gstin, $booking_id]);

        echo json_encode(['success' => true, 'billing_gstin' => $billing_gstin, 'tax_type' => $taxType]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
    }
}
?>
