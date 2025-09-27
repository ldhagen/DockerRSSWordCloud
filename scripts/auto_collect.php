<?php
/**
 * Automated RSS Collection Script
 * Runs every 30 minutes to collect feeds automatically
 */

require_once __DIR__ . '/../config.php';

// Configuration from environment variables
$MAX_FEEDS_PER_RUN = (int)($_ENV['MAX_FEEDS_PER_RUN'] ?? 5);
$COLLECTION_TIMEOUT = (int)($_ENV['COLLECTION_TIMEOUT'] ?? 30);

log_message("Starting automated collection (max $MAX_FEEDS_PER_RUN feeds)", 'INFO');
$start_time = microtime(true);

// Load feeds configuration
$feeds_data = load_json(FEEDS_FILE, ['feeds' => []]);
$stopwords = load_json(STOPWORDS_FILE, $default_stopwords);

if (empty($feeds_data['feeds'])) {
    log_message("No feeds configured for collection", 'WARNING');
    exit(0);
}

// Enhanced cycling logic
$state_file = DATA_DIR . '/collection_state.json';
$state = ['last_index' => 0, 'cycle_count' => 0];
if (file_exists($state_file)) {
    $state = json_decode(file_get_contents($state_file), true) ?: $state;
}

$total_feeds = count($feeds_data['feeds']);
$start_index = $state['last_index'];

// Calculate feeds to process this run
$feeds_to_process = [];
$feed_indices = [];
for ($i = 0; $i < $MAX_FEEDS_PER_RUN && count($feeds_to_process) < $total_feeds; $i++) {
    $index = ($start_index + $i) % $total_feeds;
    $feeds_to_process[] = $feeds_data['feeds'][$index];
    $feed_indices[] = $index;
}

// Update state for next run
$next_index = ($start_index + $MAX_FEEDS_PER_RUN) % $total_feeds;
$cycle_completed = ($next_index < $start_index || ($start_index + $MAX_FEEDS_PER_RUN) >= $total_feeds);

$state['last_index'] = $next_index;
if ($cycle_completed) {
    $state['cycle_count']++;
}
$state['cycle_completed'] = $cycle_completed;
$state['last_run'] = date('Y-m-d H:i:s');
file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

log_message("Processing feeds " . implode(',', $feed_indices) . " (cycle " . ($cycle_completed ? "completed" : "continuing") . ")", 'INFO');

// Process selected feeds
$successful_collections = 0;
$failed_collections = 0;
$total_articles = 0;
$total_words = 0;

foreach ($feeds_to_process as $index => $feed) {
    $feed_start_time = microtime(true);
    
    try {
        log_message("Processing feed: {$feed['name']}", 'INFO');
        
        // Set timeout for this feed
        set_time_limit($COLLECTION_TIMEOUT);
        
        // Fetch RSS content
        $rss_content = fetch_rss($feed['url']);
        
        if ($rss_content) {
            // Parse content (titles only based on TITLES_ONLY_ANALYSIS setting)
            $content = parse_rss_content($rss_content, TITLES_ONLY_ANALYSIS);
            
            // Extract articles
            $articles = extract_articles($rss_content, $feed['name']);
            
            // Count words
            $word_counts = count_words($content, $stopwords);
            
            if (!empty($word_counts) || !empty($articles)) {
                // Store collection data
                $collection_id = store_collection_data($feed['name'], $articles, $word_counts);
                
                if ($collection_id) {
                    $successful_collections++;
                    $total_articles += count($articles);
                    $total_words += array_sum($word_counts);
                    
                    $processing_time = round(microtime(true) - $feed_start_time, 2);
                    log_message("Successfully processed {$feed['name']}: " . count($articles) . " articles, " . array_sum($word_counts) . " words in {$processing_time}s", 'INFO');
                } else {
                    log_message("Failed to store data for {$feed['name']}", 'ERROR');
                    $failed_collections++;
                }
            } else {
                log_message("No content extracted from {$feed['name']}", 'WARNING');
                $failed_collections++;
            }
        } else {
            log_message("Failed to fetch RSS content from {$feed['name']}", 'ERROR');
            $failed_collections++;
        }
        
    } catch (Exception $e) {
        log_message("Error processing {$feed['name']}: " . $e->getMessage(), 'ERROR');
        $failed_collections++;
    }
    
    // Small delay between feeds to be respectful
    usleep(500000); // 0.5 second delay
}

// Log summary
$total_time = round(microtime(true) - $start_time, 2);
log_message("Collection completed: $successful_collections successful, $failed_collections failed, $total_articles total articles, $total_words total words in {$total_time}s", 'INFO');

// Update collection statistics
update_collection_stats($successful_collections, $failed_collections, $total_articles, $total_words);

// Cleanup if needed (every 10th cycle)
if ($state['cycle_count'] % 10 == 0) {
    cleanup_old_cache();
}

exit(0);

// Helper Functions

function update_collection_stats($successful, $failed, $articles, $words) {
    $stats_file = DATA_DIR . '/collection_stats.json';
    
    $stats = ['daily' => [], 'totals' => ['collections' => 0, 'articles' => 0, 'words' => 0]];
    if (file_exists($stats_file)) {
        $stats = json_decode(file_get_contents($stats_file), true) ?: $stats;
    }
    
    $today = date('Y-m-d');
    if (!isset($stats['daily'][$today])) {
        $stats['daily'][$today] = ['successful' => 0, 'failed' => 0, 'articles' => 0, 'words' => 0, 'runs' => 0];
    }
    
    $stats['daily'][$today]['successful'] += $successful;
    $stats['daily'][$today]['failed'] += $failed;
    $stats['daily'][$today]['articles'] += $articles;
    $stats['daily'][$today]['words'] += $words;
    $stats['daily'][$today]['runs']++;
    
    $stats['totals']['collections'] += $successful;
    $stats['totals']['articles'] += $articles;
    $stats['totals']['words'] += $words;
    
    // Keep only last 30 days of daily stats
    $cutoff_date = date('Y-m-d', strtotime('-30 days'));
    foreach ($stats['daily'] as $date => $data) {
        if ($date < $cutoff_date) {
            unset($stats['daily'][$date]);
        }
    }
    
    file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT));
}

function cleanup_old_cache() {
    $cache_dir = CACHE_DIR;
    if (!is_dir($cache_dir)) return;
    
    $files = glob($cache_dir . '/*.xml');
    $deleted = 0;
    $cache_age_limit = 24 * 3600; // 24 hours
    
    foreach ($files as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $cache_age_limit) {
            unlink($file);
            $deleted++;
        }
    }
    
    if ($deleted > 0) {
        log_message("Cleaned up $deleted old cache files", 'INFO');
    }
}
?>