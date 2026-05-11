<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $type = $_POST['type'] ?? 'client'; 
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        fgetcsv($handle, 1000, ","); // Skip Header
        
        $success_count = 0;
        $error_count = 0;
        $duplicate_count = 0;
        
        // Comprehensive Insert Statement
        $sql = "INSERT INTO partners (name, business_type, contact_person, phone, email, address, city, state, district, pincode, gstin, pan, billing_address, payment_terms, status, type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";
        $stmt = $pdo->prepare($sql);
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty($data[0])) continue; 

            // Check if GST already exists for this type
            if (!empty($data[5])) {
                $check = $pdo->prepare("SELECT id FROM partners WHERE gstin = ? AND type = ?");
                $check->execute([$data[5], $type]);
                if ($check->fetch()) {
                    $duplicate_count++;
                    continue;
                }
            }
            
            try {
                $stmt->execute([
                    $data[0],  // Name
                    $data[1],  // Business Type
                    $data[2],  // Contact Person
                    $data[3],  // Phone
                    $data[4],  // Email
                    $data[7],  // Address
                    $data[8],  // City
                    $data[9],  // State
                    $data[10], // District
                    $data[11], // Pincode
                    $data[5],  // GSTIN
                    $data[6],  // PAN
                    $data[7],  // Billing Address (Default to Address)
                    ($type === 'vendor' ? '30 Days' : ''), // Default Payment Terms
                    $type      // client/vendor
                ]);
                $success_count++;
            } catch (Exception $e) {
                $error_count++;
            }
        }
        fclose($handle);
        
        $redirect = ($type === 'vendor') ? '../modules/partners/vendors.php' : '../modules/partners/clients.php';
        header("Location: $redirect?msg=imported&success=$success_count&duplicates=$duplicate_count&errors=$error_count");
        exit;
    }
}

?>
