# Kudos Orchestrator - Laravel AI Development Orchestrator

This is a Laravel 11 application that acts as an AI-powered development orchestrator. It reads tasks from a database, breaks them into milestones, generates code changes in a separate product repository, runs tests, and creates PRs for human review.

## Project Structure
- **Framework**: Laravel 11 (PHP 8.2+)
- **Database**: PostgreSQL with migrations for tasks, milestones, ai_logs, sprints
- **Queue**: Redis with Horizon for monitoring
- **AI Integration**: Claude Sonnet 4 API for various development agents
- **Git Integration**: Automated branch creation and PR management

## Key Components
- AI Agents: PM, BA, UX, Arch, Dev, QA, Doc agents
- Security: Path sandboxing and command allowlisting 
- Services: LlmClient, AgentClient, FilePatcher, CommandRunner, GitService
- Jobs: ProcessNextTask, ProcessBugTask, ProcessNewTask, ProcessUpgradeTask
- Two-repo model: Orchestrator edits separate product repository at `/srv/work/kudos-product`

## Development Guidelines
- Follow PSR-12 coding standards
- Use PHP 8.2 enums for status fields
- Implement proper error handling and logging
- All LLM interactions must be logged with run_id tracing
- Enforce strict security guardrails for file operations
- Maintain comprehensive test coverage

## Environment Requirements
- PHP 8.2+
- Composer
- PostgreSQL
- Redis
- Git
- Node.js/NPM for frontend assets