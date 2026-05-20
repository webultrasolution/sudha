<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $field = $_POST['field'];
    $value = $_POST['value'];

    $success = false;

    if ($field === 'sale_rate') {
        $rateVal = floatval($value);
        $stmt = $pdo->prepare("UPDATE proposal_items SET sale_rate = ?, amount = sale_rate * COALESCE(days, 30) / 30 WHERE id = ?");
        $stmt->execute([$rateVal, $id]);
        $success = true;
    } elseif ($field === 'start_date') {
        $dateVal = clean($value);
        if (!empty($dateVal)) {
            $stmt = $pdo->prepare("SELECT end_date, days FROM proposal_items WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $currEndDate = $row['end_date'];
            $currDays = intval($row['days'] ?: 30);
            
            if (!empty($currEndDate)) {
                $days = max(1, ceil((strtotime($currEndDate) - strtotime($dateVal)) / 86400) + 1);
                $endDate = $currEndDate;
            } else {
                $days = $currDays;
                $endDate = date('Y-m-d', strtotime($dateVal . " + " . ($days - 1) . " days"));
            }
            
            $stmt = $pdo->prepare("UPDATE proposal_items SET start_date = ?, end_date = ?, days = ?, amount = sale_rate * ? / 30 WHERE id = ?");
            $stmt->execute([$dateVal, $endDate, $days, $days, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE proposal_items SET start_date = NULL, end_date = NULL WHERE id = ?");
            $stmt->execute([$id]);
        }
        $success = true;
    } elseif ($field === 'end_date') {
        $dateVal = clean($value);
        if (!empty($dateVal)) {
            $stmt = $pdo->prepare("SELECT start_date, days FROM proposal_items WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $currStartDate = $row['start_date'];
            $currDays = intval($row['days'] ?: 30);
            
            if (!empty($currStartDate)) {
                $days = max(1, ceil((strtotime($dateVal) - strtotime($currStartDate)) / 86400) + 1);
                $startDate = $currStartDate;
            } else {
                $days = $currDays;
                $startDate = date('Y-m-d', strtotime($dateVal . " - " . ($days - 1) . " days"));
            }
            
            $stmt = $pdo->prepare("UPDATE proposal_items SET start_date = ?, end_date = ?, days = ?, amount = sale_rate * ? / 30 WHERE id = ?");
            $stmt->execute([$startDate, $dateVal, $days, $days, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE proposal_items SET end_date = NULL WHERE id = ?");
            $stmt->execute([$id]);
        }
        $success = true;
    } elseif ($field === 'days') {
        $daysVal = max(1, intval($value));
        
        $stmt = $pdo->prepare("SELECT start_date FROM proposal_items WHERE id = ?");
        $stmt->execute([$id]);
        $startDate = $stmt->fetchColumn();
        
        if (!empty($startDate)) {
            $endDate = date('Y-m-d', strtotime($startDate . " + " . ($daysVal - 1) . " days"));
            $stmt = $pdo->prepare("UPDATE proposal_items SET days = ?, end_date = ?, amount = sale_rate * ? / 30 WHERE id = ?");
            $stmt->execute([$daysVal, $endDate, $daysVal, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE proposal_items SET days = ?, amount = sale_rate * ? / 30 WHERE id = ?");
            $stmt->execute([$daysVal, $daysVal, $id]);
        }
        $success = true;
    }

    if ($success) {
        // Fetch proposal_id
        $propId = $pdo->query("SELECT proposal_id FROM proposal_items WHERE id = $id")->fetchColumn();
        
        // Recalculate proposal total
        $newTotal = $pdo->query("SELECT SUM(amount) FROM proposal_items WHERE proposal_id = $propId")->fetchColumn();
        $tax = $newTotal * 0.18;
        
        $p = $pdo->query("SELECT printing_cost, mounting_cost, discounting_pct FROM proposals WHERE id = $propId")->fetch();
        $base = $newTotal - ($newTotal * ($p['discounting_pct'] / 100));
        $newGrand = $base + $tax + $p['printing_cost'] + $p['mounting_cost'];
        
        $pdo->prepare("UPDATE proposals SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?")
            ->execute([$newTotal, $tax, $newGrand, $propId]);

        // Revert proposal to pending_approval if non-admin edited an approved proposal
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $propRef = $pdo->query("SELECT proposal_number FROM proposals WHERE id = $propId")->fetchColumn();
            revertToPendingOnEdit($pdo, 'proposals', $propId, 'proposal', $propRef, $_SESSION['user_id'] ?? 0);
        }
            
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Field not supported']);
    }
}
?>
