<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class CommandRunner
{
    private array $allowedCommands;
    private int $timeout;
    private string $workingDirectory;

    public function __construct()
    {
        $this->allowedCommands = config('app.security.allowed_commands');
        $this->timeout = config('app.security.command_timeout');
        $this->workingDirectory = config('app.product_path');
    }

    public function run(string $command, array $args = [], ?string $workingDir = null): array
    {
        // Skip git-related commands
        if ($command === 'git') {
            // Log::info('Skipping git command execution', [
            //     'command' => $this->buildCommand($command, $args),
            // ]);
            return [
                'success' => true,
                'exit_code' => 0,
                'stdout' => 'Git command skipped',
                'stderr' => '',
                'execution_time' => 0,
                'command' => $this->buildCommand($command, $args),
            ];
        }

        // Check for specific command: git remote get-url origin
        if ($command === 'git' && $args === ['remote', 'get-url', 'origin']) {
            // Simulate no remote origin defined
            // Log::warning('Skipping git remote get-url origin: No remote origin defined');
            // return [
            //     'success' => false,
            //     'exit_code' => 2,
            //     'stdout' => '',
            //     'stderr' => 'error: No such remote \"origin\"',
            //     'execution_time' => 0,
            //     'command' => $this->buildCommand($command, $args),
            // ];
        }

        $this->validateCommand($command);
        
        $workingDir = $workingDir ?? $this->workingDirectory;
        $this->validateWorkingDirectory($workingDir);

        $fullCommand = $this->buildCommand($command, $args);

        $startTime = microtime(true);

        try {
            $result = Process::timeout($this->timeout)
                ->path($workingDir)
                ->run($fullCommand);

            $executionTime = microtime(true) - $startTime;

            $output = [
                'success' => $result->successful(),
                'exit_code' => $result->exitCode(),
                'stdout' => $result->output(),
                'stderr' => $result->errorOutput(),
                'execution_time' => $executionTime,
                'command' => $fullCommand,
            ];

            if (!$result->successful()) {
                Log::warning('Command failed', $output);
            }

            return $output;

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('Command execution error', [
                'command' => $fullCommand,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime,
            ]);

            return [
                'success' => false,
                'exit_code' => -1,
                'stdout' => '',
                'stderr' => $e->getMessage(),
                'execution_time' => $executionTime,
                'command' => $fullCommand,
            ];
        }
    }

    public function runComposer(array $args): array
    {
        return $this->run('composer', $args);
    }

    public function runArtisan(array $args): array
    {
        return $this->run('php', array_merge(['artisan'], $args));
    }

    public function runTests(array $args = []): array
    {
        // Detect project type and run appropriate tests
        $composerPath = $this->workingDirectory . '/composer.json';
        $packageJsonPath = $this->workingDirectory . '/package.json';
        $phpunitPath = $this->workingDirectory . '/vendor/bin/phpunit';
        $phpunitConfigPath = $this->workingDirectory . '/phpunit.xml';
        $phpunitDistConfigPath = $this->workingDirectory . '/phpunit.xml.dist';

        // Generate phpunit.xml if it does not exist
        if (!file_exists($phpunitConfigPath) && !file_exists($phpunitDistConfigPath)) {
            Log::info('phpunit.xml not found, generating default configuration');
            $this->run('vendor/bin/phpunit', ['--generate-configuration']);
        }

        // PHP project with PHPUnit
        if (file_exists($composerPath) && file_exists($phpunitPath)) {
            $defaultArgs = []; // Removed --verbose as it is unsupported
            return $this->run('vendor/bin/phpunit', array_merge($defaultArgs, $args));
        }
        
        // Node.js project with npm test
        if (file_exists($packageJsonPath)) {
            // Check if package.json has a test script
            $packageContent = json_decode(file_get_contents($packageJsonPath), true);
            if (isset($packageContent['scripts']['test']) && 
                $packageContent['scripts']['test'] !== 'echo "Error: no test specified" && exit 1') {
                return $this->run('npm', ['test']);
            } else {
                Log::info('npm test script not configured, skipping tests', ['working_dir' => $this->workingDirectory]);
                return [
                    'success' => true,
                    'stdout' => 'Tests skipped - npm test script not configured',
                    'stderr' => '',
                    'exit_code' => 0
                ];
            }
        }
        
        // PHP project without PHPUnit
        if (file_exists($composerPath)) {
            Log::info('PHPUnit not found in PHP project, skipping tests', ['working_dir' => $this->workingDirectory]);
            return [
                'success' => true,
                'stdout' => 'Tests skipped - PHPUnit not installed',
                'stderr' => '',
                'exit_code' => 0
            ];
        }

        // No recognizable project structure
        Log::info('No test framework detected, skipping tests', ['working_dir' => $this->workingDirectory]);
        return [
            'success' => true,
            'stdout' => 'Tests skipped - no test framework detected',
            'stderr' => '',
            'exit_code' => 0
        ];
    }

    public function runNpm(array $args): array
    {
        return $this->run('npm', $args);
    }

    private function validateCommand(string $command): void
    {
        // Extract base command (remove path)
        $baseCommand = basename($command);
        
        // Check if command is in allowed list
        $allowed = false;
        foreach ($this->allowedCommands as $allowedCommand) {
            if ($baseCommand === $allowedCommand || str_ends_with($command, $allowedCommand)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \Exception("Command not allowed: {$command}");
        }

        // Additional security checks
        if (str_contains($command, ';') || str_contains($command, '|') || str_contains($command, '&')) {
            throw new \Exception("Command contains forbidden characters: {$command}");
        }
    }

    private function validateWorkingDirectory(string $workingDir): void
    {
        $realPath = realpath($workingDir);
        
        if (!$realPath) {
            throw new \Exception("Working directory does not exist: {$workingDir}");
        }

        // Ensure working directory is within allowed paths
        $allowedPaths = [
            realpath(config('app.product_path')),
            realpath(base_path()),
        ];

        $allowed = false;
        foreach ($allowedPaths as $allowedPath) {
            if ($allowedPath && str_starts_with($realPath, $allowedPath)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \Exception("Working directory not allowed: {$workingDir}");
        }
    }

    private function buildCommand(string $command, array $args): string
    {
        $escapedArgs = array_map('escapeshellarg', $args);
        return $command . (!empty($escapedArgs) ? ' ' . implode(' ', $escapedArgs) : '');
    }

    public function getLastOutput(): ?array
    {
        // This could be implemented to store and retrieve the last command output
        return null;
    }

    public function isCommandAllowed(string $command): bool
    {
        try {
            $this->validateCommand($command);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}