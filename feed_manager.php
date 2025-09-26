<?php
require_once 'config.php';

// Load current feeds
$feeds_data = load_json(FEEDS_FILE, ['feeds' => []]);

// Default feed collections for easy import
$default_collections = [
    'news_major' => [
        'name' => 'Major News Sources',
        'feeds' => [
            ['url' => 'https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml', 'name' => 'New York Times - Home Page'],
            ['url' => 'https://feeds.bbci.co.uk/news/rss.xml', 'name' => 'BBC News'],
            ['url' => 'https://www.theguardian.com/world/rss', 'name' => 'The Guardian - World News'],
            ['url' => 'https://www.reuters.com/feed/', 'name' => 'Reuters News'],
            ['url' => 'https://feeds.washingtonpost.com/rss/national', 'name' => 'Washington Post - National'],
        ]
    ],
    'tech' => [
        'name' => 'Technology News',
        'feeds' => [
            ['url' => 'https://feeds.feedburner.com/TechCrunch', 'name' => 'TechCrunch'],
            ['url' => 'https://www.wired.com/feed/rss', 'name' => 'Wired'],
            ['url' => 'https://feeds.arstechnica.com/arstechnica/index', 'name' => 'Ars Technica'],
            ['url' => 'https://feeds.feedburner.com/venturebeat/SZYF', 'name' => 'VentureBeat'],
            ['url' => 'https://feeds.feedburner.com/TheHackersNews', 'name' => 'The Hacker News'],
        ]
    ],
    'business' => [
        'name' => 'Business & Finance',
        'feeds' => [
            ['url' => 'https://feeds.content.dowjones.io/public/rss/WSJcomUSBusiness', 'name' => 'WSJ US Business'],
            ['url' => 'https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=100003114', 'name' => 'CNBC Top News'],
            ['url' => 'https://feeds.bloomberg.com/markets/news.rss', 'name' => 'Bloomberg Markets'],
            ['url' => 'https://feeds.fortune.com/fortune/feed', 'name' => 'Fortune'],
            ['url' => 'https://feeds.feedburner.com/entrepreneur/latest', 'name' => 'Entrepreneur'],
        ]
    ],
    'science' => [
        'name' => 'Science & Research',
        'feeds' => [
            ['url' => 'https://rss.sciencedaily.com/top.xml', 'name' => 'Science Daily'],
            ['url' => 'https://feeds.feedburner.com/NG/News', 'name' => 'National Geographic News'],
            ['url' => 'https://www.nature.com/nature.rss', 'name' => 'Nature'],
            ['url' => 'https://feeds.feedburner.com/ScientificAmerican-News', 'name' => 'Scientific American'],
            ['url' => 'https://phys.org/rss-feed/', 'name' => 'Phys.org'],
        ]
    ]
];

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Import from uploaded JSON file
    if (isset($_POST['import_file']) && isset($_FILES['feed_file'])) {
        $uploaded_file = $_FILES['feed_file'];
        
        if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
            $json_content = file_get_contents($uploaded_file['tmp_name']);
            $imported_data = json_decode($json_content, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($imported_data['feeds'])) {
                $merge_option = $_POST['merge_option'] ?? 'replace';
                
                if ($merge_option === 'replace') {
                    $feeds_data = $imported_data;
                    $message = "Feeds replaced successfully! Imported " . count($imported_data['feeds']) . " feeds.";
                } else {
                    // Merge - avoid duplicates by URL
                    $existing_urls = array_column($feeds_data['feeds'], 'url');
                    $added_count = 0;
                    
                    foreach ($imported_data['feeds'] as $new_feed) {
                        if (!in_array($new_feed['url'], $existing_urls)) {
                            $feeds_data['feeds'][] = $new_feed;
                            $added_count++;
                        }
                    }
                    
                    $message = "Feeds merged successfully! Added $added_count new feeds.";
                }
                
                save_json(FEEDS_FILE, $feeds_data);
                $message_type = 'success';
                log_message("Bulk import: " . $message);
                
            } else {
                $message = "Invalid JSON format. Please ensure the file contains a 'feeds' array.";
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
            $json_content = @file_get_contents($import_url);
            
            if ($json_content !== false) {
                $imported_data = json_decode($json_content, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($imported_data['feeds'])) {
                    $merge_option = $_POST['merge_option_url'] ?? 'replace';
                    
                    if ($merge_option === 'replace') {
                        $feeds_data = $imported_data;
                        $message = "Feeds imported from URL successfully! Loaded " . count($imported_data['feeds']) . " feeds.";
                    } else {
                        $existing_urls = array_column($feeds_data['feeds'], 'url');
                        $added_count = 0;
                        
                        foreach ($imported_data['feeds'] as $new_feed) {
                            if (!in_array($new_feed['url'], $existing_urls)) {
                                $feeds_data['feeds'][] = $new_feed;
                                $added_count++;
                            }
                        }
                        
                        $message = "Feeds merged from URL successfully! Added $added_count new feeds.";
                    }
                    
                    save_json(FEEDS_FILE, $feeds_data);
                    $message_type = 'success';
                    log_message("URL import: " . $message);
                    
                } else {
                    $message = "Invalid JSON format from URL.";
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
        
        if (isset($default_collections[$collection_key])) {
            $collection = $default_collections[$collection_key];
            $merge_option = $_POST['merge_option_collection'] ?? 'merge';
            
            if ($merge_option === 'replace') {
                $feeds_data['feeds'] = $collection['feeds'];
                $message = "Replaced with " . $collection['name'] . " collection (" . count($collection['feeds']) . " feeds).";
            } else {
                $existing_urls = array_column($feeds_data['feeds'], 'url');
                $added_count = 0;
                
                foreach ($collection['feeds'] as $new_feed) {
                    if (!in_array($new_feed['url'], $existing_urls)) {
                        $feeds_data['feeds'][] = $new_feed;
                        $added_count++;
                    }
                }
                
                $message = "Added " . $collection['name'] . " collection ($added_count new feeds).";
            }
            
            save_json(FEEDS_FILE, $feeds_data);
            $message_type = 'success';
            log_message("Collection import: " . $message);
        }
    }
    
    // Clear all feeds
    if (isset($_POST['clear_all'])) {
        $feeds_data['feeds'] = [];
        save_json(FEEDS_FILE, $feeds_data);
        $message = "All feeds cleared successfully.";
        $message_type = 'success';
        log_message("All feeds cleared");
    }
}

// Export functionality
if (isset($_GET['export'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="rss_feeds_export_' . date('Y-m-d_H-i-s') . '.json"');
    echo json_encode($feeds_data, JSON_PRETTY_PRINT);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Feed Manager</title>
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
        .feed-count {
            color: #666;
            font-size: 0.9em;
        }
        .radio-group {
            display: flex;
            gap: 15px;
            margin: 10px 0;
        }
        .current-feeds {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        .feed-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .export-link {
            display: inline-block;
            padding: 10px 20px;
            background: #4caf50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        .export-link:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>📂 Bulk Feed Manager</h1>
            <div class="nav-buttons">
                <a href="index.php" class="nav-btn">← Back to Main</a>
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

        <!-- Current Feeds Status -->
        <div class="manager-card">
            <h3>📊 Current Status</h3>
            <p><strong>Total Feeds:</strong> <?= count($feeds_data['feeds']) ?></p>
            
            <?php if (!empty($feeds_data['feeds'])): ?>
                <a href="?export=1" class="export-link">📥 Export Current Feeds</a>
                
                <div class="current-feeds">
                    <?php foreach ($feeds_data['feeds'] as $feed): ?>
                        <div class="feed-item">
                            <strong><?= sanitize_output($feed['name']) ?></strong><br>
                            <small><?= sanitize_output($feed['url']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><em>No feeds currently configured. Use the import options below to get started!</em></p>
            <?php endif; ?>
        </div>

        <div class="manager-grid">
            <!-- Import from File -->
            <div class="manager-card">
                <h3>📁 Import from JSON File</h3>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Choose JSON File:</label>
                        <input type="file" name="feed_file" accept=".json" class="form-input" required>
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
                    <strong>Expected format:</strong><br>
                    <code>{"feeds": [{"url": "...", "name": "..."}]}</code>
                </small>
            </div>

            <!-- Import from URL -->
            <div class="manager-card">
                <h3>🌐 Import from URL</h3>
                <form method="post">
                    <div class="form-group">
                        <label>JSON File URL:</label>
                        <input type="url" name="import_url" class="form-input" placeholder="https://example.com/feeds.json" required>
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
                <h3>📚 Quick Start Collections</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Choose Collection:</label>
                        <select name="collection_key" class="form-input" required>
                            <?php foreach ($default_collections as $key => $collection): ?>
                                <option value="<?= $key ?>">
                                    <?= $collection['name'] ?> (<?= count($collection['feeds']) ?> feeds)
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
                    <?php foreach ($default_collections as $key => $collection): ?>
                        <div class="collection-item">
                            <div class="collection-name"><?= $collection['name'] ?></div>
                            <div class="feed-count"><?= count($collection['feeds']) ?> feeds</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Management Actions -->
            <div class="manager-card">
                <h3>⚙️ Management Actions</h3>
                
                <form method="post" onsubmit="return confirm('Are you sure you want to clear all feeds? This cannot be undone.');">
                    <button type="submit" name="clear_all" class="btn btn-danger">🗑️ Clear All Feeds</button>
                </form>
                
                <?php if (!empty($feeds_data['feeds'])): ?>
                    <a href="?export=1" class="btn btn-success" style="text-decoration: none;">💾 Export Feeds</a>
                    <a href="index.php" class="btn">🔄 Process Current Feeds</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Usage Instructions -->
        <div class="manager-card">
            <h3>📖 Usage Instructions</h3>
            <ul>
                <li><strong>Import from File:</strong> Upload a JSON file with your feed definitions</li>
                <li><strong>Import from URL:</strong> Load feeds from a remote JSON file</li>
                <li><strong>Quick Start:</strong> Choose from curated collections of quality feeds</li>
                <li><strong>Replace vs Merge:</strong> Replace removes existing feeds, Merge adds new ones without duplicates</li>
                <li><strong>Export:</strong> Download your current feed configuration as JSON</li>
            </ul>
            
            <h4>JSON Format Example:</h4>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;"><code>{
  "feeds": [
    {
      "url": "https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml",
      "name": "New York Times"
    },
    {
      "url": "https://feeds.bbci.co.uk/news/rss.xml", 
      "name": "BBC News"
    }
  ]
}</code></pre>
        </div>
    </div>
</body>
</html>