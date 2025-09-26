# Enhanced Dockerfile with Full Automation
FROM php:8.2-apache

# Install system dependencies including cron
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    cron \
    sqlite3 \
    libsqlite3-dev \
    curl \
    && docker-php-ext-install \
    intl \
    zip \
    pdo \
    pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files first
COPY . /var/www/html/

# Create directories with proper permissions for www-data
RUN mkdir -p /var/www/html/data \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/cache \
    && mkdir -p /var/www/html/scripts \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html \
    && chmod -R 777 /var/www/html/data \
    && chmod -R 777 /var/www/html/logs \
    && chmod -R 777 /var/www/html/cache

# Set up cron job for automated collection
RUN echo "*/30 * * * * www-data /usr/local/bin/php /var/www/html/scripts/auto_collect.php >> /var/www/html/logs/cron.log 2>&1" > /etc/cron.d/rss-collector
RUN echo "0 6 * * * www-data /usr/local/bin/php /var/www/html/scripts/daily_analysis.php >> /var/www/html/logs/analysis.log 2>&1" >> /etc/cron.d/rss-collector
RUN echo "0 2 * * 0 www-data /usr/local/bin/php /var/www/html/scripts/weekly_cleanup.php >> /var/www/html/logs/cleanup.log 2>&1" >> /etc/cron.d/rss-collector

# Set cron permissions
RUN chmod 0644 /etc/cron.d/rss-collector
RUN crontab /etc/cron.d/rss-collector

# Create startup script
RUN echo '#!/bin/bash' > /usr/local/bin/start-services.sh \
    && echo 'service cron start' >> /usr/local/bin/start-services.sh \
    && echo 'apache2-foreground' >> /usr/local/bin/start-services.sh \
    && chmod +x /usr/local/bin/start-services.sh

# Expose port
EXPOSE 80

# Start both cron and Apache
CMD ["/usr/local/bin/start-services.sh"]