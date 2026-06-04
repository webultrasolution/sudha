<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once __DIR__ . '/config/db.php';

echo "<h3>Fixing existing approved client printing rates</h3>";

try {
    // Run update
    $stmt = $pdo->prepare("UPDATE client_printing_rates SET is_final_invoice = 1 WHERE approval_status = 'approved' AND is_final_invoice = 0");
    $stmt->execute();
    $rowsAffected = $stmt->rowCount();
    echo "<p style='color:green;'>Successfully updated $rowsAffected rows in client_printing_rates table!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Error updating database: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>Cleanup finished. Self-destructing script...</h3>";
@unlink(__FILE__);
echo "<p style='color:green;'>Script deleted successfully for security.</p>";
?>
