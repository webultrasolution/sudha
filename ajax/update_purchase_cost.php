<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id      = intval($_POST['item_id'] ?? 0);
    $purchase_cost = floatval($_POST['purchase_cost'] ?? 0);

    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Item ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Update booking_items
        $stmt = $pdo->prepare("UPDATE booking_items SET purchase_amount = ? WHERE id = ?");
        $stmt->execute([$purchase_cost, $item_id]);

        // 2. Fetch the booking_id and site_id for this item
        $stmtFetch = $pdo->prepare("SELECT booking_id, site_id FROM booking_items WHERE id = ?");
        $stmtFetch->execute([$item_id]);
        $row = $stmtFetch->fetch();

        if ($row) {
            // 3. Sync to po_items if a PO exists for this booking + site
            $stmtSync = $pdo->prepare("
                UPDATE po_items pi
                JOIN purchase_orders po ON pi.po_id = po.id
                SET pi.cost = ?, pi.monthly_rate = ?
                WHERE pi.site_id = ?
                  AND po.campaign_id = ?
            ");
            $stmtSync->execute([$purchase_cost, $purchase_cost, $row['site_id'], $row['booking_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'synced_to_po' => ($row ? true : false)]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
