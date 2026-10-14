<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    $success = false;

    if ($field === 'sale_rate') {
        $rateVal = floatval($value);
        $stmt = $pdo->prepare("UPDATE proposal_items SET sale_rate = ?, amount = sale_rate * COALESCE(unit, 1) WHERE id = ?");
        $stmt->execute([$rateVal, $id]);
        $success = true;
    } elseif ($field === 'unit') {
        $unitVal = max(1, intval($value));
        $stmt = $pdo->prepare("UPDATE proposal_items SET unit = ?, amount = sale_rate * ? WHERE id = ?");
        $stmt->execute([$unitVal, $unitVal, $id]);
        $success = true;
    } elseif ($field === 'amount') {
        $amountVal = floatval($value);
        $stmt = $pdo->prepare("UPDATE proposal_items SET amount = ? WHERE id = ?");
        $stmt->execute([$amountVal, $id]);
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
            
            $stmt = $pdo->prepare("UPDATE proposal_items SET start_date = ?, end_date = ?, days = ? WHERE id = ?");
            $stmt->execute([$dateVal, $endDate, $days, $id]);
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
            
            $stmt = $pdo->prepare("UPDATE proposal_items SET start_date = ?, end_date = ?, days = ? WHERE id = ?");
            $stmt->execute([$startDate, $dateVal, $days, $id]);
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
            $stmt = $pdo->prepare("UPDATE proposal_items SET days = ?, end_date = ? WHERE id = ?");
            $stmt->execute([$daysVal, $endDate, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE proposal_items SET days = ? WHERE id = ?");
            $stmt->execute([$daysVal, $id]);
        }
        $success = true;
    }

    if ($success) {
        // Fetch proposal_id - use parameterized query
        $stmt = $pdo->prepare("SELECT proposal_id FROM proposal_items WHERE id = ?");
        $stmt->execute([$id]);
        $propId = $stmt->fetchColumn();

        recalculateProposalTotals($pdo, $propId);

        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $stmt = $pdo->prepare("SELECT proposal_number FROM proposals WHERE id = ?");
            $stmt->execute([$propId]);
            $propRef = $stmt->fetchColumn();
            revertToPendingOnEdit($pdo, 'proposals', $propId, 'proposal', $propRef, $_SESSION['user_id'] ?? 0);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Field not supported']);
    }
}
?>
