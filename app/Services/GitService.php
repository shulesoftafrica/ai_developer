<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\Task;
use Exception;
use Illuminate\Support\Facades\Log;

class GitService
{
    private string $productRepo;
    private CommandRunner $commandRunner;

    public function __construct(CommandRunner $commandRunner)
    {
        $this->commandRunner = $commandRunner;
        $this->productRepo = config('app.product_path');
    }

    /**
     * Create a new branch for a task
     */
    public function createTaskBranch(Task $task): string
    {
        $branchName = $this->generateBranchName($task);
        
        try {
            // Ensure we're in the product repo and on main branch
            $this->commandRunner->run("git", ["checkout", "main"], $this->productRepo);
            $this->commandRunner->run("git", ["pull", "origin", "main"], $this->productRepo);
            
            // Create and checkout new branch
            $this->commandRunner->run("git", ["checkout", "-b", $branchName], $this->productRepo);
            
            Log::info("Created branch {$branchName} for task {$task->id}");
            
            return $branchName;
        } catch (Exception $e) {
            Log::error("Failed to create branch for task {$task->id}: " . $e->getMessage());
            throw new Exception("Failed to create branch: " . $e->getMessage());
        }
    }

    /**
     * Commit changes for a milestone
     */
    public function commitMilestone(Milestone $milestone, array $changedFiles = []): string
    {
        try {
            $commitMessage = $this->generateCommitMessage($milestone);
            
            // Add changed files or all changes if none specified
            if (!empty($changedFiles)) {
                foreach ($changedFiles as $file) {
                    $this->commandRunner->run("git", ["add", $file], $this->productRepo);
                }
            } else {
                $this->commandRunner->run("git", ["add", "."], $this->productRepo);
            }
            
            // Check if there are changes to commit
            $statusResult = $this->commandRunner->run("git", ["status", "--porcelain"], $this->productRepo);
            if (empty(trim($statusResult['stdout']))) {
                Log::info("No changes to commit for milestone {$milestone->id}");
                return '';
            }
            
            // Commit changes
            $commitResult = $this->commandRunner->run(
                "git", 
                ["commit", "-m", $commitMessage, "--format=%H"], 
                $this->productRepo
            );
            
            $commitHash = trim($commitResult['stdout']);
            
            Log::info("Committed milestone {$milestone->id} with hash: {$commitHash}");
            
            return $commitHash;
        } catch (Exception $e) {
            Log::error("Failed to commit milestone {$milestone->id}: " . $e->getMessage());
            throw new Exception("Failed to commit changes: " . $e->getMessage());
        }
    }

    /**
     * Create a pull request for a completed task
     */
    public function createPullRequest(Task $task, string $branchName): array
    {
        try {
            // Push the branch
            $this->commandRunner->run("git", ["push", "origin", $branchName], $this->productRepo);
            
            $prTitle = $this->generatePRTitle($task);
            $prBody = $this->generatePRBody($task);
            
            // Create PR using GitHub CLI (assuming it's configured)
            $prResult = $this->commandRunner->run(
                "gh", 
                ["pr", "create", "--title", $prTitle, "--body", $prBody, "--head", $branchName, "--base", "main"],
                $this->productRepo
            );
            
            $prOutput = $prResult['stdout'];
            
            // Extract PR number from output
            preg_match('/\/pull\/(\d+)/', $prOutput, $matches);
            $prNumber = $matches[1] ?? null;
            
            Log::info("Created PR #{$prNumber} for task {$task->id}");
            
            return [
                'pr_number' => $prNumber,
                'pr_url' => $this->extractPRUrl($prOutput),
                'branch_name' => $branchName
            ];
        } catch (Exception $e) {
            Log::error("Failed to create PR for task {$task->id}: " . $e->getMessage());
            throw new Exception("Failed to create pull request: " . $e->getMessage());
        }
    }

    /**
     * Get the current Git status
     */
    public function getStatus(): array
    {
        try {
            $branchResult = $this->commandRunner->run("git", ["branch", "--show-current"], $this->productRepo);
            $branch = trim($branchResult['stdout']);
            
            $statusResult = $this->commandRunner->run("git", ["status", "--porcelain"], $this->productRepo);
            $status = $statusResult['stdout'];
            
            $commitResult = $this->commandRunner->run("git", ["log", "-1", "--format=%H %s"], $this->productRepo);
            $lastCommit = trim($commitResult['stdout']);
            
            return [
                'current_branch' => $branch,
                'has_changes' => !empty(trim($status)),
                'changed_files' => $this->parseStatusOutput($status),
                'last_commit' => $lastCommit
            ];
        } catch (Exception $e) {
            Log::error("Failed to get Git status: " . $e->getMessage());
            return [
                'current_branch' => 'unknown',
                'has_changes' => false,
                'changed_files' => [],
                'last_commit' => 'unknown'
            ];
        }
    }

    /**
     * Reset to main branch (cleanup)
     */
    public function resetToMain(): void
    {
        try {
            $this->commandRunner->run("git", ["checkout", "main"], $this->productRepo);
            $this->commandRunner->run("git", ["reset", "--hard", "origin/main"], $this->productRepo);
            Log::info("Reset to main branch");
        } catch (Exception $e) {
            Log::error("Failed to reset to main: " . $e->getMessage());
            throw new Exception("Failed to reset to main branch: " . $e->getMessage());
        }
    }

    /**
     * Generate branch name for a task
     */
    private function generateBranchName(Task $task): string
    {
        $prefix = match($task->type) {
            \App\Enums\TaskType::FEATURE => 'feature',
            \App\Enums\TaskType::BUG => 'bugfix',
            \App\Enums\TaskType::UPGRADE => 'upgrade',
            default => 'task'
        };
        
        $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '-', strtolower($task->name));
        $safeName = preg_replace('/-+/', '-', $safeName);
        $safeName = trim($safeName, '-');
        
        return "{$prefix}/task-{$task->id}-{$safeName}";
    }

    /**
     * Generate commit message for a milestone
     */
    private function generateCommitMessage(Milestone $milestone): string
    {
        $task = $milestone->task;
        $prefix = match($task->type) {
            \App\Enums\TaskType::FEATURE => 'feat',
            \App\Enums\TaskType::BUG => 'fix',
            \App\Enums\TaskType::UPGRADE => 'upgrade',
            default => 'task'
        };
        
        return "{$prefix}: {$milestone->name}\n\nTask: {$task->name}\nMilestone: {$milestone->name}\nAgent: {$milestone->agent_type->value}";
    }

    /**
     * Generate PR title for a task
     */
    private function generatePRTitle(Task $task): string
    {
        $prefix = match($task->type) {
            \App\Enums\TaskType::FEATURE => '[FEATURE]',
            \App\Enums\TaskType::BUG => '[BUGFIX]',
            \App\Enums\TaskType::UPGRADE => '[UPGRADE]',
            default => '[TASK]'
        };
        
        return "{$prefix} {$task->name}";
    }

    /**
     * Generate PR body for a task
     */
    private function generatePRBody(Task $task): string
    {
        $milestones = $task->milestones()->orderBy('order')->get();
        
        $body = "## Task Description\n{$task->description}\n\n";
        $body .= "## Type\n{$task->type->value}\n\n";
        $body .= "## Priority\n{$task->priority}\n\n";
        
        if ($milestones->count() > 0) {
            $body .= "## Milestones Completed\n";
            foreach ($milestones as $milestone) {
                $status = $milestone->status->value;
                $emoji = $milestone->status === \App\Enums\MilestoneStatus::COMPLETED ? '✅' : '⏳';
                $body .= "- {$emoji} **{$milestone->name}** ({$milestone->agent_type->value}) - {$status}\n";
            }
            $body .= "\n";
        }
        
        $body .= "## AI Generated\nThis PR was automatically generated by the Kudos AI Development Orchestrator.\n";
        $body .= "Please review all changes carefully before merging.\n\n";
        $body .= "**Task ID:** {$task->id}\n";
        $body .= "**Sprint:** {$task->sprint_id}\n";
        
        return $body;
    }

    /**
     * Extract PR URL from GitHub CLI output
     */
    private function extractPRUrl(string $output): ?string
    {
        if (preg_match('/https:\/\/github\.com\/[^\s]+\/pull\/\d+/', $output, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Parse Git status output into array of files
     */
    private function parseStatusOutput(string $status): array
    {
        if (empty(trim($status))) {
            return [];
        }
        
        $files = [];
        $lines = explode("\n", trim($status));
        
        foreach ($lines as $line) {
            if (preg_match('/^(.{2})\s+(.+)$/', $line, $matches)) {
                $files[] = [
                    'status' => trim($matches[1]),
                    'file' => $matches[2]
                ];
            }
        }
        
        return $files;
    }

    /**
     * Validate that the product repository exists and is a Git repository
     */
    public function validateRepository(): bool
    {
        try {
            if (!is_dir($this->productRepo)) {
                Log::error("Product repository directory does not exist: {$this->productRepo}");
                return false;
            }
            
            $this->commandRunner->run("git", ["status"], $this->productRepo);
            return true;
        } catch (Exception $e) {
            Log::error("Product repository validation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get repository information
     */
    public function getRepositoryInfo(): array
    {
        try {
            $remoteResult = $this->commandRunner->run("git", ["remote", "get-url", "origin"], $this->productRepo);
            $remote = trim($remoteResult['stdout']);
            
            $branchesResult = $this->commandRunner->run("git", ["branch", "-r"], $this->productRepo);
            $branches = $branchesResult['stdout'];
            
            $commitsResult = $this->commandRunner->run("git", ["rev-list", "--count", "HEAD"], $this->productRepo);
            $commits = trim($commitsResult['stdout']);
            
            return [
                'remote_url' => $remote,
                'remote_branches' => array_filter(array_map('trim', explode("\n", $branches))),
                'total_commits' => (int) $commits,
                'repository_path' => $this->productRepo
            ];
        } catch (Exception $e) {
            Log::error("Failed to get repository info: " . $e->getMessage());
            return [
                'remote_url' => 'unknown',
                'remote_branches' => [],
                'total_commits' => 0,
                'repository_path' => $this->productRepo
            ];
        }
    }
}