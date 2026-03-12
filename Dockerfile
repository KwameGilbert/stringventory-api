# Use an official PHP Apache image with PHP 8.1
FROM php:8.4-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    cron \
  && docker-php-ext-install pdo pdo_mysql zip \
  && apt-get clean

# Enable Apache mod_rewrite for URL routing
RUN a2enmod rewrite

# Copy Composer from the official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Set environment variable to allow Composer plugins to run as superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Set the working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./ 

# Install composer dependencies (if composer.json exists)
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Copy application files (public, src, cron)
COPY public/ ./public/
COPY src/ ./src/
COPY ussd/ ./ussd/
COPY cron.php ./cron.php
COPY .htaccess ./.htaccess

# Install composer dependencies for ussd
WORKDIR /var/www/html/ussd
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi
RUN composer dump-autoload --optimize
WORKDIR /var/www/html

# Create persistent storage directory for all logs
RUN mkdir -p /var/www/html/storage/logs/cron \
             /var/www/html/storage/queue \
  && chown -R www-data:www-data /var/www/html/storage \
  && chmod -R 775 /var/www/html/storage

# Create all upload subdirectories with correct ownership
RUN mkdir -p /var/www/html/public/uploads/images/events \
             /var/www/html/public/uploads/images/nominees \
             /var/www/html/public/uploads/banners/awards \
             /var/www/html/public/uploads/avatars \
             /var/www/html/public/uploads/documents \
             /var/www/html/public/uploads/videos \
             /var/www/html/public/uploads/tickets \
             /var/www/html/public/uploads/nominees \
  && chown -R www-data:www-data /var/www/html/public/uploads \
  && chmod -R 775 /var/www/html/public/uploads

# Ensure permissions to the / root and public directories
RUN chown -R www-data:www-data /var/www/html/public && \
    chmod -R 775 /var/www/html/public && \
    chown -R www-data:www-data /var/www/html/src && \
    chmod -R 775 /var/www/html/src && \
    chown -R www-data:www-data /var/www/html/ussd && \
    chmod -R 775 /var/www/html/ussd && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html && \
    chown -R www-data:www-data /var/www/html/.htaccess && \
    chmod -R 775 /var/www/html/.htaccess

# Run composer dump-autoload after everything is set up
RUN composer dump-autoload --optimize

# Ensure PHP populates $_ENV from container environment variables
# This allows Docker-injected env vars to be read directly without a .env file
RUN echo 'variables_order = "EGPCS"' > /usr/local/etc/php/conf.d/env-vars.ini

# Set up crontab for scheduled tasks
RUN echo "0 * * * * root php /var/www/html/cron.php expenses >> /var/www/html/storage/logs/cron/cron.log 2>&1" > /etc/cron.d/stringventory \
  && echo "* * * * * root php /var/www/html/cron.php notifications >> /var/www/html/storage/logs/cron/cron.log 2>&1" >> /etc/cron.d/stringventory \
  && chmod 0644 /etc/cron.d/stringventory

# Declare persistent volumes
VOLUME ["/var/www/html/storage"]
VOLUME ["/var/www/html/public/uploads"]

# Startup script: fix upload permissions at runtime (volumes reset ownership), then start cron + Apache
RUN printf '#!/bin/sh\nchown -R www-data:www-data /var/www/html/public/uploads\nchmod -R 775 /var/www/html/public/uploads\nchown -R www-data:www-data /var/www/html/storage\nchmod -R 775 /var/www/html/storage\ncron\napache2-foreground\n' > /start.sh && chmod +x /start.sh

# Expose port 80 for Apache
EXPOSE 80

# Start with permission fix + cron + Apache
CMD ["/start.sh"]