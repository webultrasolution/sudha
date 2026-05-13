<?php
include_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("SELECT s.id, s.site_code, (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumb FROM sites s LIMIT 10");
$results = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
