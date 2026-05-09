<?php
$pageTitle = 'PHP Error Logs';
include_once __DIR__ . '/includes/header.php';

$logFile = __DIR__ . '/php_error.log';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem;"><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> PHP Error Logs</h2>
        <div>
            <button class="btn btn-secondary" onclick="location.reload()"><i class="fas fa-sync"></i> Refresh</button>
            <form method="POST" style="display: inline;">
                <button type="submit" name="clear_log" class="btn" style="background: #fee2e2; color: #ef4444; border: 1px solid #fecaca;"><i class="fas fa-trash"></i> Clear Log</button>
            </form>
        </div>
    </div>

    <?php
    if (isset($_POST['clear_log'])) {
        file_put_contents($logFile, "# Log Cleared at " . date('Y-m-d H:i:s') . "\n");
        echo "<div style='padding: 1rem; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 1rem;'>Log cleared successfully.</div>";
    }

    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        echo "<pre style='background: #1e293b; color: #e2e8f0; padding: 1.5rem; border-radius: 8px; overflow-x: auto; font-family: \"Courier New\", Courier, monospace; font-size: 0.85rem; line-height: 1.5; max-height: 600px; overflow-y: auto;'>";
        echo htmlspecialchars($logs ?: "No errors logged yet.");
        echo "</pre>";
    } else {
        echo "<p style='color: #64748b; font-style: italic;'>Error log file not found.</p>";
    }
    ?>
</div>

<style>
pre::-webkit-scrollbar { width: 8px; height: 8px; }
pre::-webkit-scrollbar-track { background: #0f172a; }
pre::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
pre::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
