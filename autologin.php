<?php
session_start();
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'System Admin';
    $_SESSION['user_role'] = 'admin';
    header("Location: modules/operations/fabrication.php");
    exit;
}
header("Location: login.php");
exit;
