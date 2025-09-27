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
        
        // Create tables
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
        
        // Create indexes for better performance
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_word_history_word ON word_history (word)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_word_history_timestamp ON word_history (timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_collections_timestamp ON collections (timestamp)");
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return null;
    }
}

// Get database connection
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = init_database();
    }
    return $pdo;
}

// Simple HTML sanitization (keeping original function)
function sanitize_output($string) {
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Enhanced JSON loading with backup
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
    // Create backup before overwriting
    if (file_exists($file)) {
        copy($file, $file . '.bak.' . date('Y-m-d-H-i-s'));
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Enhanced RSS fetching with caching
function fetch_rss($url, $use_cache = true) {
    $cache_file = CACHE_DIR . '/' . md5($url) . '.xml';
    $cache_time = 3600; // 1 hour cache
    
    // Check cache if enabled
    if ($use_cache && file_exists($cache_file)) {
        if (time() - filemtime($cache_file) < $cache_time) {
            return file_get_contents($cache_file);
        }
    }
    
    // Fetch from URL (keeping your original logic)
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: RSS Word Counter/2.0\r\n",
            'timeout' => 30,
            'follow_location' => 1,
            'max_redirects' => 5
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            // Cache the response
            if ($use_cache) {
                file_put_contents($cache_file, $response);
            }
            return $response;
        }
    } catch (Exception $e) {
        error_log("Failed to fetch RSS: " . $e->getMessage());
    }
    
    return false;
}

// Simple string to lowercase (keeping original)
function str_lower($string) {
    return strtolower($string);
}

// Enhanced RSS parsing with configurable content selection - DEBUG VERSION
function parse_rss_content($rss_content, $titles_only = true) {
    $content = '';
    
    // Debug: Log function call
    error_log("parse_rss_content called with titles_only = " . ($titles_only ? 'true' : 'false'));
    error_log("RSS content length: " . strlen($rss_content));
    
    // Remove CDATA sections
    $rss_content = preg_replace('/<!\[CDATA\[(.*?)\]\]>/is', '$1', $rss_content);
    
    // Decode HTML entities
    $rss_content = html_entity_decode($rss_content);
    
    // Extract text between item tags
    if (preg_match_all('/<item>(.*?)<\/item>/is', $rss_content, $item_matches)) {
        error_log("Found " . count($item_matches[1]) . " RSS items");
        foreach ($item_matches[1] as $item) {
            // Always extract title
            if (preg_match('/<title>(.*?)<\/title>/is', $item, $title_match)) {
                $title_text = strip_tags($title_match[1]);
                $content .= ' ' . $title_text;
                error_log("Extracted title: " . $title_text);
            }
            
            // Extract additional content only if not titles_only
            if (!$titles_only) {
                // Extract description
                if (preg_match('/<description>(.*?)<\/description>/is', $item, $desc_match)) {
                    $content .= ' ' . strip_tags($desc_match[1]);
                }
                // Extract content:encoded
                if (preg_match('/<content:encoded>(.*?)<\/content:encoded>/is', $item, $content_match)) {
                    $content .= ' ' . strip_tags($content_match[1]);
                }
            }
        }
    } else {
        // Try Atom format
        if (preg_match_all('/<entry>(.*?)<\/entry>/is', $rss_content, $entry_matches)) {
            error_log("Found " . count($entry_matches[1]) . " Atom entries");
            foreach ($entry_matches[1] as $entry) {
                // Always extract title
                if (preg_match('/<title>(.*?)<\/title>/is', $entry, $title_match)) {
                    $title_text = strip_tags($title_match[1]);
                    $content .= ' ' . $title_text;
                    error_log("Extracted Atom title: " . $title_text);
                }
                
                // Extract additional content only if not titles_only
                if (!$titles_only) {
                    // Extract summary
                    if (preg_match('/<summary>(.*?)<\/summary>/is', $entry, $summary_match)) {
                        $content .= ' ' . strip_tags($summary_match[1]);
                    }
                    // Extract content
                    if (preg_match('/<content>(.*?)<\/content>/is', $entry, $content_match)) {
                        $content .= ' ' . strip_tags($content_match[1]);
                    }
                }
            }
        } else {
            error_log("No RSS items or Atom entries found in feed");
        }
    }
    
    error_log("Final extracted content length: " . strlen($content));
    error_log("Sample content (first 200 chars): " . substr($content, 0, 200));
    
    return $content;
}

// Enhanced article extraction (keeping your original logic)
function extract_articles($rss_content, $feed_name) {
    $articles = [];
    
    // Remove CDATA sections and decode entities
    $rss_content = preg_replace('/<!\[CDATA\[(.*?)\]\]>/is', '$1', $rss_content);
    $rss_content = html_entity_decode($rss_content);
    
    // Extract items
    if (preg_match_all('/<item>(.*?)<\/item>/is', $rss_content, $item_matches)) {
        foreach ($item_matches[1] as $item) {
            $article = [
                'title' => '',
                'link' => '',
                'description' => '',
                'feed' => $feed_name,
                'pub_date' => ''
            ];
            
            // Extract title
            if (preg_match('/<title>(.*?)<\/title>/is', $item, $title_match)) {
                $article['title'] = strip_tags($title_match[1]);
            }
            
            // Extract link
            if (preg_match('/<link>(.*?)<\/link>/is', $item, $link_match)) {
                $article['link'] = strip_tags($link_match[1]);
            }
            
            // Extract description
            if (preg_match('/<description>(.*?)<\/description>/is', $item, $desc_match)) {
                $article['description'] = strip_tags($desc_match[1]);
            }
            
            // Extract publication date
            if (preg_match('/<pubDate>(.*?)<\/pubDate>/is', $item, $date_match)) {
                $article['pub_date'] = strip_tags($date_match[1]);
            }
            
            $articles[] = $article;
        }
    } else {
        // Try Atom format
        if (preg_match_all('/<entry>(.*?)<\/entry>/is', $rss_content, $entry_matches)) {
            foreach ($entry_matches[1] as $entry) {
                $article = [
                    'title' => '',
                    'link' => '',
                    'description' => '',
                    'feed' => $feed_name,
                    'pub_date' => ''
                ];
                
                // Extract title
                if (preg_match('/<title>(.*?)<\/title>/is', $entry, $title_match)) {
                    $article['title'] = strip_tags($title_match[1]);
                }
                
                // Extract link
                if (preg_match('/<link.*?href="(.*?)".*?>/is', $entry, $link_match)) {
                    $article['link'] = $link_match[1];
                }
                
                // Extract summary
                if (preg_match('/<summary>(.*?)<\/summary>/is', $entry, $summary_match)) {
                    $article['description'] = strip_tags($summary_match[1]);
                }
                
                // Extract published date
                if (preg_match('/<published>(.*?)<\/published>/is', $entry, $date_match)) {
                    $article['pub_date'] = strip_tags($date_match[1]);
                }
                
                $articles[] = $article;
            }
        }
    }
    
    return $articles;
}

// Enhanced word counting with debug logging
function count_words($text, $stopwords) {
    error_log("count_words called with text length: " . strlen($text));
    
    if (empty($text)) {
        error_log("Empty text provided to count_words");
        return [];
    }
    
    // Convert to lowercase
    $text = str_lower($text);
    
    // Remove punctuation and numbers
    $text = preg_replace('/[^a-z\s]/', ' ', $text);
    
    // Split into words
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    error_log("Total words found: " . count($words));
    error_log("First 10 words: " . implode(', ', array_slice($words, 0, 10)));
    
    // Count words, excluding stopwords
    $word_counts = [];
    foreach ($words as $word) {
        $word = trim($word);
        if (empty($word) || in_array($word, $stopwords) || strlen($word) < 2 || is_numeric($word)) {
            continue;
        }
        
        if (!isset($word_counts[$word])) {
            $word_counts[$word] = 0;
        }
        $word_counts[$word]++;
    }
    
    error_log("Unique words after filtering: " . count($word_counts));
    error_log("Top 5 words: " . json_encode(array_slice($word_counts, 0, 5, true)));
    
    // Sort by count descending
    arsort($word_counts);
    return $word_counts;
}

// NEW FUNCTIONS - Analytics Features

// Store collection data to database
function store_collection_data($feed_name, $articles, $word_counts) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        // Insert collection record
        $stmt = $pdo->prepare("
            INSERT INTO collections (feed_name, total_articles, total_words) 
            VALUES (?, ?, ?)
        ");
        $total_words = array_sum($word_counts);
        $stmt->execute([$feed_name, count($articles), $total_words]);
        $collection_id = $pdo->lastInsertId();
        
        // Insert word history
        $stmt = $pdo->prepare("
            INSERT INTO word_history (collection_id, word, count, feed_name) 
            VALUES (?, ?, ?, ?)
        ");
        foreach ($word_counts as $word => $count) {
            $stmt->execute([$collection_id, $word, $count, $feed_name]);
        }
        
        // Insert articles
        $stmt = $pdo->prepare("
            INSERT INTO articles (collection_id, title, link, description, feed_name, pub_date) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($articles as $article) {
            $stmt->execute([
                $collection_id,
                $article['title'],
                $article['link'],
                $article['description'],
                $article['feed'],
                $article['pub_date'] ?? null
            ]);
        }
        
        $pdo->commit();
        return $collection_id;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Failed to store collection data: " . $e->getMessage());
        return false;
    }
}

// Get word trends over time
function get_word_trends($word, $days = 30) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT DATE(timestamp) as date, SUM(count) as total_count
            FROM word_history 
            WHERE word = ? AND timestamp > datetime('now', '-' || ? || ' days')
            GROUP BY DATE(timestamp)
            ORDER BY date
        ");
        $stmt->execute([$word, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get word trends: " . $e->getMessage());
        return [];
    }
}

// Get trending words
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

// Logging function
function log_message($message, $level = 'INFO') {
    $log_file = LOGS_DIR . '/analyzer.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Initialize database on first load
init_database();
?>