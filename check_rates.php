<?php include 'config/db.php'; $columns = $pdo->query('SHOW COLUMNS FROM client_printing_rates')->fetchAll(PDO::FETCH_COLUMN); print_r($columns); ?>
