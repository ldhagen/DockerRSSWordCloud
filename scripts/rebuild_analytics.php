<?php
/**
 * Analytics Rebuild Script
 * 
 * Cleans up duplicate data and recalculates word frequencies
 * based on unique articles only.
 */

require_once __DIR__ . '/../config.php';

if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
    die("This script must be run from the command line.
");
}

echo "Starting Analytics Rebuild...
";
log_message("Starting database-wide analytics rebuild", 'INFO');

$pdo = get_db();
if (!$pdo) {
    die("Database connection failed.
");
}

try {
    // 1. Get unique articles (one per link/feed combination)
    echo "Step 1: Fetching unique articles (this may take a moment)...
";
    $stmt = $pdo->query("
        SELECT title, link, description, feed_name, pub_date, timestamp
        FROM articles
        WHERE id IN (SELECT MIN(id) FROM articles GROUP BY link, feed_name)
    ");
    $unique_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_unique = count($unique_articles);
    echo "Found $total_unique unique articles (out of nearly 600,000 total records).
";

    // 2. Clear existing inflated data
    echo "Step 2: Clearing old analytics data...
";
    $pdo->exec("DELETE FROM word_history");
    $pdo->exec("DELETE FROM collections");
    $pdo->exec("DELETE FROM articles");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('word_history', 'collections', 'articles')");
    echo "Old data cleared.
";

    // 3. Group articles by feed and date to rebuild trends
    echo "Step 3: Grouping articles for trend reconstruction...
";
    $groups = [];
    foreach ($unique_articles as $article) {
        $date = date('Y-m-d', strtotime($article['timestamp']));
        $key = $article['feed_name'] . '|' . $date;
        
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'feed_name' => $article['feed_name'],
                'date' => $date,
                'articles' => []
            ];
        }
        $groups[$key]['articles'][] = $article;
    }
    echo "Created " . count($groups) . " daily feed clusters.
";

    // 4. Rebuild the data
    echo "Step 4: Rebuilding word history and article records...
";
    $stopwords = load_json(STOPWORDS_FILE, $default_stopwords);
    $pdo->beginTransaction();
    
    $processed_count = 0;
    foreach ($groups as $group) {
        // Insert collection
        $stmt = $pdo->prepare("INSERT INTO collections (feed_name, total_articles, total_words, timestamp) VALUES (?, ?, ?, ?)");
        
        // Count words for this group
        $group_text = '';
        foreach ($group['articles'] as $article) {
            $group_text .= ' ' . $article['title'];
        }
        $word_counts = count_words($group_text, $stopwords);
        $total_words = array_sum($word_counts);
        
        $group_timestamp = $group['date'] . ' 12:00:00'; // Default to midday for daily clusters
        $stmt->execute([$group['feed_name'], count($group['articles']), $total_words, $group_timestamp]);
        $collection_id = $pdo->lastInsertId();
        
        // Insert words
        if (!empty($word_counts)) {
            $word_stmt = $pdo->prepare("INSERT INTO word_history (collection_id, word, count, feed_name, timestamp) VALUES (?, ?, ?, ?, ?)");
            foreach ($word_counts as $word => $count) {
                $word_stmt->execute([$collection_id, $word, $count, $group['feed_name'], $group_timestamp]);
            }
        }
        
        // Insert articles
        $art_stmt = $pdo->prepare("INSERT INTO articles (collection_id, title, link, description, feed_name, pub_date, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($group['articles'] as $article) {
            $art_stmt->execute([
                $collection_id,
                $article['title'],
                $article['link'],
                $article['description'],
                $article['feed_name'],
                $article['pub_date'],
                $article['timestamp']
            ]);
        }
        
        $processed_count += count($group['articles']);
        if ($processed_count % 5000 < 100) {
            echo "Processed $processed_count / $total_unique articles...
";
        }
    }
    
    $pdo->commit();
    echo "Rebuild complete! Successfully processed $total_unique articles.
";
    log_message("Database rebuild successful: $total_unique unique articles restored", 'INFO');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR during rebuild: " . $e->getMessage() . "
";
    log_message("Database rebuild failed: " . $e->getMessage(), 'ERROR');
}
?>
