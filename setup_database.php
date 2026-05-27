<?php
include_once __DIR__ . '/config/db.php';

try {
    // Create the table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `media_types` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL UNIQUE
    );");
    
    // Insert the default types
    $pdo->exec("INSERT IGNORE INTO `media_types` (`name`) VALUES ('Billboard'), ('Unipole'), ('Gantry'), ('BQS'), ('DCP'), ('LED Screen');");
    
    echo "<div style='padding: 20px; font-family: sans-serif;'>";
    echo "<h2 style='color: green;'>✅ Success!</h2>";
    echo "<p>The <b>media_types</b> table has been successfully created and default data has been added to the database.</p>";
    echo "<p style='color: red;'><b>Security Warning:</b> Please delete this file (setup_database.php) from your server now.</p>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='padding: 20px; font-family: sans-serif;'>";
    echo "<h2 style='color: red;'>❌ Error!</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
