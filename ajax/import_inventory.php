<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        fgetcsv($handle, 1000, ","); // Skip Header
        
        $success_count = 0;
        $error_count = 0;
        
        $stmt = $pdo->prepare("INSERT INTO inventory (site_name, category, media_type, address, city, state, district, width, height, unit, facing, illumination, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')");
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty($data[0])) continue;
            
            try {
                // Order: Site Name, Category, Media Type, Address, City, State, District, Width, Height, Unit, Facing, Illumination
                $stmt->execute([
                    $data[0], $data[1], $data[2], $data[3], $data[4], 
                    $data[5], $data[6], $data[7], $data[8], $data[9], 
                    $data[10], $data[11]
                ]);
                $success_count++;
            } catch (Exception $e) {
                $error_count++;
            }
        }
        fclose($handle);
        header("Location: ../modules/inventory/inventory.php?msg=imported&success=$success_count&errors=$error_count");
        exit;
    }
}
?>
