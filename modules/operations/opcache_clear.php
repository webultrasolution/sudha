<?php
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "OPcache cleared successfully!\n";
    } else {
        echo "Failed to clear OPcache.\n";
    }
} else {
    echo "OPcache extension not loaded.\n";
}
@unlink(__FILE__);
?>
