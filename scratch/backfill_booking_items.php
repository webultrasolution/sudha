<?php
include_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query("
        UPDATE booking_items bi
        JOIN proposal_items pi ON bi.proposal_item_id = pi.id
        SET bi.purchase_rate = pi.purchase_rate,
            bi.purchase_amount = pi.purchase_rate
        WHERE bi.purchase_rate IS NULL
    ");
    echo "Backfilled " . $stmt->rowCount() . " booking items.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
