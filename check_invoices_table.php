<?php include 'config/db.php'; $columns = $pdo->query('SHOW COLUMNS FROM invoices')->fetchAll(PDO::FETCH_COLUMN); print_r($columns); ?>
