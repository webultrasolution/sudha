<?php
$pdo = new PDO('mysql:host=localhost;dbname=easy_outdoor_crm', 'root', '');
$pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS approval_status ENUM('pending_approval','approved','rejected') DEFAULT 'approved' AFTER type");
$pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL AFTER approval_status");
$pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by");
$pdo->exec("ALTER TABLE approval_requests MODIFY COLUMN entity_type ENUM('proposal','purchase_order','booking','invoice','client_printing','payment') NOT NULL");
echo 'Done';
?>
