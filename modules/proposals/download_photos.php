<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Invalid Proposal ID");

// Fetch Proposal Details
$stmt = $pdo->prepare("SELECT campaign_name FROM proposals WHERE id = ?");
$stmt->execute([$id]);
$proposal = $stmt->fetch();
if (!$proposal) die("Proposal not found");

// Fetch All Site Images for this Proposal
$stmt = $pdo->prepare("
    SELECT si.filename, s.location, s.city 
    FROM proposal_items pi 
    JOIN site_images si ON pi.site_id = si.site_id 
    JOIN sites s ON pi.site_id = s.id
    WHERE pi.proposal_id = ?
");
$stmt->execute([$id]);
$images = $stmt->fetchAll();

if (empty($images)) {
    die("No photos found for this proposal.");
}

// Create ZIP
$zipName = "Photos_" . str_replace(' ', '_', $proposal['campaign_name']) . "_" . date('Ymd') . ".zip";
$zipPath = sys_get_temp_dir() . '/' . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Could not create ZIP archive");
}

$uploadDir = __DIR__ . '/../../uploads/sites/';
$addedFiles = [];

foreach ($images as $img) {
    $filePath = $uploadDir . $img['filename'];
    if (file_exists($filePath)) {
        // Create a friendly name for the image in the zip
        $friendlyName = $img['city'] . "_" . str_replace(['/', '\\', ' ', ','], '_', $img['location']) . "_" . basename($img['filename']);
        $zip->addFile($filePath, $friendlyName);
        $addedFiles[] = $filePath;
    }
}

$zip->close();

if (empty($addedFiles)) {
    die("Photos were found in database but files are missing on server.");
}

// Serve ZIP
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipPath));
header('Pragma: no-cache');
header('Expires: 0');
readfile($zipPath);

// Cleanup
unlink($zipPath);
exit;
