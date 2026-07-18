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

if (!canAdd('inventory')) {
    echo json_encode(['success' => false, 'message' => 'Aapke paas site add karne ki permission nahi hai.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

$code = clean($_POST['site_code'] ?? '');
$name = clean($_POST['name'] ?? '');
$location = clean($_POST['location'] ?? '');
$city = clean($_POST['city'] ?? '');
$district = clean($_POST['district'] ?? '');
$area = clean($_POST['area'] ?? '');
$latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
$type = clean($_POST['type'] ?? '');
$width = floatval($_POST['width'] ?? 0);
$height = floatval($_POST['height'] ?? 0);
$owner_type = clean($_POST['owner_type'] ?? 'HA');
$vendor_id = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
$card_rate = floatval($_POST['card_rate'] ?? 0);
$purchase_rate = floatval($_POST['purchase_rate'] ?? 0);
$facing = clean($_POST['facing'] ?? '');
$light_type = clean($_POST['light_type'] ?? 'NL');
$hsn_code = clean($_POST['hsn_code'] ?? '998366');
$mounting_hsn = clean($_POST['mounting_hsn'] ?? '');
$vendor_gst = clean($_POST['vendor_gst'] ?? '');
$grade = clean($_POST['grade'] ?? 'A');
$available_from = !empty($_POST['available_from']) ? $_POST['available_from'] : date('Y-m-d');

if (empty($code) || empty($name) || empty($location) || empty($city) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

// Check duplicate site code
$dupStmt = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE site_code = ?");
$dupStmt->execute([$code]);
if ($dupStmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Site code already exists. Please choose a unique site code.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO sites (site_code, name, location, area, city, district, latitude, longitude, type, width, height, facing, light_type, hsn_code, mounting_hsn, vendor_gst, grade, owner_type, vendor_id, card_rate, purchase_rate, available_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $name, $location, $area, $city, $district, $latitude, $longitude, $type, $width, $height, $facing, $light_type, $hsn_code, $mounting_hsn, $vendor_gst, $grade, $owner_type, $vendor_id, $card_rate, $purchase_rate, $available_from]);
    $site_id = $pdo->lastInsertId();

    // Handle Multi-Image Upload
    $uploadedImages = [];
    if (!empty($_FILES['site_images']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/sites/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['site_images']['name'] as $key => $val) {
            $filename = time() . '_' . $site_id . '_' . basename($_FILES['site_images']['name'][$key]);
            $targetFile = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['site_images']['tmp_name'][$key], $targetFile)) {
                $pdo->prepare("INSERT INTO site_images (site_id, filename) VALUES (?, ?)")->execute([$site_id, $filename]);
                $uploadedImages[] = $filename;
            }
        }
    }

    // Fetch vendor name if TA
    $vendor_name = '';
    if ($owner_type === 'TA' && $vendor_id) {
        $vStmt = $pdo->prepare("SELECT name FROM partners WHERE id = ?");
        $vStmt->execute([$vendor_id]);
        $vendor_name = $vStmt->fetchColumn() ?: '';
    }

    echo json_encode([
        'success' => true,
        'id' => $site_id,
        'name' => $name,
        'rate' => $purchase_rate,
        'site_code' => $code,
        'location' => $location,
        'vendor_id' => $vendor_id,
        'thumbnail' => !empty($uploadedImages) ? $uploadedImages[0] : '',
        'city' => $city,
        'card_rate' => $card_rate,
        'size' => $width . 'x' . $height,
        'type' => $type,
        'light_type' => $light_type,
        'owner_type' => $owner_type,
        'vendor_name' => $vendor_name,
        'all_images' => implode(',', $uploadedImages),
        'message' => 'Site added successfully!'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
