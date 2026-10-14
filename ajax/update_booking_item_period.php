<?php
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
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

        if (in_array($field, ['print_start_date', 'print_end_date', 'print_days', 'mount_start_date', 'mount_end_date', 'mount_days'])) {
            $prefix = strpos($field, 'print_') === 0 ? 'print_' : 'mount_';
            $isStart = strpos($field, 'start_date') !== false;
            $isEnd = strpos($field, 'end_date') !== false;
            $isDays = strpos($field, 'days') !== false;
            
            $stmt = $pdo->prepare("SELECT {$prefix}start_date as sdate, {$prefix}end_date as edate, {$prefix}days as days FROM booking_items WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $currStart = $row['sdate'];
            $currEnd = $row['edate'];
            $currDays = intval($row['days'] ?: 30);
            
            if ($isStart) {
                $dateVal = clean($value);
                if (!empty($dateVal)) {
                    if (!empty($currEnd)) {
                        $days = max(1, ceil((strtotime($currEnd) - strtotime($dateVal)) / 86400) + 1);
                        $endDate = $currEnd;
                    } else {
                        $days = $currDays;
                        $endDate = date('Y-m-d', strtotime($dateVal . " + " . ($days - 1) . " days"));
                    }
                    $stmt = $pdo->prepare("UPDATE booking_items SET {$prefix}start_date = ?, {$prefix}end_date = ?, {$prefix}days = ? WHERE id = ?");
                    $stmt->execute([$dateVal, $endDate, $days, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE booking_items SET {$prefix}start_date = NULL, {$prefix}end_date = NULL WHERE id = ?");
                    $stmt->execute([$id]);
                }
            } elseif ($isEnd) {
                $dateVal = clean($value);
                if (!empty($dateVal)) {
                    if (!empty($currStart)) {
                        $days = max(1, ceil((strtotime($dateVal) - strtotime($currStart)) / 86400) + 1);
                        $startDate = $currStart;
                    } else {
                        $days = $currDays;
                        $startDate = date('Y-m-d', strtotime($dateVal . " - " . ($days - 1) . " days"));
                    }
                    $stmt = $pdo->prepare("UPDATE booking_items SET {$prefix}start_date = ?, {$prefix}end_date = ?, {$prefix}days = ? WHERE id = ?");
                    $stmt->execute([$startDate, $dateVal, $days, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE booking_items SET {$prefix}end_date = NULL WHERE id = ?");
                    $stmt->execute([$id]);
                }
            } elseif ($isDays) {
                $daysVal = max(1, intval($value));
                if (!empty($currStart)) {
                    $endDate = date('Y-m-d', strtotime($currStart . " + " . ($daysVal - 1) . " days"));
                    $stmt = $pdo->prepare("UPDATE booking_items SET {$prefix}days = ?, {$prefix}end_date = ? WHERE id = ?");
                    $stmt->execute([$daysVal, $endDate, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE booking_items SET {$prefix}days = ? WHERE id = ?");
                    $stmt->execute([$daysVal, $id]);
                }
            }
            $success = true;
        } elseif ($field === 'start_date') {
            $dateVal = clean($value);
            if (!empty($dateVal)) {
                $stmt = $pdo->prepare("SELECT bi.end_date, bi.days FROM booking_items bi WHERE bi.id = ?");
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
                $stmt = $pdo->prepare("SELECT bi.start_date, bi.days FROM booking_items bi WHERE bi.id = ?");
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
            
            $stmt = $pdo->prepare("SELECT bi.start_date FROM booking_items bi WHERE bi.id = ?");
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
            // Recalculate Booking Totals and Auto-sync linked Tax Invoices
            $stmtFetch = $pdo->prepare("SELECT booking_id FROM booking_items WHERE id = ?");
            $stmtFetch->execute([$id]);
            $bookingId = intval($stmtFetch->fetchColumn());

            if ($bookingId) {
                syncBookingInvoiceFinancials($pdo, $bookingId);
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
