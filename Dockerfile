# Use an official PHP Apache image with PHP 8.1
FROM php:8.4-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
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

# Copy application files (public and src)
COPY public/ ./public/
COPY src/ ./src/
COPY .htaccess ./.htaccess

# Create a .env file from .env.example if it exists
COPY .env.example ./.env.example
RUN if [ -f .env.example ]; then cp .env.example .env; fi

# Ensure permissions to the / root and public directories
RUN chown -R www-data:www-data /var/www/html/public && \
    chmod -R 775 /var/www/html/public && \
    chown -R www-data:www-data /var/www/html/src && \
    chmod -R 775 /var/www/html/src && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html && \
    chown -R www-data:www-data /var/www/html/.htaccess && \
    chmod -R 775 /var/www/html/.htaccess
    
# Ensure .env file is readable
RUN if [ -f .env ]; then chmod 644 /var/www/html/.env && chown www-data:www-data /var/www/html/.env; fi && \
  chmod 755 /var/www/html

# Run composer dump-autoload after everything is set up
RUN composer dump-autoload --optimize
  
# Expose port 80 for Apache
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]