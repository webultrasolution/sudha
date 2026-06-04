<?php
header('Content-Type: application/json');
include_once __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/trash_helper.php';

$output = [];

try {
    // 1. Create a temporary partner
    $stmt = $pdo->prepare("INSERT INTO partners (type, name, contact_person, phone, email) VALUES ('client', 'TEST TRASH PARTNER', 'Test Person', '1234567890', 'test@test.com')");
    $stmt->execute();
    $id = $pdo->lastInsertId();
    $output['created_id'] = $id;

    // 2. Soft-delete the partner
    $trashId = move_row_to_trash($pdo, 'partners', 'id', $id, 1, 'Testing soft delete');
    $output['moved_to_trash'] = (bool)$trashId;
    $output['trash_id'] = $trashId;

    // Verify it exists in trash table
    $chk = $pdo->prepare("SELECT * FROM trash WHERE id = ?");
    $chk->execute([$trashId]);
    $trashRow = $chk->fetch(PDO::FETCH_ASSOC);
    $output['trash_row'] = $trashRow;

    // 3. Restore the partner
    $ok = restore_trash_item($pdo, $trashId);
    $output['restored'] = $ok;

    // Verify restored partner exists in partners table
    $chk2 = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $chk2->execute([$id]);
    $partnerRow = $chk2->fetch(PDO::FETCH_ASSOC);
    $output['partner_row_restored'] = $partnerRow;

    // Clean up partner
    $pdo->prepare("DELETE FROM partners WHERE id = ?")->execute([$id]);
    $output['cleanup'] = true;

} catch (Exception $e) {
    $output['error'] = $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT);
