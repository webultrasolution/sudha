<?php

if (session_status() === PHP_SESSION_NONE) {
    // Set session lifetime to 30 days (2592000 seconds)
    ini_set('session.gc_maxlifetime', 2592000);
    ini_set('session.cookie_lifetime', 2592000);
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');




// Database Configuration
define('DB_HOST', 'localhost');

if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || (php_sapi_name() === 'cli' && strpos(__DIR__, '/home/sudhacreative') === false)) {
    // Local Configuration
    define('DB_NAME', 'shudha');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', 'http://localhost/sudha/');
} else {
    // Live Configuration
    define('DB_NAME', 'sudhacreative');
    define('DB_USER', 'sudhacreative');
    define('DB_PASS', 'M2Noida@847226');
    define('BASE_URL', 'https://sudhacreative.com/');
}

// SMTP Configuration
define('SMTP_HOST', '194.238.17.209');
define('SMTP_PORT', 25);
define('SMTP_USER', 'info@sudhacreative.com');
define('SMTP_PASS', 'M2Noida@278');
define('SMTP_ENCRYPTION', 'tls');

// App Constants
define('APP_NAME', 'Sudha Creative CRM');
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