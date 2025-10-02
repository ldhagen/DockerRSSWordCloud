<?php
require_once 'config.php';

// Handle AJAX requests for charts and data
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['ajax']) {
        case 'word_trends':
            $word = $_GET['word'] ?? '';
            $days = $_GET['days'] ?? '30';
            $feed = $_GET['feed'] ?? null;
            echo json_encode(get_word_trends($word, $days, $feed));
            break;
            
        case 'feed_activity':
            $days = $_GET['days'] ?? '30';
            echo json_encode(get_feed_activity($days));
            break;
            
        case 'daily_stats':
            $days = $_GET['days'] ?? '30';
            $feed = $_GET['feed'] ?? null;
            echo json_encode(get_daily_stats($days, $feed));
            break;
            
        case 'word_details':
            $word = $_GET['word'] ?? '';
            $days = $_GET['days'] ?? '30';
            echo json_encode(get_word_details($word, $days));
            break;
            
        case 'feed_words':
            $feed = $_GET['feed'] ?? '';
            $days = $_GET['days'] ?? '30';
            echo json_encode(get_feed_specific_words($feed, $days));
            break;
            
        case 'word_cooccurrence':
            $word = $_GET['word'] ?? '';
            $days = $_GET['days'] ?? '30';
            echo json_encode(get_word_cooccurrence($word, $days));
            break;
            
        case 'search_articles':
            $keyword = $_GET['keyword'] ?? '';
            $feed = $_GET['feed'] ?? null;
            echo json_encode(search_articles($keyword, $feed));
            break;
            
        case 'feed_list':
            echo json_encode(get_feed_list());
            break;
    }
    exit;
}

// Get analytics data
$trending_words = get_trending_words(7, 50);
$recent_collections = get_recent_collections(10);
$feed_list = get_feed_list();

function get_recent_collections($limit = 10) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT feed_name, total_articles, total_words, timestamp
            FROM collections 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function get_feed_activity($days = '30') {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $timeFilter = get_time_filter($days);
        $stmt = $pdo->prepare("
            SELECT 
                feed_name,
                COUNT(*) as collection_count,
                SUM(total_articles) as total_articles,
                SUM(total_words) as total_words,
                AVG(total_articles) as avg_articles,
                MAX(timestamp) as last_collection
            FROM collections 
            WHERE timestamp > $timeFilter
            GROUP BY feed_name
            ORDER BY collection_count DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function get_daily_stats($days = '30', $feed = null) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $timeFilter = get_time_filter($days);
        
        // For hourly granularity with 24h and 48h periods
        if ($days === '1' || $days === '2') {
            if ($feed) {
                $stmt = $pdo->prepare("
                    SELECT 
                        strftime('%Y-%m-%d %H:00:00', timestamp) as date,
                        COUNT(*) as collections,
                        SUM(total_articles) as articles,
                        SUM(total_words) as words
                    FROM collections 
                    WHERE timestamp > $timeFilter
                    AND feed_name = ?
                    GROUP BY strftime('%Y-%m-%d %H:00:00', timestamp)
                    ORDER BY date
                ");
                $stmt->execute([$feed]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        strftime('%Y-%m-%d %H:00:00', timestamp) as date,
                        COUNT(*) as collections,
                        SUM(total_articles) as articles,
                        SUM(total_words) as words
                    FROM collections 
                    WHERE timestamp > $timeFilter
                    GROUP BY strftime('%Y-%m-%d %H:00:00', timestamp)
                    ORDER BY date
                ");
                $stmt->execute();
            }
        } else {
            // Daily granularity for longer periods
            if ($feed) {
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(timestamp) as date,
                        COUNT(*) as collections,
                        SUM(total_articles) as articles,
                        SUM(total_words) as words
                    FROM collections 
                    WHERE timestamp > $timeFilter
                    AND feed_name = ?
                    GROUP BY DATE(timestamp)
                    ORDER BY date
                ");
                $stmt->execute([$feed]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(timestamp) as date,
                        COUNT(*) as collections,
                        SUM(total_articles) as articles,
                        SUM(total_words) as words
                    FROM collections 
                    WHERE timestamp > $timeFilter
                    GROUP BY DATE(timestamp)
                    ORDER BY date
                ");
                $stmt->execute();
            }
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function get_word_details($word, $days = '30') {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $timeFilter = get_time_filter($days);
        $stmt = $pdo->prepare("
            SELECT 
                feed_name,
                SUM(count) as total_mentions,
                COUNT(DISTINCT DATE(timestamp)) as days_appeared,
                MAX(timestamp) as last_seen
            FROM word_history
            WHERE LOWER(word) = LOWER(?)
            AND timestamp > $timeFilter
            GROUP BY feed_name
            ORDER BY total_mentions DESC
        ");
        $stmt->execute([$word]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in get_word_details: " . $e->getMessage());
        return [];
    }
}

function get_feed_specific_words($feed, $days = '30', $limit = 30) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $timeFilter = get_time_filter($days);
        $stmt = $pdo->prepare("
            SELECT 
                word,
                SUM(count) as total_count,
                COUNT(DISTINCT DATE(timestamp)) as days_appeared
            FROM word_history
            WHERE feed_name = ?
            AND timestamp > $timeFilter
            GROUP BY word
            ORDER BY total_count DESC
            LIMIT ?
        ");
        $stmt->execute([$feed, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in get_feed_specific_words: " . $e->getMessage());
        return [];
    }
}

function get_word_cooccurrence($word, $days = '30', $limit = 20) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $timeFilter = get_time_filter($days);
        $stmt = $pdo->prepare("
            SELECT 
                w2.word,
                COUNT(DISTINCT w2.collection_id) as cooccurrence_count,
                SUM(w2.count) as total_mentions
            FROM word_history w1
            JOIN word_history w2 ON w1.collection_id = w2.collection_id
            WHERE LOWER(w1.word) = LOWER(?)
            AND LOWER(w2.word) != LOWER(?)
            AND w1.timestamp > $timeFilter
            GROUP BY w2.word
            ORDER BY cooccurrence_count DESC, total_mentions DESC
            LIMIT ?
        ");
        $stmt->execute([$word, $word, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in get_word_cooccurrence: " . $e->getMessage());
        return [];
    }
}

function search_articles($keyword, $feed = null) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        if ($feed) {
            $stmt = $pdo->prepare("
                SELECT 
                    c.feed_name,
                    c.total_articles,
                    c.timestamp,
                    GROUP_CONCAT(w.word || ':' || w.count) as word_data
                FROM collections c
                JOIN word_history w ON c.id = w.collection_id
                WHERE (LOWER(w.word) LIKE LOWER(?) OR LOWER(c.feed_name) LIKE LOWER(?))
                AND c.feed_name = ?
                GROUP BY c.id
                ORDER BY c.timestamp DESC
                LIMIT 50
            ");
            $search = "%{$keyword}%";
            $stmt->execute([$search, $search, $feed]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    c.feed_name,
                    c.total_articles,
                    c.timestamp,
                    GROUP_CONCAT(w.word || ':' || w.count) as word_data
                FROM collections c
                JOIN word_history w ON c.id = w.collection_id
                WHERE LOWER(w.word) LIKE LOWER(?) OR LOWER(c.feed_name) LIKE LOWER(?)
                GROUP BY c.id
                ORDER BY c.timestamp DESC
                LIMIT 50
            ");
            $search = "%{$keyword}%";
            $stmt->execute([$search, $search]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in search_articles: " . $e->getMessage());
        return [];
    }
}

function get_feed_list() {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT feed_name 
            FROM collections 
            ORDER BY feed_name
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function get_time_filter($days) {
    switch ($days) {
        case '1':
            return "datetime('now', '-24 hours')";
        case '2':
            return "datetime('now', '-48 hours')";
        default:
            return "datetime('now', '-' || " . intval($days) . " || ' days')";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSS Analytics Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .analytics-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .trending-words {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 10px 0;
        }
        .word-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .word-tag:hover {
            background: #1976d2;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(25,118,210,0.3);
        }
        .stats-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .stat-value {
            font-weight: bold;
            color: #1976d2;
        }
        .controls {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .word-input, select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .word-input {
            width: 200px;
        }
        .btn {
            padding: 8px 16px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1565c0;
        }
        .btn-secondary {
            background: #666;
        }
        .btn-secondary:hover {
            background: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideDown 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .detail-section {
            margin: 20px 0;
        }
        .detail-item {
            padding: 10px;
            background: #f5f5f5;
            margin: 8px 0;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .related-words {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 10px 0;
        }
        .related-tag {
            background: #fff3e0;
            color: #f57c00;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            cursor: pointer;
        }
        .related-tag:hover {
            background: #f57c00;
            color: white;
        }
        .filter-bar {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-label {
            font-weight: bold;
            margin-right: 5px;
        }
        .active-filter {
            background: #4caf50;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .clear-filter {
            cursor: pointer;
            font-weight: bold;
        }
        .search-results {
            max-height: 400px;
            overflow-y: auto;
        }
        .search-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-item:hover {
            background: #f5f5f5;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        .badge {
            background: #ff9800;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>RSS Analytics Dashboard</h1>
            <div>
                <a href="index.php" class="btn">Back to Main</a>
                <a href="wordcloud.php" class="btn">Word Cloud</a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <span class="filter-label">Filters:</span>
            <select id="feedFilter" onchange="applyFilters()">
                <option value="">All Feeds</option>
                <?php foreach ($feed_list as $feed): ?>
                    <option value="<?= htmlspecialchars($feed) ?>"><?= htmlspecialchars($feed) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="dateRangeFilter" onchange="applyFilters()">
                <option value="1">Last 24 hours</option>
                <option value="2">Last 48 hours</option>
                <option value="7">Last 7 days</option>
                <option value="14">Last 14 days</option>
                <option value="30" selected>Last 30 days</option>
                <option value="90">Last 90 days</option>
            </select>
            <button onclick="clearFilters()" class="btn btn-secondary">Clear Filters</button>
            <div id="activeFilters" style="margin-left: auto;"></div>
        </div>

        <!-- Trending Words Section -->
        <div class="analytics-card">
            <h2>Trending Words <span id="trendingPeriod">(Last 7 Days)</span></h2>
            <div class="trending-words">
                <?php if (empty($trending_words)): ?>
                    <p>No trending words yet. <a href="index.php">Process some feeds</a> to see analytics!</p>
                <?php else: ?>
                    <?php foreach (array_slice($trending_words, 0, 30) as $word_data): ?>
                        <span class="word-tag" onclick="showWordDetails('<?= sanitize_output($word_data['word']) ?>')" title="Click for detailed analysis">
                            <?= sanitize_output($word_data['word']) ?> 
                            <span class="badge"><?= $word_data['total_count'] ?></span>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Analytics Grid -->
        <div class="analytics-grid">
            <!-- Daily Activity Chart -->
            <div class="analytics-card">
                <h3>Daily Activity</h3>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <!-- Feed Activity Chart -->
            <div class="analytics-card">
                <h3>Feed Distribution</h3>
                <div class="chart-container">
                    <canvas id="feedChart"></canvas>
                </div>
            </div>

            <!-- Word Trend Analysis -->
            <div class="analytics-card full-width">
                <h3>Word Trend Analysis</h3>
                <div class="controls">
                    <input type="text" id="wordInput" class="word-input" placeholder="Enter word to analyze">
                    <button onclick="analyzeWord()" class="btn">Analyze</button>
                    <button onclick="compareWords()" class="btn btn-secondary">Add to Compare</button>
                    <select id="trendDays" onchange="comparedWords.length > 0 ? updateComparisonChart() : analyzeWord()">
                        <option value="1">24 hours</option>
                        <option value="2">48 hours</option>
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>
                <div id="comparisonControls" style="display: none;"></div>
                <div class="chart-container" style="height: 400px;">
                    <canvas id="trendChart"></canvas>
                </div>
                <div id="relatedWords" class="detail-section" style="display: none;">
                    <h4>Related Words (Co-occurrence)</h4>
                    <div class="related-words" id="relatedWordsList"></div>
                </div>
            </div>

            <!-- Article Search -->
            <div class="analytics-card full-width">
                <h3>Search Articles</h3>
                <div class="controls">
                    <input type="text" id="searchInput" class="word-input" placeholder="Search by keyword...">
                    <button onclick="searchArticles()" class="btn">Search</button>
                </div>
                <div id="searchResults" class="search-results"></div>
            </div>

            <!-- Feed Statistics -->
            <div class="analytics-card">
                <h3>Feed Statistics</h3>
                <div id="feedStats">
                    <?php
                    $feed_activity = get_feed_activity('30');
                    if (empty($feed_activity)):
                    ?>
                        <p>No feed activity yet. <a href="index.php">Process some feeds</a> to see statistics!</p>
                    <?php else: ?>
                        <?php foreach ($feed_activity as $feed): ?>
                            <div class="stats-row" style="cursor: pointer;" onclick="showFeedDetails('<?= sanitize_output($feed['feed_name']) ?>')">
                                <span><?= sanitize_output($feed['feed_name']) ?></span>
                                <span class="stat-value"><?= number_format($feed['total_articles']) ?> articles</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Collections -->
            <div class="analytics-card">
                <h3>Recent Collections</h3>
                <?php if (empty($recent_collections)): ?>
                    <p>No collections yet. <a href="index.php">Process some feeds</a> to see recent activity!</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Feed</th>
                                <th style="text-align: right;">Articles</th>
                                <th style="text-align: right;">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_collections as $collection): ?>
                                <tr style="cursor: pointer;" onclick="showFeedDetails('<?= sanitize_output($collection['feed_name']) ?>')">
                                    <td><?= sanitize_output($collection['feed_name']) ?></td>
                                    <td style="text-align: right;"><?= number_format($collection['total_articles']) ?></td>
                                    <td style="text-align: right;"><?= date('M j, H:i', strtotime($collection['timestamp'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Word Details Modal -->
    <div id="wordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('wordModal')">&times;</span>
            <h2 id="modalWordTitle"></h2>
            <div id="modalWordContent" class="loading">Loading...</div>
        </div>
    </div>

    <!-- Feed Details Modal -->
    <div id="feedModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('feedModal')">&times;</span>
            <h2 id="modalFeedTitle"></h2>
            <div id="modalFeedContent" class="loading">Loading...</div>
        </div>
    </div>

    <script>
        let dailyChart = null;
        let feedChart = null;
        let trendChart = null;
        let currentFilters = {
            feed: null,
            dateRange: '30'
        };
        let comparedWords = [];
        const colors = [
            '#f57c00', '#1976d2', '#388e3c', '#d32f2f', 
            '#7b1fa2', '#0097a7', '#e91e63', '#00bcd4'
        ];

        document.addEventListener('DOMContentLoaded', function() {
            loadDailyChart();
            loadFeedChart();
            
            document.getElementById('wordInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') analyzeWord();
            });
            
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') searchArticles();
            });
        });

        function applyFilters() {
            currentFilters.feed = document.getElementById('feedFilter').value || null;
            currentFilters.dateRange = document.getElementById('dateRangeFilter').value;
            
            updateActiveFilters();
            loadDailyChart();
            loadFeedChart();
        }

        function clearFilters() {
            document.getElementById('feedFilter').value = '';
            document.getElementById('dateRangeFilter').value = '30';
            currentFilters = { feed: null, dateRange: '30' };
            updateActiveFilters();
            loadDailyChart();
            loadFeedChart();
        }

        function updateActiveFilters() {
            const container = document.getElementById('activeFilters');
            let html = '';
            if (currentFilters.feed) {
                html += `<span class="active-filter">${currentFilters.feed} <span class="clear-filter" onclick="clearFeedFilter()">✕</span></span>`;
            }
            container.innerHTML = html;
        }

        function clearFeedFilter() {
            document.getElementById('feedFilter').value = '';
            applyFilters();
        }

        function loadDailyChart() {
            const url = `?ajax=daily_stats&days=${currentFilters.dateRange}` + 
                        (currentFilters.feed ? `&feed=${encodeURIComponent(currentFilters.feed)}` : '');
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (dailyChart) dailyChart.destroy();
                    
                    const ctx = document.getElementById('dailyChart').getContext('2d');
                    
                    // Format labels based on time range
                    let labels = data.map(d => {
                        if (currentFilters.dateRange === '1' || currentFilters.dateRange === '2') {
                            // Show hour for 24h/48h views
                            const date = new Date(d.date);
                            return date.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric' });
                        } else {
                            return d.date;
                        }
                    });
                    
                    dailyChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Articles Collected',
                                data: data.map(d => d.articles),
                                borderColor: '#1976d2',
                                backgroundColor: 'rgba(25, 118, 210, 0.1)',
                                tension: 0.1,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true }
                            },
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });
                });
        }

        function loadFeedChart() {
            fetch(`?ajax=feed_activity&days=${currentFilters.dateRange}`)
                .then(response => response.json())
                .then(data => {
                    if (feedChart) feedChart.destroy();
                    
                    const ctx = document.getElementById('feedChart').getContext('2d');
                    feedChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.map(d => d.feed_name),
                            datasets: [{
                                data: data.map(d => d.total_articles),
                                backgroundColor: [
                                    '#1976d2', '#388e3c', '#f57c00', '#d32f2f',
                                    '#7b1fa2', '#0097a7', '#5d4037', '#616161',
                                    '#e64a19', '#1565c0', '#2e7d32', '#f9a825'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    onClick: function(e, legendItem, legend) {
                                        const feedName = legend.chart.data.labels[legendItem.index];
                                        showFeedDetails(feedName);
                                    }
                                }
                            }
                        }
                    });
                });
        }

        function analyzeWord() {
            const word = document.getElementById('wordInput').value.trim();
            const days = document.getElementById('trendDays').value;
            
            if (!word) return;
            
            // Clear comparison mode when analyzing single word
            if (comparedWords.length > 0) {
                clearComparison();
            }

            const url = `?ajax=word_trends&word=${encodeURIComponent(word)}&days=${days}` +
                        (currentFilters.feed ? `&feed=${encodeURIComponent(currentFilters.feed)}` : '');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (trendChart) trendChart.destroy();

                    const ctx = document.getElementById('trendChart').getContext('2d');
                    
                    // Format labels based on time range
                    let labels = data.map(d => {
                        if (days === '1' || days === '2') {
                            const date = new Date(d.date);
                            return date.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric' });
                        } else {
                            return d.date;
                        }
                    });
                    
                    trendChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: `"${word}" mentions`,
                                data: data.map(d => d.total_count),
                                borderColor: '#f57c00',
                                backgroundColor: 'rgba(245, 124, 0, 0.1)',
                                tension: 0.1,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });

                    loadRelatedWords(word, days);
                });
        }

        function compareWords() {
            const word = document.getElementById('wordInput').value.trim();
            
            if (!word) {
                alert('Please enter a word to compare');
                return;
            }
            
            // Check if word already in comparison
            if (comparedWords.some(w => w.toLowerCase() === word.toLowerCase())) {
                alert('This word is already in the comparison');
                return;
            }
            
            // Limit to 5 words for readability
            if (comparedWords.length >= 5) {
                alert('Maximum 5 words can be compared at once. Remove a word first.');
                return;
            }
            
            comparedWords.push(word);
            updateComparisonChart();
            updateComparisonControls();
        }

        function updateComparisonChart() {
            const days = document.getElementById('trendDays').value;
            
            if (comparedWords.length === 0) {
                if (trendChart) trendChart.destroy();
                return;
            }
            
            // Fetch data for all words
            Promise.all(
                comparedWords.map(word => {
                    const url = `?ajax=word_trends&word=${encodeURIComponent(word)}&days=${days}` +
                                (currentFilters.feed ? `&feed=${encodeURIComponent(currentFilters.feed)}` : '');
                    return fetch(url).then(r => r.json());
                })
            ).then(results => {
                if (trendChart) trendChart.destroy();
                
                const ctx = document.getElementById('trendChart').getContext('2d');
                
                // Get all unique dates from all datasets
                const allDates = new Set();
                results.forEach(data => {
                    data.forEach(d => allDates.add(d.date));
                });
                const sortedDates = Array.from(allDates).sort();
                
                // Format labels based on time range
                let labels = sortedDates.map(date => {
                    if (days === '1' || days === '2') {
                        const d = new Date(date);
                        return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric' });
                    } else {
                        return date;
                    }
                });
                
                // Create datasets for each word
                const datasets = comparedWords.map((word, index) => {
                    const wordData = results[index];
                    const dataMap = new Map(wordData.map(d => [d.date, d.total_count]));
                    
                    return {
                        label: `"${word}" mentions`,
                        data: sortedDates.map(date => dataMap.get(date) || 0),
                        borderColor: colors[index % colors.length],
                        backgroundColor: colors[index % colors.length] + '20',
                        tension: 0.1,
                        fill: false
                    };
                });
                
                trendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true }
                        },
                        plugins: {
                            legend: { 
                                display: true,
                                position: 'top'
                            }
                        }
                    }
                });
                
                // Hide related words section during comparison
                document.getElementById('relatedWords').style.display = 'none';
            });
        }

        function updateComparisonControls() {
            const container = document.getElementById('comparisonControls');
            
            if (comparedWords.length === 0) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'block';
            
            let html = '<div style="margin: 10px 0;"><strong>Comparing:</strong></div>';
            html += '<div class="trending-words">';
            
            comparedWords.forEach((word, index) => {
                html += `
                    <span class="word-tag" style="background: ${colors[index % colors.length]}; color: white;">
                        ${word} 
                        <span onclick="removeFromComparison('${word}')" style="cursor: pointer; margin-left: 5px; font-weight: bold;">✕</span>
                    </span>
                `;
            });
            
            html += '</div>';
            html += '<button onclick="clearComparison()" class="btn btn-secondary" style="margin-top: 10px;">Clear All</button>';
            
            container.innerHTML = html;
        }

        function removeFromComparison(word) {
            comparedWords = comparedWords.filter(w => w !== word);
            updateComparisonChart();
            updateComparisonControls();
        }

        function clearComparison() {
            comparedWords = [];
            updateComparisonChart();
            updateComparisonControls();
            document.getElementById('wordInput').value = '';
        }

        function loadRelatedWords(word, days) {
            fetch(`?ajax=word_cooccurrence&word=${encodeURIComponent(word)}&days=${days}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('relatedWordsList');
                    const section = document.getElementById('relatedWords');
                    
                    if (data.length > 0) {
                        section.style.display = 'block';
                        container.innerHTML = data.map(item => 
                            `<span class="related-tag" onclick="document.getElementById('wordInput').value='${item.word}'; analyzeWord();">
                                ${item.word} (${item.cooccurrence_count})
                            </span>`
                        ).join('');
                    } else {
                        section.style.display = 'none';
                    }
                });
        }

        function showWordDetails(word) {
            const modal = document.getElementById('wordModal');
            const title = document.getElementById('modalWordTitle');
            const content = document.getElementById('modalWordContent');
            
            title.textContent = `Word Analysis: "${word}"`;
            content.innerHTML = '<div class="loading">Loading details...</div>';
            modal.style.display = 'block';
            
            fetch(`?ajax=word_details&word=${encodeURIComponent(word)}&days=${currentFilters.dateRange}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        content.innerHTML = '<p>No data found for this word.</p>';
                        return;
                    }
                    
                    let html = '<div class="detail-section">';
                    html += '<h4>Feed Distribution</h4>';
                    
                    data.forEach(feed => {
                        html += `
                            <div class="detail-item">
                                <div>
                                    <strong>${feed.feed_name}</strong><br>
                                    <small>${feed.days_appeared} days active • Last seen: ${new Date(feed.last_seen).toLocaleDateString()}</small>
                                </div>
                                <div class="stat-value">${feed.total_mentions} mentions</div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    html += `<div class="controls" style="margin-top: 20px;">
                        <button onclick="document.getElementById('wordInput').value='${word}'; analyzeWord(); closeModal('wordModal');" class="btn">
                            View Trend Chart
                        </button>
                    </div>`;
                    
                    content.innerHTML = html;
                });
        }

        function showFeedDetails(feedName) {
            const modal = document.getElementById('feedModal');
            const title = document.getElementById('modalFeedTitle');
            const content = document.getElementById('modalFeedContent');
            
            title.textContent = `Feed Analysis: ${feedName}`;
            content.innerHTML = '<div class="loading">Loading details...</div>';
            modal.style.display = 'block';
            
            fetch(`?ajax=feed_words&feed=${encodeURIComponent(feedName)}&days=${currentFilters.dateRange}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        content.innerHTML = '<p>No data found for this feed.</p>';
                        return;
                    }
                    
                    let html = '<div class="detail-section">';
                    html += '<h4>Top Words in This Feed</h4>';
                    html += '<div class="trending-words">';
                    
                    data.forEach(item => {
                        html += `
                            <span class="word-tag" onclick="document.getElementById('wordInput').value='${item.word}'; analyzeWord(); closeModal('feedModal');">
                                ${item.word} <span class="badge">${item.total_count}</span>
                            </span>
                        `;
                    });
                    
                    html += '</div></div>';
                    html += `<div class="controls" style="margin-top: 20px;">
                        <button onclick="filterByFeed('${feedName}')" class="btn">
                            Filter Dashboard by This Feed
                        </button>
                    </div>`;
                    
                    content.innerHTML = html;
                });
        }

        function filterByFeed(feedName) {
            document.getElementById('feedFilter').value = feedName;
            applyFilters();
            closeModal('feedModal');
        }

        function searchArticles() {
            const keyword = document.getElementById('searchInput').value.trim();
            const resultsContainer = document.getElementById('searchResults');
            
            if (!keyword) {
                resultsContainer.innerHTML = '<p>Please enter a search term.</p>';
                return;
            }
            
            resultsContainer.innerHTML = '<div class="loading">Searching...</div>';
            
            const url = `?ajax=search_articles&keyword=${encodeURIComponent(keyword)}` +
                        (currentFilters.feed ? `&feed=${encodeURIComponent(currentFilters.feed)}` : '');
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        resultsContainer.innerHTML = '<p>No results found.</p>';
                        return;
                    }
                    
                    let html = `<p><strong>${data.length} results found</strong></p>`;
                    
                    data.forEach(item => {
                        html += `
                            <div class="search-item">
                                <strong>${item.feed_name}</strong><br>
                                <small>${item.total_articles} articles • ${new Date(item.timestamp).toLocaleString()}</small>
                            </div>
                        `;
                    });
                    
                    resultsContainer.innerHTML = html;
                });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>