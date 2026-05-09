<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u511039083_sudha');
define('DB_USER', 'u511039083_sudha');
define('DB_PASS', 'H7+iO8c6C');

// App Constants
define('APP_NAME', 'Sudha Creative CRM');
define('BASE_URL', 'https://sudha.webultrasolution.io/');
define('GST_RATE', 18); // Default GST percentage

// Company Details
define('COMPANY_NAME', 'Sudha Creative Media');
define('COMPANY_ADDRESS', '4th Floor, Skyline Plaza, Business District');
define('COMPANY_CITY', 'Mumbai, Maharashtra - 400013');
define('COMPANY_GSTIN', '27ABCDE1234F1Z1');
define('COMPANY_PHONE', '+91 99887 76655');
define('COMPANY_EMAIL', 'info@easyoutdoor.com');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
