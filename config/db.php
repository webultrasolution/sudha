<?php

// Set default timezone to India / Kolkata (IST)
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    // Set session lifetime to 30 days (2592000 seconds)
    ini_set('session.gc_maxlifetime', 2592000);
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

error_reporting(E_ALL);
// Errors are always logged; they are only shown on screen locally (see below)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');




// Database Configuration
define('DB_HOST', 'localhost');

if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || (php_sapi_name() === 'cli' && strpos(__DIR__, '/home/sudhacreative') === false)) {
    // Local Configuration
    ini_set('display_errors', 1);
    define('DB_NAME', 'shudha');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', 'http://localhost/sudha/');
} else {
    // Dynamic Live/Staging Configuration
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'sudhacreative.tech';
    
    // Check if running on staging (.tech)
    $isStaging = false;
    if (isset($_SERVER['HTTP_HOST'])) {
        if (strpos($_SERVER['HTTP_HOST'], 'sudhacreative.tech') !== false) {
            $isStaging = true;
        }
    } else {
        if (strpos(__DIR__, 'sudhacreative.tech') !== false) {
            $isStaging = true;
        }
    }
    
    if ($isStaging) {
        define('DB_NAME', 'sudhacreative_tech');
    } else {
        define('DB_NAME', 'sudhacreative');
    }
    
    define('DB_USER', 'sudhacreative');
    define('DB_PASS', 'M2Noida@847226');
    define('BASE_URL', $protocol . $host . '/');
}

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'sudhacreativemalda@gmail.com');
define('SMTP_PASS', 'vzvz nlrg oube vwsd');
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
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>