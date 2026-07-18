<?php
function search_in_dir($dir) {
    $results = [];
    $it = new RecursiveDirectoryIterator($dir);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        
        // Exclude directories
        if (strpos($filePath, '.git') !== false || strpos($filePath, 'uploads') !== false || strpos($filePath, '.gemini') !== false) {
            continue;
        }
        
        // Only include php and js files
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), ['php', 'js'])) {
            continue;
        }
        
        if (filesize($filePath) > 1024 * 1024) { // skip > 1MB
            continue;
        }
        
        $content = @file_get_contents($filePath);
        if (strpos($content, 'client_printing.php') !== false) {
            $results[] = str_replace($dir, '', $filePath);
        }
    }
    return $results;
}

echo "Files referencing 'client_printing.php':\n";
print_r(search_in_dir(__DIR__));
?>
