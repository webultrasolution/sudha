<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("SELECT bi.id, s.name, s.city, bi.custom_site_name FROM booking_items bi JOIN sites s ON bi.site_id = s.id WHERE bi.booking_id = 26");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
?>
