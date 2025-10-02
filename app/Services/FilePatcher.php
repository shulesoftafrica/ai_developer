<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FilePatcher
{
    private string $productPath;
    private array $allowedExtensions = [
        'php', 'js', 'ts', 'vue', 'blade.php', 'json', 'yaml', 'yml', 
        'md', 'txt', 'css', 'scss', 'sql', 'env.example'
    ];

    public function __construct()
    {
        $this->productPath = config('agent.workspace_path');
        
        // Log the configuration for debugging
        Log::info('FilePatcher: Product path configuration', [
            'configured_path' => $this->productPath,
            'config_exists' => !empty($this->productPath),
            'env_product_path' => env('PRODUCT_PATH')
        ]);
        
        $this->validateProductPath();
    }

    private function validateProductPath(): void
    {
        if (empty($this->productPath)) {
            throw new \Exception("Product path is not configured. Please set PRODUCT_PATH in .env file.");
        }

        // Create directory if it doesn't exist
        if (!File::isDirectory($this->productPath)) {
            Log::info('FilePatcher: Creating product path directory', ['path' => $this->productPath]);
            
            try {
                File::makeDirectory($this->productPath, 0755, true);
            } catch (\Exception $e) {
                throw new \Exception("Failed to create product path directory: {$this->productPath}. Error: {$e->getMessage()}");
            }
        }

        // Ensure path is absolute and points to a valid location
        $realPath = realpath($this->productPath);
        if (!$realPath) {
            throw new \Exception("Invalid product path: {$this->productPath} - path cannot be resolved");
        }

        // For local development, allow paths under the orchestrator directory or common development paths
        $allowedPaths = [
            '/srv/work/',  // Production path
            base_path(),   // Local development - orchestrator directory
            '/usr/share/', // Allow paths under /usr/share for development
            '/var/www/',   // Common web server path
            '/home/',      // User home directories
        ];

        $isValidPath = false;
        foreach ($allowedPaths as $allowedPath) {
            if (str_starts_with($realPath, $allowedPath)) {
                $isValidPath = true;
                Log::info('FilePatcher: Product path validated', [
                    'configured_path' => $this->productPath,
                    'resolved_path' => $realPath,
                    'allowed_under' => $allowedPath
                ]);
                break;
            }
        }

        if (!$isValidPath) {
            Log::error('FilePatcher: Invalid product path', [
                'configured_path' => $this->productPath,
                'resolved_path' => $realPath,
                'allowed_paths' => $allowedPaths
            ]);
            throw new \Exception("Invalid product path: {$this->productPath} (resolved: {$realPath}). Path must be under one of the allowed directories.");
        }
    }

    public function readFile(string $relativePath): string
    {
        $fullPath = $this->getSecurePath($relativePath);
        
        if (!File::exists($fullPath)) {
            throw new \Exception("File not found: {$relativePath}");
        }

        return File::get($fullPath);
    }

    public function writeFile(string $relativePath, string $content): bool
    {
        $fullPath = $this->getSecurePath($relativePath);
        
        // Create directory if it doesn't exist
        $directory = dirname($fullPath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        Log::info('Writing file', [
            'path' => $relativePath,
            'size' => strlen($content),
        ]);

        return File::put($fullPath, $content) !== false;
    }

    public function patchFile(string $relativePath, array $patches): array
    {
        $fullPath = $this->getSecurePath($relativePath);
        $results = [];

        if (!File::exists($fullPath)) {
            throw new \Exception("File not found: {$relativePath}");
        }

        $content = File::get($fullPath);
        $originalContent = $content;

        foreach ($patches as $index => $patch) {
            try {
                $content = $this->applyPatch($content, $patch);
                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'message' => 'Patch applied successfully',
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
                
                Log::warning('Patch failed', [
                    'file' => $relativePath,
                    'patch_index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Only write if at least one patch succeeded
        $successCount = collect($results)->where('success', true)->count();
        if ($successCount > 0 && $content !== $originalContent) {
            File::put($fullPath, $content);
            
            Log::info('File patched', [
                'path' => $relativePath,
                'patches_applied' => $successCount,
                'patches_failed' => count($patches) - $successCount,
            ]);
        }

        return $results;
    }

    private function applyPatch(string $content, array $patch): string
    {
        $type = $patch['type'] ?? 'replace';
        
        switch ($type) {
            case 'replace':
                return $this->applyReplacePatch($content, $patch);
            
            case 'insert':
                return $this->applyInsertPatch($content, $patch);
            
            case 'append':
                return $content . "\n" . ($patch['content'] ?? '');
            
            case 'prepend':
                return ($patch['content'] ?? '') . "\n" . $content;
            
            default:
                throw new \Exception("Unknown patch type: {$type}");
        }
    }

    private function applyReplacePatch(string $content, array $patch): string
    {
        $search = $patch['search'] ?? '';
        $replace = $patch['replace'] ?? '';

        if (empty($search)) {
            throw new \Exception('Replace patch requires search string');
        }

        if (!str_contains($content, $search)) {
            throw new \Exception('Search string not found in file');
        }

        return str_replace($search, $replace, $content);
    }

    private function applyInsertPatch(string $content, array $patch): string
    {
        $after = $patch['after'] ?? '';
        $insertContent = $patch['content'] ?? '';

        if (empty($after)) {
            throw new \Exception('Insert patch requires after string');
        }

        if (!str_contains($content, $after)) {
            throw new \Exception('After string not found in file');
        }

        return str_replace($after, $after . "\n" . $insertContent, $content);
    }

    public function listFiles(string $relativePath = '', array $extensions = null): array
    {
        $fullPath = $this->getSecurePath($relativePath);
        $extensions = $extensions ?? $this->allowedExtensions;

        if (!File::isDirectory($fullPath)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = $file->getExtension();
                if (in_array($extension, $extensions) || in_array(basename($file), $extensions)) {
                    $relativePath = str_replace($this->productPath . '/', '', $file->getPathname());
                    $files[] = $relativePath;
                }
            }
        }

        return $files;
    }

    public function fileExists(string $relativePath): bool
    {
        return File::exists($this->getSecurePath($relativePath));
    }

    public function deleteFile(string $relativePath): bool
    {
        $fullPath = $this->getSecurePath($relativePath);
        
        if (!File::exists($fullPath)) {
            return false;
        }

        Log::info('Deleting file', ['path' => $relativePath]);
        
        return File::delete($fullPath);
    }

    private function getSecurePath(string $relativePath): string
    {
        // Remove any leading slashes or directory traversal attempts
        $relativePath = ltrim($relativePath, '/');
        $relativePath = str_replace(['../', '..\\'], '', $relativePath);

        $fullPath = $this->productPath . '/' . $relativePath;
        
        // For empty relative path (root directory), we want to check the product path itself
        if (empty($relativePath)) {
            $realPath = realpath($this->productPath);
        } else {
            // Create directory if it doesn't exist for proper realpath resolution
            $directory = dirname($fullPath);
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
            $realPath = realpath($directory);
        }

        // Ensure the resolved path is within the product directory
        $productRealPath = realpath($this->productPath);
        if (!$realPath || !$productRealPath || !str_starts_with($realPath, $productRealPath)) {
            Log::error('Path traversal detection', [
                'relative_path' => $relativePath,
                'full_path' => $fullPath,
                'real_path' => $realPath,
                'product_real_path' => $productRealPath,
                'directory' => dirname($fullPath)
            ]);
            throw new \Exception("Path traversal attempt detected: {$relativePath}");
        }

        return $fullPath;
    }

    public function getProductPath(): string
    {
        return $this->productPath;
    }
}