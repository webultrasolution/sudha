<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    if ($id <= 0 || empty($field)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $success = false;

        if ($field === 'start_date') {
            $dateVal = clean($value);
            if (!empty($dateVal)) {
                $stmt = $pdo->prepare("SELECT end_date, days, amount, purchase_amount, sale_rate, purchase_rate FROM booking_items WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                $currEndDate = $row['end_date'];
                $currDays = intval($row['days'] ?: 30);
                
                $saleRate = floatval($row['sale_rate'] ?: $row['amount']);
                $purchaseRate = floatval($row['purchase_rate'] ?: $row['purchase_amount']);
                
                if (!empty($currEndDate)) {
                    $days = max(1, ceil((strtotime($currEndDate) - strtotime($dateVal)) / 86400) + 1);
                    $endDate = $currEndDate;
                } else {
                    $days = $currDays;
                    $endDate = date('Y-m-d', strtotime($dateVal . " + " . ($days - 1) . " days"));
                }
                
                $newAmount = $saleRate * $days / 30;
                $newPurchaseAmount = $purchaseRate * $days / 30;
                
                $stmt = $pdo->prepare("UPDATE booking_items SET start_date = ?, end_date = ?, days = ?, amount = ?, purchase_amount = ?, sale_rate = ?, purchase_rate = ? WHERE id = ?");
                $stmt->execute([$dateVal, $endDate, $days, $newAmount, $newPurchaseAmount, $saleRate, $purchaseRate, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE booking_items SET start_date = NULL, end_date = NULL WHERE id = ?");
                $stmt->execute([$id]);
            }
            $success = true;
        } elseif ($field === 'end_date') {
            $dateVal = clean($value);
            if (!empty($dateVal)) {
                $stmt = $pdo->prepare("SELECT start_date, days, amount, purchase_amount, sale_rate, purchase_rate FROM booking_items WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                $currStartDate = $row['start_date'];
                $currDays = intval($row['days'] ?: 30);
                
                $saleRate = floatval($row['sale_rate'] ?: $row['amount']);
                $purchaseRate = floatval($row['purchase_rate'] ?: $row['purchase_amount']);
                
                if (!empty($currStartDate)) {
                    $days = max(1, ceil((strtotime($dateVal) - strtotime($currStartDate)) / 86400) + 1);
                    $startDate = $currStartDate;
                } else {
                    $days = $currDays;
                    $startDate = date('Y-m-d', strtotime($dateVal . " - " . ($days - 1) . " days"));
                }
                
                $newAmount = $saleRate * $days / 30;
                $newPurchaseAmount = $purchaseRate * $days / 30;
                
                $stmt = $pdo->prepare("UPDATE booking_items SET start_date = ?, end_date = ?, days = ?, amount = ?, purchase_amount = ?, sale_rate = ?, purchase_rate = ? WHERE id = ?");
                $stmt->execute([$startDate, $dateVal, $days, $newAmount, $newPurchaseAmount, $saleRate, $purchaseRate, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE booking_items SET end_date = NULL WHERE id = ?");
                $stmt->execute([$id]);
            }
            $success = true;
        } elseif ($field === 'days') {
            $daysVal = max(1, intval($value));
            
            $stmt = $pdo->prepare("SELECT start_date, amount, purchase_amount, sale_rate, purchase_rate FROM booking_items WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $startDate = $row['start_date'];
            
            $saleRate = floatval($row['sale_rate'] ?: $row['amount']);
            $purchaseRate = floatval($row['purchase_rate'] ?: $row['purchase_amount']);
            
            $newAmount = $saleRate * $daysVal / 30;
            $newPurchaseAmount = $purchaseRate * $daysVal / 30;
            
            if (!empty($startDate)) {
                $endDate = date('Y-m-d', strtotime($startDate . " + " . ($daysVal - 1) . " days"));
                $stmt = $pdo->prepare("UPDATE booking_items SET days = ?, end_date = ?, amount = ?, purchase_amount = ?, sale_rate = ?, purchase_rate = ? WHERE id = ?");
                $stmt->execute([$daysVal, $endDate, $newAmount, $newPurchaseAmount, $saleRate, $purchaseRate, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE booking_items SET days = ?, amount = ?, purchase_amount = ?, sale_rate = ?, purchase_rate = ? WHERE id = ?");
                $stmt->execute([$daysVal, $newAmount, $newPurchaseAmount, $saleRate, $purchaseRate, $id]);
            }
            $success = true;
        }

        if ($success) {
            // Recalculate Booking Totals
            $stmtFetch = $pdo->prepare("SELECT booking_id FROM booking_items WHERE id = ?");
            $stmtFetch->execute([$id]);
            $bookingId = intval($stmtFetch->fetchColumn());

            if ($bookingId) {
                $stmtSums = $pdo->prepare("SELECT SUM(amount) as subtotal FROM booking_items WHERE booking_id = ?");
                $stmtSums->execute([$bookingId]);
                $newSubtotal = floatval($stmtSums->fetchColumn() ?: 0);

                $stmtTaxType = $pdo->prepare("SELECT tax_type FROM bookings WHERE id = ?");
                $stmtTaxType->execute([$bookingId]);
                $tax_type = $stmtTaxType->fetchColumn() ?: 'igst';

                $tax = 0;
                if ($tax_type !== 'none') {
                    $tax = $newSubtotal * 0.18;
                }
                $grand = $newSubtotal + $tax;

                $stmtUpdate = $pdo->prepare("UPDATE bookings SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?");
                $stmtUpdate->execute([$newSubtotal, $tax, $grand, $bookingId]);
                
                // Also recalculate and update the overall booking start/end dates
                $stmtDates = $pdo->prepare("SELECT MIN(start_date) as min_start, MAX(end_date) as max_end FROM booking_items WHERE booking_id = ?");
                $stmtDates->execute([$bookingId]);
                $dates = $stmtDates->fetch();
                if ($dates && $dates['min_start'] && $dates['max_end']) {
                    $stmtUpdateDates = $pdo->prepare("UPDATE bookings SET start_date = ?, end_date = ? WHERE id = ?");
                    $stmtUpdateDates->execute([$dates['min_start'], $dates['max_end'], $bookingId]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Unsupported field");
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
