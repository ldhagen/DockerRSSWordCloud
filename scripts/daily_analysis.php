<?php
/**
 * Daily Analysis Script
 * Runs every morning to analyze word trends, detect changes, and generate reports
 */

require_once __DIR__ . '/../config.php';

log_message("Starting daily analysis", 'INFO');
$start_time = microtime(true);

$pdo = get_db();
if (!$pdo) {
    log_message("Cannot connect to database for analysis", 'ERROR');
    exit(1);
}

try {
    // Generate daily analysis report
    $analysis = [
        'date' => date('Y-m-d'),
        'timestamp' => date('Y-m-d H:i:s'),
        'trending_words' => analyze_trending_words(),
        'emerging_words' => detect_emerging_words(), 
        'declining_words' => detect_declining_words(),
        'feed_performance' => analyze_feed_performance(),
        'collection_summary' => get_collection_summary(),
        'word_velocity' => calculate_word_velocity(),
        'topic_shifts' => detect_topic_shifts()
    ];
    
    // Save analysis to file
    save_daily_analysis($analysis);
    
    // Check for significant changes and create alerts
    $alerts = generate_change_alerts($analysis);
    if (!empty($alerts)) {
        save_alerts($alerts);
        log_message("Generated " . count($alerts) . " change alerts", 'INFO');
    }
    
    // Update trend coefficients
    update_trend_coefficients();
    
    // Cleanup old analysis data
    cleanup_old_analysis();
    
    $total_time = round(microtime(true) - $start_time, 2);
    log_message("Daily analysis completed in {$total_time}s", 'INFO');
    
} catch (Exception $e) {
    log_message("Daily analysis failed: " . $e->getMessage(), 'ERROR');
    exit(1);
}

// Analysis Functions

function analyze_trending_words($days = 7, $limit = 50) {
    $pdo = get_db();
    
    try {
        // Get word trends for the past week vs previous week
        $stmt = $pdo->prepare("
            SELECT 
                w1.word,
                SUM(w1.count) as current_week,
                COALESCE(w2.prev_week, 0) as prev_week,
                ROUND((SUM(w1.count) - COALESCE(w2.prev_week, 0)) * 100.0 / GREATEST(COALESCE(w2.prev_week, 1), 1), 2) as change_percent,
                COUNT(DISTINCT w1.feed_name) as feed_count
            FROM word_history w1
            LEFT JOIN (
                SELECT word, SUM(count) as prev_week
                FROM word_history 
                WHERE timestamp BETWEEN datetime('now', '-14 days') AND datetime('now', '-7 days')
                GROUP BY word
            ) w2 ON w1.word = w2.word
            WHERE w1.timestamp > datetime('now', '-7 days')
            GROUP BY w1.word
            HAVING current_week > 10
            ORDER BY change_percent DESC, current_week DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        log_message("Failed to analyze trending words: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function detect_emerging_words($threshold = 20) {
    $pdo = get_db();
    
    try {
        // Find words that appeared recently but not in previous periods
        $stmt = $pdo->prepare("
            SELECT 
                w1.word,
                SUM(w1.count) as recent_count,
                COUNT(DISTINCT w1.feed_name) as feed_count,
                MIN(w1.timestamp) as first_appearance
            FROM word_history w1
            WHERE w1.timestamp > datetime('now', '-3 days')
            AND w1.word NOT IN (
                SELECT DISTINCT word 
                FROM word_history 
                WHERE timestamp BETWEEN datetime('now', '-30 days') AND datetime('now', '-3 days')
            )
            GROUP BY w1.word
            HAVING recent_count >= ?
            ORDER BY recent_count DESC, feed_count DESC
            LIMIT 20
        ");
        
        $stmt->execute([$threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        log_message("Failed to detect emerging words: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function detect_declining_words() {
    $pdo = get_db();
    
    try {
        // Find words with significant decline
        $stmt = $pdo->prepare("
            SELECT 
                w2.word,
                w2.prev_count,
                COALESCE(w1.recent_count, 0) as recent_count,
                ROUND((COALESCE(w1.recent_count, 0) - w2.prev_count) * 100.0 / w2.prev_count, 2) as decline_percent
            FROM (
                SELECT word, SUM(count) as prev_count
                FROM word_history
                WHERE timestamp BETWEEN datetime('now', '-14 days') AND datetime('now', '-7 days')
                GROUP BY word
                HAVING prev_count > 20
            ) w2
            LEFT JOIN (
                SELECT word, SUM(count) as recent_count
                FROM word_history
                WHERE timestamp > datetime('now', '-7 days')
                GROUP BY word
            ) w1 ON w2.word = w1.word
            WHERE COALESCE(w1.recent_count, 0) < w2.prev_count * 0.5
            ORDER BY decline_percent ASC
            LIMIT 15
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        log_message("Failed to detect declining words: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function analyze_feed_performance() {
    $pdo = get_db();
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                feed_name,
                COUNT(*) as collections_today,
                SUM(total_articles) as articles_today,
                SUM(total_words) as words_today,
                AVG(total_articles) as avg_articles,
                MAX(timestamp) as last_collection
            FROM collections
            WHERE DATE(timestamp) = DATE('now')
            GROUP BY feed_name
            ORDER BY collections_today DESC, articles_today DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        log_message("Failed to analyze feed performance: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function get_collection_summary() {
    $pdo = get_db();
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_collections,
                SUM(total_articles) as total_articles,
                SUM(total_words) as total_words,
                COUNT(DISTINCT feed_name) as active_feeds,
                MIN(timestamp) as first_collection,
                MAX(timestamp) as last_collection
            FROM collections
            WHERE DATE(timestamp) = DATE('now')
        ");
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        log_message("Failed to get collection summary: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function calculate_word_velocity() {
    $pdo = get_db();
    
    try {
        // Calculate how fast words are appearing/disappearing
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT word) as unique_words_today,
                AVG(count) as avg_word_frequency,
                (
                    SELECT COUNT(DISTINCT word) 
                    FROM word_history 
                    WHERE DATE(timestamp) = DATE('now', '-1 day')
                ) as unique_words_yesterday
            FROM word_history
            WHERE DATE(timestamp) = DATE('now')
        ");
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['unique_words_yesterday'] > 0) {
            $result['velocity'] = round(
                ($result['unique_words_today'] - $result['unique_words_yesterday']) * 100.0 / 
                $result['unique_words_yesterday'], 2
            );
        }
        
        return $result;
        
    } catch (PDOException $e) {
        log_message("Failed to calculate word velocity: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function detect_topic_shifts() {
    // Analyze major changes in topic distribution
    $trending = analyze_trending_words(7, 10);
    $emerging = detect_emerging_words(15);
    
    $shifts = [];
    
    // Detect if certain topic categories are surging
    $topic_keywords = [
        'politics' => ['election', 'vote', 'campaign', 'candidate', 'political', 'government', 'policy'],
        'economy' => ['market', 'stock', 'economic', 'financial', 'price', 'inflation', 'recession'],
        'technology' => ['tech', 'ai', 'artificial', 'digital', 'cyber', 'internet', 'software'],
        'health' => ['health', 'medical', 'vaccine', 'disease', 'hospital', 'treatment', 'pandemic'],
        'climate' => ['climate', 'environmental', 'weather', 'temperature', 'carbon', 'renewable']
    ];
    
    foreach ($topic_keywords as $topic => $keywords) {
        $topic_score = 0;
        $word_count = 0;
        
        foreach ($trending as $word_data) {
            if (in_array(strtolower($word_data['word']), $keywords)) {
                $topic_score += $word_data['change_percent'];
                $word_count++;
            }
        }
        
        if ($word_count > 0) {
            $avg_change = $topic_score / $word_count;
            if ($avg_change > 50) { // Significant increase
                $shifts[] = [
                    'topic' => $topic,
                    'change_percent' => round($avg_change, 2),
                    'affected_words' => $word_count,
                    'trend' => 'rising'
                ];
            }
        }
    }
    
    return $shifts;
}

function generate_change_alerts($analysis) {
    $alerts = [];
    
    // Alert for significant trending words
    foreach ($analysis['trending_words'] as $word) {
        if ($word['change_percent'] > 200) { // 200% increase
            $alerts[] = [
                'type' => 'trending_spike',
                'word' => $word['word'],
                'change' => $word['change_percent'],
                'message' => "Word '{$word['word']}' surged {$word['change_percent']}% this week"
            ];
        }
    }
    
    // Alert for emerging words
    if (count($analysis['emerging_words']) > 5) {
        $alerts[] = [
            'type' => 'emerging_cluster',
            'count' => count($analysis['emerging_words']),
            'message' => count($analysis['emerging_words']) . " new significant words detected"
        ];
    }
    
    // Alert for feed performance issues
    $failed_feeds = array_filter($analysis['feed_performance'], function($feed) {
        return $feed['collections_today'] == 0;
    });
    
    if (count($failed_feeds) > 2) {
        $alerts[] = [
            'type' => 'feed_failures',
            'count' => count($failed_feeds),
            'message' => count($failed_feeds) . " feeds failed to collect today"
        ];
    }
    
    // Alert for topic shifts
    foreach ($analysis['topic_shifts'] as $shift) {
        $alerts[] = [
            'type' => 'topic_shift',
            'topic' => $shift['topic'],
            'change' => $shift['change_percent'],
            'message' => "Topic '{$shift['topic']}' trending up {$shift['change_percent']}%"
        ];
    }
    
    return $alerts;
}

function save_daily_analysis($analysis) {
    $analysis_dir = DATA_DIR . '/analysis';
    if (!file_exists($analysis_dir)) {
        mkdir($analysis_dir, 0755, true);
    }
    
    $filename = $analysis_dir . '/daily_' . date('Y-m-d') . '.json';
    file_put_contents($filename, json_encode($analysis, JSON_PRETTY_PRINT));
    
    log_message("Daily analysis saved to: $filename", 'INFO');
}

function save_alerts($alerts) {
    $alerts_file = DATA_DIR . '/alerts.json';
    
    // Load existing alerts
    $all_alerts = [];
    if (file_exists($alerts_file)) {
        $content = file_get_contents($alerts_file);
        $all_alerts = json_decode($content, true) ?: [];
    }
    
    // Add new alerts with timestamp
    foreach ($alerts as $alert) {
        $alert['timestamp'] = date('Y-m-d H:i:s');
        $all_alerts[] = $alert;
    }
    
    // Keep only last 500 alerts
    if (count($all_alerts) > 500) {
        $all_alerts = array_slice($all_alerts, -500);
    }
    
    file_put_contents($alerts_file, json_encode($all_alerts, JSON_PRETTY_PRINT));
}

function update_trend_coefficients() {
    // Update mathematical trend coefficients for words
    $pdo = get_db();
    
    try {
        $stmt = $pdo->prepare("
            SELECT word, COUNT(*) as data_points
            FROM word_history
            WHERE timestamp > datetime('now', '-30 days')
            GROUP BY word
            HAVING data_points >= 5
        ");
        
        $stmt->execute();
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $coefficients_updated = 0;
        
        foreach ($words as $word_info) {
            $trends = get_word_trends($word_info['word'], 30);
            if (count($trends) >= 5) {
                $coefficient = calculate_trend_coefficient($trends);
                // Store coefficient for future use
                store_trend_coefficient($word_info['word'], $coefficient);
                $coefficients_updated++;
            }
        }
        
        log_message("Updated trend coefficients for $coefficients_updated words", 'INFO');
        
    } catch (PDOException $e) {
        log_message("Failed to update trend coefficients: " . $e->getMessage(), 'ERROR');
    }
}

function calculate_trend_coefficient($trends) {
    if (count($trends) < 2) return 0;
    
    $n = count($trends);
    $sum_x = 0;
    $sum_y = 0;
    $sum_xy = 0;
    $sum_x2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = $i;
        $y = $trends[$i]['total_count'];
        $sum_x += $x;
        $sum_y += $y;
        $sum_xy += $x * $y;
        $sum_x2 += $x * $x;
    }
    
    $denominator = $n * $sum_x2 - $sum_x * $sum_x;
    if ($denominator == 0) return 0;
    
    return ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
}

function store_trend_coefficient($word, $coefficient) {
    $coefficients_file = DATA_DIR . '/trend_coefficients.json';
    
    $coefficients = [];
    if (file_exists($coefficients_file)) {
        $content = file_get_contents($coefficients_file);
        $coefficients = json_decode($content, true) ?: [];
    }
    
    $coefficients[$word] = [
        'coefficient' => round($coefficient, 4),
        'updated' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($coefficients_file, json_encode($coefficients, JSON_PRETTY_PRINT));
}

function cleanup_old_analysis() {
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

exit(0);
?>