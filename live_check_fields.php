<?php
header('Content-Type: application/json');
include_once __DIR__ . '/config/db.php';
include_once __DIR__ . '/includes/trash_helper.php';

$output = [];
try {
    $stmt = $pdo->prepare("INSERT INTO partners (type, name, contact_person, phone, email) VALUES ('client', 'TEST TRASH PARTNER', 'Test Person', '1234567890', 'test@test.com')");
    $stmt->execute();
    $id = $pdo->lastInsertId();
    $output['created_id'] = (int)$id;

    $trashId = move_row_to_trash($pdo, 'partners', 'id', $id, 1, 'Testing soft delete');
    $output['moved_to_trash'] = (bool)$trashId;
    $output['trash_id'] = (int)$trashId;

    $ok = restore_trash_item($pdo, $trashId);
    $output['restored'] = $ok;
    
    // Check if the partner exists after restore
    $chk = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE id = ?");
    $chk->execute([$id]);
    $output['restored_partner_count'] = (int)$chk->fetchColumn();

    // Clean up
    $pdo->prepare("DELETE FROM partners WHERE id = ?")->execute([$id]);
} catch (Exception $e) {
    $output['error'] = $e->getMessage();
}
echo json_encode($output);
