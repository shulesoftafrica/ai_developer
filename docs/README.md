# Kudos Orchestrator - AI Development Orchestrator

![Laravel](https://img.shields.io/badge/Laravel-11-red)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15+-blue)
![Redis](https://img.shields.io/badge/Redis-7+-red)
![License](https://img.shields.io/badge/License-MIT-green)

An AI-powered development orchestrator that automates software development workflows using intelligent agents. The system reads tasks from a database, breaks them into milestones, generates code changes, runs tests, and creates pull requests for human review.

## 🚀 Features

- **AI Agent System**: PM, BA, UX, Architect, Developer, QA, and Documentation agents
- **Task Management**: Complete workflow from planning to deployment
- **Queue Processing**: Redis-based job queue with Horizon monitoring
- **Git Integration**: Automated branch creation and PR management
- **Security**: Path sandboxing and command allowlisting
- **Dashboard**: Real-time monitoring and analytics
- **Mock Testing**: Built-in mock AI responses for development

## 🏗️ Architecture

### System Components

- **Laravel 11**: Core application framework
- **PostgreSQL**: Primary database for tasks, milestones, and logs
- **Redis**: Queue management and caching
- **AI Integration**: Claude Sonnet 4 API for development agents
- **Git Services**: Automated repository management
- **Two-Repo Model**: Orchestrator edits separate product repository

### AI Agents

1. **PM Agent**: Project planning and milestone breakdown
2. **BA Agent**: Requirements analysis and user stories
3. **UX Agent**: UI/UX design and wireframes
4. **Architect Agent**: Technical architecture and design patterns
5. **Developer Agent**: Code generation and implementation
6. **QA Agent**: Test creation and quality assurance
7. **Documentation Agent**: Technical and user documentation

## 📋 Requirements

### System Requirements

- **PHP**: 8.2 or higher
- **Composer**: Latest version
- **PostgreSQL**: 15 or higher
- **Redis**: 7 or higher
- **Git**: 2.40 or higher
- **Node.js**: 18+ (for frontend assets)

### PHP Extensions

```bash
# Required PHP extensions
php8.2-cli
php8.2-fpm
php8.2-pgsql
php8.2-redis
php8.2-xml
php8.2-mbstring
php8.2-curl
php8.2-zip
php8.2-bcmath
```

## 🛠️ Installation

### 1. Clone Repository

```bash
git clone https://github.com/your-org/kudos-orchestrator.git
cd kudos-orchestrator
```

### 2. Install Dependencies

```bash
composer install
npm install && npm run build
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Environment Variables

Edit `.env` file with your configuration:

```bash
# Application
APP_NAME="Kudos Orchestrator"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kudos_orchestrator
DB_USERNAME=kudos
DB_PASSWORD=your_secure_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# AI Configuration
ANTHROPIC_API_KEY=sk-ant-api03-your-real-key-here
LLM_PROVIDER=anthropic
LLM_MODEL=claude-3-5-sonnet-20241022
LLM_MAX_TOKENS=4096
LLM_TEMPERATURE=0.1

# Product Repository
PRODUCT_PATH=/srv/work/kudos-product

# Git Configuration
GIT_REMOTE=origin
GIT_BRANCH_PREFIX=auto/
GIT_USER_NAME=kudos-bot
GIT_USER_EMAIL=bot@yourcompany.com

# Security
ALLOWED_COMMANDS=composer,php,artisan,npm,vendor/bin/phpunit,sed,tee
COMMAND_TIMEOUT=300
```

### 5. Database Setup

```bash
# Create database
sudo -u postgres createdb kudos_orchestrator
sudo -u postgres createuser kudos

# Run migrations
php artisan migrate

# Seed with sample data (optional)
php artisan db:seed
```

### 6. Set Up Product Repository

```bash
# Create product repository directory
sudo mkdir -p /srv/work/kudos-product
sudo chown $USER:$USER /srv/work/kudos-product

# Initialize Git repository
cd /srv/work/kudos-product
git init
git config user.name "kudos-bot"
git config user.email "bot@yourcompany.com"
```

## 🚦 Running the Application

### Development Mode

```bash
# Start Redis (if not running as service)
redis-server &

# Start Laravel development server
php artisan serve

# Start queue worker
php artisan queue:work

# Optional: Start Horizon for queue monitoring
php artisan horizon
```

### Production Mode

See the [Deployment Guide](#deployment-guide) section below.

## 📊 Usage

### Web Dashboard

Access the dashboard at `http://localhost:8000/dashboard` to:

- Monitor system statistics
- View recent tasks and AI activity
- Track queue processing
- Analyze performance metrics

### CLI Commands

```bash
# Check system status
php artisan kudos:status

# Dispatch pending tasks
php artisan kudos:dispatch-tasks

# View available commands
php artisan list | grep kudos
```

### API Endpoints

```bash
# System statistics
GET /api/dashboard/stats

# Tasks data
GET /api/dashboard/tasks

# AI logs
GET /api/dashboard/ai-logs

# Analytics
GET /api/dashboard/analytics
```

## 🧪 Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Test with coverage
php artisan test --coverage
```

### Mock Testing Mode

For development and testing without API costs:

```bash
# Set mock API key in .env
ANTHROPIC_API_KEY=sk-test-mock-key-for-testing-only

# The system automatically uses mock responses
php artisan kudos:test-mock-ai
```

## 🔧 Configuration

### Security Configuration

The system includes several security features:

```php
// config/app.php
'security' => [
    'allowed_commands' => ['composer', 'php', 'artisan', 'npm'],
    'command_timeout' => 300,
]
```

### Git Configuration

```php
// config/app.php
'git' => [
    'remote' => 'origin',
    'branch_prefix' => 'auto/',
    'user_name' => 'kudos-bot',
    'user_email' => 'bot@yourcompany.com',
]
```

### LLM Configuration

```php
// config/app.php
'llm' => [
    'provider' => 'anthropic',
    'model' => 'claude-3-5-sonnet-20241022',
    'max_tokens' => 4096,
    'temperature' => 0.1,
]
```

## 📚 Documentation

- [API Documentation](docs/api.md)
- [Agent Development Guide](docs/agents.md)
- [Security Guidelines](docs/security.md)
- [Troubleshooting](docs/troubleshooting.md)

## 🚀 Deployment Guide

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed production deployment instructions.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [docs.kudos-orchestrator.com](https://docs.kudos-orchestrator.com)
- **Issues**: [GitHub Issues](https://github.com/your-org/kudos-orchestrator/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-org/kudos-orchestrator/discussions)

## 🏆 Acknowledgments

- Laravel Framework
- Anthropic Claude API
- PostgreSQL Database
- Redis Cache
- Tailwind CSS