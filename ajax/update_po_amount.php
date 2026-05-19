<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$po_id      = intval($_POST['po_id'] ?? 0);
$new_amount = floatval($_POST['amount'] ?? 0);

if ($po_id <= 0 || $new_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID or amount']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch current PO to see vendor & GST details
    $stmtFetch = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmtFetch->execute([$po_id]);
    $po = $stmtFetch->fetch();

    if (!$po) {
        throw new Exception('Purchase Order not found');
    }

    // 2. Recalculate CGST & SGST (9% each) and total
    // Check if vendor has GSTIN in database
    $stmtGst = $pdo->prepare("SELECT gstin FROM partners WHERE id = ?");
    $stmtGst->execute([$po['vendor_id']]);
    $db_vendor_gst = trim($stmtGst->fetchColumn() ?: '');
    $vendor_has_gst = !empty($db_vendor_gst);

    $cgst = 0;
    $sgst = 0;
    if ($vendor_has_gst) {
        $cgst = $new_amount * 0.09;
        $sgst = $new_amount * 0.09;
    }
    $total = $new_amount + $cgst + $sgst;

    // 3. Update the PO header
    $stmtUpdate = $pdo->prepare("
        UPDATE purchase_orders 
        SET po_amount = ?, cgst_amount = ?, sgst_amount = ?, total_amount = ? 
        WHERE id = ?
    ");
    $stmtUpdate->execute([$new_amount, $cgst, $sgst, $total, $po_id]);

    // 4. Sync to po_items so the item cost matches
    // Fetch po_items to see how many items exist
    $stmtItems = $pdo->prepare("SELECT id FROM po_items WHERE po_id = ?");
    $stmtItems->execute([$po_id]);
    $items = $stmtItems->fetchAll();

    if (count($items) === 1) {
        // If there's only 1 item, update its cost directly
        $stmtUpItem = $pdo->prepare("UPDATE po_items SET cost = ?, monthly_rate = ? WHERE id = ?");
        $stmtUpItem->execute([$new_amount, $new_amount, $items[0]['id']]);
    } else if (count($items) > 1) {
        // Distribute proportionally if there are multiple items
        $totalOldCost = 0;
        $itemDetails = [];
        foreach ($items as $item) {
            $stmtItemCost = $pdo->prepare("SELECT cost FROM po_items WHERE id = ?");
            $stmtItemCost->execute([$item['id']]);
            $cost = floatval($stmtItemCost->fetchColumn());
            $totalOldCost += $cost;
            $itemDetails[] = ['id' => $item['id'], 'old_cost' => $cost];
        }

        if ($totalOldCost > 0) {
            $stmtUpItem = $pdo->prepare("UPDATE po_items SET cost = ?, monthly_rate = ? WHERE id = ?");
            foreach ($itemDetails as $detail) {
                $ratio = $detail['old_cost'] / $totalOldCost;
                $newCost = round($new_amount * $ratio, 2);
                $stmtUpItem->execute([$newCost, $newCost, $detail['id']]);
            }
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'PO amount updated and recalculated successfully',
        'subtotal' => $new_amount,
        'total' => $total
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
