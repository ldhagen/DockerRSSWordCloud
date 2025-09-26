<?php
require_once 'config.php';

// Get word cloud data
$days = (int)($_GET['days'] ?? 7);
$min_count = (int)($_GET['min_count'] ?? 3);
$max_words = (int)($_GET['max_words'] ?? 100);

$trending_words = get_trending_words($days, $max_words);

// Filter by minimum count
$trending_words = array_filter($trending_words, function($word) use ($min_count) {
    return $word['total_count'] >= $min_count;
});

// Handle AJAX request for word details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'word_details') {
    header('Content-Type: application/json');
    $word = $_GET['word'] ?? '';
    
    $pdo = get_db();
    if ($pdo && $word) {
        try {
            // Get recent articles containing the word
            $stmt = $pdo->prepare("
                SELECT DISTINCT a.title, a.link, a.feed_name, a.timestamp
                FROM articles a
                JOIN word_history wh ON a.collection_id = wh.collection_id
                WHERE wh.word = ? 
                AND a.timestamp > datetime('now', '-7 days')
                ORDER BY a.timestamp DESC
                LIMIT 10
            ");
            $stmt->execute([$word]);
            $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get trend data
            $trends = get_word_trends($word, 30);
            
            echo json_encode([
                'word' => $word,
                'articles' => $articles,
                'trends' => $trends
            ]);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Invalid request']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Word Cloud</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.6.1/d3.min.js"></script>
    <style>
        .wordcloud-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
            text-align: center;
            min-height: 500px;
        }
        .word-cloud-item {
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Arial', sans-serif;
            font-weight: bold;
        }
        .word-cloud-item:hover {
            opacity: 0.7;
            transform: scale(1.1);
        }
        .controls-panel {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .control-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .control-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 80px;
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            max-height: 80%;
            overflow-y: auto;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .article-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .article-title {
            font-weight: bold;
            color: #1976d2;
            text-decoration: none;
        }
        .article-title:hover {
            text-decoration: underline;
        }
        .article-source {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        #wordCloudSvg {
            width: 100%;
            height: 500px;
        }
        .analytics-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #1976d2;
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
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>🎨 Interactive Word Cloud</h1>
            <div class="nav-buttons">
                <a href="index.php" class="nav-btn">← Back to Main</a>
                <a href="analytics.php" class="nav-btn">📊 Analytics Dashboard</a>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls-panel">
            <div class="control-group">
                <label>Time Range:</label>
                <select id="daysSelect" class="control-input" onchange="updateWordCloud()">
                    <option value="1">1 day</option>
                    <option value="3">3 days</option>
                    <option value="7" <?= $days == 7 ? 'selected' : '' ?>>7 days</option>
                    <option value="14">14 days</option>
                    <option value="30">30 days</option>
                </select>
            </div>
            <div class="control-group">
                <label>Min Count:</label>
                <input type="number" id="minCountInput" class="control-input" value="<?= $min_count ?>" min="1" onchange="updateWordCloud()">
            </div>
            <div class="control-group">
                <label>Max Words:</label>
                <input type="number" id="maxWordsInput" class="control-input" value="<?= $max_words ?>" min="10" max="200" onchange="updateWordCloud()">
            </div>
            <button onclick="updateWordCloud()" class="btn">Update Cloud</button>
            <button onclick="toggleAnimation()" class="btn" id="animationBtn">Pause Animation</button>
        </div>

        <!-- Word Cloud -->
        <div class="wordcloud-container">
            <?php if (empty($trending_words)): ?>
                <div style="padding: 100px 0;">
                    <h3>No words to display yet!</h3>
                    <p>Go to the <a href="index.php">main page</a> and process some RSS feeds to generate your word cloud.</p>
                </div>
            <?php else: ?>
                <svg id="wordCloudSvg"></svg>
            <?php endif; ?>
        </div>

        <!-- Statistics -->
        <?php if (!empty($trending_words)): ?>
        <div class="analytics-card">
            <h3>Word Cloud Statistics</h3>
            <div style="display: flex; justify-content: space-around; text-align: center;">
                <div>
                    <div class="stat-value"><?= count($trending_words) ?></div>
                    <div>Total Words</div>
                </div>
                <div>
                    <div class="stat-value"><?= array_sum(array_column($trending_words, 'total_count')) ?></div>
                    <div>Total Mentions</div>
                </div>
                <div>
                    <div class="stat-value"><?= $days ?></div>
                    <div>Days Analyzed</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Word Details Modal -->
    <div id="wordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalWordTitle"></h2>
            <div id="modalContent">Loading...</div>
        </div>
    </div>

    <script>
        const wordData = <?= json_encode(array_values($trending_words)) ?>;
        let animationRunning = true;
        
        // Only create word cloud if we have data
        if (wordData.length > 0) {
            // Color scales
            const colorScale = d3.scaleOrdinal()
                .domain(['high', 'medium', 'low'])
                .range(['#1976d2', '#388e3c', '#f57c00', '#d32f2f', '#7b1fa2', '#0097a7']);

            // Create word cloud
            function createWordCloud() {
                const svg = d3.select('#wordCloudSvg');
                svg.selectAll("*").remove();
                
                const width = 800;
                const height = 500;
                const centerX = width / 2;
                const centerY = height / 2;
                
                // Calculate font sizes
                const maxCount = Math.max(...wordData.map(d => d.total_count));
                const minCount = Math.min(...wordData.map(d => d.total_count));
                const fontScale = d3.scaleLinear()
                    .domain([minCount, maxCount])
                    .range([12, 48]);
                
                // Create word elements
                const words = svg.selectAll('.word-cloud-item')
                    .data(wordData)
                    .enter()
                    .append('text')
                    .attr('class', 'word-cloud-item')
                    .attr('text-anchor', 'middle')
                    .attr('font-size', d => fontScale(d.total_count) + 'px')
                    .attr('fill', (d, i) => colorScale(i % 6))
                    .attr('x', centerX)
                    .attr('y', centerY)
                    .text(d => d.word)
                    .style('cursor', 'pointer')
                    .on('click', function(event, d) {
                        showWordDetails(d.word);
                    })
                    .on('mouseover', function(event, d) {
                        d3.select(this).style('opacity', 0.7);
                        // Show tooltip
                        const tooltip = d3.select('body').append('div')
                            .attr('class', 'tooltip')
                            .style('position', 'absolute')
                            .style('background', 'rgba(0,0,0,0.8)')
                            .style('color', 'white')
                            .style('padding', '8px')
                            .style('border-radius', '4px')
                            .style('font-size', '12px')
                            .style('pointer-events', 'none')
                            .style('left', (event.pageX + 10) + 'px')
                            .style('top', (event.pageY - 10) + 'px')
                            .text(`${d.word}: ${d.total_count} mentions`);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 1);
                        d3.selectAll('.tooltip').remove();
                    });

                // Position words in a spiral pattern
                let angle = 0;
                let radius = 30;
                
                words.each(function(d, i) {
                    const x = centerX + radius * Math.cos(angle);
                    const y = centerY + radius * Math.sin(angle);
                    
                    d3.select(this)
                        .attr('x', x)
                        .attr('y', y);
                    
                    angle += 0.5;
                    radius += 1.5;
                    
                    // Reset radius if getting too far from center
                    if (radius > 200) {
                        radius = 30;
                        angle += 1;
                    }
                });

                // Add animation
                if (animationRunning) {
                    animateWords();
                }
            }

            function animateWords() {
                if (!animationRunning) return;
                
                d3.selectAll('.word-cloud-item')
                    .transition()
                    .duration(3000)
                    .attr('transform', function() {
                        const rotate = Math.random() * 10 - 5; // Random rotation between -5 and 5 degrees
                        return `rotate(${rotate})`;
                    })
                    .on('end', function() {
                        // Continue animation
                        setTimeout(animateWords, 1000);
                    });
            }

            // Initialize word cloud
            createWordCloud();
        }

        function toggleAnimation() {
            animationRunning = !animationRunning;
            document.getElementById('animationBtn').textContent = animationRunning ? 'Pause Animation' : 'Start Animation';
            
            if (animationRunning && wordData.length > 0) {
                animateWords();
            }
        }

        function updateWordCloud() {
            const days = document.getElementById('daysSelect').value;
            const minCount = document.getElementById('minCountInput').value;
            const maxWords = document.getElementById('maxWordsInput').value;
            
            window.location.href = `?days=${days}&min_count=${minCount}&max_words=${maxWords}`;
        }

        function showWordDetails(word) {
            document.getElementById('modalWordTitle').textContent = `"${word}" Details`;
            document.getElementById('modalContent').innerHTML = 'Loading...';
            document.getElementById('wordModal').style.display = 'block';
            
            fetch(`?ajax=word_details&word=${encodeURIComponent(word)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('modalContent').innerHTML = `<p>Error: ${data.error}</p>`;
                        return;
                    }
                    
                    let content = '<div class="word-details">';
                    
                    // Articles section
                    if (data.articles && data.articles.length > 0) {
                        content += '<h3>Recent Articles</h3>';
                        data.articles.forEach(article => {
                            content += `
                                <div class="article-item">
                                    <a href="${article.link}" target="_blank" class="article-title">${article.title}</a>
                                    <div class="article-source">${article.feed_name} - ${new Date(article.timestamp).toLocaleDateString()}</div>
                                </div>
                            `;
                        });
                    } else {
                        content += '<h3>No recent articles found</h3>';
                    }
                    
                    // Trend information
                    if (data.trends && data.trends.length > 0) {
                        const totalMentions = data.trends.reduce((sum, trend) => sum + parseInt(trend.total_count), 0);
                        content += `<h3>Trend Summary (Last 30 Days)</h3>`;
                        content += `<p>Total mentions: <strong>${totalMentions}</strong></p>`;
                        content += `<p>Active days: <strong>${data.trends.length}</strong></p>`;
                    }
                    
                    content += '</div>';
                    document.getElementById('modalContent').innerHTML = content;
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = `<p>Error loading details: ${error.message}</p>`;
                });
        }

        function closeModal() {
            document.getElementById('wordModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('wordModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>