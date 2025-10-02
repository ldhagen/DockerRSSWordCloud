# RSS Word Counter with Analytics

A comprehensive RSS feed analyzer that tracks word frequencies, generates interactive visualizations, and provides historical trend analysis. Built with PHP, SQLite, and Chart.js.

## Features

### Core Functionality
- **Multi-Feed Processing**: Analyze multiple RSS feeds simultaneously
- **Word Frequency Analysis**: Track most common words across feeds
- **Customizable Stopwords**: Filter out common words via web interface
- **Flexible Analysis Modes**: Choose between title-only or full content analysis
- **Caching System**: Optional caching for faster repeated analysis

### Analytics Dashboard
- **Interactive Charts**: Line charts, doughnut charts, and trend visualizations
- **Time Range Filters**: 24 hours, 48 hours, 7 days, 14 days, 30 days, 90 days
- **Hourly Granularity**: See hourly breakdowns for 24h and 48h time periods
- **Word Trend Analysis**: Track individual word frequency over time
- **Multi-Word Comparison**: Compare up to 5 words simultaneously on the same chart
- **Feed-Specific Filtering**: Analyze data for specific RSS feeds
- **Co-occurrence Detection**: Find related words that appear together
- **Article Search**: Search through processed articles by keyword
- **Feed Statistics**: View collection counts, article totals, and activity metrics
- **Interactive Modals**: Click-through details for words and feeds

### Word Cloud Visualization
- **Dynamic D3.js Word Clouds**: Size-based frequency visualization
- **Interactive Elements**: Click words for detailed analysis
- **Time Period Filtering**: View word clouds for different date ranges
- **Color-Coded Display**: Visual hierarchy based on frequency

### Historical Tracking
- **SQLite Database**: Persistent storage of all analysis data
- **Trend Analysis**: Compare word usage across time periods
- **Collection History**: View all past feed processing runs
- **Article Metadata Storage**: Full article details with timestamps

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
├── analytics.php           # Analytics dashboard with charts and trends
├── wordcloud.php           # Interactive word cloud visualization
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

### Analytics Dashboard

#### Main Features

**Trending Words Section**
- View top words from the last 7 days
- Click any word for detailed feed distribution analysis
- See total mention counts via badges

**Daily Activity Chart**
- Line chart showing articles collected over time
- Automatic hourly granularity for 24h/48h periods
- Daily aggregation for longer time periods

**Feed Distribution Chart**
- Doughnut chart showing article distribution by feed
- Click legend items to view feed details
- Color-coded for easy identification

**Word Trend Analysis**
- Enter a word and click "Analyze" to see its trend over time
- Use "Add to Compare" to compare up to 5 words simultaneously
- Each compared word gets a unique color
- Remove words individually or clear all at once
- View related words that appear together (co-occurrence)
- Switch between time ranges to see different patterns

**Article Search**
- Search through processed articles by keyword
- Filter by specific feeds
- View collection timestamps and article counts

**Feed Statistics**
- Click any feed to see top words for that feed
- View collection counts and article totals
- Filter entire dashboard by specific feed

**Recent Collections**
- Table showing most recent feed processing runs
- Click entries for detailed analysis

#### Time Range Filters

Available time ranges:
- **Last 24 hours**: Hourly breakdown of recent activity
- **Last 48 hours**: Two-day hourly view
- **Last 7 days**: Weekly trends
- **Last 14 days**: Two-week analysis
- **Last 30 days**: Monthly overview (default)
- **Last 90 days**: Quarterly trends

#### Using Word Comparison

1. Enter a word in the input field
2. Click "Add to Compare" (not "Analyze")
3. Repeat for additional words (up to 5 total)
4. All words appear on the same chart with different colors
5. Click X on any word tag to remove it from comparison
6. Click "Clear All" to reset and start over
7. Clicking "Analyze" exits comparison mode

#### Interactive Elements

- **Trending Words**: Click to see feed distribution
- **Feed Names**: Click to view feed-specific top words
- **Chart Legends**: Click to explore feed details
- **Related Words**: Click to analyze those words
- **Time Filters**: Apply to entire dashboard instantly

### Word Cloud Visualization

1. Navigate to Word Cloud (`wordcloud.php`)
2. Interactive visualization with size-based frequency
3. Click words to see detailed information
4. Filter by time period
5. Hover for instant frequency display

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

- `?ajax=word_trends&word=X&days=30` - Get word trend data over time
- `?ajax=feed_activity&days=30` - Get feed activity statistics
- `?ajax=daily_stats&days=30&feed=X` - Get daily/hourly collection statistics
- `?ajax=word_details&word=X&days=30` - Get word distribution by feed
- `?ajax=feed_words&feed=X&days=30` - Get top words for specific feed
- `?ajax=word_cooccurrence&word=X&days=30` - Get related words (co-occurrence)
- `?ajax=search_articles&keyword=X&feed=X` - Search articles by keyword
- `?ajax=feed_list` - Get list of all feeds

All endpoints support the `days` parameter with values: 1, 2, 7, 14, 30, 90

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
CREATE INDEX idx_word_history_collection ON word_history (collection_id);
CREATE INDEX idx_collections_timestamp ON collections (timestamp);
CREATE INDEX idx_articles_collection ON articles (collection_id);
```

### Batch Processing

Word frequencies are inserted in batch transactions for better performance.

### Query Optimization

- Time filters use SQLite datetime functions for efficiency
- Hourly aggregation for short time periods (24h/48h)
- Daily aggregation for longer periods
- Prepared statements prevent SQL injection and improve performance

## Troubleshooting

### No Data Showing in Analytics

1. Check that feeds have been processed: Look for "Stored in database (ID: X)" messages
2. Verify database exists: `ls -la data/analytics.db`
3. Check database contents: Visit `debug_db.php`
4. Ensure `word_history` table has data
5. Clear browser cache (Ctrl+Shift+R)
6. Check console for JavaScript errors (F12)

### Charts Not Displaying

1. Verify Chart.js is loading (check browser console)
2. Ensure JavaScript is enabled in browser
3. Check that database has data for selected time period
4. Try different time range filters
5. Clear browser cache and reload

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
sqlite3 data/analytics.db "DELETE FROM articles WHERE timestamp < datetime('now', '-90 days');"
sqlite3 data/analytics.db "VACUUM;"
```

### Performance Monitoring

Check database size and optimize:

```bash
# Check database size
ls -lh data/analytics.db

# Optimize database
sqlite3 data/analytics.db "VACUUM;"
sqlite3 data/analytics.db "ANALYZE;"
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

### Adding New Time Filters

Modify `get_time_filter()` function in `analytics.php`:

```php
function get_time_filter($days) {
    switch ($days) {
        case '1':
            return "datetime('now', '-24 hours')";
        case '2':
            return "datetime('now', '-48 hours')";
        case 'custom':
            return "datetime('now', '-X hours')";
        default:
            return "datetime('now', '-' || " . intval($days) . " || ' days')";
    }
}
```

### Customizing Chart Colors

Edit the `colors` array in `analytics.php`:

```javascript
const colors = [
    '#f57c00', '#1976d2', '#388e3c', '#d32f2f', 
    '#7b1fa2', '#0097a7', '#e91e63', '#00bcd4'
];
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
3. **XSS Prevention**: Output escaping on all user-generated content
4. **File Access**: All file operations use validated paths
5. **SSL Verification**: Disabled for RSS fetching (can be re-enabled)
6. **Production Deployment**: Consider enabling SSL verification and adding authentication

## Best Practices

### Regular Processing
- Process feeds regularly for accurate trend data
- Use cache for frequent processing of the same feeds
- Disable cache when you need the absolute latest data

### Data Analysis
- Start with 30-day view for overview
- Use 7-day view for recent trends
- Use 24h/48h for real-time monitoring
- Compare related words to find patterns
- Click through to feed details for deeper insights

### Performance
- Clean old data periodically to maintain performance
- Run VACUUM on database after large deletions
- Monitor database file size
- Use caching for repeated analysis

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

- **v3.1** - Added 24h/48h time filters with hourly granularity and multi-word comparison feature
- **v3.0** - Added comprehensive analytics dashboard with interactive features
- **v2.0** - Integrated SQLite database for historical tracking
- **v1.0** - Initial release with basic word counting

## Roadmap

Future enhancements under consideration:
- Export data to CSV/JSON
- Email alerts for trending words
- Sentiment analysis integration
- Multi-language support
- User authentication system
- API key support for external access
- Mobile-responsive improvements
- Dark mode theme

---

**Project Repository**: https://github.com/ldhagen/DockerRSSWordCloud