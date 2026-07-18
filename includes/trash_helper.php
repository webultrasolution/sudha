<?php
// Trash helper: move rows into `trash` table and restore them
if (!defined('IN_APP')) define('IN_APP', true);
require_once __DIR__ . '/../config/db.php';

function ensure_trash_table($pdo) {
    static $ensured = false;
    if ($ensured) return;
    try {
        // Run a lightweight query to check if table exists without committing transaction
        $pdo->query("SELECT 1 FROM `trash` LIMIT 1");
        $ensured = true;
    } catch (Exception $e) {
        // Table doesn't exist, create it (this will implicitly commit any active transaction, but it's only run once ever)
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
        $ensured = true;
    }
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

    // Generic check: if any field contains file paths starting with uploads/
    foreach ($row as $colVal) {
        if (is_string($colVal) && !empty($colVal)) {
            $parts = explode('||', $colVal);
            foreach ($parts as $part) {
                $part = trim($part);
                if (strpos($part, 'uploads/') === 0) {
                    $src = __DIR__ . '/../' . $part;
                    if (file_exists($src)) {
                        $dstDir = __DIR__ . '/../uploads/trash/';
                        if (!is_dir($dstDir)) @mkdir($dstDir, 0777, true);
                        $dst = $dstDir . basename($part);
                        @rename($src, $dst);
                    }
                }
            }
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

    // Check if multiple rows (sequential array)
    $is_multi = isset($rowData[0]) && is_array($rowData[0]);
    $rows = $is_multi ? $rowData : [$rowData];

    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $cols = array_keys($row);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $colSql = '`' . implode('`,`', $cols) . '`';
            $values = array_values($row);
            
            // Try to insert
            try {
                $insertSql = "INSERT INTO `$table` ($colSql) VALUES ($placeholders)";
                $ins = $pdo->prepare($insertSql);
                $ins->execute($values);
            } catch (Exception $e) {
                // Fallback to update if PK exists
                if (!isset($row[$pk])) {
                    throw $e;
                }
                $updates = [];
                $updateVals = [];
                foreach ($cols as $c) {
                    if ($c === $pk) continue;
                    $updates[] = "`$c` = ?";
                    $updateVals[] = $row[$c];
                }
                $updateSql = "UPDATE `$table` SET " . implode(',', $updates) . " WHERE `$pk` = ?";
                $updateVals[] = $row[$pk];
                $upd = $pdo->prepare($updateSql);
                $upd->execute($updateVals);
            }

            // Move files back to their respective folder upon restore
            $files = [];
            if (isset($row['filename']) && !empty($row['filename'])) {
                $files[] = ['file' => $row['filename'], 'type' => 'filename'];
            }
            if (isset($row['attachments']) && !empty($row['attachments'])) {
                $atts = explode('||', $row['attachments']);
                foreach ($atts as $att) {
                    if (!empty($att)) $files[] = ['file' => $att, 'type' => 'attachments'];
                }
            }
            if (isset($row['client_tax_order']) && !empty($row['client_tax_order'])) {
                $files[] = ['file' => $row['client_tax_order'], 'type' => 'client_tax_order'];
            }

            foreach ($files as $fileData) {
                $f = $fileData['file'];
                $trashPath = __DIR__ . '/../uploads/trash/' . $f;
                $destPath = null;
                if ($table === 'site_images') {
                    $destPath = __DIR__ . '/../uploads/sites/' . $f;
                } elseif ($table === 'po_attachments') {
                    $destPath = __DIR__ . '/../uploads/pos/' . $f;
                } elseif ($table === 'vendor_printing_rates' || $table === 'client_printing_rates') {
                    if ($fileData['type'] === 'attachments') {
                        $destPath = __DIR__ . '/../uploads/pos/' . $f;
                    } elseif ($fileData['type'] === 'client_tax_order') {
                        $destPath = __DIR__ . '/../uploads/pos/tax_orders/' . $f;
                    }
                }
                if ($destPath && file_exists($trashPath)) {
                    if (!is_dir(dirname($destPath))) @mkdir(dirname($destPath), 0777, true);
                    @rename($trashPath, $destPath);
                }
            }

            // Generic check to move files starting with uploads/ back to their respective folder upon restore
            foreach ($row as $colVal) {
                if (is_string($colVal) && !empty($colVal)) {
                    $parts = explode('||', $colVal);
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if (strpos($part, 'uploads/') === 0) {
                            $trashPath = __DIR__ . '/../uploads/trash/' . basename($part);
                            $destPath = __DIR__ . '/../' . $part;
                            if (file_exists($trashPath)) {
                                if (!is_dir(dirname($destPath))) @mkdir(dirname($destPath), 0777, true);
                                @rename($trashPath, $destPath);
                            }
                        }
                    }
                }
            }
        }

        // If restoring related child rows for a parent entity, restore them too.
        $firstRow = $rows[0];
        if (isset($firstRow['id'])) {
            if ($trash['table_name'] === 'invoices') {
                restore_related_trash_rows($pdo, 'invoice_items', 'invoice_id', $firstRow['id']);
            }
            if ($trash['table_name'] === 'proposals') {
                restore_related_trash_rows($pdo, 'proposal_items', 'proposal_id', $firstRow['id']);
            }
            if ($trash['table_name'] === 'bookings') {
                restore_related_trash_rows($pdo, 'operations', 'booking_id', $firstRow['id']);
            }
            if ($trash['table_name'] === 'vendor_printing_rates') {
                $po_number = $firstRow['po_number'] ?? '';
                if (!empty($po_number)) {
                    $stmtPOTrash = $pdo->prepare("SELECT id, row_data FROM trash WHERE table_name = 'purchase_orders'");
                    $stmtPOTrash->execute();
                    while ($trPO = $stmtPOTrash->fetch(PDO::FETCH_ASSOC)) {
                        $poData = json_decode($trPO['row_data'], true);
                        if (is_array($poData) && isset($poData['po_number']) && $poData['po_number'] === $po_number) {
                            $poCols = array_keys($poData);
                            $poPlaceholders = implode(',', array_fill(0, count($poCols), '?'));
                            $poColSql = '`' . implode('`,`', $poCols) . '`';
                            $poValues = array_values($poData);
                            
                            $pdo->prepare("INSERT INTO `purchase_orders` ($poColSql) VALUES ($poPlaceholders)")->execute($poValues);
                            $pdo->prepare("DELETE FROM trash WHERE id = ?")->execute([$trPO['id']]);
                            
                            // Restore related po_items and po_attachments
                            if (isset($poData['id'])) {
                                restore_related_trash_rows($pdo, 'po_items', 'po_id', $poData['id']);
                                restore_related_trash_rows($pdo, 'po_attachments', 'po_id', $poData['id']);
                            }
                        }
                    }
                }
            }
            if ($trash['table_name'] === 'purchase_orders') {
                restore_related_trash_rows($pdo, 'po_items', 'po_id', $firstRow['id']);
                restore_related_trash_rows($pdo, 'po_attachments', 'po_id', $firstRow['id']);
            }
            if ($trash['table_name'] === 'sites') {
                restore_related_trash_rows($pdo, 'site_images', 'site_id', $firstRow['id']);
            }
        }

        $del = $pdo->prepare("DELETE FROM trash WHERE id = ?");
        $del->execute([$trashId]);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
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

        // Generic check to relocate files back if they exist in trash
        foreach ($itemData as $colVal) {
            if (is_string($colVal) && !empty($colVal)) {
                $parts = explode('||', $colVal);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (strpos($part, 'uploads/') === 0) {
                        $trashPath = __DIR__ . '/../uploads/trash/' . basename($part);
                        $destPath = __DIR__ . '/../' . $part;
                        if (file_exists($trashPath)) {
                            if (!is_dir(dirname($destPath))) @mkdir(dirname($destPath), 0777, true);
                            @rename($trashPath, $destPath);
                        }
                    }
                }
            }
        }

        $del = $pdo->prepare("DELETE FROM trash WHERE id = ?");
        $del->execute([$item['id']]);
    }
}

function move_multiple_rows_to_trash($pdo, $table, $pk, $ids, $userId = null, $reason = null) {
    ensure_trash_table($pdo);

    $table = preg_replace('/[^a-z0-9_]/i','', $table);
    $pk = preg_replace('/[^a-z0-9_]/i','', $pk);

    if (empty($ids)) return false;

    // Fetch all matching rows
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` IN ($in)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return false;

    // Move files to trash if any
    foreach ($rows as $row) {
        $files = [];
        if (!empty($row['filename'])) $files[] = ['file' => $row['filename'], 'type' => 'filename'];
        if (!empty($row['attachments'])) {
            $atts = explode('||', $row['attachments']);
            foreach ($atts as $att) {
                if (!empty($att)) $files[] = ['file' => $att, 'type' => 'attachments'];
            }
        }
        if (!empty($row['client_tax_order'])) $files[] = ['file' => $row['client_tax_order'], 'type' => 'client_tax_order'];

        foreach ($files as $fileData) {
            $f = $fileData['file'];
            $src = null;
            if ($table === 'site_images') {
                $src = __DIR__ . '/../uploads/sites/' . $f;
            } elseif ($table === 'po_attachments') {
                $src = __DIR__ . '/../uploads/pos/' . $f;
            } elseif ($table === 'vendor_printing_rates' || $table === 'client_printing_rates') {
                if ($fileData['type'] === 'attachments') {
                    $src = __DIR__ . '/../uploads/pos/' . $f;
                } elseif ($fileData['type'] === 'client_tax_order') {
                    $src = __DIR__ . '/../uploads/pos/tax_orders/' . $f;
                }
            }
            if ($src) {
                $dstDir = __DIR__ . '/../uploads/trash/';
                if (!is_dir($dstDir)) @mkdir($dstDir, 0777, true);
                $dst = $dstDir . $f;
                if (file_exists($src)) @rename($src, $dst);
            }
        }

        // Generic check: if any field contains file paths starting with uploads/
        foreach ($row as $colVal) {
            if (is_string($colVal) && !empty($colVal)) {
                $parts = explode('||', $colVal);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (strpos($part, 'uploads/') === 0) {
                        $src = __DIR__ . '/../' . $part;
                        if (file_exists($src)) {
                            $dstDir = __DIR__ . '/../uploads/trash/';
                            if (!is_dir($dstDir)) @mkdir($dstDir, 0777, true);
                            $dst = $dstDir . basename($part);
                            @rename($src, $dst);
                        }
                    }
                }
            }
        }
    }

    // Set row_id to group info (e.g. PO number if present in first row, else the first ID)
    $rowId = !empty($rows[0]['po_number']) ? $rows[0]['po_number'] : (string)$rows[0][$pk];

    $rowDataEncoded = json_encode($rows);

    $ins = $pdo->prepare("INSERT INTO trash (table_name, row_id, pk_name, row_data, deleted_by, reason) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->execute([$table, $rowId, $pk, $rowDataEncoded, $userId, $reason]);
    $trashId = $pdo->lastInsertId();

    $del = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` IN ($in)");
    $del->execute($ids);

    return $trashId;
}

function delete_trash_item_files($table, $rowData) {
    if (!is_array($rowData)) return;
    $is_multi = isset($rowData[0]) && is_array($rowData[0]);
    $rows = $is_multi ? $rowData : [$rowData];
    
    foreach ($rows as $row) {
        $files = [];
        if (isset($row['filename']) && !empty($row['filename'])) {
            $files[] = basename($row['filename']);
        }
        if (isset($row['attachments']) && !empty($row['attachments'])) {
            $atts = explode('||', $row['attachments']);
            foreach ($atts as $att) {
                if (!empty($att)) $files[] = basename($att);
            }
        }
        if (isset($row['client_tax_order']) && !empty($row['client_tax_order'])) {
            $files[] = basename($row['client_tax_order']);
        }
        
        // Generic check: if any field contains file paths starting with uploads/
        foreach ($row as $colVal) {
            if (is_string($colVal) && !empty($colVal)) {
                $parts = explode('||', $colVal);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (strpos($part, 'uploads/') === 0) {
                        $files[] = basename($part);
                    }
                }
            }
        }
        
        $files = array_unique($files);
        
        foreach ($files as $f) {
            $filePath = __DIR__ . '/../uploads/trash/' . $f;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }
}

function delete_related_trash_rows($pdo, $childTable, $foreignKey, $foreignValue) {
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
        
        delete_trash_item_files($childTable, $itemData);
        
        $del = $pdo->prepare("DELETE FROM trash WHERE id = ?");
        $del->execute([$item['id']]);
    }
}

// Initialize trash table outside any transaction when this helper is loaded
ensure_trash_table($pdo);
?>
