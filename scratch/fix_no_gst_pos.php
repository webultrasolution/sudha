<?php
include_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // Fetch all POs with vendor GSTIN
    $stmt = $pdo->query("SELECT po.id, po.vendor_id, po.po_amount, p.gstin FROM purchase_orders po JOIN partners p ON po.vendor_id = p.id");
    $pos = $stmt->fetchAll();

    $fixedCount = 0;
    foreach ($pos as $po) {
        $gstin = trim($po['gstin'] ?: '');
        if (empty($gstin)) {
            // This vendor has no GST! Update the PO to have 0 tax and sync total amount to base amount
            $stmtUp = $pdo->prepare("UPDATE purchase_orders SET cgst_amount = 0, sgst_amount = 0, igst_amount = 0, total_amount = po_amount WHERE id = ?");
            $stmtUp->execute([$po['id']]);
            $fixedCount++;
        }
    }

    $pdo->commit();
    echo "Successfully updated $fixedCount POs that had no GST vendor.\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
?>
