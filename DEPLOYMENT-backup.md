# Deployment Guide - Kudos Orchestrator

This guide covers production deployment of the Kudos Orchestrator system.

## 🎯 Production Architecture

### Recommended Infrastructure

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Load Balancer │────│  Web Servers    │────│   Database      │
│   (nginx/HAProxy)│    │   (PHP-FPM)     │    │  (PostgreSQL)   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                              │
                       ┌─────────────────┐
                       │  Queue Workers  │
                       │    (Redis)      │
                       └─────────────────┘
```

### Server Requirements

#### Web Server
- **CPU**: 4+ cores
- **RAM**: 8GB+ 
- **Storage**: 100GB+ SSD
- **OS**: Ubuntu 22.04 LTS or CentOS 8+

#### Database Server
- **CPU**: 4+ cores
- **RAM**: 16GB+
- **Storage**: 500GB+ SSD with backups
- **OS**: Ubuntu 22.04 LTS

#### Queue Server
- **CPU**: 2+ cores
- **RAM**: 4GB+
- **Storage**: 50GB+ SSD
- **OS**: Ubuntu 22.04 LTS

## 🐧 Ubuntu/Debian Deployment

### 1. System Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y software-properties-common curl wget gnupg2

# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
```

### 2. Install Dependencies

```bash
# Install PHP 8.2 and extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-pgsql php8.2-redis \
                    php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip \
                    php8.2-bcmath php8.2-intl php8.2-gd

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Git
sudo apt install -y git
```

### 3. Install PostgreSQL

```bash
# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Configure PostgreSQL
sudo -u postgres psql -c "CREATE DATABASE kudos_orchestrator;"
sudo -u postgres psql -c "CREATE USER kudos WITH PASSWORD 'secure_password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE kudos_orchestrator TO kudos;"

# Configure PostgreSQL settings
sudo nano /etc/postgresql/14/main/postgresql.conf
# Adjust settings for production:
# shared_buffers = 256MB
# effective_cache_size = 1GB
# work_mem = 4MB
# maintenance_work_mem = 64MB

sudo systemctl restart postgresql
```

### 4. Install Redis

```bash
# Install Redis
sudo apt install -y redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
# Set: maxmemory 512mb
# Set: maxmemory-policy allkeys-lru

sudo systemctl restart redis-server
sudo systemctl enable redis-server
```

### 5. Install Nginx

```bash
# Install Nginx
sudo apt install -y nginx

# Create site configuration
sudo nano /etc/nginx/sites-available/kudos-orchestrator
```

**Nginx Configuration:**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /usr/share/ngnix/html/kudos-orchestrator/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/kudos-orchestrator /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## 🚀 Application Deployment

### 1. Deploy Application

```bash
# Create application directory
sudo mkdir -p /usr/share/ngnix/html/kudos-orchestrator
sudo chown $USER:www-data /usr/share/ngnix/html/kudos-orchestrator

# Clone repository
cd /var/www
git clone https://github.com/your-org/kudos-orchestrator.git
cd kudos-orchestrator

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Set permissions
sudo chown -R www-data:www-data /usr/share/ngnix/html/kudos-orchestrator
sudo chmod -R 755 /usr/share/ngnix/html/kudos-orchestrator
sudo chmod -R 775 /usr/share/ngnix/html/kudos-orchestrator/storage
sudo chmod -R 775 /usr/share/ngnix/html/kudos-orchestrator/bootstrap/cache
```

### 2. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit environment configuration
nano .env
```

**Production Environment Variables:**

```bash
APP_NAME="Kudos Orchestrator"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kudos_orchestrator
DB_USERNAME=kudos
DB_PASSWORD=secure_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis

# AI Configuration
ANTHROPIC_API_KEY=sk-ant-api03-your-real-key-here
LLM_PROVIDER=anthropic
LLM_MODEL=claude-3-5-sonnet-20241022

# Product Repository
PRODUCT_PATH=/srv/work/kudos-product

# Git Configuration
GIT_REMOTE=origin
GIT_BRANCH_PREFIX=auto/
GIT_USER_NAME=kudos-bot
GIT_USER_EMAIL=bot@yourcompany.com

# Session and Cache
SESSION_DRIVER=redis
CACHE_DRIVER=redis

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=info
```

### 3. Database Migration

```bash
# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Set Up Product Repository

```bash
# Create product repository
sudo mkdir -p /srv/work/kudos-product
sudo chown www-data:www-data /srv/work/kudos-product

# Initialize as www-data user
sudo -u www-data git init /srv/work/kudos-product
cd /srv/work/kudos-product
sudo -u www-data git config user.name "kudos-bot"
sudo -u www-data git config user.email "bot@yourcompany.com"
```

## ⚙️ Service Configuration

### 1. PHP-FPM Configuration

```bash
# Edit PHP-FPM pool configuration
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

```bash
sudo systemctl restart php8.2-fpm
```

### 2. Queue Worker Service

Create systemd service for queue workers:

```bash
sudo nano /etc/systemd/system/kudos-worker.service
```

```ini
[Unit]
Description=Kudos Orchestrator Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /usr/share/ngnix/html/kudos-orchestrator/artisan queue:work --sleep=3 --tries=3 --timeout=300
WorkingDirectory=/usr/share/ngnix/html/kudos-orchestrator

[Install]
WantedBy=multi-user.target
```

```bash
# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable kudos-worker
sudo systemctl start kudos-worker
```

### 3. Horizon Service (Optional)

For queue monitoring:

```bash
sudo nano /etc/systemd/system/kudos-horizon.service
```

```ini
[Unit]
Description=Kudos Orchestrator Horizon
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /usr/share/ngnix/html/kudos-orchestrator/artisan horizon
WorkingDirectory=/usr/share/ngnix/html/kudos-orchestrator

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable kudos-horizon
sudo systemctl start kudos-horizon
```

## 🔒 SSL/TLS Configuration

### 1. Install Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
```

### 2. Obtain SSL Certificate

```bash
sudo certbot --nginx -d your-domain.com
```

### 3. Auto-renewal

```bash
# Test renewal
sudo certbot renew --dry-run

# Add cron job for auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

## 📊 Monitoring and Logging

### 1. Log Configuration

```bash
# Create log rotation
sudo nano /etc/logrotate.d/kudos-orchestrator
```

```
/usr/share/ngnix/html/kudos-orchestrator/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 644 www-data www-data
}
```

### 2. System Monitoring

```bash
# Install monitoring tools
sudo apt install -y htop iotop netstat-nat

# Monitor services
sudo systemctl status nginx php8.2-fpm postgresql redis-server kudos-worker
```

### 3. Health Check Script

```bash
nano /usr/share/ngnix/html/kudos-orchestrator/health-check.sh
```

```bash
#!/bin/bash
# Health check script

echo "=== Kudos Orchestrator Health Check ==="

# Check services
services=("nginx" "php8.2-fpm" "postgresql" "redis-server" "kudos-worker")
for service in "${services[@]}"; do
    if systemctl is-active --quiet $service; then
        echo "✓ $service is running"
    else
        echo "✗ $service is not running"
    fi
done

# Check application
if curl -s http://localhost/health > /dev/null; then
    echo "✓ Application is responding"
else
    echo "✗ Application is not responding"
fi

# Check database
cd /usr/share/ngnix/html/kudos-orchestrator
if php artisan migrate:status > /dev/null 2>&1; then
    echo "✓ Database is accessible"
else
    echo "✗ Database connection failed"
fi
```

```bash
chmod +x health-check.sh
```

## 🔄 Backup Strategy

### 1. Database Backup

```bash
# Create backup script
nano /home/ubuntu/backup-database.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/kudos-orchestrator"
mkdir -p $BACKUP_DIR

# Database backup
pg_dump -U kudos -h localhost kudos_orchestrator | gzip > \
    $BACKUP_DIR/database-$(date +%Y%m%d_%H%M%S).sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "database-*.sql.gz" -mtime +7 -delete
```

```bash
chmod +x /home/ubuntu/backup-database.sh

# Add to crontab
crontab -e
# Add: 0 2 * * * /home/ubuntu/backup-database.sh
```

### 2. Application Backup

```bash
# Application files backup
rsync -av --exclude 'storage/logs' --exclude 'node_modules' \
    /usr/share/ngnix/html/kudos-orchestrator/ /var/backups/kudos-app/
```

## 🚀 Deployment Automation

### 1. Deployment Script

```bash
nano /home/ubuntu/deploy.sh
```

```bash
#!/bin/bash
set -e

echo "Starting deployment..."

cd /usr/share/ngnix/html/kudos-orchestrator

# Pull latest changes
git pull origin main

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Run migrations
php artisan migrate --force

# Clear and cache config
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart kudos-worker

echo "Deployment completed successfully!"
```

### 2. Zero-Downtime Deployment

For production environments requiring zero downtime:

```bash
nano /home/ubuntu/zero-downtime-deploy.sh
```

```bash
#!/bin/bash
set -e

DEPLOY_PATH="/var/www"
REPO_URL="https://github.com/your-org/kudos-orchestrator.git"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
NEW_RELEASE_PATH="$DEPLOY_PATH/releases/$TIMESTAMP"
CURRENT_PATH="$DEPLOY_PATH/current"

# Create release directory
mkdir -p $NEW_RELEASE_PATH

# Clone repository
git clone $REPO_URL $NEW_RELEASE_PATH

cd $NEW_RELEASE_PATH

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Copy environment file
cp $CURRENT_PATH/.env .env

# Link storage directory
ln -s $DEPLOY_PATH/shared/storage storage

# Run migrations
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Switch symlink
ln -sfn $NEW_RELEASE_PATH $CURRENT_PATH

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart kudos-worker

# Cleanup old releases (keep last 5)
cd $DEPLOY_PATH/releases
ls -t | tail -n +6 | xargs rm -rf

echo "Zero-downtime deployment completed!"
```

## 🔧 Performance Optimization

### 1. PHP OPcache

```bash
sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### 2. Database Optimization

```sql
-- PostgreSQL optimization queries
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '1GB';
ALTER SYSTEM SET maintenance_work_mem = '64MB';
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
ALTER SYSTEM SET wal_buffers = '16MB';
SELECT pg_reload_conf();
```

### 3. Redis Optimization

```bash
# Redis configuration
sudo nano /etc/redis/redis.conf
```

```
maxmemory 512mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

## 🆘 Troubleshooting

### Common Issues

1. **Permission Issues**
   ```bash
   sudo chown -R www-data:www-data /usr/share/ngnix/html/kudos-orchestrator
   sudo chmod -R 755 /usr/share/ngnix/html/kudos-orchestrator
   ```

2. **Queue Worker Not Processing**
   ```bash
   sudo systemctl restart kudos-worker
   php artisan queue:restart
   ```

3. **Database Connection Issues**
   ```bash
   sudo systemctl status postgresql
   sudo -u postgres psql -c "SELECT version();"
   ```

4. **High Memory Usage**
   ```bash
   php artisan optimize:clear
   sudo systemctl restart php8.2-fpm
   ```

### Log Locations

- **Application Logs**: `/usr/share/ngnix/html/kudos-orchestrator/storage/logs/`
- **Nginx Logs**: `/var/log/nginx/`
- **PHP-FPM Logs**: `/var/log/php8.2-fpm.log`
- **PostgreSQL Logs**: `/var/log/postgresql/`
- **System Logs**: `/var/log/syslog`

## 📈 Scaling Considerations

### Horizontal Scaling

1. **Load Balancer Setup**
2. **Session Storage** (use Redis)
3. **File Storage** (shared NFS or S3)
4. **Database Read Replicas**

### Performance Monitoring

1. **Application Performance Monitoring (APM)**
2. **Database Query Analysis**
3. **Queue Processing Metrics**
4. **Resource Usage Monitoring**

This completes the comprehensive deployment guide. The system is now ready for production use with proper monitoring, backups, and security configurations.