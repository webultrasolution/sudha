<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$owner_type = clean($_GET['owner_type'] ?? 'HA');
$vendor_id = !empty($_GET['vendor_id']) ? intval($_GET['vendor_id']) : null;

function generateNextCode($last_code, $default_prefix) {
    if (preg_match('/^([A-Za-z]+)([0-9]+)$/', $last_code, $matches)) {
        $prefix = $matches[1];
        $number_part = $matches[2];
        $next_number = intval($number_part) + 1;
        // Make sure we pad to at least 3 digits if it's shorter
        $pad_len = max(strlen($number_part), 3);
        $padded = str_pad($next_number, $pad_len, '0', STR_PAD_LEFT);
        return $prefix . $padded;
    }
    return $default_prefix . '001';
}

try {
    if ($owner_type === 'HA') {
        // Get last SC code
        $stmt = $pdo->prepare("SELECT site_code FROM sites WHERE owner_type = 'HA' AND site_code LIKE 'SC%' ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $last_code = $stmt->fetchColumn();
        
        $next_code = generateNextCode($last_code ?: 'SC000', 'SC');
        
        // Ensure uniqueness
        while (true) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE site_code = ?");
            $chk->execute([$next_code]);
            if ($chk->fetchColumn() == 0) {
                break;
            }
            $next_code = generateNextCode($next_code, 'SC');
        }
        
        echo json_encode(['success' => true, 'site_code' => $next_code]);
        exit;
    } else {
        // Owner type is TA (Vendor)
        if (!$vendor_id) {
            echo json_encode(['success' => false, 'message' => 'Vendor ID is required for Third Party sites.']);
            exit;
        }
        
        // Find last site code for this vendor
        $stmt = $pdo->prepare("SELECT site_code FROM sites WHERE owner_type = 'TA' AND vendor_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$vendor_id]);
        $last_code = $stmt->fetchColumn();
        
        if ($last_code) {
            $next_code = generateNextCode($last_code, 'VN');
        } else {
            // Generate a prefix from vendor name
            $vStmt = $pdo->prepare("SELECT name FROM partners WHERE id = ?");
            $vStmt->execute([$vendor_id]);
            $vendor_name = $vStmt->fetchColumn();
            
            $prefix = 'VN';
            if ($vendor_name) {
                // Keep only letters, convert to uppercase, take first 3 chars
                $cleaned = preg_replace('/[^A-Za-z]/', '', $vendor_name);
                if (strlen($cleaned) >= 2) {
                    $prefix = strtoupper(substr($cleaned, 0, 3));
                }
            }
            $next_code = $prefix . '001';
        }
        
        // Ensure uniqueness
        while (true) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE site_code = ?");
            $chk->execute([$next_code]);
            if ($chk->fetchColumn() == 0) {
                break;
            }
            $next_code = generateNextCode($next_code, 'VN');
        }
        
        echo json_encode(['success' => true, 'site_code' => $next_code]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
