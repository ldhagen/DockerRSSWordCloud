<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration and helper functions
header('Content-Type: text/html; charset=UTF-8');

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set($_ENV['PHP_TIMEZONE'] ?? 'UTC');

// RSS parsing configuration
define('TITLES_ONLY_ANALYSIS', true); // Set to false for full content analysis
define('FETCH_RETRY_COUNT', 3);
define('FETCH_RETRY_DELAY', 1); // seconds
define('FETCH_TIMEOUT', 60); // seconds

// Paths - Enhanced for Docker
define('DATA_DIR', 'data');
define('LOGS_DIR', 'logs');
define('CACHE_DIR', 'cache');
define('FEEDS_FILE', DATA_DIR . '/feeds.json');
define('STOPWORDS_FILE', DATA_DIR . '/stopwords.json');
define('DATABASE_FILE', DATA_DIR . '/analytics.db');

// Create directories if they don't exist
foreach ([DATA_DIR, LOGS_DIR, CACHE_DIR] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Default stopwords (expanded list)
$default_stopwords = [
    'the', 'and', 'to', 'of', 'a', 'in', 'that', 'is', 'it', 'with',
    'for', 'on', 'as', 'was', 'by', 'at', 'an', 'be', 'this', 'have',
    'from', 'or', 'which', 'one', 'you', 'we', 'are', 'all', 'your',
    'their', 'what', 'our', 'us', 'has', 'had', 'but', 'not', 'they',
    'i', 'he', 'she', 'his', 'her', 'him', 'them', 'so', 'if', 'about',
    'who', 'get', 'like', 'just', 'my', 'me', 'more', 'out', 'up', 'some',
    'will', 'how', 'when', 'where', 'why', 'can', 'should', 'would', 'could',
    'continue', 'reading', 'after', 'says', 'other', 'its', 'it\'s', 'were',
    'said', 'over', 'been', 'went', 'say', 'than', 'apos', 'week', 'year',
    'two', 'first', 'into', 'news', 'new', 'years', 'down', 'discuss',
    'next', 'while', 'time', 'cnbc', 'being', 'under', 'no', 'between',
    'latest', 'now', 'many', 'last', 'off', 'use', 'live', 'make', 'there', 'here'
];

// Database initialization
function init_database() {
    try {
        $pdo = new PDO('sqlite:' . DATABASE_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS collections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                feed_name TEXT NOT NULL,
                total_articles INTEGER DEFAULT 0,
                total_words INTEGER DEFAULT 0
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS word_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection_id INTEGER,
                word TEXT NOT NULL,
                count INTEGER NOT NULL,
                feed_name TEXT NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (collection_id) REFERENCES collections (id)
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection_id INTEGER,
                title TEXT NOT NULL,
                link TEXT,
                description TEXT,
                feed_name TEXT NOT NULL,
                pub_date TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (collection_id) REFERENCES collections (id)
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_word_history_word ON word_history (word)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_word_history_timestamp ON word_history (timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_word_history_feed ON word_history (feed_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_collections_timestamp ON collections (timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_collections_feed ON collections (feed_name)");
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return null;
    }
}

function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = init_database();
    }
    return $pdo;
}

function sanitize_output($string) {
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function load_json($file, $default = []) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data ?: $default;
        }
    }
    save_json($file, $default);
    return $default;
}

function save_json($file, $data) {
    if (file_exists($file)) {
        copy($file, $file . '.bak.' . date('Y-m-d-H-i-s'));
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Enhanced RSS fetching with cURL, Retry logic, and Fast Fail
 */
function fetch_rss($url, $use_cache = true) {
    $cache_file = CACHE_DIR . '/' . md5($url) . '.xml';
    $cache_time = 3600; 
    
    if ($use_cache && file_exists($cache_file)) {
        if (time() - filemtime($cache_file) < $cache_time) {
            return file_get_contents($cache_file);
        }
    }
    
    $attempts = 0;
    while ($attempts < FETCH_RETRY_COUNT) {
        $attempts++;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => FETCH_TIMEOUT,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => "",
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $http_code === 200) {
            if ($use_cache) {
                file_put_contents($cache_file, $response);
            }
            return $response;
        }

        // Fast Fail Logic: Stop immediately on permission errors
        if ($http_code === 403 || $http_code === 401) {
            log_message("Fast Fail: Permission denied (HTTP $http_code) for $url. Skipping retries.", "WARNING");
            break; 
        }

        log_message("Fetch attempt $attempts failed for $url: HTTP $http_code. Error: $curl_error", "ERROR");
        
        if ($attempts < FETCH_RETRY_COUNT) {
            sleep(FETCH_RETRY_DELAY);
        }
    }
    
    return false;
}

function str_lower($string) {
    return strtolower($string);
}

function parse_rss_content($rss_content, $titles_only = true) {
    $content = '';
    $rss_content = preg_replace('/<!\[CDATA\[(.*?)\]\]>/is', '$1', $rss_content);
    $rss_content = html_entity_decode($rss_content);
    
    if (preg_match_all('/<item>(.*?)<\/item>/is', $rss_content, $item_matches)) {
        foreach ($item_matches[1] as $item) {
            if (preg_match('/<title>(.*?)<\/title>/is', $item, $title_match)) {
                $content .= ' ' . strip_tags($title_match[1]);
            }
            if (!$titles_only) {
                if (preg_match('/<description>(.*?)<\/description>/is', $item, $desc_match)) {
                    $content .= ' ' . strip_tags($desc_match[1]);
                }
            }
        }
    }
    return $content;
}

function extract_articles($rss_content, $feed_name) {
    $articles = [];
    $rss_content = preg_replace('/<!\[CDATA\[(.*?)\]\]>/is', '$1', $rss_content);
    $rss_content = html_entity_decode($rss_content);
    
    if (preg_match_all('/<item>(.*?)<\/item>/is', $rss_content, $item_matches)) {
        foreach ($item_matches[1] as $item) {
            $article = ['title' => '', 'link' => '', 'description' => '', 'feed' => $feed_name, 'pub_date' => ''];
            if (preg_match('/<title>(.*?)<\/title>/is', $item, $title_match)) $article['title'] = trim(strip_tags($title_match[1]));
            if (preg_match('/<link>(.*?)<\/link>/is', $item, $link_match)) $article['link'] = trim(strip_tags($link_match[1]));
            if (preg_match('/<description>(.*?)<\/description>/is', $item, $desc_match)) $article['description'] = trim(strip_tags($desc_match[1]));
            if (preg_match('/<pubDate>(.*?)<\/pubDate>/is', $item, $date_match)) $article['pub_date'] = trim(strip_tags($date_match[1]));
            if (!empty($article['title'])) $articles[] = $article;
        }
    }
    return $articles;
}

function filter_new_articles($articles, $feed_name) {
    $pdo = get_db();
    if (!$pdo || empty($articles)) return $articles;
    $new_articles = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE link = ? AND feed_name = ?");
    foreach ($articles as $article) {
        $stmt->execute([$article['link'], $feed_name]);
        if ($stmt->fetchColumn() == 0) $new_articles[] = $article;
    }
    return $new_articles;
}

function get_content_from_articles($articles, $titles_only = true) {
    $content = '';
    foreach ($articles as $article) {
        $content .= ' ' . $article['title'];
        if (!$titles_only && !empty($article['description'])) $content .= ' ' . $article['description'];
    }
    return $content;
}

function count_words($text, $stopwords) {
    if (empty($text)) return [];
    $text = str_lower($text);
    $text = preg_replace('/[^a-z\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $word_counts = [];
    foreach ($words as $word) {
        $word = trim($word);
        if (empty($word) || in_array($word, $stopwords) || strlen($word) < 2 || is_numeric($word)) continue;
        if (!isset($word_counts[$word])) $word_counts[$word] = 0;
        $word_counts[$word]++;
    }
    arsort($word_counts);
    return $word_counts;
}

function store_collection_data($feed_name, $articles, $word_counts) {
    $pdo = get_db();
    if (!$pdo) return false;
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO collections (feed_name, total_articles, total_words) VALUES (?, ?, ?)");
        $stmt->execute([$feed_name, count($articles), array_sum($word_counts)]);
        $collection_id = $pdo->lastInsertId();
        
        if (!empty($word_counts)) {
            $stmt = $pdo->prepare("INSERT INTO word_history (collection_id, word, count, feed_name) VALUES (?, ?, ?, ?)");
            foreach ($word_counts as $word => $count) $stmt->execute([$collection_id, $word, $count, $feed_name]);
        }
        
        if (!empty($articles)) {
            $stmt = $pdo->prepare("INSERT INTO articles (collection_id, title, link, description, feed_name, pub_date) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($articles as $article) $stmt->execute([$collection_id, $article['title'], $article['link'], $article['description'], $article['feed'], $article['pub_date']]);
        }
        $pdo->commit();
        return $collection_id;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Failed to store collection data: " . $e->getMessage());
        return false;
    }
}

function get_word_trends($word, $days = 30, $feed = null) {
    $pdo = get_db();
    if (!$pdo) return [];
    try {
        if ($feed) {
            $stmt = $pdo->prepare("
                SELECT DATE(timestamp) as date, SUM(count) as total_count 
                FROM word_history 
                WHERE LOWER(word) = LOWER(?) AND feed_name = ?
                AND timestamp > datetime('now', '-' || ? || ' days')
                GROUP BY DATE(timestamp)
                ORDER BY date
            ");
            $stmt->execute([$word, $feed, $days]);
        } else {
            $stmt = $pdo->prepare("
                SELECT DATE(timestamp) as date, SUM(count) as total_count 
                FROM word_history 
                WHERE LOWER(word) = LOWER(?)
                AND timestamp > datetime('now', '-' || ? || ' days')
                GROUP BY DATE(timestamp)
                ORDER BY date
            ");
            $stmt->execute([$word, $days]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get word trends: " . $e->getMessage());
        return [];
    }
}

function get_trending_words($days = 7, $limit = 20) {
    $pdo = get_db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT word, SUM(count) as total_count, COUNT(DISTINCT feed_name) as feed_count
            FROM word_history 
            WHERE timestamp > datetime('now', '-' || ? || ' days')
            GROUP BY word
            HAVING total_count > 5
            ORDER BY total_count DESC, feed_count DESC
            LIMIT ?
        ");
        $stmt->execute([$days, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get trending words: " . $e->getMessage());
        return [];
    }
}

function log_message($message, $level = 'INFO') {
    $log_file = LOGS_DIR . '/analyzer.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] [$level] $message\n", FILE_APPEND | LOCK_EX);
}

init_database();
?>
