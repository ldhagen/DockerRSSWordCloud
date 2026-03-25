# RSS Word Counter with Analytics

A comprehensive RSS feed analyzer that tracks word frequencies, generates interactive visualizations, and provides historical trend analysis. Built with PHP, SQLite, and Chart.js.

## Features

### Core Functionality
- **Multi-Feed Processing**: Analyze multiple RSS feeds simultaneously
- **Word Frequency Analysis**: Track most common words across feeds
- **Customizable Stopwords**: Filter out common words via web interface
- **Flexible Analysis Modes**: Choose between title-only or full content analysis
- **Caching System**: Optional caching for faster repeated analysis
- **Robust Fetching**: cURL-based retrieval with modern User-Agent strings to bypass scraper blocks.

### Analytics Dashboard
- **Interactive Charts**: Line charts, doughnut charts, and trend visualizations
- **Time Range Filters**: 24 hours, 48 hours, 7 days, 14 days, 30 days, 90 days
- **Hourly Granularity**: See hourly breakdowns for 24h and 48h time periods
- **Word Trend Analysis**: Track individual word frequency over time
- **Multi-Word Comparison**: Compare up to 5 words simultaneously on the same chart
- **Feed-Specific Filtering**: Analyze data for specific RSS feeds
- **Co-occurrence Detection**: Find related words that appear together
- **Advanced Article Search**: Search through processed articles by keyword, date range, and scope (Title, Description, or both)

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

- **v3.5** - Migrated to cURL for RSS fetching. Added modern browser User-Agent mimicking and "Fast Fail" logic to handle 403 Forbidden/401 Unauthorized errors efficiently without unnecessary retries.
- **v3.4** - Enhanced Article Search with date range filters, search scope (Title/Description), and improved results display.
- **v3.3** - Implemented duplicate article detection via unique URL filtering to prevent inflated word frequencies and provided `rebuild_analytics.php` utility.
- **v3.2** - Fixed database writing issues, including "readonly database" errors and SQL `GREATEST` function incompatibility with SQLite.
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

---

**Project Repository**: https://github.com/ldhagen/DockerRSSWordCloud
