<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce permission
requirePermission('inventory', 'add');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        fgetcsv($handle, 1000, ","); // Skip Header
        
        $success_count = 0;
        $error_count = 0;
        
        $stmt = $pdo->prepare("INSERT INTO sites (
            site_code, name, location, area, city, district, state, type, 
            width, height, facing, light_type, owner_type, vendor_id, 
            card_rate, purchase_rate, available_from, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')");
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty($data[0]) && empty($data[1])) continue; // Skip empty rows
            
            try {
                // Column Mapping:
                // 0: Media Code
                // 1: Site Location/Name
                // 2: Landmark
                // 3: Area
                // 4: City
                // 5: District
                // 6: State
                // 7: Media Type
                // 8: Width (ft)
                // 9: Height (ft)
                // 10: Facing
                // 11: Illumination (BL, NL, FL)
                // 12: Ownership (HA, TA)
                // 13: Vendor ID (Integer or empty)
                // 14: Card Rate (Selling Price)
                // 15: Purchase Rate (Cost)
                // 16: Available From (YYYY-MM-DD or empty)
                
                $code = clean($data[0]);
                $name = clean($data[1]);
                $location = clean($data[2] ?? '');
                $area = clean($data[3] ?? '');
                $city = clean($data[4] ?? '');
                $district = clean($data[5] ?? '');
                $state = clean($data[6] ?? '');
                $type = clean($data[7] ?? 'Billboard');
                $width = floatval($data[8] ?? 0);
                $height = floatval($data[9] ?? 0);
                $facing = clean($data[10] ?? 'Front');
                
                // Normalise Illumination
                $illum = strtoupper(trim($data[11] ?? 'NL'));
                if ($illum === 'BACKLIT' || $illum === 'BACK-LIT') $illum = 'BL';
                elseif ($illum === 'FRONTLIT' || $illum === 'FRONT-LIT') $illum = 'FL';
                elseif ($illum === 'NONLIT' || $illum === 'NON-LIT' || $illum === 'NO') $illum = 'NL';
                if (!in_array($illum, ['BL', 'NL', 'FL'])) $illum = 'NL';
                
                // Ownership
                $owner = strtoupper(trim($data[12] ?? 'HA'));
                if ($owner !== 'TA') $owner = 'HA';
                
                $vendor_id = !empty($data[13]) ? intval($data[13]) : null;
                $card_rate = floatval($data[14] ?? 0);
                $purchase_rate = floatval($data[15] ?? 0);
                
                $avail = trim($data[16] ?? '');
                if (empty($avail)) {
                    $avail = date('Y-m-d');
                } else {
                    $avail = date('Y-m-d', strtotime($avail));
                }
                
                // Handle duplicate asset code check
                if (!empty($code)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM sites WHERE site_code = ?");
                    $checkStmt->execute([$code]);
                    if ($checkStmt->fetch()) {
                        // Skip or suffix duplicate
                        $code .= '-DUP';
                    }
                }
                
                $stmt->execute([
                    $code, $name, $location, $area, $city, $district, $state, $type,
                    $width, $height, $facing, $illum, $owner, $vendor_id,
                    $card_rate, $purchase_rate, $avail
                ]);
                
                $success_count++;
            } catch (Exception $e) {
                $error_count++;
            }
        }
        fclose($handle);
        header("Location: ../modules/inventory/sites.php?msg=imported&success=$success_count&errors=$error_count");
        exit;
    }
}
?>
