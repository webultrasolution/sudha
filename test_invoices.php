<?php include 'config/db.php'; try { $pdo->query('SELECT client_id FROM invoices LIMIT 1'); echo 'HAS_CLIENT_ID'; } catch (Exception $e) { echo 'NO_CLIENT_ID'; } ?>
