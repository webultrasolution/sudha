<?php
include_once __DIR__ . '/config/db.php';

try {
    echo "Updating payments table...<br>";
    
    // Rename entity_id to partner_id if it exists and partner_id doesn't
    $check = $pdo->query("SHOW COLUMNS FROM payments LIKE 'partner_id'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE payments CHANGE entity_id partner_id INT(11)");
        echo "Renamed entity_id to partner_id<br>";
    }

    // Rename reference_no to transaction_id if it exists and transaction_id doesn't
    $check = $pdo->query("SHOW COLUMNS FROM payments LIKE 'transaction_id'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE payments CHANGE reference_no transaction_id VARCHAR(100)");
        echo "Renamed reference_no to transaction_id<br>";
    }

    // Add proposal_id if missing (renaming po_id if it exists)
    $check = $pdo->query("SHOW COLUMNS FROM payments LIKE 'proposal_id'");
    if ($check->rowCount() == 0) {
        $checkPO = $pdo->query("SHOW COLUMNS FROM payments LIKE 'po_id'");
        if ($checkPO->rowCount() > 0) {
            $pdo->exec("ALTER TABLE payments CHANGE po_id proposal_id INT(11)");
            echo "Renamed po_id to proposal_id<br>";
        } else {
            $pdo->exec("ALTER TABLE payments ADD proposal_id INT(11) AFTER invoice_id");
            echo "Added column: proposal_id<br>";
        }
    }

    echo "Done!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
