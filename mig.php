<?php
include_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("ALTER TABLE booking_items ADD COLUMN selected_image VARCHAR(255) NULL;");
    echo "Success";
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
