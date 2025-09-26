<?php
require_once 'config.php';

// Load current stopwords
$current_stopwords = load_json(STOPWORDS_FILE, $default_stopwords);

// Predefined stopword collections
$stopword_collections = [
    'basic_english' => [
        'name' => 'Basic English (Essential)',
        'description' => 'Core English articles, pronouns, and common words',
        'words' => [
            'the', 'and', 'to', 'of', 'a', 'in', 'that', 'is', 'it', 'with',
            'for', 'on', 'as', 'was', 'by', 'at', 'an', 'be', 'this', 'have',
            'from', 'or', 'which', 'one', 'you', 'we', 'are', 'all', 'your',
            'their', 'what', 'our', 'us', 'has', 'had', 'but', 'not', 'they',
            'i', 'he', 'she', 'his', 'her', 'him', 'them', 'so', 'if', 'about',
            'who', 'get', 'like', 'just', 'my', 'me', 'more', 'out', 'up', 'some'
        ]
    ],
    'extended_english' => [
        'name' => 'Extended English (Comprehensive)',
        'description' => 'Expanded list including verbs, adverbs, and conjunctions',
        'words' => [
            'the', 'and', 'to', 'of', 'a', 'in', 'that', 'is', 'it', 'with',
            'for', 'on', 'as', 'was', 'by', 'at', 'an', 'be', 'this', 'have',
            'from', 'or', 'which', 'one', 'you', 'we', 'are', 'all', 'your',
            'their', 'what', 'our', 'us', 'has', 'had', 'but', 'not', 'they',
            'i', 'he', 'she', 'his', 'her', 'him', 'them', 'so', 'if', 'about',
            'who', 'get', 'like', 'just', 'my', 'me', 'more', 'out', 'up', 'some',
            'will', 'how', 'when', 'where', 'why', 'can', 'should', 'would', 'could',
            'do', 'does', 'did', 'shall', 'may', 'might', 'must', 'ought',
            'then', 'than', 'now', 'here', 'there', 'while',
            'before', 'after', 'during', 'between', 'among', 'through', 'into',
            'over', 'under', 'above', 'below', 'off', 'down', 'away', 'back'
        ]
    ],
    'news_specific' => [
        'name' => 'News & Media Terms',
        'description' => 'Common words found in news articles and media',
        'words' => [
            'said', 'says', 'according', 'report', 'reports', 'reported', 'reporting',
            'news', 'new', 'article', 'story', 'breaking', 'update', 'updated',
            'continue', 'continued', 'reading', 'read', 'also', 'see',
            'today', 'yesterday', 'tomorrow', 'week', 'month', 'year', 'day',
            'time', 'first', 'last', 'next', 'previous', 'latest', 'recent',
            'people', 'person', 'man', 'woman', 'child', 'children', 'family',
            'government', 'official', 'officials', 'president', 'minister',
            'company', 'business', 'market', 'economy', 'economic', 'financial',
            'world', 'country', 'state', 'city', 'local', 'national', 'international'
        ]
    ],
    'social_media' => [
        'name' => 'Social Media & Web',
        'description' => 'Common terms from social platforms and web content',
        'words' => [
            'post', 'posts', 'comment', 'comments', 'like', 'likes', 'share', 'shared',
            'follow', 'following', 'follower', 'followers', 'user', 'users',
            'click', 'link', 'links', 'video', 'videos', 'image', 'images', 'photo', 'photos',
            'tweet', 'tweets', 'retweet', 'facebook', 'twitter', 'instagram', 'youtube',
            'social', 'media', 'platform', 'platforms', 'online', 'internet', 'web',
            'site', 'website', 'page', 'pages', 'blog', 'blogger', 'content',
            'digital', 'app', 'apps', 'mobile', 'phone', 'smartphone', 'technology'
        ]
    ],
    'numbers_dates' => [
        'name' => 'Numbers, Dates & Time',
        'description' => 'Numeric expressions, dates, and time-related terms',
        'words' => [
            'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
            'first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth',
            'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august',
            'september', 'october', 'november', 'december',
            'morning', 'afternoon', 'evening', 'night', 'today', 'tomorrow', 'yesterday',
            'week', 'weeks', 'month', 'months', 'year', 'years', 'day', 'days',
            'hour', 'hours', 'minute', 'minutes', 'second', 'seconds', 'time', 'times'
        ]
    ],
    'profanity_filter' => [
        'name' => 'Profanity & Offensive Terms',
        'description' => 'Common profanity and potentially offensive words',
        'words' => [
            'damn', 'hell', 'crap', 'stupid', 'idiot', 'moron', 'fool', 'dumb',
            'hate', 'sucks', 'worst', 'terrible', 'awful', 'horrible', 'disgusting',
            'annoying', 'boring', 'lame', 'pathetic', 'ridiculous', 'absurd'
        ]
    ]
];

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Import from uploaded JSON/text file
    if (isset($_POST['import_file']) && isset($_FILES['stopword_file'])) {
        $uploaded_file = $_FILES['stopword_file'];
        
        if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
            $file_content = file_get_contents($uploaded_file['tmp_name']);
            $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
            
            $imported_words = [];
            
            if ($file_extension === 'json') {
                $json_data = json_decode($file_content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $imported_words = is_array($json_data) ? $json_data : [];
                }
            } else {
                // Treat as text file - one word per line or comma-separated
                $file_content = strtolower($file_content);
                $file_content = preg_replace('/[^\w\s,\n\r]/', '', $file_content);
                $words = preg_split('/[\s,\n\r]+/', $file_content, -1, PREG_SPLIT_NO_EMPTY);
                $imported_words = array_unique(array_filter($words, function($word) {
                    return strlen(trim($word)) >= 2;
                }));
            }
            
            if (!empty($imported_words)) {
                $merge_option = $_POST['merge_option'] ?? 'replace';
                
                if ($merge_option === 'replace') {
                    $current_stopwords = array_values(array_unique($imported_words));
                    $message = "Stopwords replaced successfully! Imported " . count($current_stopwords) . " words.";
                } else {
                    // Merge - avoid duplicates
                    $merged_words = array_unique(array_merge($current_stopwords, $imported_words));
                    $added_count = count($merged_words) - count($current_stopwords);
                    $current_stopwords = array_values($merged_words);
                    $message = "Stopwords merged successfully! Added $added_count new words.";
                }
                
                save_json(STOPWORDS_FILE, $current_stopwords);
                $message_type = 'success';
                log_message("Bulk stopword import: " . $message);
                
            } else {
                $message = "No valid words found in the uploaded file.";
                $message_type = 'error';
            }
        } else {
            $message = "File upload failed. Please try again.";
            $message_type = 'error';
        }
    }
    
    // Import from URL
    if (isset($_POST['import_url'])) {
        $import_url = trim($_POST['import_url']);
        
        if (!empty($import_url)) {
            $file_content = @file_get_contents($import_url);
            
            if ($file_content !== false) {
                $imported_words = [];
                
                // Try JSON first
                $json_data = json_decode($file_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                    $imported_words = $json_data;
                } else {
                    // Treat as text
                    $file_content = strtolower($file_content);
                    $file_content = preg_replace('/[^\w\s,\n\r]/', '', $file_content);
                    $words = preg_split('/[\s,\n\r]+/', $file_content, -1, PREG_SPLIT_NO_EMPTY);
                    $imported_words = array_unique(array_filter($words, function($word) {
                        return strlen(trim($word)) >= 2;
                    }));
                }
                
                if (!empty($imported_words)) {
                    $merge_option = $_POST['merge_option_url'] ?? 'replace';
                    
                    if ($merge_option === 'replace') {
                        $current_stopwords = array_values(array_unique($imported_words));
                        $message = "Stopwords imported from URL successfully! Loaded " . count($current_stopwords) . " words.";
                    } else {
                        $merged_words = array_unique(array_merge($current_stopwords, $imported_words));
                        $added_count = count($merged_words) - count($current_stopwords);
                        $current_stopwords = array_values($merged_words);
                        $message = "Stopwords merged from URL successfully! Added $added_count new words.";
                    }
                    
                    save_json(STOPWORDS_FILE, $current_stopwords);
                    $message_type = 'success';
                    log_message("URL stopword import: " . $message);
                    
                } else {
                    $message = "No valid words found at the provided URL.";
                    $message_type = 'error';
                }
            } else {
                $message = "Failed to fetch data from URL. Please check the URL and try again.";
                $message_type = 'error';
            }
        }
    }
    
    // Import predefined collection
    if (isset($_POST['import_collection'])) {
        $collection_key = $_POST['collection_key'];
        
        if (isset($stopword_collections[$collection_key])) {
            $collection = $stopword_collections[$collection_key];
            $merge_option = $_POST['merge_option_collection'] ?? 'merge';
            
            if ($merge_option === 'replace') {
                $current_stopwords = array_values(array_unique($collection['words']));
                $message = "Replaced with " . $collection['name'] . " collection (" . count($current_stopwords) . " words).";
            } else {
                $merged_words = array_unique(array_merge($current_stopwords, $collection['words']));
                $added_count = count($merged_words) - count($current_stopwords);
                $current_stopwords = array_values($merged_words);
                $message = "Added " . $collection['name'] . " collection ($added_count new words).";
            }
            
            save_json(STOPWORDS_FILE, $current_stopwords);
            $message_type = 'success';
            log_message("Stopword collection import: " . $message);
        }
    }
    
    // Bulk add words (manual entry)
    if (isset($_POST['bulk_add'])) {
        $bulk_words = trim($_POST['bulk_words']);
        
        if (!empty($bulk_words)) {
            // Parse words - support comma, space, or newline separated
            $bulk_words = strtolower($bulk_words);
            $bulk_words = preg_replace('/[^\w\s,\n\r]/', '', $bulk_words);
            $words = preg_split('/[\s,\n\r]+/', $bulk_words, -1, PREG_SPLIT_NO_EMPTY);
            $words = array_unique(array_filter($words, function($word) {
                return strlen(trim($word)) >= 2;
            }));
            
            if (!empty($words)) {
                $merged_words = array_unique(array_merge($current_stopwords, $words));
                $added_count = count($merged_words) - count($current_stopwords);
                $current_stopwords = array_values($merged_words);
                
                save_json(STOPWORDS_FILE, $current_stopwords);
                $message = "Added $added_count new stopwords successfully!";
                $message_type = 'success';
                log_message("Bulk manual stopword add: $added_count words");
            }
        }
    }
    
    // Clear all stopwords
    if (isset($_POST['clear_all'])) {
        $current_stopwords = [];
        save_json(STOPWORDS_FILE, $current_stopwords);
        $message = "All stopwords cleared successfully.";
        $message_type = 'success';
        log_message("All stopwords cleared");
    }
    
    // Reset to defaults
    if (isset($_POST['reset_defaults'])) {
        $current_stopwords = $default_stopwords;
        save_json(STOPWORDS_FILE, $current_stopwords);
        $message = "Stopwords reset to default list (" . count($current_stopwords) . " words).";
        $message_type = 'success';
        log_message("Stopwords reset to defaults");
    }
}

// Export functionality
if (isset($_GET['export'])) {
    $format = $_GET['format'] ?? 'json';
    
    if ($format === 'txt') {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="stopwords_export_' . date('Y-m-d_H-i-s') . '.txt"');
        echo implode("\n", $current_stopwords);
    } else {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="stopwords_export_' . date('Y-m-d_H-i-s') . '.json"');
        echo json_encode($current_stopwords, JSON_PRETTY_PRINT);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Stopword Manager</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .manager-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .manager-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
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
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
        }
        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            min-height: 100px;
            resize: vertical;
        }
        .btn {
            padding: 10px 20px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn:hover {
            background: #1565c0;
        }
        .btn-danger {
            background: #d32f2f;
        }
        .btn-danger:hover {
            background: #b71c1c;
        }
        .btn-success {
            background: #388e3c;
        }
        .btn-success:hover {
            background: #2e7d32;
        }
        .btn-warning {
            background: #f57c00;
        }
        .btn-warning:hover {
            background: #ef6c00;
        }
        .collection-item {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .collection-name {
            font-weight: bold;
            color: #1976d2;
        }
        .collection-description {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .word-count {
            color: #666;
            font-size: 0.9em;
            float: right;
        }
        .radio-group {
            display: flex;
            gap: 15px;
            margin: 10px 0;
        }
        .current-stopwords {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            background: #fafafa;
        }
        .stopword-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .stopword-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            white-space: nowrap;
        }
        .export-links {
            display: flex;
            gap: 10px;
            margin: 10px 0;
        }
        .export-link {
            display: inline-block;
            padding: 8px 16px;
            background: #4caf50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .export-link:hover {
            background: #45a049;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #1976d2;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>🚫 Bulk Stopword Manager</h1>
            <div class="nav-buttons">
                <a href="index.php" class="nav-btn">← Back to Main</a>
                <a href="feed_manager.php" class="nav-btn">📂 Feed Manager</a>
                <a href="analytics.php" class="nav-btn">📊 Analytics</a>
                <a href="wordcloud.php" class="nav-btn">🎨 Word Cloud</a>
            </div>
        </div>

        <!-- Status Message -->
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= sanitize_output($message) ?>
            </div>
        <?php endif; ?>

        <!-- Current Status -->
        <div class="manager-card">
            <h3>📊 Current Stopword Status</h3>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?= count($current_stopwords) ?></div>
                    <div class="stat-label">Total Stopwords</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= count(array_filter($current_stopwords, function($w) { return strlen($w) <= 3; })) ?></div>
                    <div class="stat-label">Short Words (≤3 chars)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= count(array_filter($current_stopwords, function($w) { return strlen($w) >= 7; })) ?></div>
                    <div class="stat-label">Long Words (≥7 chars)</div>
                </div>
            </div>
            
            <?php if (!empty($current_stopwords)): ?>
                <div class="export-links">
                    <a href="?export=1&format=json" class="export-link">📥 Export as JSON</a>
                    <a href="?export=1&format=txt" class="export-link">📄 Export as Text</a>
                </div>
                
                <div class="current-stopwords">
                    <div class="stopword-cloud">
                        <?php foreach (array_slice($current_stopwords, 0, 100) as $word): ?>
                            <span class="stopword-tag"><?= sanitize_output($word) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($current_stopwords) > 100): ?>
                            <span class="stopword-tag" style="background: #fff3e0; color: #e65100;">
                                +<?= count($current_stopwords) - 100 ?> more...
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p><em>No stopwords currently configured. Use the import options below to get started!</em></p>
            <?php endif; ?>
        </div>

        <div class="manager-grid">
            <!-- Import from File -->
            <div class="manager-card">
                <h3>📁 Import from File</h3>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Choose File (JSON or Text):</label>
                        <input type="file" name="stopword_file" accept=".json,.txt,.csv" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Import Method:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="merge_option" value="replace" checked> Replace All</label>
                            <label><input type="radio" name="merge_option" value="merge"> Merge (Add New)</label>
                        </div>
                    </div>
                    
                    <button type="submit" name="import_file" class="btn">📁 Import from File</button>
                </form>
                
                <small>
                    <strong>Supported formats:</strong><br>
                    • JSON: <code>["word1", "word2", "word3"]</code><br>
                    • Text: One word per line or comma-separated
                </small>
            </div>

            <!-- Import from URL -->
            <div class="manager-card">
                <h3>🌐 Import from URL</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Stopwords File URL:</label>
                        <input type="url" name="import_url" class="form-input" placeholder="https://example.com/stopwords.json" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Import Method:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="merge_option_url" value="replace" checked> Replace All</label>
                            <label><input type="radio" name="merge_option_url" value="merge"> Merge (Add New)</label>
                        </div>
                    </div>
                    
                    <button type="submit" name="import_url" class="btn">🌐 Import from URL</button>
                </form>
            </div>

            <!-- Predefined Collections -->
            <div class="manager-card">
                <h3>📚 Predefined Collections</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Choose Collection:</label>
                        <select name="collection_key" class="form-input" required>
                            <?php foreach ($stopword_collections as $key => $collection): ?>
                                <option value="<?= $key ?>">
                                    <?= $collection['name'] ?> (<?= count($collection['words']) ?> words)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Import Method:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="merge_option_collection" value="replace"> Replace All</label>
                            <label><input type="radio" name="merge_option_collection" value="merge" checked> Merge (Add New)</label>
                        </div>
                    </div>
                    
                    <button type="submit" name="import_collection" class="btn btn-success">📚 Import Collection</button>
                </form>
                
                <!-- Collection Details -->
                <div style="margin-top: 15px;">
                    <h4>Available Collections:</h4>
                    <?php foreach ($stopword_collections as $key => $collection): ?>
                        <div class="collection-item">
                            <div class="collection-name">
                                <?= $collection['name'] ?>
                                <span class="word-count"><?= count($collection['words']) ?> words</span>
                            </div>
                            <div class="collection-description"><?= $collection['description'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Manual Bulk Add -->
            <div class="manager-card">
                <h3>✏️ Manual Bulk Entry</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Add Multiple Words:</label>
                        <textarea name="bulk_words" class="form-textarea" placeholder="Enter words separated by commas, spaces, or new lines:&#10;example, words, here&#10;or one per line&#10;like this"></textarea>
                    </div>
                    
                    <button type="submit" name="bulk_add" class="btn">✏️ Add Words</button>
                </form>
                
                <small>
                    <strong>Tips:</strong><br>
                    • Words will be automatically lowercased<br>
                    • Duplicates will be removed<br>
                    • Only words 2+ characters will be kept
                </small>
            </div>

            <!-- Management Actions -->
            <div class="manager-card">
                <h3>⚙️ Management Actions</h3>
                
                <form method="post" style="display: inline-block;">
                    <button type="submit" name="reset_defaults" class="btn btn-warning" onclick="return confirm('Reset to default stopwords? This will replace your current list.');">🔄 Reset to Defaults</button>