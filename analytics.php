<?php
require_once 'config.php';

// Handle AJAX requests for charts
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['ajax']) {
        case 'word_trends':
            $word = $_GET['word'] ?? '';
            $days = (int)($_GET['days'] ?? 30);
            echo json_encode(get_word_trends($word, $days));
            break;
            
        case 'feed_activity':
            echo json_encode(get_feed_activity(30));
            break;
            
        case 'daily_stats':
            echo json_encode(get_daily_stats(30));
            break;
    }
    exit;
}

// Get analytics data
$trending_words = get_trending_words(7, 50);
$recent_collections = get_recent_collections(10);

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

function get_feed_activity($days = 30) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                feed_name,
                COUNT(*) as collection_count,
                SUM(total_articles) as total_articles,
                SUM(total_words) as total_words,
                AVG(total_articles) as avg_articles,
                MAX(timestamp) as last_collection
            FROM collections 
            WHERE timestamp > datetime('now', '-' || ? || ' days')
            GROUP BY feed_name
            ORDER BY collection_count DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function get_daily_stats($days = 30) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(timestamp) as date,
                COUNT(*) as collections,
                SUM(total_articles) as articles,
                SUM(total_words) as words
            FROM collections 
            WHERE timestamp > datetime('now', '-' || ? || ' days')
            GROUP BY DATE(timestamp)
            ORDER BY date
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
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
            padding: 4px 8px;
            border-radius: 16px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .word-tag:hover {
            background: #bbdefb;
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
        }
        .word-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        }
        .btn:hover {
            background: #1565c0;
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
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>📊 RSS Analytics Dashboard</h1>
            <div>
                <a href="index.php" class="btn">← Back to Main</a>
                <a href="wordcloud.php" class="btn">🎨 Word Cloud</a>
            </div>
        </div>

        <!-- Trending Words Section -->
        <div class="analytics-card">
            <h2>🔥 Trending Words (Last 7 Days)</h2>
            <div class="trending-words">
                <?php if (empty($trending_words)): ?>
                    <p>No trending words yet. <a href="index.php">Process some feeds</a> to see analytics!</p>
                <?php else: ?>
                    <?php foreach (array_slice($trending_words, 0, 30) as $word_data): ?>
                        <span class="word-tag" onclick="showWordTrend('<?= sanitize_output($word_data['word']) ?>')" title="<?= $word_data['total_count'] ?> mentions across <?= $word_data['feed_count'] ?> feeds">
                            <?= sanitize_output($word_data['word']) ?> (<?= $word_data['total_count'] ?>)
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Analytics Grid -->
        <div class="analytics-grid">
            <!-- Daily Activity Chart -->
            <div class="analytics-card">
                <h3>📈 Daily Activity (Last 30 Days)</h3>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <!-- Feed Activity Chart -->
            <div class="analytics-card">
                <h3>📰 Feed Activity</h3>
                <div class="chart-container">
                    <canvas id="feedChart"></canvas>
                </div>
            </div>

            <!-- Word Trend Analysis -->
            <div class="analytics-card">
                <h3>🔍 Word Trend Analysis</h3>
                <div class="controls">
                    <input type="text" id="wordInput" class="word-input" placeholder="Enter word to analyze">
                    <button onclick="analyzeWord()" class="btn">Analyze</button>
                    <select id="trendDays" onchange="analyzeWord()">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Feed Statistics -->
            <div class="analytics-card">
                <h3>📊 Feed Statistics</h3>
                <div id="feedStats">
                    <?php
                    $feed_activity = get_feed_activity(30);
                    if (empty($feed_activity)):
                    ?>
                        <p>No feed activity yet. <a href="index.php">Process some feeds</a> to see statistics!</p>
                    <?php else: ?>
                        <?php foreach ($feed_activity as $feed): ?>
                            <div class="stats-row">
                                <span><?= sanitize_output($feed['feed_name']) ?></span>
                                <span class="stat-value"><?= number_format($feed['total_articles']) ?> articles</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Collections -->
        <div class="analytics-card">
            <h3>⏰ Recent Collections</h3>
            <?php if (empty($recent_collections)): ?>
                <p>No collections yet. <a href="index.php">Process some feeds</a> to see recent activity!</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Feed</th>
                            <th style="text-align: right;">Articles</th>
                            <th style="text-align: right;">Words</th>
                            <th style="text-align: right;">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_collections as $collection): ?>
                            <tr>
                                <td><?= sanitize_output($collection['feed_name']) ?></td>
                                <td style="text-align: right;"><?= number_format($collection['total_articles']) ?></td>
                                <td style="text-align: right;"><?= number_format($collection['total_words']) ?></td>
                                <td style="text-align: right;"><?= date('M j, H:i', strtotime($collection['timestamp'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Initialize charts
        let dailyChart = null;
        let feedChart = null;
        let trendChart = null;

        // Load daily activity chart
        fetch('?ajax=daily_stats&days=30')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('dailyChart').getContext('2d');
                dailyChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(d => d.date),
                        datasets: [{
                            label: 'Articles Collected',
                            data: data.map(d => d.articles),
                            borderColor: '#1976d2',
                            backgroundColor: 'rgba(25, 118, 210, 0.1)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });

        // Load feed activity chart
        fetch('?ajax=feed_activity&days=30')
            .then(response => response.json())
            .then(data => {
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
                                position: 'bottom'
                            }
                        }
                    }
                });
            });

        // Analyze word trends
        function analyzeWord() {
            const word = document.getElementById('wordInput').value.trim();
            const days = document.getElementById('trendDays').value;
            
            if (!word) return;

            fetch(`?ajax=word_trends&word=${encodeURIComponent(word)}&days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (trendChart) {
                        trendChart.destroy();
                    }

                    const ctx = document.getElementById('trendChart').getContext('2d');
                    trendChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.map(d => d.date),
                            datasets: [{
                                label: `"${word}" mentions`,
                                data: data.map(d => d.total_count),
                                borderColor: '#f57c00',
                                backgroundColor: 'rgba(245, 124, 0, 0.1)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                });
        }

        // Show word trend from trending words
        function showWordTrend(word) {
            document.getElementById('wordInput').value = word;
            analyzeWord();
        }

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
            