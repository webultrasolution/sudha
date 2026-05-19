<?php
include_once __DIR__ . '/../config/db.php';

try {
    // Check if start_date exists
    $stmt = $pdo->query("SHOW COLUMNS FROM proposal_items LIKE 'start_date'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("ALTER TABLE proposal_items ADD COLUMN start_date DATE DEFAULT NULL AFTER selected_image");
        echo "Successfully added 'start_date' column to proposal_items.\n";
    } else {
        echo "'start_date' column already exists in proposal_items.\n";
    }

    // Check if end_date exists
    $stmt = $pdo->query("SHOW COLUMNS FROM proposal_items LIKE 'end_date'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("ALTER TABLE proposal_items ADD COLUMN end_date DATE DEFAULT NULL AFTER start_date");
        echo "Successfully added 'end_date' column to proposal_items.\n";
    } else {
        echo "'end_date' column already exists in proposal_items.\n";
    }

} catch (Exception $e) {
    echo "Error migrating table: " . $e->getMessage() . "\n";
}
?>
