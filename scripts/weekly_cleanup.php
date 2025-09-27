<?php
/**
 * Weekly Cleanup Script
 * Runs every Sunday at 2 AM to clean old data and optimize database
 */

require_once __DIR__ . '/../config.php';

$RETENTION_DAYS = (int)($_ENV['RETENTION_DAYS'] ?? 90);

log_message("Starting weekly cleanup (retention: $RETENTION_DAYS days)", 'INFO');
$start_time = microtime(true);

$pdo = get_db();
if (!$pdo) {
    log_message("Cannot connect to database for cleanup", 'ERROR');
    exit(1);
}

try {
    // Cleanup old word history
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-$RETENTION_DAYS days"));
    
    $stmt = $pdo->prepare("DELETE FROM word_history WHERE timestamp < ?");
    $stmt->execute([$cutoff_date]);
    $deleted_words = $stmt->rowCount();
    
    // Cleanup old collections
    $stmt = $pdo->prepare("DELETE FROM collections WHERE timestamp < ?");
    $stmt->execute([$cutoff_date]);
    $deleted_collections = $stmt->rowCount();
    
    // Cleanup old articles
    $stmt = $pdo->prepare("DELETE FROM articles WHERE timestamp < ?");
    $stmt->execute([$cutoff_date]);
    $deleted_articles = $stmt->rowCount();
    
    // Optimize database
    $pdo->exec("VACUUM");
    $pdo->exec("ANALYZE");
    
    // Cleanup old log files
    cleanup_old_logs();
    
    // Cleanup old cache files
    cleanup_old_cache_files();
    
    // Cleanup old analysis files
    cleanup_old_analysis_files();
    
    // Generate cleanup report
    $total_time = round(microtime(true) - $start_time, 2);
    log_message("Weekly cleanup completed in {$total_time}s: $deleted_collections collections, $deleted_articles articles, $deleted_words word records", 'INFO');
    
} catch (Exception $e) {
    log_message("Weekly cleanup failed: " . $e->getMessage(), 'ERROR');
    exit(1);
}

exit(0);

// Helper Functions

function cleanup_old_logs() {
    $log_files = glob(LOGS_DIR . '/*.log');
    $deleted = 0;
    $log_retention = 30; // Keep 30 days of logs
    
    foreach ($log_files as $file) {
        if (is_file($file) && (time() - filemtime($file)) > ($log_retention * 24 * 3600)) {
            unlink($file);
            $deleted++;
        }
    }
    
    if ($deleted > 0) {
        log_message("Cleaned up $deleted old log files", 'INFO');
    }
}

function cleanup_old_cache_files() {
    $cache_files = glob(CACHE_DIR . '/*');
    $deleted = 0;
    $cache_retention = 7; // Keep 7 days of cache
    
    foreach ($cache_files as $file) {
        if (is_file($file) && (time() - filemtime($file)) > ($cache_retention * 24 * 3600)) {
            unlink($file);
            $deleted++;
        }
    }
    
    if ($deleted > 0) {
        log_message("Cleaned up $deleted old cache files", 'INFO');
    }
}

function cleanup_old_analysis_files() {
    $analysis_dir = DATA_DIR . '/analysis';
    if (!file_exists($analysis_dir)) return;
    
    $files = glob($analysis_dir . '/daily_*.json');
    $keep_days = 90; // Keep 90 days of analysis
    $cutoff_date = date('Y-m-d', strtotime("-{$keep_days} days"));
    
    $deleted = 0;
    foreach ($files as $file) {
        if (preg_match('/daily_(\d{4}-\d{2}-\d{2})\.json$/', basename($file), $matches)) {
            if ($matches[1] < $cutoff_date) {
                unlink($file);
                $deleted++;
            }
        }
    }
    
    if ($deleted > 0) {
        log_message("Cleaned up $deleted old analysis files", 'INFO');
    }
}
?>