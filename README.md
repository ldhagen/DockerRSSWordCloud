# RSS Word Counter with Analytics

A comprehensive RSS feed analyzer that tracks word frequencies, generates interactive visualizations, and provides historical trend analysis. Built with PHP, SQLite, and Chart.js.

## Features

- **Multi-Feed Processing**: Analyze multiple RSS feeds simultaneously
- **Word Frequency Analysis**: Track most common words across feeds
- **Interactive Analytics Dashboard**: View trends, charts, and feed statistics
- **Word Cloud Visualization**: Dynamic D3.js-powered word clouds
- **Historical Tracking**: SQLite database stores all data for trend analysis
- **Customizable Stopwords**: Filter out common words
- **Feed-Specific Analysis**: Compare word usage across different sources
- **Co-occurrence Detection**: Find related words that appear together
- **Article Search**: Search through processed articles by keyword
- **Caching System**: Optional caching for faster repeated analysis

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: SQLite 3
- **Frontend**: HTML5, CSS3, JavaScript
- **Visualizations**: Chart.js 3.9.1, D3.js
- **Web Server**: Apache/Nginx with Docker support

## Installation

### Docker Deployment (Recommended)

1. Clone the repository:
```bash
git clone https://github.com/ldhagen/DockerRSSWordCloud.git
cd DockerRSSWordCloud
```

2. Build and start the container:
```bash
docker-compose build --no-cache
docker-compose up -d
```

3. Access the application:
```
http://localhost:8080
```

### Manual Installation

1. Requirements:
   - PHP 7.4 or higher
   - SQLite3 extension enabled
   - Web server (Apache/Nginx)

2. Clone and configure:
```bash
git clone https://github.com/ldhagen/DockerRSSWordCloud.git
cd DockerRSSWordCloud
chmod -R 755 data logs cache
```

3. Configure your web server to serve the directory

4. Access via browser

## Directory Structure

```
rss-word-counter/
├── config.php              # Core configuration and database functions
├── index.php               # Main feed processing interface
├── analytics.php           # Analytics dashboard
├── wordcloud.php           # Interactive word cloud
├── status.php              # System status and configuration viewer
├── debug_db.php           # Database structure inspector
├── migrate_db.php         # Database migration tool
├── Dockerfile             # Docker configuration
├── docker-compose.yml     # Docker Compose configuration
├── data/                  # Data directory
│   ├── analytics.db       # SQLite database
│   ├── feeds.json         # RSS feed list
│   └── stopwords.json     # Stopword list
├── logs/                  # Log files
│   └── analyzer.log       # Application logs
├── cache/                 # RSS feed cache
├── css/                   # Stylesheets
│   └── style.css
└── js/                    # JavaScript files
    └── script.js
```

## Configuration

### Core Settings (config.php)

Key configuration constants:

```php
// Content Analysis Mode
define('TITLES_ONLY_ANALYSIS', true);  // true = titles only, false = full content

// Directory Paths
define('DATA_DIR', 'data');
define('LOGS_DIR', 'logs');
define('CACHE_DIR', 'cache');
define('DATABASE_FILE', DATA_DIR . '/analytics.db');

// Timezone
date_default_timezone_set('UTC');
```

### Feed Management

Add feeds via the web interface or manually edit `data/feeds.json`:

```json
{
  "feeds": [
    {
      "url": "https://example.com/rss",
      "name": "Example Feed"
    }
  ]
}
```

### Stopwords

Customize stopwords in the web interface or edit `data/stopwords.json`:

```json
["the", "and", "to", "of", "a", ...]
```

## Usage

### Processing Feeds

1. Navigate to the main page (`index.php`)
2. Select feeds to process
3. Optional: Uncheck "Use cache" for fresh data
4. Optional: Check "Show counts per feed" for feed-specific results
5. Set word limit (default: 50)
6. Click "Process Feeds & Store Analytics"

### Viewing Analytics

1. Navigate to Analytics Dashboard (`analytics.php`)
2. Click trending words for detailed analysis
3. Use filters to narrow by feed or date range
4. Analyze word trends over time
5. Explore related words (co-occurrence)
6. Search articles by keyword

### Word Cloud Visualization

1. Navigate to Word Cloud (`wordcloud.php`)
2. Interactive visualization with size-based frequency
3. Click words to see detailed information
4. Filter by time period

## Database Schema

### Collections Table
Stores metadata for each feed processing run:
```sql
CREATE TABLE collections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    feed_name TEXT NOT NULL,
    total_articles INTEGER DEFAULT 0,
    total_words INTEGER DEFAULT 0
)
```

### Word History Table
Stores individual word frequencies:
```sql
CREATE TABLE word_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    collection_id INTEGER,
    word TEXT NOT NULL,
    count INTEGER NOT NULL,
    feed_name TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (collection_id) REFERENCES collections (id)
)
```

### Articles Table
Stores article metadata:
```sql
CREATE TABLE articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    collection_id INTEGER,
    title TEXT NOT NULL,
    link TEXT,
    description TEXT,
    feed_name TEXT NOT NULL,
    pub_date TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (collection_id) REFERENCES collections (id)
)
```

## API Endpoints

Analytics dashboard provides AJAX endpoints:

- `?ajax=word_trends&word=X&days=30` - Get word trend data
- `?ajax=feed_activity&days=30` - Get feed activity statistics
- `?ajax=daily_stats&days=30` - Get daily collection statistics
- `?ajax=word_details&word=X&days=30` - Get word distribution by feed
- `?ajax=feed_words&feed=X&days=30` - Get top words for specific feed
- `?ajax=word_cooccurrence&word=X&days=30` - Get related words
- `?ajax=search_articles&keyword=X` - Search articles

## Performance Optimization

### Caching

RSS feeds are cached for 1 hour by default. Modify in `config.php`:

```php
$cache_time = 3600; // seconds
```

### Database Indexes

Indexes are automatically created for optimal query performance:

```sql
CREATE INDEX idx_word_history_word ON word_history (word);
CREATE INDEX idx_word_history_timestamp ON word_history (timestamp);
CREATE INDEX idx_word_history_feed ON word_history (feed_name);
```

### Batch Processing

Word frequencies are inserted in batch transactions for better performance.

## Troubleshooting

### No Data Showing in Analytics

1. Check that feeds have been processed: Look for "Stored in database (ID: X)" messages
2. Verify database exists: `ls -la data/analytics.db`
3. Check database contents: Visit `debug_db.php`
4. Ensure `word_history` table has data
5. Clear browser cache (Ctrl+Shift+R)

### Permission Errors

```bash
chmod -R 777 data logs cache
# Or for Docker:
docker-compose down
sudo chmod -R 777 data logs cache
docker-compose up -d
```

### Database Migration

If upgrading from an older version, run the migration:

```bash
# Visit in browser:
http://localhost:8080/migrate_db.php
```

### Check System Status

Visit the status page for comprehensive system information:

```bash
http://localhost:8080/status.php
```

## Maintenance

### Viewing Logs

```bash
tail -f logs/analyzer.log
```

### Database Backup

```bash
cp data/analytics.db data/analytics.db.backup.$(date +%Y%m%d)
```

### Clearing Cache

```bash
rm -rf cache/*.xml
# Or via Docker:
docker-compose exec rss-analyzer rm -rf cache/*.xml
```

### Database Cleanup

Remove old data (older than 90 days):

```sql
sqlite3 data/analytics.db "DELETE FROM word_history WHERE timestamp < datetime('now', '-90 days');"
sqlite3 data/analytics.db "DELETE FROM collections WHERE timestamp < datetime('now', '-90 days');"
sqlite3 data/analytics.db "VACUUM;"
```

## Development

### Adding New Feeds Programmatically

```php
$feeds_data = load_json(FEEDS_FILE);
$feeds_data['feeds'][] = [
    'url' => 'https://example.com/feed.rss',
    'name' => 'New Feed'
];
save_json(FEEDS_FILE, $feeds_data);
```

### Custom Word Processing

Modify `count_words()` function in `config.php` to adjust word filtering logic.

### Extending Analytics

Add new AJAX endpoints in `analytics.php`:

```php
case 'custom_endpoint':
    $param = $_GET['param'] ?? '';
    echo json_encode(custom_function($param));
    break;
```

## Docker Commands

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Restart services
docker-compose restart

# Rebuild after changes
docker-compose build --no-cache
docker-compose up -d

# Access container shell
docker-compose exec rss-analyzer bash

# Check container status
docker-compose ps

# View resource usage
docker stats rss-word-counter
```

## Security Considerations

1. **Input Sanitization**: All user inputs are sanitized via `htmlspecialchars()`
2. **SQL Injection Protection**: Prepared statements used throughout
3. **File Access**: All file operations use validated paths
4. **SSL Verification**: Disabled for RSS fetching (can be re-enabled)
5. **Production Deployment**: Consider enabling SSL verification and adding authentication

## License

This project is provided as-is for educational and personal use.

## Contributing

Contributions welcome! Please submit pull requests or open issues on GitHub.

## Support

For issues or questions:
- Check `status.php` for system diagnostics
- Review `logs/analyzer.log` for errors
- Visit `debug_db.php` for database inspection
- Open an issue on GitHub

## Credits

- Chart.js for data visualization
- D3.js for word cloud rendering
- SQLite for database storage
- Docker for containerization

## Version History

- **v3.0** - Added comprehensive analytics dashboard with interactive features
- **v2.0** - Integrated SQLite database for historical tracking
- **v1.0** - Initial release with basic word counting

---

**Project Repository**: https://github.com/ldhagen/DockerRSSWordCloud