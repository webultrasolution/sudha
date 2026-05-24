<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $bookingId = intval($data['booking_id'] ?? 0);
    $sitesData = $data['sites'] ?? [];

    if (!$bookingId || empty($sitesData)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtBooking = $pdo->prepare("SELECT start_date, end_date FROM bookings WHERE id = ?");
        $stmtBooking->execute([$bookingId]);
        $booking = $stmtBooking->fetch();
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }

        $stmtSite = $pdo->prepare("SELECT card_rate, purchase_rate FROM sites WHERE id = ?");
        // We include selected_image since we added it to booking_items
        $stmtItem = $pdo->prepare("INSERT INTO booking_items (booking_id, site_id, purchase_rate, sale_rate, start_date, end_date, days, purchase_amount, amount, selected_image) VALUES (?, ?, ?, ?, ?, ?, 30, 0, ?, ?)");
        $stmtOps = $pdo->prepare("INSERT INTO operations (booking_id, site_id, status) VALUES (?, ?, 'pending')");

        foreach ($sitesData as $s) {
            $sid = intval($s['id']);
            $img = isset($s['image']) && $s['image'] ? $s['image'] : null;

            // Check if already in booking
            $chk = $pdo->prepare("SELECT id FROM booking_items WHERE booking_id = ? AND site_id = ?");
            $chk->execute([$bookingId, $sid]);
            if ($chk->fetch()) continue; // Skip existing

            $stmtSite->execute([$sid]);
            $site = $stmtSite->fetch();
            if ($site) {
                $pRate = floatval($site['purchase_rate']);
                $sRate = floatval($site['card_rate']); // Default to card rate
                
                // Insert booking item
                $stmtItem->execute([
                    $bookingId, 
                    $sid, 
                    $pRate, 
                    $sRate, 
                    $booking['start_date'], 
                    $booking['end_date'], 
                    $sRate, 
                    $img
                ]);

                // Create operation task
                $stmtOps->execute([$bookingId, $sid]);
            }
        }

        // Recalculate totals
        $newTotal = $pdo->query("SELECT SUM(amount) FROM booking_items WHERE booking_id = $bookingId")->fetchColumn() ?: 0;
        $tax = $newTotal * 0.18; // Simple tax logic, assuming 18% GST on all for now, or you could retain the original logic
        
        $p = $pdo->query("SELECT total_amount, tax_amount, grand_total FROM bookings WHERE id = $bookingId")->fetch();
        // Here we just use the new totals
        $newGrand = $newTotal + $tax;

        $pdo->prepare("UPDATE bookings SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?")
            ->execute([$newTotal, $tax, $newGrand, $bookingId]);

        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $propRef = $pdo->query("SELECT id FROM bookings WHERE id = $bookingId")->fetchColumn();
            revertToPendingOnEdit($pdo, 'bookings', $bookingId, 'booking', 'Booking #'.$propRef, $_SESSION['user_id'] ?? 0);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
