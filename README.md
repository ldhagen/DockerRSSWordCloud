# RSS Word Counter - Enhanced Analytics Platform

A comprehensive RSS feed analysis system that automatically monitors news headlines, tracks word trends, and provides real-time insights into media coverage patterns.

## Features

### Core Functionality
- **Headlines-Only Analysis**: Focused word counting from RSS feed titles for cleaner trend detection
- **Multi-Feed Support**: Process multiple RSS feeds simultaneously with intelligent cycling
- **Real-Time Processing**: Live word frequency analysis with interactive results
- **Automated Collection**: Background processing every 30 minutes with smart feed rotation

### Analytics & Insights
- **Trend Analysis**: Historical word frequency tracking with change detection
- **Interactive Dashboard**: Charts and visualizations powered by Chart.js
- **Word Cloud Visualization**: Dynamic D3.js-powered word clouds with drill-down capabilities
- **Daily Reports**: Automated analysis reports with trending words and topic shifts
- **Change Alerts**: Automatic detection of significant word spikes and emerging topics

### Data Management
- **SQLite Database**: Persistent storage for historical analysis and trending
- **Smart Caching**: 1-hour RSS cache to reduce server load
- **Automated Cleanup**: Weekly maintenance and data retention management
- **Export Capabilities**: JSON and text export for feeds and stopwords

## Quick Start

### Docker Deployment (Recommended)

```bash
# Clone the repository
git clone <your-repo-url>
cd rss-word-counter

# Start the application
docker-compose up -d

# Access the application
open http://localhost:8080
```

### Manual Installation

```bash
# Requirements: PHP 8.2+, SQLite, Apache/Nginx

# Install dependencies
composer install

# Set permissions
chmod 755 data/ logs/ cache/
chmod +x scripts/*.php

# Configure web server to point to project root
# Ensure mod_rewrite is enabled for Apache
```

## Configuration

### Environment Variables (Docker)

```yaml
# Basic Configuration
PHP_TIMEZONE: "America/Chicago"
ANALYZER_ENV: "docker"

# Automation Settings
AUTO_COLLECT_ENABLED: true
MAX_FEEDS_PER_RUN: 5          # Feeds per collection cycle
COLLECTION_TIMEOUT: 30        # Timeout per feed (seconds)
RETENTION_DAYS: 90           # Data retention period

# Analysis Settings
DAILY_ANALYSIS_ENABLED: true
CHANGE_DETECTION_THRESHOLD: 50
ALERT_GENERATION: true

# Performance Settings
CACHE_DURATION: 3600         # RSS cache duration (seconds)
DB_OPTIMIZE_FREQUENCY: daily
LOG_LEVEL: INFO
```

### Feed Configuration

Add RSS feeds via the web interface or by editing `data/feeds.json`:

```json
{
  "feeds": [
    {
      "url": "https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml",
      "name": "New York Times - Home Page"
    },
    {
      "url": "https://feeds.bbci.co.uk/news/rss.xml",
      "name": "BBC News"
    }
  ]
}
```

## Architecture

### File Structure

```
rss-word-counter/
├── index.php              # Main application interface
├── config.php             # Core configuration and functions
├── analytics.php          # Analytics dashboard
├── wordcloud.php          # Word cloud visualization
├── feed_manager.php       # Bulk feed management
├── stopword_manager.php   # Stopword management
├── debug.php             # System diagnostics
├── docker-compose.yml    # Docker configuration
├── Dockerfile           # Container definition
├── scripts/             # Automation scripts
│   ├── auto_collect.php    # Automated feed collection
│   ├── daily_analysis.php  # Daily trend analysis
│   └── weekly_cleanup.php  # Maintenance and cleanup
├── data/               # Data storage
│   ├── feeds.json         # Feed configurations
│   ├── stopwords.json     # Stopword list
│   └── analytics.db       # SQLite database
├── logs/              # Application logs
├── cache/             # RSS feed cache
└── css/js/           # Frontend assets
```

### Database Schema

```sql
-- Collections: Feed processing history
CREATE TABLE collections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    feed_name TEXT NOT NULL,
    total_articles INTEGER DEFAULT 0,
    total_words INTEGER DEFAULT 0
);

-- Word History: Historical word frequency data
CREATE TABLE word_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    collection_id INTEGER,
    word TEXT NOT NULL,
    count INTEGER NOT NULL,
    feed_name TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Articles: Article metadata and content
CREATE TABLE articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    collection_id INTEGER,
    title TEXT NOT NULL,
    link TEXT,
    description TEXT,
    feed_name TEXT NOT NULL,
    pub_date TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## Automation Schedule

The system runs three automated processes:

- **Feed Collection**: Every 30 minutes
  - Processes 5 feeds per cycle in rotation
  - Stores articles and word frequencies
  - Updates trending data

- **Daily Analysis**: Every day at 6:00 AM
  - Generates trend reports
  - Detects emerging and declining words
  - Creates change alerts
  - Updates trend coefficients

- **Weekly Cleanup**: Every Sunday at 2:00 AM
  - Removes data older than retention period
  - Optimizes database performance
  - Cleans cache and log files

## API Endpoints

### Analytics Data (JSON)

```bash
# Daily statistics
GET /analytics.php?ajax=daily_stats&days=30

# Feed activity
GET /analytics.php?ajax=feed_activity&days=30

# Word trends
GET /analytics.php?ajax=word_trends&word=trump&days=30
```

### Export Functions

```bash
# Export feeds
GET /feed_manager.php?export=1

# Export stopwords (JSON)
GET /stopword_manager.php?export=1&format=json

# Export stopwords (text)
GET /stopword_manager.php?export=1&format=txt
```

## Performance Optimization

### Caching Strategy
- RSS feeds cached for 1 hour
- Database queries optimized with indexes
- Static assets served with appropriate headers

### Resource Management
- Memory-efficient word processing
- Chunked feed processing to prevent timeouts
- Automatic cleanup of old data and cache files

### Scaling Considerations
- SQLite suitable for moderate traffic
- Consider PostgreSQL/MySQL for high-volume deployments
- Feed processing can be distributed across multiple containers

## Monitoring & Maintenance

### Log Files
```bash
# Application logs
docker-compose logs --follow

# Collection logs
tail -f logs/analyzer.log

# Cron execution logs
tail -f logs/cron.log
```

### Health Checks
```bash
# Container health
docker-compose ps

# Database status
sqlite3 data/analytics.db ".tables"

# Processing statistics
curl http://localhost:8080/debug.php
```

### Troubleshooting

**Common Issues:**

1. **RSS feeds failing**: Check feed URLs and network connectivity
2. **No word counts**: Verify `TITLES_ONLY_ANALYSIS` setting and regex patterns
3. **Database errors**: Check file permissions on data directory
4. **Automation not running**: Verify cron service in container

**Debug Mode:**
Enable debug logging by setting `LOG_LEVEL=DEBUG` in docker-compose.yml

## Contributing

### Development Setup
```bash
# Clone and setup
git clone <repo-url>
cd rss-word-counter

# Development with live reload
docker-compose -f docker-compose.dev.yml up

# Run tests
php -f scripts/test_feeds.php
```

### Code Standards
- PSR-12 coding standards for PHP
- ESLint configuration for JavaScript
- SQLite for development, PostgreSQL for production

## Security Considerations

- Input sanitization for all user data
- SQL injection prevention with prepared statements
- XSS protection with proper output encoding
- Rate limiting on RSS feed requests
- Secure session handling

## License

MIT License - see LICENSE file for details

## Support

- **Documentation**: Check the `/debug.php` endpoint for system diagnostics
- **Issues**: Create GitHub issues for bugs and feature requests
- **Logs**: Monitor `logs/analyzer.log` for application events