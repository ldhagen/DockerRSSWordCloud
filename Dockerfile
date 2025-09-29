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

# Copy application files
COPY . /var/www/html/

# Fix ownership of all files (handles files with wrong ownership from git)
RUN chown -R www-data:www-data /var/www/html

# Create directories with proper permissions for www-data
RUN mkdir -p /var/www/html/data \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/cache \
    && mkdir -p /var/www/html/scripts \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/logs /var/www/html/cache /var/www/html/scripts \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/data \
    && chmod -R 775 /var/www/html/logs \
    && chmod -R 775 /var/www/html/cache

# Copy cron file and set permissions
COPY cron-rss-collector /etc/cron.d/rss-collector
RUN chmod 0644 /etc/cron.d/rss-collector

# Create startup script to run both cron and Apache
RUN printf '#!/bin/bash\nservice cron start\napache2-foreground\n' > /usr/local/bin/start-services.sh \
    && chmod +x /usr/local/bin/start-services.sh

# Expose port
EXPOSE 80

# Start both cron and Apache
CMD ["/usr/local/bin/start-services.sh"]