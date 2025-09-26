<?php
require_once 'config.php';

echo "Testing RSS Word Counter (No extensions required)...\n\n";

// Test JSON file loading
echo "1. Testing JSON file loading...\n";
$feeds = load_json(FEEDS_FILE);
$stopwords = load_json(STOPWORDS_FILE);

echo "Feeds loaded: " . count($feeds['feeds']) . "\n";
echo "Stopwords loaded: " . count($stopwords) . "\n";

// Test string functions
echo "\n2. Testing string functions...\n";
$test_text = "Hello World! This is a TEST.";
echo "Original: $test_text\n";
echo "Lowercase: " . str_lower($test_text) . "\n";

// Test word counting
echo "\n3. Testing word counting...\n";
$test_content = "the quick brown fox jumps over the lazy dog. The quick fox is quick.";
$test_counts = count_words($test_content, ['the', 'is', 'a']);
echo "Test content: $test_content\n";
echo "Word counts: " . json_encode($test_counts) . "\n";

// Test RSS parsing with a simple example
echo "\n4. Testing RSS parsing...\n";
$simple_rss = '<?xml version="1.0"?>
<rss version="2.0">
<channel>
<title>Test Feed</title>
<item>
<title>Test Article 1</title>
<link>http://example.com/1</link>
<description>This is the first test article about programming.</description>
</item>
<item>
<title>Test Article 2</title>
<link>http://example.com/2</link>
<description>This is the second test article about technology.</description>
</item>
</channel>
</rss>';

$parsed = parse_rss_content($simple_rss);
echo "Parsed content: " . substr($parsed, 0, 100) . "...\n";

// Test article extraction
$extracted = extract_articles($simple_rss, "Test Feed");
echo "Articles extracted: " . count($extracted) . "\n";

echo "\nTest completed successfully!\n";
