<?php
include_once __DIR__ . '/config/db.php';

function addCol($pdo, $table, $col, $type) {
    $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($col, $columns)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $col $type");
        echo "Added $col to $table<br>";
    }
}

function renameCol($pdo, $table, $old, $new, $type) {
    $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array($old, $columns) && !in_array($new, $columns)) {
        $pdo->exec("ALTER TABLE $table CHANGE $old $new $type");
        echo "Renamed $old to $new in $table<br>";
    }
}

try {
    renameCol($pdo, 'payments', 'entity_id', 'partner_id', 'INT NOT NULL');
    renameCol($pdo, 'payments', 'reference_no', 'transaction_id', 'VARCHAR(100)');
    
    addCol($pdo, 'payments', 'payment_mode', "ENUM('Cash', 'Cheque', 'NEFT', 'RTGS', 'UPI', 'Other') DEFAULT 'NEFT'");
    addCol($pdo, 'payments', 'invoice_id', 'INT');
    addCol($pdo, 'payments', 'proposal_id', 'INT');
    addCol($pdo, 'payments', 'notes', 'TEXT');

    echo "Migration complete.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
