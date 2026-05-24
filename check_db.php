<?php include 'config/db.php'; $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); print_r($tables); ?>
