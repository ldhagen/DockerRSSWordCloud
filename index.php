<?php
require_once 'config.php';

// Load data
$feeds_data = load_json(FEEDS_FILE, ['feeds' => []]);
$stopwords = load_json(STOPWORDS_FILE, $default_stopwords);

// Get collapse states from POST, GET, or initialize
$feed_collapsed = isset($_POST['feed_collapsed']) ? (bool)$_POST['feed_collapsed'] : 
                 (isset($_GET['feed_collapsed']) ? (bool)$_GET['feed_collapsed'] : false);
$stopword_collapsed = isset($_POST['stopword_collapsed']) ? (bool)$_POST['stopword_collapsed'] : 
                     (isset($_GET['stopword_collapsed']) ? (bool)$_GET['stopword_collapsed'] : false);

// Initialize session variables if not set
if (!isset($_SESSION['word_counts'])) {
    $_SESSION['word_counts'] = [];
}
if (!isset($_SESSION['articles'])) {
    $_SESSION['articles'] = [];
}
if (!isset($_SESSION['form_state'])) {
    $_SESSION['form_state'] = [
        'selected_feeds' => [],
        'limit' => 50,
        'feed_specific' => false,
        'feeds_data' => $feeds_data['feeds'] // Store the actual feed data for checkboxes
    ];
}

// Load from session
$form_state = $_SESSION['form_state'];
$selected_feeds = $form_state['selected_feeds'];
$limit = $form_state['limit'];
$feed_specific = $form_state['feed_specific'];
$word_counts = $_SESSION['word_counts'];
$articles = $_SESSION['articles'];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new feed
    if (isset($_POST['add_feed'])) {
        $url = trim($_POST['feed_url'] ?? '');
        $name = trim($_POST['feed_name'] ?? '');
        
        if (!empty($url) && !empty($name)) {
            $feeds_data['feeds'][] = [
                'url' => $url,
                'name' => $name
            ];
            save_json(FEEDS_FILE, $feeds_data);
            // Update session with new feeds data
            $_SESSION['form_state']['feeds_data'] = $feeds_data['feeds'];
            log_message("New feed added: $name ($url)");
        }
    }
    
    // Remove feed
    if (isset($_POST['remove_feed'])) {
        $index = (int)($_POST['feed_index'] ?? -1);
        if (isset($feeds_data['feeds'][$index])) {
            $removed_feed = $feeds_data['feeds'][$index];
            array_splice($feeds_data['feeds'], $index, 1);
            save_json(FEEDS_FILE, $feeds_data);
            // Update session with updated feeds data
            $_SESSION['form_state']['feeds_data'] = $feeds_data['feeds'];
            log_message("Feed removed: " . $removed_feed['name']);
        }
    }
    
    // Add stopword
    if (isset($_POST['add_stopword'])) {
        $word = trim(strtolower($_POST['stopword'] ?? ''));
        if (!empty($word) && !in_array($word, $stopwords)) {
            $stopwords[] = $word;
            save_json(STOPWORDS_FILE, $stopwords);
            log_message("Stopword added: $word");
        }
    }
    
    // Remove stopword
    if (isset($_POST['remove_stopword'])) {
        $word = $_POST['stopword'] ?? '';
        $index = array_search($word, $stopwords);
        if ($index !== false) {
            array_splice($stopwords, $index, 1);
            save_json(STOPWORDS_FILE, $stopwords);
            log_message("Stopword removed: $word");
        }
    }
    
    // Process RSS feeds - ENHANCED VERSION
    if (isset($_POST['process_feeds'])) {
        $limit = (int)($_POST['limit'] ?? 50);
        $selected_feeds = $_POST['feeds'] ?? [];
        $feed_specific = isset($_POST['feed_specific']);
        
        $all_content = '';
        $articles = [];
        $word_counts = [];
        $processing_log = [];
        
        foreach ($feeds_data['feeds'] as $index => $feed) {
            if (empty($selected_feeds) || in_array($index, $selected_feeds)) {
                $start_time = microtime(true);
                $rss_content = fetch_rss($feed['url']);
                
                if ($rss_content) {
                    $content = parse_rss_content($rss_content);
                    $all_content .= ' ' . $content;
                    
                    // Store articles for this feed
                    $feed_articles = extract_articles($rss_content, $feed['name']);
                    $articles = array_merge($articles, $feed_articles);
                    
                    if ($feed_specific) {
                        $feed_word_counts = count_words($content, $stopwords);
                        $word_counts[$feed['name']] = array_slice($feed_word_counts, 0, $limit);
                        
                        // ENHANCED: Store in database
                        store_collection_data($feed['name'], $feed_articles, $feed_word_counts);
                    }
                    
                    $processing_time = round(microtime(true) - $start_time, 2);
                    $processing_log[] = [
                        'feed' => $feed['name'],
                        'articles' => count($feed_articles),
                        'words' => array_sum($feed_word_counts ?? []),
                        'time' => $processing_time,
                        'status' => 'success'
                    ];
                    
                    log_message("Processed feed: {$feed['name']} - " . count($feed_articles) . " articles in {$processing_time}s");
                } else {
                    $processing_log[] = [
                        'feed' => $feed['name'],
                        'articles' => 0,
                        'words' => 0,
                        'time' => 0,
                        'status' => 'failed'
                    ];
                    log_message("Failed to process feed: {$feed['name']}", 'ERROR');
                }
            }
        }
        
        if (!$feed_specific && !empty($all_content)) {
            $all_word_counts = count_words($all_content, $stopwords);
            $word_counts['all'] = array_slice($all_word_counts, 0, $limit);
            
            // ENHANCED: Store combined data
            store_collection_data('Combined Feeds', $articles, $all_word_counts);
        }
        
        // Store results and form state in session
        $_SESSION['word_counts'] = $word_counts;
        $_SESSION['articles'] = $articles;
        $_SESSION['processing_log'] = $processing_log; // NEW: Store processing stats
        $_SESSION['form_state'] = [
            'selected_feeds' => $selected_feeds,
            'limit' => $limit,
            'feed_specific' => $feed_specific,
            'feeds_data' => $feeds_data['feeds']
        ];
        
        // Update local variables from session
        $form_state = $_SESSION['form_state'];
        $selected_feeds = $form_state['selected_feeds'];
        $limit = $form_state['limit'];
        $feed_specific = $form_state['feed_specific'];
        
        log_message("Processing completed - Total articles: " . count($articles) . ", Total words: " . array_sum(array_map('array_sum', $word_counts)));
    }
}

// Display articles for a specific word
$word_articles = [];
if (isset($_GET['word'])) {
    $search_word = urldecode($_GET['word']);
    foreach ($articles as $article) {
        if (stripos($article['title'] . ' ' . $article['description'], $search_word) !== false) {
            $word_articles[] = $article;
        }
    }
}

// Function to generate URL with preserved state
function generate_url_with_state($params = []) {
    $current_params = $_GET;
    $current_params['feed_collapsed'] = $GLOBALS['feed_collapsed'] ? '1' : '0';
    $current_params['stopword_collapsed'] = $GLOBALS['stopword_collapsed'] ? '1' : '0';
    
    foreach ($params as $key => $value) {
        $current_params[$key] = $value;
    }
    
    return '?' . http_build_query($current_params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSS Word Counter - Enhanced</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .nav-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .nav-btn {
            padding: 10px 20px;
            background: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .nav-btn:hover {
            background: #1565c0;
        }
        .processing-stats {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .stat-item {
            display: inline-block;
            margin: 5px 15px 5px 0;
            padding: 5px 10px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #1976d2;
        }
        .stat-success { border-left-color: #4caf50; }
        .stat-error { border-left-color: #f44336; }
        .database-info {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📰 RSS Word Counter - Enhanced</h1>
        
        <!-- Navigation -->
        <div class="nav-buttons">
            <a href="analytics.php" class="nav-btn">📊 Analytics Dashboard</a>
            <a href="wordcloud.php" class="nav-btn">🎨 Word Cloud</a>
        </div>

        <!-- Database Status -->
        <?php if (get_db()): ?>
            <div class="database-info">
                ✅ Enhanced analytics enabled - Data is being stored for trend analysis
            </div>
        <?php endif; ?>
        
        <!-- Feed Management -->
        <div class="section">
            <h2 onclick="toggleSection('feed-management')" style="cursor: pointer; display: flex; align-items: center;">
                <span>Manage RSS Feeds</span>
                <span class="toggle-icon" id="feed-toggle"><?= $feed_collapsed ? '▶' : '▼' ?></span>
            </h2>
            <div id="feed-management" class="collapsible-content" style="<?= $feed_collapsed ? 'display: none;' : '' ?>">
                <form method="post" class="form-inline">
                    <input type="hidden" name="feed_collapsed" id="feed-collapsed-input" value="<?= $feed_collapsed ? '1' : '0' ?>">
                    <input type="hidden" name="stopword_collapsed" id="stopword-collapsed-input" value="<?= $stopword_collapsed ? '1' : '0' ?>">
                    <input type="url" name="feed_url" placeholder="RSS URL" required>
                    <input type="text" name="feed_name" placeholder="Feed Name" required>
                    <button type="submit" name="add_feed">Add Feed</button>
                </form>
                
                <div class="feed-list">
                    <h3>Current Feeds (<?= count($feeds_data['feeds']) ?>)</h3>
                    <?php foreach ($feeds_data['feeds'] as $index => $feed): ?>
                        <div class="feed-item">
                            <span><?= sanitize_output($feed['name']) ?>: <?= sanitize_output($feed['url']) ?></span>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="feed_collapsed" value="<?= $feed_collapsed ? '1' : '0' ?>">
                                <input type="hidden" name="stopword_collapsed" value="<?= $stopword_collapsed ? '1' : '0' ?>">
                                <input type="hidden" name="feed_index" value="<?= $index ?>">
                                <button type="submit" name="remove_feed" class="btn-danger">Remove</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Stopwords Management -->
        <div class="section">
            <h2 onclick="toggleSection('stopword-management')" style="cursor: pointer; display: flex; align-items: center;">
                <span>Manage Stopwords</span>
                <span class="toggle-icon" id="stopword-toggle"><?= $stopword_collapsed ? '▶' : '▼' ?></span>
            </h2>
            <div id="stopword-management" class="collapsible-content" style="<?= $stopword_collapsed ? 'display: none;' : '' ?>">
                <form method="post" class="form-inline">
                    <input type="hidden" name="feed_collapsed" value="<?= $feed_collapsed ? '1' : '0' ?>">
                    <input type="hidden" name="stopword_collapsed" value="<?= $stopword_collapsed ? '1' : '0' ?>">
                    <input type="text" name="stopword" placeholder="Stopword" required>
                    <button type="submit" name="add_stopword">Add Stopword</button>
                </form>
                
                <div class="stopword-list">
                    <h3>Current Stopwords (<?= count($stopwords) ?>)</h3>
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($stopwords as $word): ?>
                            <div class="stopword-item">
                                <span><?= sanitize_output($word) ?></span>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="feed_collapsed" value="<?= $feed_collapsed ? '1' : '0' ?>">
                                    <input type="hidden" name="stopword_collapsed" value="<?= $stopword_collapsed ? '1' : '0' ?>">
                                    <input type="hidden" name="stopword" value="<?= sanitize_output($word) ?>">
                                    <button type="submit" name="remove_stopword" class="btn-danger">Remove</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Process Feeds -->
        <div class="section">
            <h2>🔄 Process RSS Feeds</h2>
            <form method="post">
                <input type="hidden" name="feed_collapsed" id="process-feed-collapsed" value="<?= $feed_collapsed ? '1' : '0' ?>">
                <input type="hidden" name="stopword_collapsed" id="process-stopword-collapsed" value="<?= $stopword_collapsed ? '1' : '0' ?>">
                
                <div class="form-group">
                    <label>Select Feeds:</label>
                    <div class="feed-selector">
                        <button type="button" onclick="selectAllFeeds()">Select All</button>
                        <button type="button" onclick="deselectAllFeeds()">Deselect All</button>
                    </div>
                    <?php foreach ($feeds_data['feeds'] as $index => $feed): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="feeds[]" value="<?= $index ?>" 
                                <?= in_array($index, $selected_feeds) ? 'checked' : '' ?>>
                            <?= sanitize_output($feed['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="feed_specific" value="1" <?= $feed_specific ? 'checked' : '' ?>>
                        Show counts per feed (stores individual feed data)
                    </label>
                </div>
                
                <div class="form-group">
                    <label>Number of words to display:</label>
                    <input type="number" name="limit" value="<?= $limit ?>" min="1" max="500">
                </div>
                
                <button type="submit" name="process_feeds" style="background: #4caf50; padding: 12px 24px; font-size: 16px;">
                    🚀 Process Feeds & Store Analytics
                </button>
            </form>
        </div>

        <!-- Processing Statistics -->
        <?php if (isset($_SESSION['processing_log'])): ?>
            <div class="processing-stats">
                <h3>📊 Processing Results</h3>
                <?php foreach ($_SESSION['processing_log'] as $log): ?>
                    <div class="stat-item <?= $log['status'] === 'success' ? 'stat-success' : 'stat-error' ?>">
                        <strong><?= sanitize_output($log['feed']) ?></strong>: 
                        <?php if ($log['status'] === 'success'): ?>
                            <?= $log['articles'] ?> articles, <?= number_format($log['words']) ?> words (<?= $log['time'] ?>s)
                        <?php else: ?>
                            ❌ Failed to fetch
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Results -->
        <?php if (!empty($word_counts)): ?>
            <div class="section">
                <h2>📈 Word Count Results</h2>
                
                <?php if ($feed_specific): ?>
                    <?php foreach ($word_counts as $feed_name => $counts): ?>
                        <h3><?= sanitize_output($feed_name) ?></h3>
                        <div class="word-cloud">
                            <?php foreach ($counts as $word => $count): ?>
                                <span class="word-item">
                                    <a href="<?= generate_url_with_state(['word' => $word]) ?>" class="word-link">
                                        <?= sanitize_output($word) ?> (<?= $count ?>)
                                    </a>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="word-cloud">
                        <?php foreach ($word_counts['all'] as $word => $count): ?>
                            <span class="word-item">
                                <a href="<?= generate_url_with_state(['word' => $word]) ?>" class="word-link">
                                    <?= sanitize_output($word) ?> (<?= $count ?>)
                                </a>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Enhanced Actions -->
                <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 8px;">
                    <p><strong>🎯 Explore Your Results:</strong></p>
                    <a href="analytics.php" class="nav-btn" style="margin-right: 10px;">📊 View Trends & Analytics</a>
                    <a href="wordcloud.php" class="nav-btn">🎨 Interactive Word Cloud</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Word Articles -->
        <?php if (!empty($word_articles)): ?>
            <div class="section">
                <h2>📰 Articles containing "<?= sanitize_output($search_word) ?>"</h2>
                <div class="article-list">
                    <?php foreach ($word_articles as $article): ?>
                        <div class="article-item">
                            <h3><a href="<?= sanitize_output($article['link']) ?>" target="_blank"><?= sanitize_output($article['title']) ?></a></h3>
                            <p><?= sanitize_output($article['description']) ?></p>
                            <small>Source: <?= sanitize_output($article['feed']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 20px;">
                    <a href="<?= generate_url_with_state(['word' => null]) ?>" class="btn-back">← Back to Results</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Start Guide -->
        <?php if (empty($word_counts)): ?>
            <div class="section" style="background: #f8f9fa; border-left: 5px solid #1976d2;">
                <h3>🚀 Quick Start Guide</h3>
                <ol>
                    <li><strong>Select Feeds:</strong> Choose which RSS feeds to analyze above</li>
                    <li><strong>Click Process:</strong> Hit the "Process Feeds" button</li>
                    <li><strong>Explore Results:</strong> View word frequencies and click words to see source articles</li>
                    <li><strong>Analyze Trends:</strong> Visit the Analytics Dashboard to see word trends over time</li>
                    <li><strong>Visualize:</strong> Check out the Interactive Word Cloud for a visual representation</li>
                </ol>
                <p><em>All data is automatically stored for advanced analytics and trend tracking!</em></p>
            </div>
        <?php endif; ?>
    </div>

    <script src="js/script.js"></script>
    <script>
        function selectAllFeeds() {
            document.querySelectorAll('input[name="feeds[]"]').forEach(cb => cb.checked = true);
        }
        
        function deselectAllFeeds() {
            document.querySelectorAll('input[name="feeds[]"]').forEach(cb => cb.checked = false);
        }
        
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            const toggle = document.getElementById(sectionId.replace('-', '-') + (sectionId.includes('feed') ? '' : '') + 'toggle');
            
            if (section.style.display === 'none') {
                section.style.display = 'block';
                toggle.textContent = '▼';
            } else {
                section.style.display = 'none';
                toggle.textContent = '▶';
            }
        }
    </script>
</body>
</html>