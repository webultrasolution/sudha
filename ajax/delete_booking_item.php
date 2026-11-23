<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
if (!(canEdit('bookings'))) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit bookings.']);
    exit;
}
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/trash_helper.php';
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

    // Check if invoice exists and user is not admin
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    if (!$isAdmin) {
        $stmtInvCheck = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ? AND type = 'tax' AND approval_status != 'rejected' LIMIT 1");
        $stmtInvCheck->execute([$bookingId]);
        if ($stmtInvCheck->fetch()) {
            throw new Exception('Deleting items is locked as a Tax Invoice has already been generated. Only Admin can modify items.');
        }
    }

    // 1. Move the booking item to trash
    $trashItemId = move_row_to_trash($pdo, 'booking_items', 'id', $id, $_SESSION['user_id'] ?? null, 'Deleted booking item');
    if (!$trashItemId) throw new Exception('Failed to move booking item to trash');

    // 2. Move the corresponding operation task to trash (if exists)
    $stmtOp = $pdo->prepare("SELECT id FROM operations WHERE booking_id = ? AND site_id = ? LIMIT 1");
    $stmtOp->execute([$bookingId, $siteId]);
    $op = $stmtOp->fetch(PDO::FETCH_ASSOC);
    if ($op) {
        move_row_to_trash($pdo, 'operations', 'id', $op['id'], $_SESSION['user_id'] ?? null, 'Deleted alongside booking item');
    }

    // 3. Auto sync booking totals and linked Tax Invoice
    syncBookingInvoiceFinancials($pdo, $bookingId);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
