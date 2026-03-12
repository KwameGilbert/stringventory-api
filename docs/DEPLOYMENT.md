# Deployment Guide

> Comprehensive guide for deploying Eventic API to production environments

---

## Table of Contents

- [Pre-Deployment Checklist](#pre-deployment-checklist)
- [Server Requirements](#server-requirements)
- [Deployment Methods](#deployment-methods)
- [Web Server Configuration](#web-server-configuration)
- [Environment Configuration](#environment-configuration)
- [Database Setup](#database-setup)
- [Security Hardening](#security-hardening)
- [Monitoring & Maintenance](#monitoring--maintenance)
- [Troubleshooting](#troubleshooting)

---

## Pre-Deployment Checklist

### ✅ Code Preparation

- [ ] All tests passing
- [ ] Code reviewed and merged to main branch
- [ ] Version tagged in Git
- [ ] Dependencies up to date (`composer update`)
- [ ] Environment-specific configs prepared
- [ ] Database migrations tested
- [ ] Backup procedures documented

### ✅ Security Check

- [ ] Generated strong JWT_SECRET (256-bit minimum)
- [ ] Removed development dependencies
- [ ] Disabled debug mode (`APP_ENV=production`)
- [ ] CORS configured for production domains
- [ ] SSL/TLS certificates acquired
- [ ] Database credentials secured
- [ ] File permissions set correctly
- [ ] Error logging configured

### ✅ Infrastructure

- [ ] Production server provisioned
- [ ] Database server configured
- [ ] Email service configured (SMTP)
- [ ] Backup systemin place
- [ ] Monitoring tools set up
- [ ] Load balancer configured (if applicable)

---

## Server Requirements

### Minimum Specifications

**Application Server:**
- **CPU:** 2 cores
- **RAM:** 2 GB
- **Storage:** 20 GB SSD
- **OS:** Ubuntu 20.04 LTS or higher / CentOS 8+

**Database Server** (if separate):
- **CPU:** 2 cores
- **RAM:** 4 GB
- **Storage:** 50 GB SSD (depends on data volume)

### Software Requirements

```bash
# PHP
PHP >= 8.1
php-fpm
php-pdo
php-mysql (or php-pgsql)
php-mbstring
php-xml
php-json
php-sodium
php-curl

# Web Server
Apache 2.4+ or Nginx 1.18+

# Database
MySQL 5.7+ or PostgreSQL 12+

# Process Manager
Supervisor (for background jobs)
```

---

## Deployment Methods

### Method 1: Manual Deployment (Simple)

#### Step 1: Prepare Server

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.1
sudo apt install php8.1 php8.1-fpm php8.1-mysql php8.1-mbstring \
    php8.1-xml php8.1-curl php8.1-zip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx
sudo apt install nginx
```

#### Step 2: Clone Repository

```bash
# Create project directory
sudo mkdir -p /var/www/eventic
sudo chown $USER:$USER /var/www/eventic

# Clone repository
cd /var/www
git clone https://github.com/yourusername/eventic.git
cd eventic

# Install dependencies (production only)
composer install --no-dev --optimize-autoloader
```

#### Step 3: Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit configuration
nano .env
```

Set production values:
```env
APP_ENV=production
APP_NAME=Eventic
APP_VERSION=1.0.0
BASE_PATH=/

# Database (Production)
ENVIRONMENT=production
PROD_DB_DRIVER=mysql
PROD_DB_HOST=localhost
PROD_DB_PORT=3306
PROD_DB_DATABASE=eventic_prod
PROD_DB_USERNAME=eventic_user
PROD_DB_PASSWORD=STRONG_PASSWORD_HERE
PROD_DB_SSL=true
PROD_DB_CHARSET=utf8mb4

# JWT Configuration
JWT_SECRET=GENERATE_256_BIT_SECRET_HERE
JWT_ALGORITHM=HS256
JWT_EXPIRE=3600
REFRESH_TOKEN_EXPIRE=604800
REFRESH_TOKEN_ALGO=sha256
JWT_ISSUER=eventic-api

# Email (Production SMTP)
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=YOUR_SENDGRID_API_KEY
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=Eventic

# CORS - Production Domains Only
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://app.yourdomain.com

# Rate Limiting
RATE_LIMIT=10
RATE_LIMIT_WINDOW=1

# Logging
LOG_LEVEL=ERROR
```

**Generate JWT Secret:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```

#### Step 4: Set Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/eventic
sudo chown -R $USER:www-data /var/www/eventic

# Set directory permissions
find /var/www/eventic -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/eventic -type f -exec chmod 644 {} \;

# Make logs writable
chmod -R 775 /var/www/eventic/src/logs
chown -R www-data:www-data /var/www/eventic/src/logs
```

#### Step 5: Run Migrations

```bash
cd /var/www/eventic
./vendor/bin/phinx migrate -e production
```

---

### Method 2: Docker Deployment

#### Dockerfile

Create `Dockerfile` in project root:

```dockerfile
FROM php:8.1-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libsodium-dev

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sodium

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . /var/www

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www

EXPOSE 9000
CMD ["php-fpm"]
```

#### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: eventic-app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./:/var/www
    networks:
      - eventic-network

  nginx:
    image: nginx:alpine
    container_name: eventic-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www
      - ./docker/nginx:/etc/nginx/conf.d
    networks:
      - eventic-network

  db:
    image: mysql:8.0
    container_name: eventic-db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: eventic
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_USER: eventic_user
      MYSQL_PASSWORD: user_password
    volumes:
      - dbdata:/var/lib/mysql
    networks:
      - eventic-network

networks:
  eventic-network:
    driver: bridge

volumes:
  dbdata:
    driver: local
```

#### Deploy with Docker

```bash
# Build and start containers
docker-compose up -d

# Run migrations
docker-compose exec app ./vendor/bin/phinx migrate

# View logs
docker-compose logs -f app
```

---

## Web Server Configuration

### Nginx Configuration

Create `/etc/nginx/sites-available/eventic`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.yourdomain.com;
    
    root /var/www/eventic/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Logging
    access_log /var/log/nginx/eventic-access.log;
    error_log /var/log/nginx/eventic-error.log;
    
    # Max upload size
    client_max_body_size 10M;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
    
    # Deny access to sensitive files
    location ~ /(\.env|composer\.json|composer\.lock|phinx\.php) {
        deny all;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/eventic /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Apache Configuration

Create `/etc/apache2/sites-available/eventic.conf`:

```apache
<VirtualHost *:80>
    ServerName api.yourdomain.com
    Redirect permanent / https://api.yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/eventic/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/api.yourdomain.com/privkey.pem
    
    <Directory /var/www/eventic/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/eventic-error.log
    CustomLog ${APACHE_LOG_DIR}/eventic-access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite eventic
sudo a2enmod ssl rewrite headers
sudo systemctl reload apache2
```

---

## SSL/TLS Configuration

### Using Let's Encrypt (Free)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d api.yourdomain.com

# Auto-renewal (already set up)
sudo certbot renew --dry-run
```

---

## Database Setup

### MySQL

```bash
# Login to MySQL
sudo mysql -u root -p

# Create database
CREATE DATABASE eventic_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user
CREATE USER 'eventic_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';

# Grant privileges
GRANT ALL PRIVILEGES ON eventic_prod.* TO 'eventic_user'@'localhost';
FLUSH PRIVILEGES;

# Exit
EXIT;
```

### Run Migrations

```bash
cd /var/www/eventic
./vendor/bin/phinx migrate -e production
```

---

## Security Hardening

### 1. File Permissions

```bash
# Restrict .env file
chmod 600 /var/www/eventic/.env
chown www-data:www-data /var/www/eventic/.env

# Logs directory
chmod -R 750 /var/www/eventic/src/logs
chown -R www-data:www-data /var/www/eventic/src/logs
```

### 2. Hide PHP Version

```bash
# Edit php.ini
sudo nano /etc/php/8.1/fpm/php.ini

# Set
expose_php = Off
```

### 3. Rate Limiting (Nginx)

```nginx
# In nginx.conf http block
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;

# In server block
location /v1/ {
    limit_req zone=api_limit burst=20 nodelay;
    # ... other config
}
```

### 4. Firewall (UFW)

```bash
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable
```

### 5. Fail2Ban (Brute Force Protection)

```bash
sudo apt install fail2ban

# Create jail for Nginx
sudo nano /etc/fail2ban/jail.local
```

```ini
[nginx-limit-req]
enabled = true
filter = nginx-limit-req
logpath = /var/log/nginx/*error.log
maxretry = 5
findtime = 600
bantime = 3600
```

---

## Monitoring & Maintenance

### Log Rotation

Create `/etc/logrotate.d/eventic`:

```
/var/www/eventic/src/logs/*/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### Health Monitoring

Set up monitoring for:
- `/health` endpoint (should return 200)
- Database connectivity
- Disk space
- Memory usage
- Error rates

**Example with cron:**
```bash
# /etc/cron.d/eventic-health
*/5 * * * * www-data curl -f http://localhost/health || echo "API Down!" | mail -s "Eventic API Alert" admin@yourdomain.com
```

### Backups

#### Database Backup Script

Create `/usr/local/bin/backup-eventic-db.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/eventic"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

mysqldump -u eventic_user -p'PASSWORD' eventic_prod | gzip > $BACKUP_DIR/eventic_$DATE.sql.gz

# Keep only last 30 days
find $BACKUP_DIR -name "eventic_*.sql.gz" -mtime +30 -delete
```

Schedule with cron:
```bash
0 2 * * * /usr/local/bin/backup-eventic-db.sh
```

---

## Troubleshooting

### Common Issues

#### 1. 500 Internal Server Error

**Symptoms:** API returns 500 status

**Solutions:**
```bash
# Check PHP-FPM logs
sudo tail -f /var/log/php8.1-fpm.log

# Check Nginx error log
sudo tail -f /var/log/nginx/eventic-error.log

# Check application logs
tail -f /var/www/eventic/src/logs/error/error.log

# Verify file permissions
ls -la /var/www/eventic/

# Check PHP errors are logged
php -i | grep error_log
```

#### 2. Database Connection Failed

**Solutions:**
```bash
# Test database connection
mysql -h localhost -u eventic_user -p eventic_prod

# Check .env credentials
cat /var/www/eventic/.env | grep DB_

# Verify Eloquent bootstrap
php -r "require 'vendor/autoload.php'; \App\Config\EloquentBootstrap::boot();"
```

#### 3. JWT Token Invalid

**Solutions:**
```bash
# Verify JWT_SECRET is set
cat .env | grep JWT_SECRET

# Check token expiry
# Access tokens expire in 1 hour by default

# Verify algorithm
cat .env | grep JWT_ALGORITHM
```

#### 4. Rate Limiting Too Aggressive

**Solutions:**
```bash
# Check rate limit settings
cat .env | grep RATE_LIMIT

# Clear rate limit files
rm -rf /tmp/rate_limits/*

# Adjust in .env
RATE_LIMIT=20
RATE_LIMIT_WINDOW=1
```

---

## Performance Optimization

### PHP OPcache

```bash
# Edit php.ini
sudo nano /etc/php/8.1/fpm/php.ini
```

```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### Database Query Caching

Enable query cache in MySQL:
```sql
SET GLOBAL query_cache_size = 67108864;
SET GLOBAL query_cache_type = ON;
```

---

## Deployment Checklist

### Pre-Deploy

- [ ] Code tested in staging
- [ ] Database backup created
- [ ] Migration scripts reviewed
- [ ] Rollback plan prepared

### During Deploy

- [ ] Put site in maintenance mode (optional)
- [ ] Pull latest code
- [ ] Run `composer install --no-dev`
- [ ] Run migrations
- [ ] Clear caches
- [ ] Restart PHP-FPM
- [ ] Verify deployment

### Post-Deploy

- [ ] Smoke tests passed
- [ ] Monitoring active
- [ ] Logs being captured
- [ ] Team notified

---

## Rollback Procedure

If deployment fails:

```bash
# 1. Revert code
git reset --hard PREVIOUS_TAG

# 2. Rollback migrations (if needed)
./vendor/bin/phinx rollback -e production -t PREVIOUS_VERSION

# 3. Reinstall dependencies
composer install --no-dev

# 4. Restart services
sudo systemctl restart php8.1-fpm nginx

# 5. Verify
curl https://api.yourdomain.com/health
```

---

Your Eventic API is now deployed and production-ready! 🚀
