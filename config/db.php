<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'easy_outdoor_crm');
define('DB_USER', 'root');
define('DB_PASS', '');

// App Constants
define('APP_NAME', 'Easy Outdoor CRM');
define('BASE_URL', 'http://localhost/easy-outdoor-crm/');
define('GST_RATE', 18); // Default GST percentage

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
