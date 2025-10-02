# Local Development Testing Guide

## 🤖 Quick Start with Helper Script

We've created a convenient helper script to simplify local testing and monitoring:

```bash
# Make script executable (first time only)
chmod +x kudos.sh

# Check system status and requirements
./kudos.sh status

# Start real-time system monitor (like Horizon alternative)
./kudos.sh monitor

# Run comprehensive system tests
./kudos.sh test

# Process queue jobs manually
./kudos.sh queue

# View recent logs
./kudos.sh logs

# Create demo data for testing
./kudos.sh demo

# Test API endpoints
./kudos.sh api

# Show all available commands
./kudos.sh help
```

The helper script provides color-coded output and makes it easy to monitor your local development environment without needing Horizon.

## 🚀 Quick Local Setup

### Step 1: Basic Environment Setup

```bash
# 1. Navigate to project directory
cd /usr/share/nginx/html/aicoder/kudos-orchestrator

# 2. Verify prerequisites
php --version    # Should show 8.1+ (8.2+ recommended)
composer --version
psql --version   # PostgreSQL
redis-cli ping   # Should return "PONG"

# 3. Install dependencies (if not already done)
composer install --no-dev
```

### Step 2: Environment Configuration

```bash
# Copy environment file if not exists
cp .env.example .env.local
cp .env .env.backup

# Edit .env for local testing
nano .env
```

**Local Testing .env Configuration:**
```env
APP_NAME="Kudos Orchestrator Local"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (use your current settings)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kudos_orchestrator
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# AI Configuration (for testing without costs)
ANTHROPIC_API_KEY=sk-test-mock-for-local-testing
AI_MODEL=claude-sonnet-4-20250514

# Product Repository (current working path)
PRODUCT_PATH=/usr/share/nginx/html/aicoder/kudos-orchestrator/product-repo

# Security
ALLOWED_COMMANDS=composer,php,artisan,npm,vendor/bin/phpunit,sed,tee,git
COMMAND_TIMEOUT=300

# Queue
QUEUE_CONNECTION=redis
```

### Step 3: Start Local Services

**Terminal 1 - Laravel Development Server:**
```bash
cd /usr/share/nginx/html/aicoder/kudos-orchestrator
php artisan serve
# Access: http://localhost:8000
```

**Terminal 2 - Queue Worker:**
```bash
cd /usr/share/nginx/html/aicoder/kudos-orchestrator
php artisan queue:work --verbose --timeout=120
```

**Terminal 3 - Queue Monitoring (Simple):**
```bash
cd /usr/share/nginx/html/aicoder/kudos-orchestrator
watch -n 2 'php artisan queue:work --once --timeout=5 2>/dev/null && echo "Queue processed" || echo "No jobs"'
```

## 🧪 Testing Workflows

### 1. Health Check Tests

```bash
# Test 1: Basic application health
curl http://localhost:8000/api/health
# Expected: {"status":"ok","timestamp":"..."}

# Test 2: Dashboard access
curl -s http://localhost:8000/dashboard | grep -o "<title>.*</title>"
# Expected: Should show dashboard title

# Test 3: API endpoints
curl http://localhost:8000/api/v1/stats
# Expected: JSON with tasks, sprints, milestones, ai_logs data
```

### 2. AI Integration Tests

```bash
# Test AI API connection (with mock responses)
php artisan kudos:test-ai
# Expected: ✅ AI API Response received successfully!

# Test AI logging
php artisan tinker --execute="
\App\Models\AiLog::create([
    'run_id' => 'test_' . uniqid(),
    'agent_type' => 'dev',
    'status' => 'success',
    'prompt_tokens' => 100,
    'completion_tokens' => 50,
    'total_tokens' => 150,
    'execution_time' => 2.5,
    'cost' => 0.01
]);
echo 'Test AI log created';
"
```

### 3. Task Processing Tests

```bash
# Create test task via Tinker
php artisan tinker --execute="
\$task = \App\Models\Task::create([
    'title' => 'Local Test Task',
    'description' => 'Testing local development environment',
    'type' => 'feature',
    'status' => 'pending',
    'priority' => 'medium',
    'estimated_hours' => 2
]);
echo 'Task created with ID: ' . \$task->id;
"

# Dispatch job for processing
php artisan tinker --execute="
\$task = \App\Models\Task::latest()->first();
\App\Jobs\ProcessNewTask::dispatch(\$task);
echo 'Job dispatched for task: ' . \$task->id;
"

# Process the job manually
php artisan queue:work --once --verbose
```

### 4. Database Tests

```bash
# Test database connection
php artisan tinker --execute="
try {
    DB::connection()->getPdo();
    echo 'Database connection: ✅ Success';
} catch (Exception \$e) {
    echo 'Database connection: ❌ Failed - ' . \$e->getMessage();
}
"

# Test model relationships
php artisan tinker --execute="
echo 'Tasks count: ' . \App\Models\Task::count();
echo 'Milestones count: ' . \App\Models\Milestone::count();
echo 'AI Logs count: ' . \App\Models\AiLog::count();
"
```

### 5. Git Integration Tests

```bash
# Test git service
php artisan tinker --execute="
\$gitService = app(\App\Services\GitService::class);
echo 'Repository valid: ' . (\$gitService->validateRepository() ? 'Yes' : 'No');
\$status = \$gitService->getStatus();
echo 'Current branch: ' . \$status['current_branch'];
"
```

## 📊 Monitoring During Testing

### Simple Queue Monitor Script

Create a simple monitoring script:

```bash
# Create monitor.sh
cat > monitor.sh << 'EOF'
#!/bin/bash
echo "=== Kudos Orchestrator Local Monitor ==="
echo "Time: $(date)"
echo ""

# Check services
echo "🔍 Service Status:"
echo "Laravel Server: $(curl -s http://localhost:8000/api/health | jq -r .status 2>/dev/null || echo 'Not running')"
echo "Redis: $(redis-cli ping 2>/dev/null || echo 'Not running')"
echo "PostgreSQL: $(pg_isready -q && echo 'Ready' || echo 'Not ready')"
echo ""

# Check queue
echo "📋 Queue Status:"
cd /usr/share/nginx/html/aicoder/kudos-orchestrator
QUEUE_SIZE=$(redis-cli llen queues:default 2>/dev/null || echo "0")
echo "Jobs in queue: $QUEUE_SIZE"
echo ""

# Check recent logs
echo "📝 Recent Activity:"
tail -5 storage/logs/laravel.log | grep -E "(INFO|ERROR)" | tail -3
echo ""
echo "=====================================+"
EOF

chmod +x monitor.sh

# Run monitor
./monitor.sh
```

### Performance Testing

```bash
# Test response times
curl -w "@-" -s -o /dev/null http://localhost:8000/api/v1/stats << 'EOF'
Total time: %{time_total}s
Connect time: %{time_connect}s
Transfer time: %{time_starttransfer}s
Size: %{size_download} bytes
EOF

# Load test with multiple requests
for i in {1..10}; do
  echo "Request $i:"
  curl -w "Time: %{time_total}s, Status: %{http_code}\n" -s -o /dev/null http://localhost:8000/api/v1/stats
done
```

## 🔧 Troubleshooting Local Issues

### Common Local Issues:

**1. Port 8000 already in use:**
```bash
# Check what's using the port
lsof -i :8000

# Use different port
php artisan serve --port=8001
```

**2. Database connection fails:**
```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Test direct connection
psql -h localhost -U your_username -d kudos_orchestrator -c "SELECT version();"
```

**3. Redis connection fails:**
```bash
# Check Redis status
sudo systemctl status redis-server

# Test Redis connection
redis-cli ping
```

**4. Queue jobs not processing:**
```bash
# Check for failed jobs
php artisan queue:failed

# Clear failed jobs
php artisan queue:flush

# Restart queue worker
php artisan queue:restart
```

## 📱 Frontend Testing

```bash
# If you have Node.js installed
npm install
npm run dev

# Test with hot reloading during development
npm run watch
```

## 🧪 API Testing Examples

### Using cURL:

```bash
# Get system statistics
curl -X GET "http://localhost:8000/api/v1/stats" \
  -H "Accept: application/json" | jq .

# Get tasks list
curl -X GET "http://localhost:8000/api/v1/tasks" \
  -H "Accept: application/json" | jq .

# Create a new task
curl -X POST "http://localhost:8000/api/v1/tasks" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "API Test Task",
    "description": "Testing API endpoints locally",
    "type": "feature", 
    "priority": "low",
    "estimated_hours": 3
  }' | jq .
```

### Testing with Postman/Insomnia:

Import this collection:
```json
{
  "name": "Kudos Orchestrator Local Testing",
  "requests": [
    {
      "name": "Health Check",
      "method": "GET",
      "url": "http://localhost:8000/api/health"
    },
    {
      "name": "System Stats", 
      "method": "GET",
      "url": "http://localhost:8000/api/v1/stats"
    },
    {
      "name": "Dashboard",
      "method": "GET", 
      "url": "http://localhost:8000/dashboard"
    }
  ]
}
```

## ✅ Local Testing Checklist

- [ ] **Environment Setup**
  - [ ] PHP 8.1+ running
  - [ ] PostgreSQL connected and accessible
  - [ ] Redis server running and responsive
  - [ ] .env configured for local development

- [ ] **Application Services**
  - [ ] Laravel dev server starts (`php artisan serve`)
  - [ ] Dashboard loads at http://localhost:8000
  - [ ] API endpoints respond with JSON
  - [ ] Queue worker processes jobs

- [ ] **Core Functionality**
  - [ ] AI test command succeeds
  - [ ] Database operations work (create/read tasks)
  - [ ] Git integration functional
  - [ ] Queue jobs dispatch and process

- [ ] **API Testing**
  - [ ] GET /api/v1/stats returns system data
  - [ ] GET /api/v1/tasks returns task list
  - [ ] POST /api/v1/tasks creates new tasks
  - [ ] Error responses are properly formatted

- [ ] **Performance & Monitoring**
  - [ ] Response times under 1 second
  - [ ] No memory leaks during extended testing
  - [ ] Logs show clean operation
  - [ ] Queue processing doesn't accumulate

## 🎯 Quick Validation Commands

Run these commands to validate your local setup:

```bash
# Quick health check
cd /usr/share/nginx/html/aicoder/kudos-orchestrator

echo "=== Quick Local Validation ==="
echo "1. PHP Version: $(php --version | head -1)"
echo "2. Laravel Version: $(php artisan --version)"
echo "3. Database: $(php artisan tinker --execute='echo DB::connection()->getDatabaseName();' 2>/dev/null)"
echo "4. Redis: $(redis-cli ping 2>/dev/null)"
echo "5. Queue Connection: $(php artisan tinker --execute='echo config("queue.default");' 2>/dev/null)"
echo "6. AI Service: $(php artisan kudos:test-ai 2>/dev/null | grep -o '✅.*' || echo 'Check AI configuration')"
echo "7. Git Repository: $(php artisan tinker --execute='echo app(\App\Services\GitService::class)->validateRepository() ? "Valid" : "Invalid";' 2>/dev/null)"
```

This comprehensive local testing setup will help you validate all components of the Kudos AI Development Orchestrator without needing a production environment.