<?php
require_once 'config.php';

// Get system information
$php_version = phpversion();
$sqlite_version = SQLite3::version()['versionString'];
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';

// Check directory permissions
function check_directory_permission($dir) {
    if (!file_exists($dir)) {
        return ['exists' => false, 'readable' => false, 'writable' => false];
    }
    return [
        'exists' => true,
        'readable' => is_readable($dir),
        'writable' => is_writable($dir)
    ];
}

// Get database statistics
function get_database_stats() {
    $pdo = get_db();
    if (!$pdo) return null;
    
    try {
        $stats = [];
        
        // Get table counts
        $tables = ['collections', 'word_history', 'articles'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
            $stats[$table . '_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        }
        
        // Database file size
        if (file_exists(DATABASE_FILE)) {
            $stats['db_size'] = filesize(DATABASE_FILE);
            $stats['db_size_mb'] = round(filesize(DATABASE_FILE) / 1024 / 1024, 2);
        }
        
        // Date ranges
        $stmt = $pdo->query("SELECT MIN(timestamp) as first, MAX(timestamp) as last FROM collections");
        $dates = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['first_collection'] = $dates['first'];
        $stats['last_collection'] = $dates['last'];
        
        // Unique words
        $stmt = $pdo->query("SELECT COUNT(DISTINCT word) as cnt FROM word_history");
        $stats['unique_words'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        
        // Unique feeds
        $stmt = $pdo->query("SELECT COUNT(DISTINCT feed_name) as cnt FROM collections");
        $stats['unique_feeds'] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        
        return $stats;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// Get configuration settings
$config_settings = [
    'TITLES_ONLY_ANALYSIS' => TITLES_ONLY_ANALYSIS,
    'DATA_DIR' => DATA_DIR,
    'LOGS_DIR' => LOGS_DIR,
    'CACHE_DIR' => CACHE_DIR,
    'DATABASE_FILE' => DATABASE_FILE,
    'FEEDS_FILE' => FEEDS_FILE,
    'STOPWORDS_FILE' => STOPWORDS_FILE,
];

// Check file existence
$feeds_data = load_json(FEEDS_FILE, ['feeds' => []]);
$stopwords = load_json(STOPWORDS_FILE, []);

// Directory permissions
$dir_perms = [
    'data' => check_directory_permission(DATA_DIR),
    'logs' => check_directory_permission(LOGS_DIR),
    'cache' => check_directory_permission(CACHE_DIR),
];

// Get database stats
$db_stats = get_database_stats();

// Cache information
$cache_files = glob(CACHE_DIR . '/*.xml');
$cache_count = count($cache_files);
$cache_size = 0;
foreach ($cache_files as $file) {
    if (file_exists($file)) {
        $cache_size += filesize($file);
    }
}

// Log file information
$log_file = LOGS_DIR . '/analyzer.log';
$log_exists = file_exists($log_file);
$log_size = $log_exists ? filesize($log_file) : 0;
$log_lines = 0;
if ($log_exists && $log_size > 0) {
    $log_lines = count(file($log_file));
}

// PHP Extensions
$required_extensions = ['pdo', 'pdo_sqlite', 'sqlite3', 'json', 'mbstring'];
$extension_status = [];
foreach ($required_extensions as $ext) {
    $extension_status[$ext] = extension_loaded($ext);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - RSS Word Counter</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0 0 10px 0;
        }
        .header p {
            margin: 0;
            opacity: 0.9;
        }
        .nav-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            background: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1565c0;
        }
        .section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section h2 {
            margin-top: 0;
            color: #1976d2;
            border-bottom: 2px solid #e3f2fd;
            padding-bottom: 10px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .status-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #1976d2;
        }
        .status-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .status-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .status-ok {
            border-left-color: #4caf50;
        }
        .status-warning {
            border-left-color: #ff9800;
        }
        .status-error {
            border-left-color: #f44336;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }
        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }
        .badge-info {
            background: #e3f2fd;
            color: #1565c0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        table tr:hover {
            background: #fafafa;
        }
        .config-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .config-key {
            font-weight: 600;
            color: #555;
        }
        .config-value {
            color: #1976d2;
            font-family: 'Courier New', monospace;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .alert-info {
            background: #e3f2fd;
            border-left: 4px solid #1976d2;
            color: #1565c0;
        }
        .alert-warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            color: #ef6c00;
        }
        .alert-danger {
            background: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>System Status & Configuration</h1>
            <p>RSS Word Counter Analytics Platform</p>
        </div>

        <div class="nav-buttons">
            <a href="index.php" class="btn">Main Page</a>
            <a href="analytics.php" class="btn">Analytics</a>
            <a href="wordcloud.php" class="btn">Word Cloud</a>
            <a href="debug_db.php" class="btn">Database Debug</a>
        </div>

        <!-- System Information -->
        <div class="section">
            <h2>System Information</h2>
            <div class="status-grid">
                <div class="status-item">
                    <div class="status-label">PHP Version</div>
                    <div class="status-value"><?= $php_version ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">SQLite Version</div>
                    <div class="status-value"><?= $sqlite_version ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">Web Server</div>
                    <div class="status-value"><?= htmlspecialchars($server_software) ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">Document Root</div>
                    <div class="status-value" style="font-size: 12px; word-break: break-all;"><?= htmlspecialchars($document_root) ?></div>
                </div>
            </div>
        </div>

        <!-- PHP Extensions -->
        <div class="section">
            <h2>PHP Extensions</h2>
            <table>
                <thead>
                    <tr>
                        <th>Extension</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($extension_status as $ext => $loaded): ?>
                        <tr>
                            <td><code><?= $ext ?></code></td>
                            <td>
                                <?php if ($loaded): ?>
                                    <span class="badge badge-success">Loaded</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Not Loaded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Configuration Settings -->
        <div class="section">
            <h2>Configuration Settings</h2>
            <div class="alert alert-info">
                <strong>Note:</strong> These settings are defined in <code>config.php</code>
            </div>
            <?php foreach ($config_settings as $key => $value): ?>
                <div class="config-item">
                    <span class="config-key"><?= $key ?></span>
                    <span class="config-value">
                        <?php if (is_bool($value)): ?>
                            <?= $value ? 'true' : 'false' ?>
                        <?php else: ?>
                            <?= htmlspecialchars($value) ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                <h4 style="margin-top: 0;">Configurable Options</h4>
                <ul>
                    <li><strong>TITLES_ONLY_ANALYSIS:</strong> Set to <code>true</code> for title-only analysis, <code>false</code> for full content</li>
                    <li><strong>DATA_DIR:</strong> Directory for data files (feeds.json, stopwords.json, database)</li>
                    <li><strong>LOGS_DIR:</strong> Directory for application logs</li>
                    <li><strong>CACHE_DIR:</strong> Directory for RSS feed cache files</li>
                    <li><strong>Timezone:</strong> Set via <code>date_default_timezone_set()</code> in config.php</li>
                    <li><strong>Cache Duration:</strong> Modify <code>$cache_time</code> in <code>fetch_rss()</code> function (default: 3600 seconds)</li>
                </ul>
            </div>
        </div>

        <!-- Directory Permissions -->
        <div class="section">
            <h2>Directory Permissions</h2>
            <table>
                <thead>
                    <tr>
                        <th>Directory</th>
                        <th>Exists</th>
                        <th>Readable</th>
                        <th>Writable</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dir_perms as $dir => $perms): ?>
                        <tr>
                            <td><code><?= $dir ?>/</code></td>
                            <td>
                                <?php if ($perms['exists']): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($perms['readable']): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($perms['writable']): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($perms['exists'] && $perms['readable'] && $perms['writable']): ?>
                                    <span class="badge badge-success">OK</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Issues Detected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!$dir_perms['data']['writable'] || !$dir_perms['logs']['writable'] || !$dir_perms['cache']['writable']): ?>
                <div class="alert alert-danger">
                    <strong>Permission Error!</strong> Some directories are not writable. Fix with:
                    <br><code>chmod -R 777 data logs cache</code>
                </div>
            <?php endif; ?>
        </div>

        <!-- Database Statistics -->
        <div class="section">
            <h2>Database Statistics</h2>
            <?php if ($db_stats && !isset($db_stats['error'])): ?>
                <div class="status-grid">
                    <div class="status-item status-ok">
                        <div class="status-label">Collections</div>
                        <div class="status-value"><?= number_format($db_stats['collections_count']) ?></div>
                    </div>
                    <div class="status-item status-ok">
                        <div class="status-label">Word Entries</div>
                        <div class="status-value"><?= number_format($db_stats['word_history_count']) ?></div>
                    </div>
                    <div class="status-item status-ok">
                        <div class="status-label">Articles</div>
                        <div class="status-value"><?= number_format($db_stats['articles_count']) ?></div>
                    </div>
                    <div class="status-item status-ok">
                        <div class="status-label">Unique Words</div>
                        <div class="status-value"><?= number_format($db_stats['unique_words']) ?></div>
                    </div>
                    <div class="status-item status-ok">
                        <div class="status-label">Unique Feeds</div>
                        <div class="status-value"><?= $db_stats['unique_feeds'] ?></div>
                    </div>
                    <div class="status-item status-ok">
                        <div class="status-label">Database Size</div>
                        <div class="status-value"><?= $db_stats['db_size_mb'] ?> MB</div>
                    </div>
                </div>
                
                <?php if ($db_stats['first_collection']): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                        <p><strong>First Collection:</strong> <?= date('Y-m-d H:i:s', strtotime($db_stats['first_collection'])) ?></p>
                        <p><strong>Last Collection:</strong> <?= date('Y-m-d H:i:s', strtotime($db_stats['last_collection'])) ?></p>
                    </div>
                <?php endif; ?>
            <?php elseif (isset($db_stats['error'])): ?>
                <div class="alert alert-danger">
                    <strong>Database Error:</strong> <?= htmlspecialchars($db_stats['error']) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <strong>No Database Connection</strong>
                </div>
            <?php endif; ?>
        </div>

        <!-- Feed & Stopword Information -->
        <div class="section">
            <h2>Feed & Stopword Configuration</h2>
            <div class="status-grid">
                <div class="status-item">
                    