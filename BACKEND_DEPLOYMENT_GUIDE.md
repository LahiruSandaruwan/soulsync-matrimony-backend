# SoulSync Matrimony Backend - Production Deployment Guide

## Overview
This guide provides comprehensive instructions for deploying the SoulSync Matrimony Laravel backend to production environments.

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Server Setup](#server-setup)
3. [Database Configuration](#database-configuration)
4. [Application Deployment](#application-deployment)
5. [Web Server Configuration](#web-server-configuration)
6. [SSL/TLS Setup](#ssltls-setup)
7. [Environment Configuration](#environment-configuration)
8. [Queue & Caching Setup](#queue--caching-setup)
9. [Monitoring & Logging](#monitoring--logging)
10. [Performance Optimization](#performance-optimization)
11. [Security Hardening](#security-hardening)
12. [Backup Strategy](#backup-strategy)
13. [Maintenance & Updates](#maintenance--updates)

## Prerequisites

### Server Requirements
- **OS**: Ubuntu 20.04 LTS or CentOS 8+ (recommended)
- **PHP**: 8.1 or higher
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Memory**: Minimum 2GB RAM (4GB+ recommended)
- **Storage**: 20GB+ SSD storage
- **CPU**: 2+ cores

### Required Software
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y nginx mysql-server redis-server supervisor
sudo apt install -y php8.1-fpm php8.1-mysql php8.1-redis php8.1-mbstring
sudo apt install -y php8.1-xml php8.1-curl php8.1-gd php8.1-zip php8.1-bcmath

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js (for Laravel Mix if needed)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
```

## Server Setup

### 1. Create Application User
```bash
sudo adduser soulsync
sudo usermod -aG sudo soulsync
sudo su - soulsync
```

### 2. Directory Structure
```bash
mkdir -p /var/www/soulsync
mkdir -p /var/www/soulsync/backend
mkdir -p /var/www/soulsync/storage
mkdir -p /var/www/soulsync/logs
```

### 3. Set Permissions
```bash
sudo chown -R soulsync:www-data /var/www/soulsync
sudo chmod -R 755 /var/www/soulsync
```

## Database Configuration

### MySQL Setup
```sql
-- Create database and user
CREATE DATABASE soulsync_matrimony CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'soulsync'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON soulsync_matrimony.* TO 'soulsync'@'localhost';
FLUSH PRIVILEGES;

-- Configure MySQL for production
-- Add to /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
innodb_buffer_pool_size = 1G
max_connections = 200
query_cache_size = 64M
tmp_table_size = 128M
max_heap_table_size = 128M
```

### Redis Setup
```bash
# Configure Redis
sudo nano /etc/redis/redis.conf

# Set password
requirepass your_redis_password_here

# Enable persistence
save 900 1
save 300 10
save 60 10000

# Restart Redis
sudo systemctl restart redis-server
sudo systemctl enable redis-server
```

## Application Deployment

### 1. Clone Repository
```bash
cd /var/www/soulsync
git clone https://github.com/your-repo/soulsync-matrimony-backend.git backend
cd backend
```

### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run production
```

### 3. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate

# Edit .env file
nano .env
```

### 4. Database Migration
```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder
```

### 5. Storage & Cache
```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Web Server Configuration

### Nginx Configuration
```nginx
# /etc/nginx/sites-available/soulsync-api
server {
    listen 80;
    server_name api.soulsync.com;
    root /var/www/soulsync/backend/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 10240;
    gzip_proxied expired no-cache no-store private must-revalidate;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/json;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req zone=api burst=20 nodelay;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

    # Cache static assets
    location ~* \.(css|js|jpeg|jpg|png|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # File upload size
    client_max_body_size 10M;
}
```

### Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/soulsync-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## SSL/TLS Setup

### Using Certbot (Let's Encrypt)
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d api.soulsync.com
sudo systemctl enable certbot.timer
```

### Manual SSL Certificate
```nginx
# Add to Nginx config
listen 443 ssl http2;
ssl_certificate /path/to/certificate.crt;
ssl_certificate_key /path/to/private.key;
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;
ssl_session_cache shared:SSL:10m;
```

## Environment Configuration

### Production .env Settings
```env
APP_NAME="SoulSync Matrimony API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.soulsync.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soulsync_matrimony
DB_USERNAME=soulsync
DB_PASSWORD=your_secure_password

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_mailgun_username
MAIL_PASSWORD=your_mailgun_password
MAIL_ENCRYPTION=tls

# Payment Gateways
STRIPE_PUBLIC_KEY=pk_live_your_stripe_key
STRIPE_SECRET_KEY=sk_live_your_stripe_key
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_secret

# Push Notifications
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_key
PUSHER_APP_SECRET=your_pusher_secret
PUSHER_APP_CLUSTER=mt1

# File Storage (AWS S3)
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=soulsync-storage

# Frontend URLs
FRONTEND_URL=https://app.soulsync.com
SANCTUM_STATEFUL_DOMAINS=app.soulsync.com
```

## Queue & Caching Setup

### Queue Workers with Supervisor
```ini
# /etc/supervisor/conf.d/soulsync-worker.conf
[program:soulsync-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/soulsync/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=soulsync
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/soulsync/logs/worker.log
stopwaitsecs=3600
```

### Start Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start soulsync-worker:*
```

### Cron Jobs
```bash
# Add to crontab: sudo crontab -e
* * * * * cd /var/www/soulsync/backend && php artisan schedule:run >> /dev/null 2>&1

# Laravel Horizon (alternative to Supervisor)
# * * * * * cd /var/www/soulsync/backend && php artisan horizon >> /dev/null 2>&1
```

## Monitoring & Logging

### Log Rotation
```bash
# /etc/logrotate.d/soulsync
/var/www/soulsync/backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    copytruncate
}
```

### System Monitoring
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs

# Monitor Laravel logs
tail -f /var/www/soulsync/backend/storage/logs/laravel.log

# Monitor Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Monitor system resources
htop
```

### Application Monitoring (Recommended)
- **New Relic** or **DataDog** for application performance
- **Sentry** for error tracking
- **Laravel Telescope** for development debugging

## Performance Optimization

### PHP-FPM Configuration
```ini
# /etc/php/8.1/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

# PHP.ini optimizations
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
```

### Database Optimization
```sql
-- Add indexes for frequently queried columns
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_country ON users(country_code);
CREATE INDEX idx_matches_user_action ON user_matches(user_id, user_action);
CREATE INDEX idx_messages_conversation ON messages(conversation_id, created_at);

-- Optimize MySQL configuration
SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB
SET GLOBAL query_cache_size = 67108864; -- 64MB
```

### Caching Strategy
```bash
# Redis configuration for Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cache user sessions, preferences, and frequently accessed data
```

## Security Hardening

### Firewall Setup
```bash
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw allow 3306 # MySQL (restrict to application server only)
sudo ufw allow 6379 # Redis (restrict to localhost)
sudo ufw enable
```

### PHP Security
```ini
# php.ini security settings
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
display_errors = Off
log_errors = On
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_only_cookies = 1
```

### Database Security
```sql
-- Remove test databases and users
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
FLUSH PRIVILEGES;
```

### Application Security
```bash
# Set proper file permissions
sudo chown -R soulsync:www-data /var/www/soulsync/backend
sudo chmod -R 755 /var/www/soulsync/backend
sudo chmod -R 775 /var/www/soulsync/backend/storage
sudo chmod -R 775 /var/www/soulsync/backend/bootstrap/cache

# Secure sensitive files
sudo chmod 600 /var/www/soulsync/backend/.env
```

## Backup Strategy

### Database Backup Script
```bash
#!/bin/bash
# /usr/local/bin/backup-soulsync-db.sh

BACKUP_DIR="/var/backups/soulsync"
DB_NAME="soulsync_matrimony"
DB_USER="soulsync"
DB_PASS="your_password"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Create database backup
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Keep only last 7 days of backups
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete

# Upload to S3 (optional)
aws s3 cp $BACKUP_DIR/db_backup_$DATE.sql.gz s3://your-backup-bucket/database/
```

### File Backup
```bash
#!/bin/bash
# Backup user uploads and important files
tar -czf /var/backups/soulsync/files_backup_$(date +%Y%m%d).tar.gz \
    /var/www/soulsync/backend/storage/app/public \
    /var/www/soulsync/backend/.env

# Upload to S3
aws s3 sync /var/backups/soulsync/ s3://your-backup-bucket/files/
```

### Automated Backups
```bash
# Add to crontab
0 2 * * * /usr/local/bin/backup-soulsync-db.sh
0 3 * * 0 /usr/local/bin/backup-soulsync-files.sh
```

## Maintenance & Updates

### Zero-Downtime Deployment
```bash
#!/bin/bash
# deployment-script.sh

cd /var/www/soulsync/backend

# Pull latest code
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo supervisorctl restart soulsync-worker:*
sudo systemctl reload php8.1-fpm
sudo systemctl reload nginx

echo "Deployment completed successfully!"
```

### Health Checks
```bash
# Create health check endpoint: /api/v1/health
# Monitor critical services
curl -f https://api.soulsync.com/api/v1/health || exit 1

# Check database connectivity
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB Connected';"

# Check Redis connectivity
redis-cli ping
```

### Monitoring Scripts
```bash
#!/bin/bash
# monitor-services.sh

# Check if services are running
services=("nginx" "mysql" "redis-server" "php8.1-fpm")

for service in "${services[@]}"; do
    if ! systemctl is-active --quiet $service; then
        echo "$service is not running!"
        systemctl restart $service
    fi
done

# Check disk space
if [ $(df / | tail -1 | awk '{print $5}' | sed 's/%//') -gt 85 ]; then
    echo "Disk space is running low!"
fi

# Check memory usage
if [ $(free | awk '/^Mem:/{printf("%.0f", $3/$2*100)}') -gt 90 ]; then
    echo "Memory usage is high!"
fi
```

## Troubleshooting

### Common Issues

1. **500 Internal Server Error**
   - Check Laravel logs: `tail -f storage/logs/laravel.log`
   - Check Nginx error logs: `tail -f /var/log/nginx/error.log`
   - Verify file permissions

2. **Database Connection Issues**
   - Verify database credentials in `.env`
   - Check MySQL service: `systemctl status mysql`
   - Test connection: `mysql -u soulsync -p soulsync_matrimony`

3. **Queue Jobs Not Processing**
   - Check Supervisor status: `supervisorctl status`
   - Restart workers: `supervisorctl restart soulsync-worker:*`
   - Check Redis connection

4. **High Memory Usage**
   - Optimize PHP-FPM configuration
   - Check for memory leaks in application code
   - Increase server memory if needed

### Performance Issues
- Enable query logging to identify slow queries
- Use Laravel Debugbar for development debugging
- Monitor with New Relic or similar APM tools
- Optimize database indexes

## Conclusion

This deployment guide provides a comprehensive setup for running SoulSync Matrimony backend in production. Regular monitoring, maintenance, and security updates are essential for optimal performance and security.

For additional support or customization, refer to the Laravel documentation and consider consulting with DevOps professionals for complex deployments.
