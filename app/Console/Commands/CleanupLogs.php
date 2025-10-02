<?php

namespace App\Console\Commands;

use App\Models\AiLog;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kudos:cleanup-logs
                            {--days=30 : Number of days to keep logs}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old AI logs and system logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = Carbon::now()->subDays($days);
        
        $this->info("Cleaning up logs older than {$days} days (before {$cutoffDate->format('Y-m-d H:i:s')})");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        // Clean up AI logs
        $this->cleanupAiLogs($cutoffDate, $dryRun);

        // Clean up Laravel logs
        $this->cleanupLaravelLogs($days, $dryRun);

        $this->info('Log cleanup completed!');
        
        return 0;
    }

    private function cleanupAiLogs(Carbon $cutoffDate, bool $dryRun): void
    {
        $this->info('Cleaning up AI logs...');

        $query = AiLog::where('created_at', '<', $cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->line('No old AI logs found.');
            return;
        }

        $this->line("Found {$count} AI logs to delete.");

        if (!$dryRun) {
            // Delete in batches to avoid memory issues
            $deleted = 0;
            $batchSize = 1000;

            while (true) {
                $batch = $query->limit($batchSize)->get();
                
                if ($batch->isEmpty()) {
                    break;
                }

                foreach ($batch as $log) {
                    $log->delete();
                    $deleted++;
                }

                $this->line("Deleted {$deleted}/{$count} AI logs...");
            }

            $this->info("✓ Deleted {$deleted} AI logs");
        } else {
            $this->line("Would delete {$count} AI logs");
        }
    }

    private function cleanupLaravelLogs(int $days, bool $dryRun): void
    {
        $this->info('Cleaning up Laravel logs...');

        $logPath = storage_path('logs');
        
        if (!is_dir($logPath)) {
            $this->warn('Log directory not found: ' . $logPath);
            return;
        }

        $files = glob($logPath . '/laravel-*.log');
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $filesToDelete = [];

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                $filesToDelete[] = $file;
            }
        }

        if (empty($filesToDelete)) {
            $this->line('No old Laravel log files found.');
            return;
        }

        $this->line('Found ' . count($filesToDelete) . ' old log files:');
        
        foreach ($filesToDelete as $file) {
            $fileName = basename($file);
            $fileDate = date('Y-m-d H:i:s', filemtime($file));
            $fileSize = $this->formatBytes(filesize($file));
            
            $this->line("  - {$fileName} (modified: {$fileDate}, size: {$fileSize})");
            
            if (!$dryRun) {
                if (unlink($file)) {
                    $this->line("    ✓ Deleted");
                } else {
                    $this->error("    ✗ Failed to delete");
                }
            }
        }

        if ($dryRun) {
            $this->line('Would delete ' . count($filesToDelete) . ' log files');
        } else {
            $this->info('✓ Completed Laravel log cleanup');
        }
    }

    private function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }
}