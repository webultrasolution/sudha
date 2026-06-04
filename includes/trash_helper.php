<?php
// Trash helper: move rows into `trash` table and restore them
if (!defined('IN_APP')) define('IN_APP', true);
require_once __DIR__ . '/../config/db.php';

function ensure_trash_table($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS trash (
        id INT AUTO_INCREMENT PRIMARY KEY,
        table_name VARCHAR(100) NOT NULL,
        row_id VARCHAR(100) DEFAULT NULL,
        pk_name VARCHAR(100) DEFAULT 'id',
        row_data LONGTEXT,
        deleted_by INT DEFAULT NULL,
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reason TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function move_row_to_trash($pdo, $table, $pk, $id, $userId = null, $reason = null) {
    ensure_trash_table($pdo);

    $table = preg_replace('/[^a-z0-9_]/i','', $table);
    $pk = preg_replace('/[^a-z0-9_]/i','', $pk);

    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;

    // If this is a site image or po attachment, attempt to move file to uploads/trash
    if (!empty($row['filename'])) {
        $src = null;
        if ($table === 'site_images') {
            $src = __DIR__ . '/../uploads/sites/' . $row['filename'];
        } elseif ($table === 'po_attachments') {
            $src = __DIR__ . '/../uploads/pos/' . $row['filename'];
        }
        if ($src) {
            $dstDir = __DIR__ . '/../uploads/trash/';
            if (!is_dir($dstDir)) @mkdir($dstDir, 0777, true);
            $dst = $dstDir . $row['filename'];
            if (file_exists($src)) @rename($src, $dst);
        }
    }

    $ins = $pdo->prepare("INSERT INTO trash (table_name, row_id, pk_name, row_data, deleted_by, reason) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->execute([$table, (string)$id, $pk, json_encode($row), $userId, $reason]);
    $trashId = $pdo->lastInsertId();

    $del = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
    $del->execute([$id]);

    return $trashId;
}

function restore_trash_item($pdo, $trashId) {
    ensure_trash_table($pdo);
    $stmt = $pdo->prepare("SELECT * FROM trash WHERE id = ? LIMIT 1");
    $stmt->execute([$trashId]);
    $trash = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$trash) return false;

    $table = $trash['table_name'];
    $pk = $trash['pk_name'] ?: 'id';
    $rowData = json_decode($trash['row_data'], true);
    if (!is_array($rowData)) return false;

    $cols = array_keys($rowData);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colSql = '`' . implode('`,`', $cols) . '`';
    $values = array_values($rowData);

    $pdo->beginTransaction();
    try {
        $insertSql = "INSERT INTO `$table` ($colSql) VALUES ($placeholders)";
        $ins = $pdo->prepare($insertSql);
        $ins->execute($values);
    } catch (Exception $e) {
        // Fallback to update if PK exists
        if (!isset($rowData[$pk])) {
            $pdo->rollBack();
            throw $e;
        }
        $updates = [];
        $updateVals = [];
        foreach ($cols as $c) {
            if ($c === $pk) continue;
            $updates[] = "`$c` = ?";
            $updateVals[] = $rowData[$c];
        }
        $updateSql = "UPDATE `$table` SET " . implode(',', $updates) . " WHERE `$pk` = ?";
        $updateVals[] = $rowData[$pk];
        $upd = $pdo->prepare($updateSql);
        $upd->execute($updateVals);
    }

    // Move files back to their respective folder upon restore
    if (isset($rowData['filename']) && !empty($rowData['filename'])) {
        $trashPath = __DIR__ . '/../uploads/trash/' . $rowData['filename'];
        $destPath = null;
        if ($table === 'site_images') {
            $destPath = __DIR__ . '/../uploads/sites/' . $rowData['filename'];
        } elseif ($table === 'po_attachments') {
            $destPath = __DIR__ . '/../uploads/pos/' . $rowData['filename'];
        }
        if ($destPath && file_exists($trashPath)) {
            if (!is_dir(dirname($destPath))) @mkdir(dirname($destPath), 0777, true);
            @rename($trashPath, $destPath);
        }
    }

    // If restoring related child rows for a parent entity, restore them too.
    if (isset($rowData['id'])) {
        if ($trash['table_name'] === 'invoices') {
            restore_related_trash_rows($pdo, 'invoice_items', 'invoice_id', $rowData['id']);
        }
        if ($trash['table_name'] === 'proposals') {
            restore_related_trash_rows($pdo, 'proposal_items', 'proposal_id', $rowData['id']);
        }
        if ($trash['table_name'] === 'bookings') {
            restore_related_trash_rows($pdo, 'operations', 'booking_id', $rowData['id']);
        }
        if ($trash['table_name'] === 'purchase_orders') {
            restore_related_trash_rows($pdo, 'po_items', 'po_id', $rowData['id']);
            restore_related_trash_rows($pdo, 'po_attachments', 'po_id', $rowData['id']);
        }
        if ($trash['table_name'] === 'sites') {
            restore_related_trash_rows($pdo, 'site_images', 'site_id', $rowData['id']);
        }
    }

    $del = $pdo->prepare("DELETE FROM trash WHERE id = ?");
    $del->execute([$trashId]);
    $pdo->commit();
    return true;
}

function restore_related_trash_rows($pdo, $childTable, $foreignKey, $foreignValue) {
    $stmt = $pdo->prepare("SELECT id, row_data FROM trash WHERE table_name = ?");
    $stmt->execute([$childTable]);
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $itemData = json_decode($item['row_data'], true);
        if (!is_array($itemData) || !isset($itemData[$foreignKey])) {
            continue;
        }
        if ((string)$itemData[$foreignKey] !== (string)$foreignValue) {
            continue;
        }
        $cols = array_keys($itemData);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colSql = '`' . implode('`,`', $cols) . '`';
        $values = array_values($itemData);
        $insertSql = "INSERT INTO `$childTable` ($colSql) VALUES ($placeholders)";
        $ins = $pdo->prepare($insertSql);
        $ins->execute($values);

        // Relocate files back if they exist in trash
        if (isset($itemData['filename']) && !empty($itemData['filename'])) {
            $trashPath = __DIR__ . '/../uploads/trash/' . $itemData['filename'];
            $destPath = null;
            if ($childTable === 'site_images') {
                $destPath = __DIR__ . '/../uploads/sites/' . $itemData['filename'];
            } elseif ($childTable === 'po_attachments') {
                $destPath = __DIR__ . '/../uploads/pos/' . $itemData['filename'];
            }
            if ($destPath && file_exists($trashPath)) {
                if (!is_dir(dirname($destPath))) @mkdir(dirname($destPath), 0777, true);
                @rename($trashPath, $destPath);
            }
        }

        $del = $pdo->prepare("DELETE FROM trash WHERE id = ?");
        $del->execute([$item['id']]);
    }
}

?>
