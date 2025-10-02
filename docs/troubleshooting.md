# Troubleshooting Guide - Kudos Orchestrator

This guide helps diagnose and resolve common issues with the Kudos Orchestrator system.

## 🔍 Quick Diagnosis

### System Health Check

Run this command to get an overview of system status:

```bash
# From application directory
cd /usr/share/nginx/html/aicoder/kudos-orchestrator

# Check application status
php artisan about

# Check services (for production)
sudo systemctl status nginx php8.2-fpm postgresql redis-server kudos-worker

# Check logs for errors
tail -f storage/logs/laravel.log
```

## 🧪 Local Development Testing

### Prerequisites for Local Testing

Before testing locally, ensure you have:

```bash
# Required software
php --version    # Should be 8.2+
composer --version
psql --version   # PostgreSQL 15+
redis-cli ping   # Should return "PONG"
git --version
node --version   # For asset compilation
```

### Quick Local Setup

1. **Clone and Install:**
```bash
# Clone the repository
git clone <your-repo-url>
cd kudos-orchestrator

# Install dependencies
composer install
npm install && npm run build

# Copy environment file
cp .env.example .env
php artisan key:generate
```

2. **Database Setup:**
```bash
# Create PostgreSQL database
sudo -u postgres createdb kudos_orchestrator_local
sudo -u postgres createuser kudos_local
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE kudos_orchestrator_local TO kudos_local;"

# Run migrations
php artisan migrate
php artisan db:seed
```

3. **Environment Configuration:**
```bash
# Edit .env file for local testing
nano .env
```

**Local .env Configuration:**
```env
APP_NAME="Kudos Orchestrator Local"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kudos_orchestrator_local
DB_USERNAME=kudos_local
DB_PASSWORD=your_local_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# AI Configuration (for testing)
ANTHROPIC_API_KEY=sk-test-mock-key-for-local-testing
AI_MODEL=claude-sonnet-4-20250514

# Product Repository (local path)
PRODUCT_PATH=/tmp/kudos-product-local

# Security (for local development)
ALLOWED_COMMANDS=composer,php,artisan,npm,vendor/bin/phpunit,sed,tee,git
COMMAND_TIMEOUT=300

# Queue
QUEUE_CONNECTION=redis
```

### 4. **Start Local Services:**

```bash
# Terminal 1: Start Laravel development server
php artisan serve
# Accessible at: http://localhost:8000

# Terminal 2: Start queue worker
php artisan queue:work --verbose

# Terminal 3: Optional - Start Horizon (queue monitoring)
php artisan horizon
# Accessible at: http://localhost:8000/horizon
```

### Local Testing Workflows

#### 1. **Test AI Integration**
```bash
# Test AI API connection
php artisan kudos:test-ai

# Expected output for mock API:
# ✅ AI API Response received successfully!
# Response: Mock response for local testing
```

#### 2. **Test Dashboard Functionality**
```bash
# Check dashboard endpoints
curl http://localhost:8000/api/v1/stats
curl http://localhost:8000/dashboard

# Test API authentication (if enabled)
curl -H "Authorization: Bearer test-token" http://localhost:8000/api/v1/tasks
```

#### 3. **Test Task Processing**
```bash
# Create a test task via Tinker
php artisan tinker
>>> $task = \App\Models\Task::create([
    'title' => 'Test Local Task',
    'description' => 'Testing local development setup',
    'type' => 'feature',
    'status' => 'pending',
    'priority' => 'medium',
    'estimated_hours' => 4
]);
>>> \App\Jobs\ProcessNewTask::dispatch($task);
>>> exit

# Monitor the queue processing
php artisan queue:work --once --verbose
```

#### 4. **Test Git Integration**
```bash
# Create local product repository
mkdir -p /tmp/kudos-product-local
cd /tmp/kudos-product-local
git init
git config user.email "test@local.dev"
git config user.name "Local Tester"
echo "# Local Test Repo" > README.md
git add README.md
git commit -m "Initial commit"

# Test git operations from the app
cd /usr/share/nginx/html/aicoder/kudos-orchestrator
php artisan tinker
>>> $gitService = app(\App\Services\GitService::class);
>>> $gitService->validateRepository();
>>> $gitService->getStatus();
```

### Mock Testing (No AI API Costs)

For local development without using real AI API credits:

```bash
# Set mock API key in .env
ANTHROPIC_API_KEY=sk-test-mock-key-for-local-testing

# The system automatically detects mock keys and provides sample responses
# You can process tasks without real AI costs
```

### Database Testing

```bash
# Reset database for clean testing
php artisan migrate:fresh --seed

# Test database connections
php artisan tinker
>>> DB::connection()->getPdo();
>>> \App\Models\Task::count();
>>> \App\Models\Milestone::count();

# Test migrations
php artisan migrate:rollback
php artisan migrate
```

### Performance Testing

```bash
# Test with multiple concurrent tasks
php artisan tinker
>>> for ($i = 1; $i <= 10; $i++) {
...     \App\Models\Task::create([
...         'title' => "Test Task $i",
...         'description' => "Performance testing task $i",
...         'type' => 'feature',
...         'status' => 'pending',
...         'priority' => 'medium',
...         'estimated_hours' => rand(2, 8)
...     ]);
... }

# Process multiple jobs
php artisan queue:work --sleep=1 --tries=3
```

### Frontend Testing

```bash
# Compile assets for development
npm run dev

# Watch for changes during development
npm run watch

# Test with hot reloading
npm run hot
```

### API Testing with Different Tools

#### Using cURL:
```bash
# Health check
curl -X GET http://localhost:8000/api/health

# Get system stats
curl -X GET http://localhost:8000/api/v1/stats

# Create a task
curl -X POST http://localhost:8000/api/v1/tasks \
  -H "Content-Type: application/json" \
  -d '{
    "title": "API Test Task",
    "description": "Testing API via cURL",
    "type": "feature",
    "priority": "low",
    "estimated_hours": 2
  }'
```

#### Using Postman Collection:
```json
{
  "info": {
    "name": "Kudos Orchestrator Local",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/"
  },
  "item": [
    {
      "name": "Health Check",
      "request": {
        "method": "GET",
        "header": [],
        "url": "http://localhost:8000/api/health"
      }
    },
    {
      "name": "System Stats",
      "request": {
        "method": "GET",
        "header": [],
        "url": "http://localhost:8000/api/v1/stats"
      }
    }
  ]
}
```

### Load Testing

```bash
# Install Apache Bench (if not installed)
sudo apt install apache2-utils

# Basic load test
ab -n 100 -c 10 http://localhost:8000/api/v1/stats

# More comprehensive test
for i in {1..5}; do
  echo "Test $i:"
  curl -w "@curl-format.txt" -s -o /dev/null http://localhost:8000/dashboard
done
```

Create `curl-format.txt`:
```
     time_namelookup:  %{time_namelookup}\n
        time_connect:  %{time_connect}\n
     time_appconnect:  %{time_appconnect}\n
    time_pretransfer:  %{time_pretransfer}\n
       time_redirect:  %{time_redirect}\n
  time_starttransfer:  %{time_starttransfer}\n
                     ----------\n
          time_total:  %{time_total}\n
```

### Debugging Local Issues

#### Common Local Development Issues:

1. **Port Already in Use:**
```bash
# Check what's using port 8000
lsof -i :8000

# Use different port
php artisan serve --port=8001
```

2. **Permission Issues:**
```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache
```

3. **Database Connection Issues:**
```bash
# Check PostgreSQL is running
sudo systemctl status postgresql

# Test direct connection
psql -h localhost -U kudos_local -d kudos_orchestrator_local
```

4. **Redis Connection Issues:**
```bash
# Check Redis is running
redis-cli ping

# Clear Redis cache
redis-cli flushall
```

### Local Testing Checklist

- [ ] **Environment Setup**
  - [ ] PHP 8.2+ installed and working
  - [ ] Composer dependencies installed
  - [ ] PostgreSQL database created and accessible
  - [ ] Redis server running
  - [ ] .env file configured for local development

- [ ] **Application Setup**
  - [ ] Database migrations completed
  - [ ] Seed data loaded
  - [ ] Application key generated
  - [ ] Storage permissions set correctly

- [ ] **Service Testing**
  - [ ] Laravel development server starts (`php artisan serve`)
  - [ ] Dashboard accessible at http://localhost:8000
  - [ ] API endpoints respond correctly
  - [ ] Queue worker processes jobs
  - [ ] Git integration works with local repository

- [ ] **AI Integration**
  - [ ] AI test command works (`php artisan kudos:test-ai`)
  - [ ] Mock responses work for development
  - [ ] Real API integration (if testing with real key)

- [ ] **End-to-End Testing**
  - [ ] Can create tasks via API/dashboard
  - [ ] Tasks are processed by queue workers
  - [ ] Milestones are created and processed
  - [ ] Git operations work correctly
  - [ ] Dashboard shows real-time updates

### Automated Testing

```bash
# Run unit tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run tests with coverage
php artisan test --coverage

# Run database tests
php artisan test tests/Feature/DatabaseTest.php
```

### Common Symptoms and Quick Fixes

| Symptom | Quick Fix | Section |
|---------|-----------|---------|
| Dashboard not loading | Check web server and PHP-FPM | [Web Server Issues](#web-server-issues) |
| Tasks not processing | Restart queue workers | [Queue Issues](#queue-issues) |
| AI requests failing | Check API key and connectivity | [AI Service Issues](#ai-service-issues) |
| Database errors | Check PostgreSQL status | [Database Issues](#database-issues) |
| High memory usage | Clear cache and restart services | [Performance Issues](#performance-issues) |

## 🌐 Web Server Issues

### Nginx Not Starting

**Symptoms:**
- Site not accessible
- "Connection refused" errors
- 502 Bad Gateway

**Diagnosis:**
```bash
# Check Nginx status
sudo systemctl status nginx

# Check configuration syntax
sudo nginx -t

# Check error logs
sudo tail -f /var/log/nginx/error.log
```

**Solutions:**

1. **Configuration Syntax Error:**
   ```bash
   sudo nginx -t
   # Fix any syntax errors in /etc/nginx/sites-available/kudos-orchestrator
   sudo systemctl restart nginx
   ```

2. **Port Conflict:**
   ```bash
   sudo netstat -tlnp | grep :80
   # If another service is using port 80, change Nginx port or stop conflicting service
   ```

3. **Permission Issues:**
   ```bash
   sudo chown -R www-data:www-data /usr/share/ngix/html/kudos-orchestrator
   sudo chmod -R 755 /usr/share/ngix/html/kudos-orchestrator
   ```

### PHP-FPM Issues

**Symptoms:**
- 504 Gateway Timeout
- 502 Bad Gateway
- Slow response times

**Diagnosis:**
```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check PHP-FPM logs
sudo tail -f /var/log/php8.2-fpm.log

# Check process list
ps aux | grep php-fpm
```

**Solutions:**

1. **PHP-FPM Not Running:**
   ```bash
   sudo systemctl start php8.2-fpm
   sudo systemctl enable php8.2-fpm
   ```

2. **Memory Limit Issues:**
   ```bash
   # Edit PHP configuration
   sudo nano /etc/php/8.2/fpm/php.ini
   # Increase: memory_limit = 512M
   sudo systemctl restart php8.2-fpm
   ```

3. **Pool Configuration:**
   ```bash
   # Edit pool configuration
   sudo nano /etc/php/8.2/fpm/pool.d/www.conf
   # Adjust: pm.max_children = 50
   sudo systemctl restart php8.2-fpm
   ```

### SSL/TLS Issues

**Symptoms:**
- Certificate warnings
- "Not secure" in browser
- SSL handshake failures

**Solutions:**

1. **Renew SSL Certificate:**
   ```bash
   sudo certbot renew --dry-run
   sudo certbot renew
   sudo systemctl reload nginx
   ```

2. **Certificate Path Issues:**
   ```bash
   # Check certificate files exist
   sudo ls -la /etc/letsencrypt/live/your-domain.com/
   
   # Update Nginx configuration if needed
   sudo nano /etc/nginx/sites-available/kudos-orchestrator
   ```

## 📊 Database Issues

### PostgreSQL Connection Problems

**Symptoms:**
- "Connection refused" errors
- "Database not found" errors
- Migration failures

**Diagnosis:**
```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Test connection
sudo -u postgres psql -c "SELECT version();"

# Check database exists
sudo -u postgres psql -l | grep kudos_orchestrator

# Test application connection
cd /usr/share/ngix/html/kudos-orchestrator
php artisan tinker
>>> DB::connection()->getPdo();
```

**Solutions:**

1. **PostgreSQL Not Running:**
   ```bash
   sudo systemctl start postgresql
   sudo systemctl enable postgresql
   ```

2. **Database Doesn't Exist:**
   ```bash
   sudo -u postgres createdb kudos_orchestrator
   sudo -u postgres createuser kudos
   sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE kudos_orchestrator TO kudos;"
   ```

3. **Authentication Issues:**
   ```bash
   # Edit PostgreSQL configuration
   sudo nano /etc/postgresql/14/main/pg_hba.conf
   # Ensure line exists: local   all   kudos   md5
   sudo systemctl restart postgresql
   ```

4. **Connection Limit Reached:**
   ```bash
   # Check active connections
   sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"
   
   # Increase connection limit
   sudo nano /etc/postgresql/14/main/postgresql.conf
   # Set: max_connections = 200
   sudo systemctl restart postgresql
   ```

### Migration Issues

**Symptoms:**
- Migration failures
- "Table doesn't exist" errors
- Schema inconsistencies

**Solutions:**

1. **Run Migrations:**
   ```bash
   cd /usr/share/ngix/html/kudos-orchestrator
   php artisan migrate:status
   php artisan migrate --force
   ```

2. **Reset Database (Development Only):**
   ```bash
   php artisan migrate:fresh --seed
   ```

3. **Fix Migration Lock:**
   ```bash
   # Check for stuck migrations
   php artisan migrate:status
   
   # Manually remove migration lock if needed
   sudo -u postgres psql kudos_orchestrator -c "DELETE FROM migrations WHERE migration = 'stuck_migration_name';"
   ```

## ⚡ Redis Issues

### Redis Connection Problems

**Symptoms:**
- Queue jobs not processing
- Cache misses
- Session issues

**Diagnosis:**
```bash
# Check Redis status
sudo systemctl status redis-server

# Test Redis connection
redis-cli ping

# Check Redis logs
sudo tail -f /var/log/redis/redis-server.log

# Test from application
cd /usr/share/ngix/html/kudos-orchestrator
php artisan tinker
>>> Redis::ping();
```

**Solutions:**

1. **Redis Not Running:**
   ```bash
   sudo systemctl start redis-server
   sudo systemctl enable redis-server
   ```

2. **Memory Issues:**
   ```bash
   # Check Redis memory usage
   redis-cli info memory
   
   # Clear Redis cache if needed
   redis-cli flushall
   
   # Adjust memory limit
   sudo nano /etc/redis/redis.conf
   # Set: maxmemory 512mb
   sudo systemctl restart redis-server
   ```

3. **Configuration Issues:**
   ```bash
   # Check Redis configuration
   redis-cli config get "*"
   
   # Verify application Redis settings
   grep -r REDIS_HOST /usr/share/ngix/html/kudos-orchestrator/.env
   ```

## 🔄 Queue Issues

### Queue Workers Not Processing Jobs

**Symptoms:**
- Jobs stuck in queue
- Tasks not progressing
- Queue size growing

**Diagnosis:**
```bash
# Check queue status
cd /usr/share/ngix/html/kudos-orchestrator
php artisan queue:work --once

# Check worker processes
ps aux | grep "queue:work"

# Check failed jobs
php artisan queue:failed

# Monitor queue in real-time
php artisan horizon
```

**Solutions:**

1. **Restart Queue Workers:**
   ```bash
   # Restart worker service
   sudo systemctl restart kudos-worker
   
   # Or restart manually
   php artisan queue:restart
   php artisan queue:work &
   ```

2. **Clear Failed Jobs:**
   ```bash
   # Retry all failed jobs
   php artisan queue:retry all
   
   # Clear failed jobs
   php artisan queue:flush
   ```

3. **Fix Worker Configuration:**
   ```bash
   # Check worker service
   sudo systemctl status kudos-worker
   
   # Edit service file if needed
   sudo nano /etc/systemd/system/kudos-worker.service
   sudo systemctl daemon-reload
   sudo systemctl restart kudos-worker
   ```

### Queue Memory Issues

**Symptoms:**
- Worker processes killed
- "Allowed memory size exceeded" errors
- Workers stopping unexpectedly

**Solutions:**

1. **Increase Memory Limit:**
   ```bash
   # Edit worker service
   sudo nano /etc/systemd/system/kudos-worker.service
   # Add: Environment=PHP_MEMORY_LIMIT=512M
   sudo systemctl daemon-reload
   sudo systemctl restart kudos-worker
   ```

2. **Add Memory Restart:**
   ```bash
   # Edit queue worker command
   php artisan queue:work --memory=512 --timeout=300
   ```

### Job Timeout Issues

**Symptoms:**
- Jobs failing with timeout errors
- Long-running tasks killed
- Inconsistent job completion

**Solutions:**

1. **Increase Timeout:**
   ```bash
   # In worker command
   php artisan queue:work --timeout=600
   
   # In job class
   public $timeout = 600;
   ```

2. **Check Job Logic:**
   ```php
   // Add progress tracking to long jobs
   public function handle()
   {
       $this->job->reserveTime(600); // Reserve more time
       // Job logic here
   }
   ```

## 🤖 AI Service Issues

### AI API Failures

**Symptoms:**
- AI requests timing out
- "API key invalid" errors
- High error rates in AI logs

**Diagnosis:**
```bash
# Check AI logs
cd /usr/share/ngix/html/kudos-orchestrator
grep -r "anthropic" storage/logs/laravel.log

# Test API key
curl -H "Authorization: Bearer $ANTHROPIC_API_KEY" \
     -H "Content-Type: application/json" \
     https://api.anthropic.com/v1/messages

# Check application AI configuration
php artisan tinker
>>> config('app.llm.api_key');
```

**Solutions:**

1. **Invalid API Key:**
   ```bash
   # Update .env file
   nano .env
   # Set: ANTHROPIC_API_KEY=sk-ant-api03-your-real-key-here
   
   # Clear config cache
   php artisan config:clear
   php artisan config:cache
   ```

2. **Rate Limiting:**
   ```bash
   # Check AI logs for rate limit errors
   grep "rate_limit" storage/logs/laravel.log
   
   # Implement backoff strategy in LlmClient
   # Add delays between requests
   ```

3. **Network Issues:**
   ```bash
   # Test connectivity
   curl -I https://api.anthropic.com
   
   # Check firewall rules
   sudo ufw status
   ```

### Mock AI Testing

**For development/testing without API costs:**

```bash
# Set mock API key
nano .env
# Set: ANTHROPIC_API_KEY=sk-test-mock-key-for-testing-only

# The system automatically detects mock key and uses sample responses
php artisan queue:work
```

## 🚀 Performance Issues

### High Memory Usage

**Symptoms:**
- Server running out of memory
- Processes being killed
- Slow response times

**Diagnosis:**
```bash
# Check memory usage
free -h
ps aux --sort=-%mem | head -10

# Check PHP memory usage
php -i | grep memory_limit

# Application memory usage
cd /usr/share/ngix/html/kudos-orchestrator
php artisan tinker
>>> memory_get_usage(true);
```

**Solutions:**

1. **Optimize PHP Configuration:**
   ```bash
   # Edit PHP-FPM configuration
   sudo nano /etc/php/8.2/fpm/pool.d/www.conf
   # Reduce: pm.max_children = 25
   # Adjust: pm.max_requests = 200
   
   sudo systemctl restart php8.2-fpm
   ```

2. **Clear Application Cache:**
   ```bash
   cd /usr/share/ngix/html/kudos-orchestrator
   php artisan cache:clear
   php artisan view:clear
   php artisan route:clear
   ```

3. **Optimize Database:**
   ```bash
   # PostgreSQL optimization
   sudo -u postgres psql kudos_orchestrator -c "VACUUM ANALYZE;"
   ```

### Slow Response Times

**Symptoms:**
- Pages loading slowly
- API timeouts
- Poor user experience

**Solutions:**

1. **Enable OPcache:**
   ```bash
   # Check OPcache status
   php -i | grep opcache
   
   # Enable if not active
   sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini
   # Set: opcache.enable=1
   sudo systemctl restart php8.2-fpm
   ```

2. **Database Query Optimization:**
   ```bash
   # Enable query logging temporarily
   cd /usr/share/ngix/html/kudos-orchestrator
   php artisan tinker
   >>> DB::enableQueryLog();
   # Make some requests
   >>> DB::getQueryLog();
   ```

3. **Add Caching:**
   ```php
   // Cache expensive operations
   $stats = Cache::remember('dashboard_stats', 300, function () {
       return $this->calculateStats();
   });
   ```

## 🔧 File Permission Issues

### Permission Denied Errors

**Symptoms:**
- "Permission denied" errors
- Unable to write files
- Cache/session issues

**Solutions:**

1. **Fix File Permissions:**
   ```bash
   cd /usr/share/ngix/html/kudos-orchestrator
   
   # Set correct ownership
   sudo chown -R www-data:www-data .
   
   # Set correct permissions
   sudo chmod -R 755 .
   sudo chmod -R 775 storage bootstrap/cache
   ```

2. **SELinux Issues (CentOS/RHEL):**
   ```bash
   # Check SELinux status
   sestatus
   
   # Set proper context
   sudo setsebool -P httpd_can_network_connect 1
   sudo setsebool -P httpd_unified 1
   ```

## 🔒 Security Issues

### Unauthorized Access

**Solutions:**

1. **Check API Token Security:**
   ```bash
   # Regenerate compromised tokens
   cd /usr/share/ngix/html/kudos-orchestrator
   php artisan tinker
   >>> $user = User::first();
   >>> $user->tokens()->delete();
   >>> $token = $user->createToken('new-api-token');
   ```

2. **Review Access Logs:**
   ```bash
   sudo tail -f /var/log/nginx/access.log | grep -E "(401|403|404)"
   ```

3. **Update Security Headers:**
   ```nginx
   # Add to Nginx configuration
   add_header X-Frame-Options "SAMEORIGIN";
   add_header X-XSS-Protection "1; mode=block";
   add_header X-Content-Type-Options "nosniff";
   ```

## 📝 Log Analysis

### Important Log Locations

```bash
# Application logs
tail -f /usr/share/ngix/html/kudos-orchestrator/storage/logs/laravel.log

# Web server logs
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/access.log

# System logs
tail -f /var/log/syslog

# Database logs
sudo tail -f /var/log/postgresql/postgresql-14-main.log

# Queue worker logs
journalctl -f -u kudos-worker
```

### Log Analysis Commands

```bash
# Count error types
grep "ERROR" storage/logs/laravel.log | cut -d' ' -f4 | sort | uniq -c

# Find slow queries
grep "slow query" /var/log/postgresql/postgresql-14-main.log

# Monitor queue job failures
grep "queue.*failed" storage/logs/laravel.log

# Check AI API errors
grep "anthropic.*error" storage/logs/laravel.log
```

## 🆘 Emergency Procedures

### Complete System Reset (Development Only)

```bash
# Stop all services
sudo systemctl stop kudos-worker nginx php8.2-fpm

# Reset database
cd /usr/share/ngix/html/kudos-orchestrator
php artisan migrate:fresh --seed

# Clear all caches
php artisan optimize:clear
redis-cli flushall

# Restart services
sudo systemctl start php8.2-fpm nginx kudos-worker
```

### Backup and Restore

**Create Backup:**
```bash
# Database backup
pg_dump -U kudos kudos_orchestrator > backup_$(date +%Y%m%d).sql

# Files backup
tar -czf app_backup_$(date +%Y%m%d).tar.gz /usr/share/ngix/html/kudos-orchestrator
```

**Restore Backup:**
```bash
# Restore database
sudo -u postgres psql kudos_orchestrator < backup_20240115.sql

# Restore files
tar -xzf app_backup_20240115.tar.gz -C /
```

## 📞 Getting Help

### Diagnostic Information to Collect

When seeking help, provide:

1. **System Information:**
   ```bash
   uname -a
   php -v
   psql --version
   redis-server --version
   ```

2. **Error Logs:**
   ```bash
   tail -50 storage/logs/laravel.log
   tail -20 /var/log/nginx/error.log
   ```

3. **Configuration:**
   ```bash
   php artisan about
   php artisan config:show
   ```

4. **Service Status:**
   ```bash
   sudo systemctl status nginx php8.2-fpm postgresql redis-server kudos-worker
   ```

### Support Resources

- **Documentation**: Check the main README and API docs
- **GitHub Issues**: Search existing issues and create new ones
- **Community Forum**: Join discussions with other users
- **Professional Support**: Contact for enterprise support options

This troubleshooting guide covers the most common issues you may encounter with the Kudos Orchestrator system. Always check logs first and follow the diagnostic steps before implementing solutions.