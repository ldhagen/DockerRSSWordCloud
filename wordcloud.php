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
    <script src="https://cdn.jsdelivr.net/npm/gifshot@0.4.5/dist/gifshot.min.js"></script>
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
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
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
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
            display: none;
        }
        .progress-fill {
            height: 100%;
            background: #1976d2;
            width: 0%;
            transition: width 0.3s ease;
        }
        .export-status {
            margin-top: 10px;
            font-weight: bold;
            color: #1976d2;
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
            <button onclick="exportAsImage()" class="btn btn-success">📷 Export as PNG</button>
            <button onclick="exportAsVideo()" class="btn btn-success" id="exportBtn">🎬 Export as Video</button>
            <button onclick="exportAsGif()" class="btn btn-warning" id="exportGifBtn">🎞️ Export as GIF</button>
        </div>

        <div id="exportProgress" style="display: none; padding: 10px; background: #f5f5f5; border-radius: 8px; margin-bottom: 20px;">
            <div class="export-status" id="exportStatus">Preparing export...</div>
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>

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
        
        if (wordData.length > 0) {
            const colorScale = d3.scaleOrdinal()
                .domain(['high', 'medium', 'low'])
                .range(['#1976d2', '#388e3c', '#f57c00', '#d32f2f', '#7b1fa2', '#0097a7']);

            function createWordCloud() {
                const svg = d3.select('#wordCloudSvg');
                svg.selectAll("*").remove();
                
                const width = 800;
                const height = 500;
                const centerX = width / 2;
                const centerY = height / 2;
                
                const maxCount = Math.max(...wordData.map(d => d.total_count));
                const minCount = Math.min(...wordData.map(d => d.total_count));
                const fontScale = d3.scaleLinear()
                    .domain([minCount, maxCount])
                    .range([12, 48]);
                
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
                    
                    if (radius > 200) {
                        radius = 30;
                        angle += 1;
                    }
                });

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
                        const rotate = Math.random() * 10 - 5;
                        return `rotate(${rotate})`;
                    })
                    .on('end', function() {
                        setTimeout(animateWords, 1000);
                    });
            }

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

        async function exportAsImage() {
            try {
                const wasAnimating = animationRunning;
                if (wasAnimating) {
                    animationRunning = false;
                }
                
                const canvas = await svgToCanvas();
                
                canvas.toBlob(function(blob) {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `wordcloud_${new Date().getTime()}.png`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    if (wasAnimating) {
                        animationRunning = true;
                        animateWords();
                    }
                }, 'image/png');
                
            } catch (error) {
                console.error('Export failed:', error);
                alert('Export failed: ' + error.message);
            }
        }

        async function exportAsGif() {
            const exportGifBtn = document.getElementById('exportGifBtn');
            const exportProgress = document.getElementById('exportProgress');
            const exportStatus = document.getElementById('exportStatus');
            const progressBar = document.getElementById('progressBar');
            const progressFill = document.getElementById('progressFill');
            
            exportGifBtn.disabled = true;
            exportGifBtn.textContent = 'Creating GIF...';
            exportProgress.style.display = 'block';
            progressBar.style.display = 'block';
            
            try {
                const wasAnimating = animationRunning;
                if (wasAnimating) {
                    animationRunning = false;
                }
                
                exportStatus.textContent = 'Capturing animation frames...';
                progressFill.style.width = '10%';
                
                const images = [];
                const fps = 10;
                const duration = 3;
                const totalFrames = fps * duration;
                
                for (let frame = 0; frame < totalFrames; frame++) {
                    const progress = 10 + (frame / totalFrames * 60);
                    progressFill.style.width = progress + '%';
                    exportStatus.textContent = `Capturing frame ${frame + 1}/${totalFrames}...`;
                    
                    d3.selectAll('.word-cloud-item')
                        .attr('transform', function(d, idx) {
                            const t = frame / fps;
                            const rotate = Math.sin(t * 2 + idx * 0.3) * 10;
                            const scale = 1 + Math.sin(t * 3 + idx * 0.5) * 0.08;
                            return `rotate(${rotate}) scale(${scale})`;
                        });
                    
                    await new Promise(resolve => setTimeout(resolve, 50));
                    
                    const canvas = await svgToCanvas();
                    images.push(canvas.toDataURL('image/png'));
                }
                
                exportStatus.textContent = 'Creating GIF from frames...';
                progressFill.style.width = '75%';
                
                gifshot.createGIF({
                    images: images,
                    gifWidth: 800,
                    gifHeight: 500,
                    interval: 0.1,
                    numFrames: totalFrames,
                    frameDuration: 1,
                    sampleInterval: 10,
                    numWorkers: 2
                }, function(obj) {
                    if (!obj.error) {
                        exportStatus.textContent = 'Preparing download...';
                        progressFill.style.width = '95%';
                        
                        fetch(obj.image)
                            .then(res => res.blob())
                            .then(blob => {
                                const url = URL.createObjectURL(blob);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = `wordcloud_animated_${new Date().getTime()}.gif`;
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                                URL.revokeObjectURL(url);
                                
                                exportStatus.textContent = 'GIF download complete!';
                                progressFill.style.width = '100%';
                                
                                setTimeout(() => {
                                    exportProgress.style.display = 'none';
                                    exportGifBtn.disabled = false;
                                    exportGifBtn.textContent = '🎞️ Export as GIF';
                                    progressFill.style.width = '0%';
                                    
                                    d3.selectAll('.word-cloud-item').attr('transform', '');
                                    
                                    if (wasAnimating) {
                                        animationRunning = true;
                                        animateWords();
                                    }
                                }, 2000);
                            });
                    } else {
                        throw new Error(obj.error);
                    }
                });
                
            } catch (error) {
                console.error('GIF export failed:', error);
                exportStatus.textContent = 'Export failed: ' + error.message;
                exportGifBtn.disabled = false;
                exportGifBtn.textContent = '🎞️ Export as GIF';
                
                d3.selectAll('.word-cloud-item').attr('transform', '');
                
                setTimeout(() => {
                    exportProgress.style.display = 'none';
                }, 3000);
            }
        }

        async function exportAsVideo() {
            const exportBtn = document.getElementById('exportBtn');
            const exportProgress = document.getElementById('exportProgress');
            const exportStatus = document.getElementById('exportStatus');
            const progressBar = document.getElementById('progressBar');
            const progressFill = document.getElementById('progressFill');
            
            exportBtn.disabled = true;
            exportBtn.textContent = 'Recording...';
            exportProgress.style.display = 'block';
            progressBar.style.display = 'block';
            
            try {
                const wasAnimating = animationRunning;
                if (wasAnimating) {
                    animationRunning = false;
                }
                
                exportStatus.textContent = 'Preparing frames...';
                progressFill.style.width = '10%';
                
                const frames = [];
                const fps = 20;
                const duration = 3;
                const totalFrames = fps * duration;
                
                exportStatus.textContent = 'Capturing animation frames...';
                
                for (let frame = 0; frame < totalFrames; frame++) {
                    const progress = 10 + (frame / totalFrames * 70);
                    progressFill.style.width = progress + '%';
                    exportStatus.textContent = `Capturing frame ${frame + 1}/${totalFrames}...`;
                    
                    d3.selectAll('.word-cloud-item')
                        .attr('transform', function(d, idx) {
                            const t = frame / fps;
                            const rotate = Math.sin(t * 2 + idx * 0.3) * 10;
                            const scale = 1 + Math.sin(t * 3 + idx * 0.5) * 0.08;
                            return `rotate(${rotate}) scale(${scale})`;
                        });
                    
                    await new Promise(resolve => setTimeout(resolve, 50));
                    
                    const canvas = await svgToCanvas();
                    const imageData = canvas.toDataURL('image/png');
                    frames.push(imageData);
                }
                
                exportStatus.textContent = 'Creating video from frames...';
                progressFill.style.width = '85%';
                
                const recordCanvas = document.createElement('canvas');
                recordCanvas.width = 800;
                recordCanvas.height = 500;
                const ctx = recordCanvas.getContext('2d');
                
                const stream = recordCanvas.captureStream(fps);
                const mediaRecorder = new MediaRecorder(stream, {
                    mimeType: 'video/webm;codecs=vp9',
                    videoBitsPerSecond: 5000000
                });
                
                const chunks = [];
                mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) {
                        chunks.push(e.data);
                    }
                };
                
                mediaRecorder.onstop = () => {
                    progressFill.style.width = '100%';
                    exportStatus.textContent = 'Download ready!';
                    
                    const blob = new Blob(chunks, { type: 'video/webm' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `wordcloud_animated_${new Date().getTime()}.webm`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    setTimeout(() => {
                        exportProgress.style.display = 'none';
                        exportBtn.disabled = false;
                        exportBtn.textContent = '🎬 Export as Video';
                        progressFill.style.width = '0%';
                        
                        d3.selectAll('.word-cloud-item').attr('transform', '');
                        
                        if (wasAnimating) {
                            animationRunning = true;
                            animateWords();
                        }
                    }, 1500);
                };
                
                mediaRecorder.start();
                
                let frameIndex = 0;
                const playFrames = () => {
                    if (frameIndex >= frames.length) {
                        setTimeout(() => {
                            mediaRecorder.stop();
                            exportStatus.textContent = 'Finalizing video...';
                            progressFill.style.width = '95%';
                        }, 500);
                        return;
                    }
                    
                    const img = new Image();
                    img.onload = () => {
                        ctx.fillStyle = 'white';
                        ctx.fillRect(0, 0, recordCanvas.width, recordCanvas.height);
                        ctx.drawImage(img, 0, 0);
                        
                        frameIndex++;
                        setTimeout(playFrames, 1000 / fps);
                    };
                    img.src = frames[frameIndex];
                };
                
                playFrames();
                
            } catch (error) {
                console.error('Export failed:', error);
                exportStatus.textContent = 'Export failed: ' + error.message;
                exportBtn.disabled = false;
                exportBtn.textContent = '🎬 Export as Video';
                
                d3.selectAll('.word-cloud-item').attr('transform', '');
                
                setTimeout(() => {
                    exportProgress.style.display = 'none';
                }, 3000);
            }
        }
        
        async function svgToCanvas() {
            const svg = document.getElementById('wordCloudSvg');
            const canvas = document.createElement('canvas');
            canvas.width = 800;
            canvas.height = 500;
            const ctx = canvas.getContext('2d');
            
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            const svgData = new XMLSerializer().serializeToString(svg);
            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(svgBlob);
            
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = function() {
                    ctx.drawImage(img, 0, 0);
                    URL.revokeObjectURL(url);
                    resolve(canvas);
                };
                img.onerror = reject;
                img.src = url;
            });
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

        window.onclick = function(event) {
            const modal = document.getElementById('wordModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
