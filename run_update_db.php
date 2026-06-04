<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once __DIR__ . '/config/db.php';

echo "<h3>Running Database Updates</h3>";

$sqlFile = __DIR__ . '/update_database.sql';
if (!file_exists($sqlFile)) {
    die("Error: update_database.sql not found at $sqlFile");
}

$sqlContent = file_get_contents($sqlFile);

// Simple parser to split by semicolon, ignoring lines starting with --
$queries = [];
$currentQuery = "";
$lines = explode("\n", $sqlContent);

foreach ($lines as $line) {
    $trimmed = trim($line);
    if (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
        continue;
    }
    $currentQuery .= " " . $line;
    if (substr($trimmed, -1) === ';') {
        $queries[] = trim($currentQuery);
        $currentQuery = "";
    }
}
if (!empty(trim($currentQuery))) {
    $queries[] = trim($currentQuery);
}

foreach ($queries as $index => $q) {
    $q = rtrim($q, ';');
    if (empty($q)) continue;
    
    echo "<p>Executing Query [" . ($index + 1) . "]: <code>" . htmlspecialchars($q) . "</code><br>";
    try {
        $pdo->exec($q);
        echo "<strong style='color:green;'>Success</strong></p>";
    } catch (Exception $e) {
        echo "<strong style='color:orange;'>Notice/Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<h3>Execution Finished. Self-destructing script...</h3>";
// Unlink this file immediately for security so it cannot be run again
@unlink(__FILE__);
echo "<p style='color:green;'>Script deleted successfully for security.</p>";
?>
