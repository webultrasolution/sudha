<?php
require __DIR__ . '/../config/db.php';
$stmt = $pdo->prepare("SELECT id, booking_id, amount FROM booking_items WHERE booking_id = ?");
$stmt->execute([35]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->prepare("SELECT total_amount, grand_total FROM bookings WHERE id = ?");
$stmt2->execute([35]);
print_r($stmt2->fetch(PDO::FETCH_ASSOC));
