<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$trashId = isset($data['trash_id']) ? intval($data['trash_id']) : 0;
$trashIdsInput = isset($data['trash_ids']) ? $data['trash_ids'] : null;

$trashIds = [];
if (is_array($trashIdsInput)) {
    $trashIds = array_map('intval', $trashIdsInput);
} elseif (!empty($trashIdsInput)) {
    if (is_string($trashIdsInput)) {
        $trashIds = array_map('intval', explode(',', $trashIdsInput));
    } else {
        $trashIds = [intval($trashIdsInput)];
    }
} elseif ($trashId > 0) {
    $trashIds = [$trashId];
}

$trashIds = array_filter($trashIds);

if (empty($trashIds)) {
    echo json_encode(['success' => false, 'message' => 'Missing trash_id(s)']);
    exit;
}

try {
    include_once __DIR__ . '/../includes/trash_helper.php';
    
    foreach ($trashIds as $id) {
        // Fetch the trash item first to check table and parent id
        $stmt = $pdo->prepare("SELECT * FROM trash WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $trash = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($trash) {
            $table = $trash['table_name'];
            $rowData = json_decode($trash['row_data'], true);
            
            // Delete physical files for the parent item
            delete_trash_item_files($table, $rowData);
            
            if (is_array($rowData)) {
                $firstRow = isset($rowData[0]) && is_array($rowData[0]) ? $rowData[0] : $rowData;
                if (isset($firstRow['id'])) {
                    $parentValue = $firstRow['id'];
                    if ($table === 'invoices') {
                        delete_related_trash_rows($pdo, 'invoice_items', 'invoice_id', $parentValue);
                    } elseif ($table === 'proposals') {
                        delete_related_trash_rows($pdo, 'proposal_items', 'proposal_id', $parentValue);
                    } elseif ($table === 'bookings') {
                        delete_related_trash_rows($pdo, 'operations', 'booking_id', $parentValue);
                    } elseif ($table === 'purchase_orders') {
                        delete_related_trash_rows($pdo, 'po_items', 'po_id', $parentValue);
                        delete_related_trash_rows($pdo, 'po_attachments', 'po_id', $parentValue);
                    } elseif ($table === 'sites') {
                        delete_related_trash_rows($pdo, 'site_images', 'site_id', $parentValue);
                    } elseif ($table === 'vendor_printing_rates') {
                        // Delete associated purchase_orders item from trash if exists
                        $po_number = $firstRow['po_number'] ?? '';
                        if (!empty($po_number)) {
                            $stmtPOTrash = $pdo->prepare("SELECT id, row_data FROM trash WHERE table_name = 'purchase_orders'");
                            $stmtPOTrash->execute();
                            while ($trPO = $stmtPOTrash->fetch(PDO::FETCH_ASSOC)) {
                                $poData = json_decode($trPO['row_data'], true);
                                if (is_array($poData) && isset($poData['po_number']) && $poData['po_number'] === $po_number) {
                                    if (isset($poData['id'])) {
                                        delete_related_trash_rows($pdo, 'po_items', 'po_id', $poData['id']);
                                        delete_related_trash_rows($pdo, 'po_attachments', 'po_id', $poData['id']);
                                    }
                                    $pdo->prepare("DELETE FROM trash WHERE id = ?")->execute([$trPO['id']]);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM trash WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
