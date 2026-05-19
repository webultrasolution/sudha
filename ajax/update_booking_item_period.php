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
                $stmt = $pdo->prepare("SELECT end_date, days FROM booking_items WHERE id = ?");
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
                
                $stmt = $pdo->prepare("UPDATE booking_items SET start_date = ?, end_date = ?, days = ? WHERE id = ?");
                $stmt->execute([$dateVal, $endDate, $days, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE booking_items SET start_date = NULL, end_date = NULL WHERE id = ?");
                $stmt->execute([$id]);
            }
            $success = true;
        } elseif ($field === 'end_date') {
            $dateVal = clean($value);
            if (!empty($dateVal)) {
                $stmt = $pdo->prepare("SELECT start_date, days FROM booking_items WHERE id = ?");
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
                
                $stmt = $pdo->prepare("UPDATE booking_items SET start_date = ?, end_date = ?, days = ? WHERE id = ?");
                $stmt->execute([$startDate, $dateVal, $days, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE booking_items SET end_date = NULL WHERE id = ?");
                $stmt->execute([$id]);
            }
            $success = true;
        } elseif ($field === 'days') {
            $daysVal = max(1, intval($value));
            
            $stmt = $pdo->prepare("SELECT start_date FROM booking_items WHERE id = ?");
            $stmt->execute([$id]);
            $startDate = $stmt->fetchColumn();
            
            if (!empty($startDate)) {
                $endDate = date('Y-m-d', strtotime($startDate . " + " . ($daysVal - 1) . " days"));
                $stmt = $pdo->prepare("UPDATE booking_items SET days = ?, end_date = ? WHERE id = ?");
                $stmt->execute([$daysVal, $endDate, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE booking_items SET days = ? WHERE id = ?");
                $stmt->execute([$daysVal, $id]);
            }
            $success = true;
        }

        if ($success) {
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
