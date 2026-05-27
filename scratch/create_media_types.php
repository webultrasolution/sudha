<?php
include_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `media_types` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL UNIQUE
    );");
    $pdo->exec("INSERT IGNORE INTO `media_types` (`name`) VALUES ('Billboard'), ('Unipole'), ('Gantry'), ('BQS'), ('DCP'), ('LED Screen');");
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
