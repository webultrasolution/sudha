<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get details to cleanup operations and update booking totals
    $stmt = $pdo->prepare("SELECT booking_id, site_id FROM booking_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        throw new Exception("Item not found");
    }

    $bookingId = $item['booking_id'];
    $siteId = $item['site_id'];

    // 1. Delete the item
    $pdo->prepare("DELETE FROM booking_items WHERE id = ?")->execute([$id]);

    // 2. Delete the corresponding operation task
    $pdo->prepare("DELETE FROM operations WHERE booking_id = ? AND site_id = ?")->execute([$bookingId, $siteId]);

    // 3. Recalculate Booking Totals
    $stmtSums = $pdo->prepare("SELECT SUM(amount) as subtotal FROM booking_items WHERE booking_id = ?");
    $stmtSums->execute([$bookingId]);
    $sums = $stmtSums->fetch();
    $newSubtotal = $sums['subtotal'] ?: 0;

    $tax = $newSubtotal * 0.18;
    $grand = $newSubtotal + $tax;

    $stmtUpdate = $pdo->prepare("UPDATE bookings SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?");
    $stmtUpdate->execute([$newSubtotal, $tax, $grand, $bookingId]);

    // Revert booking to pending_approval if non-admin deleted an item from an approved booking
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    if (!$isAdmin) {
        $bookRef = $pdo->query("SELECT campaign_name FROM bookings WHERE id = $bookingId")->fetchColumn();
        revertToPendingOnEdit($pdo, 'bookings', $bookingId, 'booking', "Booking: $bookRef", $_SESSION['user_id'] ?? 0);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
