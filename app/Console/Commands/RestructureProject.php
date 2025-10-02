<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ClaudeAgentService;

class RestructureProject extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'kudos:restructure
                          {--execute : Actually apply changes instead of dry run}
                          {--file= : Specific file to work on}
                          {--task= : Task to perform (improve, replace, analyze, etc.)}
                          {--context= : Additional context for the task}';

    /**
     * The console command description.
     */
    protected $description = 'Restructure Laravel project or work on specific files using Claude Agent Mode';

    protected ClaudeAgentService $agent;

    public function __construct(ClaudeAgentService $agent)
    {
        parent::__construct();
        $this->agent = $agent;
    }

    public function handle()
    {
        $file = $this->option('file');
        $task = $this->option('task');
        $additionalContext = $this->option('context');

        if ($file && $task) {
            // File-specific task
            $availableActions = [
                'analyze' => 'analyze_file - Analyze the file and provide insights',
                'improve' => 'improve_file - Make targeted improvements to existing code',
                'replace' => 'replace_file - Replace the entire file content',
                'add' => 'add_to_file - Add new content to the file',
                'remove' => 'remove_from_file - Remove specific content from the file'
            ];

            $context = "You are working on a specific file: '{$file}'. Task: {$task} the file.";
            if ($additionalContext) {
                $context .= " Additional context: {$additionalContext}";
            }

            $context .= "\n\nAvailable actions you can use in your JSON response:\n";
            foreach ($availableActions as $action => $description) {
                $context .= "- {$description}\n";
            }

            $context .= "\nGuidelines:\n";
            $context .= "- For small improvements, use 'improve_file' with specific improvements\n";
            $context .= "- For major changes, use 'replace_file' with complete new content\n";
            $context .= "- For additions, use 'add_to_file' with position and content\n";
            $context .= "- For removals, use 'remove_from_file' with content to remove\n";
            $context .= "- Always provide detailed reasoning in the action parameters\n";

            // Read the file content if it exists
            $filePath = config('agent.workspace_path') . '/' . $file;
            if (file_exists($filePath)) {
                $fileContent = file_get_contents($filePath);
                $context .= "\n\nCurrent file content:\n```php\n{$fileContent}\n```";
            } else {
                $context .= "\n\nNote: The file does not currently exist and needs to be created.";
            }

            $this->info("Working on file: {$file}");
            $this->info("Task: {$task}");
            $this->info("Available actions: " . implode(', ', array_keys($availableActions)));
        } else {
            // Project-level task (original functionality)
            $context = "install laravel v12 in current folder and implement Authentication system using Laravel breeze";
            $this->info("Performing project restructuring...");
        }

        // Step 1: Get plan from Claude
        $this->info("Requesting plan from Claude...");
        $plan = $this->agent->getRestructurePlan($context);

        if (empty($plan)) {
            $this->error("No plan received from Claude.");
            return;
        }

        $this->line("Plan received:");
        $this->line(json_encode($plan, JSON_PRETTY_PRINT));

        // Step 2: Execute or Dry Run
        $dryRun = !$this->option('execute');
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No actual changes will be made");
        }

        $results = $this->agent->executePlan($plan, $dryRun);

        $this->line("\nExecution results:");
        foreach ($results as $r) {
            $this->line("- " . $r);
        }
    }
}
