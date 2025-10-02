# Kudos AI Development Orchestrator

![Laravel](https://img.shields.io/badge/Laravel-11-red)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15+-blue)
![Redis](https://img.shields.io/badge/Redis-7+-red)
![AI](https://img.shields.io/badge/AI-Claude%20Sonnet%204-purple)
![License](https://img.shields.io/badge/License-MIT-green)

## 🤖 Overview

**Kudos AI Development Orchestrator** is a revolutionary Laravel 11 application that transforms software development through intelligent automation. Powered by Anthropic's Claude Sonnet 4, it orchestrates complete development workflows using specialized AI agents that handle everything from project planning to code deployment.

## ✨ Key Features

### 🎯 **Multi-Agent AI System**
- **Project Manager (PM)**: Sprint planning, task prioritization, resource allocation
- **Business Analyst (BA)**: Requirements gathering, user story creation, acceptance criteria
- **UX Designer**: User experience design, wireframes, interaction flows
- **Architect**: System design, technology decisions, scalability planning
- **Developer**: Code generation, implementation, debugging, optimization
- **QA Engineer**: Test planning, automated testing, quality assurance
- **Documentation**: Technical writing, API docs, user guides

### 🔄 **Automated Development Workflow**
- Intelligent task breakdown and milestone creation
- Automated code generation and file modifications
- Real-time progress tracking and status updates
- Automated testing and quality assurance
- Pull request creation and review management

### 📊 **Enterprise Dashboard**
- Real-time monitoring of all development activities
- Interactive charts and analytics
- Task and milestone progress tracking
- AI agent performance metrics
- Git repository status and branch management
- Live system health monitoring

### 🛡️ **Enterprise Security**
- Command sandboxing and allowlisting
- Path validation and access controls
- API rate limiting and authentication
- Secure environment variable management
- Audit logging for all operations

## 🏗️ System Architecture

### Core Technology Stack
```
┌─────────────────────────────────────────────────────────┐
│                   Frontend Dashboard                    │
│                (Tailwind CSS + Chart.js)               │
└─────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────┐
│                   Laravel 11 API                       │
│              (Controllers + Services)                   │
└─────────────────────────────────────────────────────────┘
                              │
┌─────────────────┬─────────────────┬─────────────────────┐
│   PostgreSQL    │      Redis      │    Claude Sonnet    │
│   (Database)    │    (Queue)      │    (AI Engine)      │
└─────────────────┴─────────────────┴─────────────────────┘
                              │
┌─────────────────────────────────────────────────────────┐
│              Git Repository Management                  │
│            (Automated Branch & PR Creation)            │
└─────────────────────────────────────────────────────────┘
```

### Database Schema
- **Tasks**: Core development tasks with status tracking
- **Milestones**: Granular task breakdowns with AI agent assignments
- **Sprints**: Time-boxed development cycles
- **AI Logs**: Complete audit trail of AI interactions
- **Users**: Authentication and authorization

### Queue System
- **Redis Backend**: High-performance job processing
- **Horizon Dashboard**: Real-time queue monitoring
- **Job Types**: ProcessNewTask, ProcessBugTask, ProcessUpgradeTask
- **Error Handling**: Automatic retry logic and failure tracking

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- PostgreSQL 15+
- Redis 7+
- Git
- Anthropic API Key

### AI Agents

1. **PM Agent**: Project planning and milestone breakdown
2. **BA Agent**: Requirements analysis and user stories
3. **UX Agent**: UI/UX design and wireframes
4. **Architect Agent**: Technical architecture and design patterns
5. **Developer Agent**: Code generation and implementation
6. **QA Agent**: Test creation and quality assurance
7. **Documentation Agent**: Technical and user documentation

## � Requirements

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

See the [Deployment Guide](DEPLOYMENT.md) section below.

## � Usage

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

## � Configuration

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

## � Documentation

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