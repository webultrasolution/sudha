<?php
include_once __DIR__ . '/../config/db.php';
echo "--- PROPOSALS ---\n";
$stmt = $pdo->query("DESCRIBE proposals");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
