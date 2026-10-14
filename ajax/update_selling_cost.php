<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id      = intval($_POST['item_id'] ?? 0);
    $selling_cost = floatval($_POST['selling_cost'] ?? 0);

    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Item ID']);
        exit;
    }

    // Always fetch bookingId first
    $stmtFetch = $pdo->prepare("SELECT booking_id FROM booking_items WHERE id = ?");
    $stmtFetch->execute([$item_id]);
    $bookingId = intval($stmtFetch->fetchColumn());

    // Check if invoice exists and user is not admin
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    if (!$isAdmin && $bookingId) {
        $stmtInvCheck = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ? AND type = 'tax' AND approval_status != 'rejected' LIMIT 1");
        $stmtInvCheck->execute([$bookingId]);
        if ($stmtInvCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Editing is locked as a Tax Invoice has already been generated. Only Admin can modify financials.']);
            exit;
        }
    }

    try {
        $pdo->beginTransaction();

        // Fetch unit
        $stmtFetchUnit = $pdo->prepare("SELECT unit FROM booking_items WHERE id = ?");
        $stmtFetchUnit->execute([$item_id]);
        $u = max(1, intval($stmtFetchUnit->fetchColumn() ?: 1));
        $calculatedSaleRate = round($selling_cost / $u, 2);

        // 1. Update booking_items (both amount and sale_rate)
        $stmt = $pdo->prepare("UPDATE booking_items SET amount = ?, sale_rate = ? WHERE id = ?");
        $stmt->execute([$selling_cost, $calculatedSaleRate, $item_id]);

        // 2. Automatically sync booking totals and any linked Tax Invoice
        if ($bookingId) {
            syncBookingInvoiceFinancials($pdo, $bookingId);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
