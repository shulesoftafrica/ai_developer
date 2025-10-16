<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAgentService
{
    protected ?string $apiKey;
    protected string $apiUrl = 'https://api.anthropic.com/v1/messages';
    protected ?string $model;
    protected string $workspace;


    public function __construct()
    {
        $this->apiKey = env('ANTHROPIC_API_KEY');
        $this->model = env('LLM_MODEL');
        $this->workspace = env('PRODUCT_PATH', '/usr/share/nginx/html/aicoder/learn');
    }

    protected function callClaude(string $prompt): string
    {
        $url = $this->apiUrl;

        // Enable streaming without rewind
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => env('ANTHROPIC_VERSION'),
        ])->withOptions([
            'stream' => true,
            'timeout' => 3000, // increase for big plans
        ])->post($url, [
            'model' => $this->model,
            'max_tokens' => 40000,
            'stream' => true,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        $stream = $response->getBody();
        $rawStreamData = '';

        // First, collect all raw stream data
        while (!$stream->eof()) {
            $chunk = $stream->read(1024);
            if ($chunk === false) break;
            $rawStreamData .= $chunk;
        }

        // Log raw stream data for debugging
        Log::info('Raw Claude stream data', ['data' => substr($rawStreamData, 0, 100) . (strlen($rawStreamData) > 100 ? '... (truncated)' : '')]);

        $accumulatedText = '';

        // Parse the collected SSE data line by line
        $lines = explode("\n", $rawStreamData);
        foreach ($lines as $line) {
            $line = trim($line);

            // Parse SSE format: "data: {...}"
            if (strpos($line, 'data: ') === 0) {
                $jsonData = substr($line, 6); // Remove 'data: ' prefix
                $data = json_decode($jsonData, true);

                if ($data && isset($data['type']) && $data['type'] === 'content_block_delta') {
                    if (isset($data['delta']['type']) && $data['delta']['type'] === 'text_delta') {
                        $accumulatedText .= $data['delta']['text'];
                    }
                }
            }
        }

        Log::info('Extracted text from Claude stream', ['text' => $accumulatedText]);

        return $accumulatedText;
    }

    /**
     * Get a structured "plan" from Claude
     */
    public function getRestructurePlan(string $context): array
    {
        $prompt = "Restructure the response in JSON only with a list of programming language-style actions.
Example:
{
    \"actions\": [
        {\"action\": \"create_folder\", \"path\": \"app/Http/Controllers\"},
        {\"action\": \"create_file\", \"path\": \"app/Models/User.php\", \"content\": \"<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass User extends Model\n{\n    //\n}\"},
        {\"action\": \"move_file\", \"from\": \"app/Models/Milestone.php\", \"to\": \"app/Milestones/Milestone.php\"}
    ]
}
IMPORTANT: For create_file and update_file actions, always include complete  file content.
Respond with raw JSON only. Do not include Markdown fences, explanations, or extra text.

Context: {$context}";

        // Get the extracted text from streaming response
        $jsonString = trim($this->callClaude($prompt));

        if (empty($jsonString)) {
            Log::warning("No content received from Claude streaming response");
            return [];
        }

        // Check if JSON appears to be truncated (doesn't end with closing brace)
        $lastChar = substr($jsonString, -1);
        if ($lastChar !== '}') {
            Log::warning("JSON appears to be truncated, attempting to fix", ['last_char' => $lastChar, 'json_length' => strlen($jsonString)]);

            // Try to find the last complete JSON object by counting braces
            $braceCount = 0;
            $lastValidPos = -1;
            $inString = false;
            $escapeNext = false;

            for ($i = 0; $i < strlen($jsonString); $i++) {
                $char = $jsonString[$i];

                if ($escapeNext) {
                    $escapeNext = false;
                    continue;
                }

                if ($char === '\\') {
                    $escapeNext = true;
                    continue;
                }

                if ($char === '"' && !$escapeNext) {
                    $inString = !$inString;
                    continue;
                }

                if (!$inString) {
                    if ($char === '{') {
                        $braceCount++;
                    } elseif ($char === '}') {
                        $braceCount--;
                        if ($braceCount === 0) {
                            $lastValidPos = $i;
                        }
                    }
                }
            }

            if ($lastValidPos > 0 && $braceCount === 0) {
                $jsonString = substr($jsonString, 0, $lastValidPos + 1);
                Log::info("Trimmed JSON to last complete object", ['new_length' => strlen($jsonString)]);
            } elseif ($braceCount > 0) {
                // Try to close unclosed braces
                $jsonString .= str_repeat('}', $braceCount);
                Log::info("Added missing closing braces", ['braces_added' => $braceCount]);
            }
        }

        // Decode JSON directly
        $json = json_decode($jsonString, true);
        if (!$json || !isset($json['actions'])) {
            Log::warning("Failed to decode JSON plan", ['json' => substr($jsonString, 0, 500) . (strlen($jsonString) > 500 ? '... (truncated)' : '')]);
            return [];
        }

        Log::info('Successfully parsed restructure plan', ['actions_count' => count($json['actions'])]);

        return $json['actions'];
    }



    /**
     * Get a structured test plan from Claude
     */
    public function getTestPlan(string $context): array
    {
        $prompt = "Create comprehensive testing features and unit tests based on the provided context.
Respond with JSON only containing a list of programming language-style actions for creating test files.

Example format:
{
    \"actions\": [
        {
            \"action\": \"create_folder\",
            \"path\": \"tests/Unit\"
        },
        {
            \"action\": \"create_file\",
            \"path\": \"tests/Unit/ExampleTest.php\",
            \"content\": \"<?php\n\nnamespace Tests\\Unit;\n\nuse PHPUnit\\Framework\\TestCase;\n\nclass ExampleTest extends TestCase\n{\n    public function test_example()\n    {\n        \$this->assertTrue(true);\n    }\n}\"
        },
        {
            \"action\": \"create_file\",
            \"path\": \"tests/Feature/ExampleFeatureTest.php\",
            \"content\": \"<?php\n\nnamespace Tests\\Feature;\n\nuse Tests\\TestCase;\n\nclass ExampleFeatureTest extends TestCase\n{\n    public function test_example_feature()\n    {\n        \$response = \$this->get('/');\n\n        \$response->assertStatus(200);\n    }\n}\"
        }
    ]
}

IMPORTANT:
- For create_file actions, always include complete, functioning test code
- Use appropriate testing frameworks and conventions for the detected programming language
- Create unit tests, integration tests, and feature tests as needed
- Follow language-specific testing best practices and naming conventions
- Include realistic test cases that would actually validate the implemented functionality
- Generate proper test data, mocks, and fixtures when needed

Respond with raw JSON only. Do not include Markdown fences, explanations, or extra text.

Context: {$context}";

        // Get the extracted text from streaming response
        $jsonString = trim($this->callClaude($prompt));

        if (empty($jsonString)) {
            Log::warning("No content received from Claude streaming response for test plan");
            return [];
        }

        // Check if JSON appears to be truncated (doesn't end with closing brace)
        $lastChar = substr($jsonString, -1);
        if ($lastChar !== '}') {
            Log::warning("Test plan JSON appears to be truncated, attempting to fix", ['last_char' => $lastChar, 'json_length' => strlen($jsonString)]);

            // Try to find the last complete JSON object by counting braces
            $braceCount = 0;
            $lastValidPos = -1;
            $inString = false;
            $escapeNext = false;

            for ($i = 0; $i < strlen($jsonString); $i++) {
                $char = $jsonString[$i];

                if ($escapeNext) {
                    $escapeNext = false;
                    continue;
                }

                if ($char === '\\') {
                    $escapeNext = true;
                    continue;
                }

                if ($char === '"' && !$escapeNext) {
                    $inString = !$inString;
                    continue;
                }

                if (!$inString) {
                    if ($char === '{') {
                        $braceCount++;
                    } elseif ($char === '}') {
                        $braceCount--;
                        if ($braceCount === 0) {
                            $lastValidPos = $i;
                        }
                    }
                }
            }

            if ($lastValidPos > 0 && $braceCount === 0) {
                $jsonString = substr($jsonString, 0, $lastValidPos + 1);
                Log::info("Trimmed test plan JSON to last complete object", ['new_length' => strlen($jsonString)]);
            } elseif ($braceCount > 0) {
                // Try to close unclosed braces
                $jsonString .= str_repeat('}', $braceCount);
                Log::info("Added missing closing braces to test plan", ['braces_added' => $braceCount]);
            }
        }

        // Decode JSON directly
        $json = json_decode($jsonString, true);
        if (!$json || !isset($json['actions'])) {
            Log::warning("Failed to decode test plan JSON", ['json' => substr($jsonString, 0, 500) . (strlen($jsonString) > 500 ? '... (truncated)' : '')]);
            return [];
        }

        Log::info('Successfully parsed test plan', ['actions_count' => count($json['actions'])]);

        return $json['actions'];
    }

    /**
     * Execute the structured plan safely
     */
    public function executePlan(array $actions): array
    {
        $results = [];
        $currentDir = $this->workspace;

        foreach ($actions as $action) {
            $type = $action['action'] ?? null;

            switch ($type) {
                case 'create_folder':
                    $path = $this->workspace . '/' . ltrim($action['path'], '/');
                    if (!is_dir($path)) {
                        mkdir($path, 0755, true);
                        $results[] = "Created folder: {$path}";
                    } else {
                        $results[] = "Folder already exists: {$path}";
                    }
                    break;

                case 'move_file':
                    $from = $this->workspace . '/' . ltrim($action['from'], '/');
                    $to   = $this->workspace . '/' . ltrim($action['to'], '/');
                    if (file_exists($from)) {
                        @mkdir(dirname($to), 0755, true);
                        rename($from, $to);
                        $results[] = "Moved file: {$from} → {$to}";
                    } else {
                        $results[] = "Source file not found: {$from}";
                    }
                    break;

                case 'create_file':
                    $path = $currentDir . '/' . ltrim($action['path'], '/');
                    @mkdir(dirname($path), 0755, true);
                    if (!file_exists($path)) {
                        $content = $action['content'] ?? $this->defaultStub($path);
                        Log::info('Creating file with content', [
                            'path' => $path,
                            'has_action_content' => isset($action['content']),
                            'content_length' => strlen($content),
                            'content_preview' => substr($content, 0, 100)
                        ]);
                        file_put_contents($path, $content);
                        $results[] = "Created file: {$path} (content: " . strlen($content) . " bytes)";
                    } else {
                        $results[] = "File already exists: {$path}";
                    }
                    break;

                case 'update_file':
                    $path = $currentDir . '/' . ltrim($action['path'], '/');
                    if (file_exists($path)) {
                        $content = $action['content'] ?? $action['update'] ?? '';
                        file_put_contents($path, $content);
                        $results[] = "Updated file: {$path}";
                    } else {
                        $results[] = "File not found for update: {$path}";
                    }
                    break;

                case 'run_command':
                    // Note: Running arbitrary commands can be dangerous
                    // In a real application, you should validate/sanitize commands
                    $originalDir = getcwd();
                    chdir($currentDir);
                    $output = shell_exec($action['command'] . ' 2>&1');
                    chdir($originalDir); // Restore original directory
                    $results[] = "Ran command: {$action['command']} (in {$currentDir}) - Output: " . ($output ?: 'No output');
                    if ($output === null) {
                        Log::warning("Command failed or returned no output", ['command' => $action['command'], 'directory' => $currentDir]);
                    }
                    break;

                case 'change_directory':
                    $newDir = $currentDir . '/' . ltrim($action['path'], '/');
                    if (is_dir($newDir)) {
                        $currentDir = realpath($newDir);
                        $results[] = "Changed directory to: {$currentDir}";
                    } else {
                        $results[] = "Directory does not exist: {$newDir}";
                    }
                    break;

                case 'install_package':
                    $originalDir = getcwd();
                    chdir($currentDir);
                    $output = shell_exec($action['command'] . ' 2>&1');
                    chdir($originalDir);
                    $results[] = "Installed package: {$action['command']} - Output: " . ($output ?: 'No output');
                    if ($output === null) {
                        Log::warning("Package installation failed", ['command' => $action['command'], 'directory' => $currentDir]);
                    }
                    break;

                case 'run_artisan':
                    $originalDir = getcwd();
                    chdir($currentDir);
                    $output = shell_exec($action['command'] . ' 2>&1');
                    chdir($originalDir);
                    $results[] = "Ran artisan: {$action['command']} - Output: " . ($output ?: 'No output');
                    if ($output === null) {
                        Log::warning("Artisan command failed", ['command' => $action['command'], 'directory' => $currentDir]);
                    }
                    break;

                case 'install_npm':
                    $originalDir = getcwd();
                    chdir($currentDir);
                    $output = shell_exec($action['command'] . ' 2>&1');
                    chdir($originalDir);
                    $results[] = "Ran npm install: {$action['command']} - Output: " . ($output ?: 'No output');
                    if ($output === null) {
                        Log::warning("NPM install failed", ['command' => $action['command'], 'directory' => $currentDir]);
                    }
                    break;

                case 'run_npm':
                    $originalDir = getcwd();
                    chdir($currentDir);
                    $output = shell_exec($action['command'] . ' 2>&1');
                    chdir($originalDir);
                    $results[] = "Ran npm: {$action['command']} - Output: " . ($output ?: 'No output');
                    if ($output === null) {
                        Log::warning("NPM command failed", ['command' => $action['command'], 'directory' => $currentDir]);
                    }
                    break;

                case 'analyze_file':
                    $path = $currentDir . '/' . ltrim($action['path'], '/');
                    if (file_exists($path)) {
                        $content = file_get_contents($path);
                        $analysis = $action['analysis'] ?? 'General file analysis needed';
                        $results[] = "Analyzed file: {$path} - {$analysis}";
                        Log::info("File analysis completed", ['file' => $path, 'analysis' => $analysis]);
                    } else {
                        $results[] = "File not found for analysis: {$path}";
                    }
                    break;

                case 'improve_file':
                    $path = $currentDir . '/' . ltrim($action['path'], '/');
                    if (file_exists($path)) {
                        $improvements = $action['improvements'] ?? [];
                        $content = file_get_contents($path);

                        // Apply improvements (this is a simple example - could be more sophisticated)
                        foreach ($improvements as $improvement) {
                            if (isset($improvement['replace']) && isset($improvement['with'])) {
                                $content = str_replace($improvement['replace'], $improvement['with'], $content);
                            }
                        }

                        file_put_contents($path, $content);
                        $results[] = "Improved file: {$path}";
                        Log::info("File improvements applied", ['file' => $path, 'improvement_count' => count($improvements)]);
                    } else {
                        $results[] = "File not found for improvement: {$path}";
                    }
                    break;

                case 'replace_file':
                    $path = $currentDir . '/' . ltrim($action['path'], '/');
                    @mkdir(dirname($path), 0755, true);
                    $newContent = $action['content'] ?? '';
                    file_put_contents($path, $newContent);
                    $results[] = "Replaced entire file: {$path}";
                    Log::info("File completely replaced", ['file' => $path]);
                    break;

                case 'add_to_file':
                    $path = $currentDir . '/' . ltrim($action['path'], '/');
                    if (file_exists($path)) {
                        $content = file_get_contents($path);
                        $newContent = $action['content'] ?? '';
                        $position = $action['position'] ?? 'end';

                        if ($position === 'end') {
                            $content .= "\n" . $newContent;
                        } elseif ($position === 'start') {
                            $content = $newContent . "\n" . $content;
                        } elseif (isset($action['after'])) {
                            $content = str_replace($action['after'], $action['after'] . "\n" . $newContent, $content);
                        }

                        file_put_contents($path, $content);
                        $results[] = "Added content to file: {$path}";
                        Log::info("Content added to file", ['file' => $path, 'position' => $position]);
                    } else {
                        $results[] = "File not found for adding content: {$path}";
                    }
                    break;

                case 'remove_from_file':
                    $path = $currentDir . '/' . ltrim($action['path'], '/');
                    if (file_exists($path)) {
                        $content = file_get_contents($path);
                        $toRemove = $action['content'] ?? '';

                        $content = str_replace($toRemove, '', $content);
                        file_put_contents($path, $content);
                        $results[] = "Removed content from file: {$path}";
                        Log::info("Content removed from file", ['file' => $path, 'removed_content' => substr($toRemove, 0, 100)]);
                    } else {
                        $results[] = "File not found for content removal: {$path}";
                    }
                    break;

                default:
                    $results[] = "Unknown action: " . json_encode($action);
                    Log::warning("Unknown action in Claude plan", $action);
            }
        }

        return $results;
    }

    protected function defaultStub(string $path): string
    {
        $pathInfo = pathinfo($path);
        $extension = $pathInfo['extension'] ?? '';
        $filename = $pathInfo['filename'] ?? 'Unknown';

        if ($extension === 'php') {
            // Determine namespace and class name from path
            $relativePath = str_replace($this->workspace . '/', '', $path);
            $namespace = '';
            $className = $filename;

            if (strpos($relativePath, 'app/') === 0) {
                $namespace = 'App';
                $parts = explode('/', dirname(substr($relativePath, 4)));
                $parts = array_filter($parts, fn($part) => $part !== '.');
                if (!empty($parts)) {
                    $namespace .= '\\' . implode('\\', $parts);
                }
            }

            $stub = "<?php\n\n";
            if ($namespace) {
                $stub .= "namespace {$namespace};\n\n";
            }

            // Determine class type based on path
            if (strpos($relativePath, 'app/Models/') === 0) {
                $stub .= "use Illuminate\\Database\\Eloquent\\Model;\n\n";
                $stub .= "class {$className} extends Model\n{\n    protected \$fillable = [];\n}\n";
            } elseif (strpos($relativePath, 'app/Http/Controllers/') === 0) {
                $stub .= "use Illuminate\\Http\\Request;\n";
                $stub .= "use App\\Http\\Controllers\\Controller;\n\n";
                $stub .= "class {$className} extends Controller\n{\n    public function index()\n    {\n        //\n    }\n}\n";
            } elseif (strpos($relativePath, 'database/migrations/') === 0) {
                $stub .= "use Illuminate\\Database\\Migrations\\Migration;\n";
                $stub .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
                $stub .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
                $stub .= "return new class extends Migration\n{\n    public function up()\n    {\n        //\n    }\n\n    public function down()\n    {\n        //\n    }\n};\n";
            } elseif (strpos($relativePath, 'app/Http/Requests/') === 0) {
                $stub .= "use Illuminate\\Foundation\\Http\\FormRequest;\n\n";
                $stub .= "class {$className} extends FormRequest\n{\n    public function authorize()\n    {\n        return true;\n    }\n\n    public function rules()\n    {\n        return [];\n    }\n}\n";
            } elseif (strpos($relativePath, 'routes/') === 0) {
                $stub .= "use Illuminate\\Support\\Facades\\Route;\n\n";
                $stub .= "// Routes for {$filename}\n";
            } else {
                $stub .= "class {$className}\n{\n    //\n}\n";
            }

            return $stub;
        }

        if (str_contains($path, '.blade.php')) {
            return "<h1>Generated View</h1>\n<p>This is a placeholder view.</p>";
        }

        return "// Generated file: {$path}\n";
    }
}
