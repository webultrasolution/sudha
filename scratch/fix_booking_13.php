<?php
include_once __DIR__ . '/../config/db.php';

$bookingId = 13;

// Fetch sites from operations for this booking
$stmtOps = $pdo->prepare("SELECT site_id FROM operations WHERE booking_id = ?");
$stmtOps->execute([$bookingId]);
$sites = $stmtOps->fetchAll(PDO::FETCH_COLUMN);

if (empty($sites)) {
    echo "No sites found in operations for booking $bookingId\n";
    exit;
}

// Fetch booking dates
$stmtB = $pdo->prepare("SELECT start_date, end_date FROM bookings WHERE id = ?");
$stmtB->execute([$bookingId]);
$b = $stmtB->fetch();

$stmtBI = $pdo->prepare("INSERT INTO booking_items (booking_id, site_id, start_date, end_date, days, purchase_amount, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");

foreach ($sites as $sid) {
    // Check if already exists
    $stmtCheck = $pdo->prepare("SELECT id FROM booking_items WHERE booking_id = ? AND site_id = ?");
    $stmtCheck->execute([$bookingId, $sid]);
    if ($stmtCheck->fetch()) {
        echo "Site $sid already exists in booking_items for $bookingId\n";
        continue;
    }

    // Try to find rate from po_items if possible, or use 0
    $stmtRate = $pdo->prepare("
        SELECT cost FROM po_items pi 
        JOIN purchase_orders po ON pi.po_id = po.id 
        WHERE po.customer_id = (SELECT client_id FROM bookings WHERE id = ?) 
        AND pi.site_id = ? 
        ORDER BY po.id DESC LIMIT 1
    ");
    $stmtRate->execute([$bookingId, $sid]);
    $rate = $stmtRate->fetchColumn() ?: 0;

    $stmtBI->execute([
        $bookingId,
        $sid,
        $b['start_date'],
        $b['end_date'],
        30,
        $rate,
        $rate
    ]);
    echo "Inserted site $sid for booking $bookingId\n";
}
echo "Done.\n";
