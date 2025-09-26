<?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>RSS Word Counter - Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #2c3e50; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #fafafa; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .test-result { margin: 10px 0; padding: 10px; border-left: 4px solid #3498db; background: #ecf0f1; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>RSS Word Counter - Debug Information</h1>";

echo "<div class='section'>
        <h2>PHP Environment</h2>
        <div class='test-result'>
            <strong>PHP Version:</strong> " . PHP_VERSION . "
        </div>
        <div class='test-result'>
            <strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "
        </div>
        <div class='test-result'>
            <strong>Session Status:</strong> " . session_status() . " (" . 
            (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 
             (session_status() === PHP_SESSION_NONE ? 'None' : 'Disabled')) . ")
        </div>
    </div>";

// Check extensions
echo "<div class='section'>
        <h2>PHP Extensions</h2>";
$extensions = [
    'json' => ['function' => 'json_decode', 'required' => true, 'status' => function_exists('json_decode')],
    'simplexml' => ['function' => 'simplexml_load_string', 'required' => false, 'status' => function_exists('simplexml_load_string')],
    'curl' => ['function' => 'curl_init', 'required' => false, 'status' => function_exists('curl_init')],
    'mbstring' => ['function' => 'mb_strtolower', 'required' => false, 'status' => function_exists('mb_strtolower')],
    'libxml' => ['function' => 'libxml_use_internal_errors', 'required' => false, 'status' => function_exists('libxml_use_internal_errors')]
];

foreach ($extensions as $name => $ext) {
    $status = $ext['status'] ? "<span class='success'>✓ Available</span>" : 
              ($ext['required'] ? "<span class='error'>✗ Required but missing!</span>" : "<span class='warning'>⚠ Optional</span>");
    echo "<div class='test-result'><strong>{$name}:</strong> {$status}</div>";
}
echo "</div>";

// Check file permissions
echo "<div class='section'>
        <h2>File Permissions</h2>";
$files = [
    FEEDS_FILE => 'RSS Feeds Configuration',
    STOPWORDS_FILE => 'Stopwords List',
    'config.php' => 'Main Configuration',
    'index.php' => 'Main Application'
];

foreach ($files as $file => $description) {
    if (file_exists($file)) {
        $readable = is_readable($file) ? "<span class='success'>Readable</span>" : "<span class='error'>Not Readable</span>";
        $writable = is_writable($file) ? "<span class='success'>Writable</span>" : "<span class='warning'>Not Writable</span>";
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "<div class='test-result'>
                <strong>{$description} ({$file}):</strong> 
                Permissions: {$perms}, {$readable}, {$writable}
              </div>";
    } else {
        echo "<div class='test-result'>
                <strong>{$description} ({$file}):</strong> 
                <span class='error'>File not found!</span>
              </div>";
    }
}
echo "</div>";

// Test JSON functionality
echo "<div class='section'>
        <h2>JSON Functionality</h2>";
try {
    $feeds = load_json(FEEDS_FILE);
    $stopwords = load_json(STOPWORDS_FILE);
    
    echo "<div class='test-result success'>
            ✓ JSON files loaded successfully<br>
            Feeds: " . count($feeds['feeds'] ?? []) . " feeds<br>
            Stopwords: " . count($stopwords) . " words
          </div>";
} catch (Exception $e) {
    echo "<div class='test-result error'>
            ✗ JSON Error: " . htmlspecialchars($e->getMessage()) . "
          </div>";
}
echo "</div>";

// Test RSS fetching
echo "<div class='section'>
        <h2>RSS Fetching Test</h2>";

if (!empty($feeds['feeds'])) {
    $test_feed = $feeds['feeds'][0];
    echo "<div class='test-result'>
            <strong>Testing feed:</strong> " . htmlspecialchars($test_feed['name']) . "<br>
            <strong>URL:</strong> " . htmlspecialchars($test_feed['url']) . "
          </div>";
    
    $rss_content = fetch_rss($test_feed['url']);
    if ($rss_content) {
        echo "<div class='test-result success'>
                ✓ RSS feed fetched successfully (" . strlen($rss_content) . " bytes)
              </div>";
        
        // Test parsing
        $parsed = parse_rss_content($rss_content);
        echo "<div class='test-result'>
                <strong>Parsed content:</strong> " . strlen($parsed) . " characters
              </div>";
        
        // Test word counting
        $word_counts = count_words($parsed, $stopwords);
        echo "<div class='test-result success'>
                ✓ Word counting successful: " . count($word_counts) . " unique words
              </div>";
        
        if (!empty($word_counts)) {
            $top_words = array_slice($word_counts, 0, 5);
            echo "<div class='test-result'>
                    <strong>Top 5 words:</strong><br>";
            foreach ($top_words as $word => $count) {
                echo "&nbsp;&nbsp;{$word}: {$count}<br>";
            }
            echo "</div>";
        }
        
        // Test article extraction
        $articles = extract_articles($rss_content, "Test Feed");
        echo "<div class='test-result success'>
                ✓ Article extraction: " . count($articles) . " articles found
              </div>";
        
    } else {
        echo "<div class='test-result error'>
                ✗ Failed to fetch RSS feed. Check if allow_url_fopen is enabled.
              </div>";
    }
} else {
    echo "<div class='test-result warning'>
            ⚠ No feeds configured. Add some feeds in the main application first.
          </div>";
}

echo "</div>";

// Test session functionality
echo "<div class='section'>
        <h2>Session Test</h2>";
$_SESSION['debug_test'] = 'test_value';
$session_working = isset($_SESSION['debug_test']) && $_SESSION['debug_test'] === 'test_value';

if ($session_working) {
    echo "<div class='test-result success'>
            ✓ Sessions are working correctly
          </div>";
} else {
    echo "<div class='test-result error'>
            ✗ Session test failed. Sessions may not be configured properly.
          </div>";
}
echo "</div>";

// PHP configuration info
echo "<div class='section'>
        <h2>PHP Configuration</h2>
        <pre>";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'On' : 'Off') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "</pre>
    </div>";

echo "<div class='section'>
        <h2>Quick Fixes</h2>
        <div class='test-result'>
            <strong>If RSS fetching fails:</strong><br>
            1. Check if allow_url_fopen is enabled in php.ini<br>
            2. Try a different RSS feed URL<br>
            3. Check firewall/network restrictions
        </div>
        <div class='test-result'>
            <strong>If file permissions are wrong:</strong><br>
            Run: <code>chmod 644 *.json</code> and <code>chmod 644 *.php</code>
        </div>
    </div>";

echo "</div></body></html>";
