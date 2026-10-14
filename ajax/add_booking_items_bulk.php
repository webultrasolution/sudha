<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check permission
    if (!canEdit('bookings')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to edit bookings.']);
        exit;
    }

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

        $days = 30;
        if ($booking && !empty($booking['start_date']) && !empty($booking['end_date'])) {
            $d1 = new DateTime($booking['start_date']);
            $d2 = new DateTime($booking['end_date']);
            $days = $d1->diff($d2)->days + 1;
        }

        // Fetch existing service rates and rental status for this booking
        $stmtRates = $pdo->prepare("
            SELECT client_print_rate, client_mount_rate, amount 
            FROM booking_items 
            WHERE booking_id = ? 
            ORDER BY id ASC LIMIT 1
        ");
        $stmtRates->execute([$bookingId]);
        $existingRates = $stmtRates->fetch();

        $defaultPrintRate = 0.00;
        $defaultMountRate = 0.00;
        $rentalIsActive = true; // default to true

        if ($existingRates) {
            $defaultPrintRate = floatval($existingRates['client_print_rate']);
            $defaultMountRate = floatval($existingRates['client_mount_rate']);
            $rentalIsActive = floatval($existingRates['amount']) > 0;
        }

        $stmtSite = $pdo->prepare("SELECT card_rate, purchase_rate, owner_type FROM sites WHERE id = ?");
        // We include selected_image, client_print_rate, client_mount_rate
        $stmtItem = $pdo->prepare("
            INSERT INTO booking_items 
            (booking_id, site_id, purchase_rate, sale_rate, start_date, end_date, days, purchase_amount, amount, selected_image, client_print_rate, client_mount_rate) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
        ");
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
                $pRate = ($site['owner_type'] === 'HA') ? 0 : floatval($site['purchase_rate']);
                $sRate = floatval($site['card_rate']); // Default to card rate
                $itemAmount = $sRate * $days / 30;
                $finalAmount = $rentalIsActive ? $itemAmount : 0;
                
                // Insert booking item
                $stmtItem->execute([
                    $bookingId, 
                    $sid, 
                    $pRate, 
                    $sRate, 
                    $booking['start_date'], 
                    $booking['end_date'], 
                    $days, 
                    $finalAmount, 
                    $img,
                    $defaultPrintRate,
                    $defaultMountRate
                ]);

                // Create operation task
                $stmtOps->execute([$bookingId, $sid]);
            }
        }

        // Check if invoice exists and user is not admin
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $stmtInvCheck = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ? AND type = 'tax' AND approval_status != 'rejected' LIMIT 1");
            $stmtInvCheck->execute([$bookingId]);
            if ($stmtInvCheck->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Adding items is locked as a Tax Invoice has already been generated. Only Admin can modify items.']);
                exit;
            }
        }

        // Auto-sync booking totals and any linked Tax Invoice
        syncBookingInvoiceFinancials($pdo, $bookingId);

        if (!$isAdmin) {
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ?");
            $stmt->execute([$bookingId]);
            $propRef = $stmt->fetchColumn();
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
