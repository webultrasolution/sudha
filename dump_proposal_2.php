<?php
require 'config/db.php';
$stmt = $pdo->prepare("SELECT * FROM proposals WHERE id = 2");
$stmt->execute();
print_r($stmt->fetch());
