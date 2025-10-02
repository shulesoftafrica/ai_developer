#!/bin/bash

# Kudos Orchestrator - Local Development Helper Script

echo "🤖 Kudos AI Development Orchestrator - Local Testing Helper"
echo "=========================================================="

# Color definitions
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

show_help() {
    echo ""
    echo "Available commands:"
    echo ""
    echo "  ${CYAN}./kudos.sh status${NC}     - Check system status"
    echo "  ${CYAN}./kudos.sh monitor${NC}    - Start system monitor (real-time)"
    echo "  ${CYAN}./kudos.sh queue${NC}      - Process queue jobs manually"
    echo "  ${CYAN}./kudos.sh logs${NC}       - Show recent logs"
    echo "  ${CYAN}./kudos.sh test${NC}       - Run comprehensive tests"
    echo "  ${CYAN}./kudos.sh setup${NC}      - Initial setup and validation"
    echo "  ${CYAN}./kudos.sh demo${NC}       - Create demo data for testing"
    echo "  ${CYAN}./kudos.sh api${NC}        - Test API endpoints"
    echo "  ${CYAN}./kudos.sh help${NC}       - Show this help"
    echo ""
}

check_requirements() {
    echo -e "${BLUE}🔍 Checking requirements...${NC}"
    
    # Check PHP
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -r "echo PHP_VERSION;")
        echo -e "  PHP: ${GREEN}✅ $PHP_VERSION${NC}"
    else
        echo -e "  PHP: ${RED}❌ Not found${NC}"
        return 1
    fi
    
    # Check Composer
    if command -v composer &> /dev/null; then
        echo -e "  Composer: ${GREEN}✅ Found${NC}"
    else
        echo -e "  Composer: ${RED}❌ Not found${NC}"
        return 1
    fi
    
    # Check Redis
    if command -v redis-cli &> /dev/null; then
        if redis-cli ping &> /dev/null; then
            echo -e "  Redis: ${GREEN}✅ Running${NC}"
        else
            echo -e "  Redis: ${YELLOW}⚠️ Not running${NC}"
        fi
    else
        echo -e "  Redis: ${YELLOW}⚠️ CLI not found${NC}"
    fi
    
    # Check PostgreSQL
    if command -v psql &> /dev/null; then
        echo -e "  PostgreSQL: ${GREEN}✅ CLI found${NC}"
    else
        echo -e "  PostgreSQL: ${YELLOW}⚠️ CLI not found${NC}"
    fi
    
    echo ""
}

system_status() {
    check_requirements
    
    echo -e "${BLUE}📊 System Status${NC}"
    echo "─────────────────"
    
    # Laravel status
    if php artisan --version &> /dev/null; then
        LARAVEL_VERSION=$(php artisan --version)
        echo -e "  Laravel: ${GREEN}✅ $LARAVEL_VERSION${NC}"
    else
        echo -e "  Laravel: ${RED}❌ Not working${NC}"
        return 1
    fi
    
    # Database migration status
    echo -e "${BLUE}🗄️ Database Status${NC}"
    php artisan migrate:status 2>/dev/null || echo -e "  ${RED}❌ Database not accessible${NC}"
    
    echo ""
}

start_monitor() {
    echo -e "${CYAN}🔄 Starting system monitor...${NC}"
    echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
    echo ""
    php artisan kudos:monitor
}

process_queue() {
    echo -e "${CYAN}🔄 Processing queue jobs...${NC}"
    php artisan queue:work --once --verbose
}

show_logs() {
    echo -e "${CYAN}📄 Recent logs:${NC}"
    echo "─────────────────"
    if [ -f storage/logs/laravel.log ]; then
        tail -20 storage/logs/laravel.log
    else
        echo -e "${YELLOW}No logs found${NC}"
    fi
}

run_tests() {
    echo -e "${CYAN}🧪 Running comprehensive tests...${NC}"
    echo ""
    
    # Basic Laravel tests
    echo -e "${BLUE}1. Laravel Application Test${NC}"
    php artisan about
    echo ""
    
    # Database connectivity
    echo -e "${BLUE}2. Database Connectivity Test${NC}"
    php artisan migrate:status
    echo ""
    
    # Queue system test
    echo -e "${BLUE}3. Queue System Test${NC}"
    php artisan queue:failed
    echo ""
    
    # Service tests
    echo -e "${BLUE}4. Service Tests${NC}"
    php artisan kudos:test-services
}

initial_setup() {
    echo -e "${CYAN}🚀 Initial Setup${NC}"
    echo "─────────────────"
    
    # Install dependencies
    echo -e "${BLUE}1. Installing dependencies...${NC}"
    composer install --no-dev --optimize-autoloader
    
    # Environment setup
    echo -e "${BLUE}2. Environment setup...${NC}"
    if [ ! -f .env ]; then
        cp .env.example .env
        php artisan key:generate
    fi
    
    # Database setup
    echo -e "${BLUE}3. Database setup...${NC}"
    php artisan migrate --force
    
    # Cache optimization
    echo -e "${BLUE}4. Cache optimization...${NC}"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    echo -e "${GREEN}✅ Setup complete!${NC}"
}

create_demo_data() {
    echo -e "${CYAN}🎭 Creating demo data...${NC}"
    php artisan db:seed --class=PendingTaskSeeder
    echo -e "${GREEN}✅ Demo data created!${NC}"
}

test_api() {
    echo -e "${CYAN}🌐 Testing API endpoints...${NC}"
    echo ""
    
    # Check if server is running
    if curl -s http://localhost:8000 > /dev/null; then
        echo -e "${GREEN}✅ Laravel server is running${NC}"
        
        echo -e "${BLUE}Testing endpoints:${NC}"
        
        # Health check
        echo -n "  Health: "
        curl -s http://localhost:8000/api/health | grep -q "ok" && echo -e "${GREEN}✅${NC}" || echo -e "${RED}❌${NC}"
        
        # Tasks endpoint
        echo -n "  Tasks: "
        curl -s http://localhost:8000/api/v1/tasks | grep -q "\[\]" && echo -e "${GREEN}✅${NC}" || echo -e "${RED}❌${NC}"
        
    else
        echo -e "${RED}❌ Laravel server not running${NC}"
        echo -e "${YELLOW}Start with: php artisan serve${NC}"
    fi
}

# Main command dispatcher
case "${1:-help}" in
    "status")
        system_status
        ;;
    "monitor")
        start_monitor
        ;;
    "queue")
        process_queue
        ;;
    "logs")
        show_logs
        ;;
    "test")
        run_tests
        ;;
    "setup")
        initial_setup
        ;;
    "demo")
        create_demo_data
        ;;
    "api")
        test_api
        ;;
    "help"|*)
        show_help
        ;;
esac