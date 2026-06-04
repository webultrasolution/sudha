<?php
$logFile = __DIR__ . '/php_error.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $last_lines = array_slice($lines, -30);
    echo "ERROR_LOG_START\n";
    foreach ($last_lines as $line) {
        echo $line;
    }
    echo "ERROR_LOG_END\n";
} else {
    echo "Log file does not exist at: " . $logFile;
}
