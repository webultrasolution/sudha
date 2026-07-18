<?php
include_once __DIR__ . '/../config/db.php';
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

    try {
        $pdo->beginTransaction();

        // 1. Update booking_items
        $stmt = $pdo->prepare("UPDATE booking_items SET amount = ? WHERE id = ?");
        $stmt->execute([$selling_cost, $item_id]);

        // 2. Fetch the booking_id for this item
        $stmtFetch = $pdo->prepare("SELECT booking_id FROM booking_items WHERE id = ?");
        $stmtFetch->execute([$item_id]);
        $bookingId = intval($stmtFetch->fetchColumn());

        if ($bookingId) {
            // 3. Recalculate Booking Totals
            $stmtSums = $pdo->prepare("SELECT SUM(amount) as subtotal FROM booking_items WHERE booking_id = ?");
            $stmtSums->execute([$bookingId]);
            $newSubtotal = floatval($stmtSums->fetchColumn() ?: 0);

            // Fetch the booking's tax type
            $stmtTaxType = $pdo->prepare("SELECT tax_type FROM bookings WHERE id = ?");
            $stmtTaxType->execute([$bookingId]);
            $tax_type = $stmtTaxType->fetchColumn() ?: 'igst';

            $tax = 0;
            if ($tax_type !== 'none') {
                $tax = $newSubtotal * 0.18;
            }
            $grand = $newSubtotal + $tax;

            // 4. Update the bookings table
            $stmtUpdate = $pdo->prepare("UPDATE bookings SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?");
            $stmtUpdate->execute([$newSubtotal, $tax, $grand, $bookingId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
