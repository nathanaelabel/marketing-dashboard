<?php

namespace App\Console\Commands\Production;

use Illuminate\Console\Command;
use App\Models\SyncBatch;
use App\Models\SyncProgress;

class SyncStatusCommand extends Command
{
    protected $signature = 'app:sync-status 
                           {batch_id? : The batch ID to check status for}
                           {--latest : Show status of the latest batch}
                           {--all : Show all batches}
                           {--failed : Show only failed tables}';
    
    protected $description = 'Check the status of sync operations';

    public function handle()
    {
        $batchId = $this->argument('batch_id');
        $showLatest = $this->option('latest');
        $showAll = $this->option('all');
        $showFailed = $this->option('failed');

        if ($showAll) {
            $this->showAllBatches();
            return Command::SUCCESS;
        }

        if ($showLatest || !$batchId) {
            $batch = SyncBatch::orderBy('started_at', 'desc')->first();
            if (!$batch) {
                $this->error('No sync batches found.');
                return Command::FAILURE;
            }
            $batchId = $batch->batch_id;
        } else {
            $batch = SyncBatch::find($batchId);
            if (!$batch) {
                $this->error("Batch ID [{$batchId}] not found.");
                return Command::FAILURE;
            }
        }

        $this->showBatchDetails($batch, $showFailed);
        return Command::SUCCESS;
    }

    private function showAllBatches()
    {
        $this->info('====================================================================');
        $this->info('All Sync Batches');
        $this->info('====================================================================');
        $this->line('');

        $batches = SyncBatch::orderBy('started_at', 'desc')->take(20)->get();

        if ($batches->isEmpty()) {
            $this->warn('No sync batches found.');
            return;
        }

        $headers = ['Batch ID', 'Status', 'Started', 'Duration', 'Progress', 'Failed'];
        $rows = [];

        foreach ($batches as $batch) {
            $progress = "{$batch->completed_tables}/{$batch->total_tables}";
            $percentage = $batch->getProgressPercentage();
            $duration = $batch->duration_seconds 
                ? gmdate('H:i:s', $batch->duration_seconds) 
                : ($batch->status === 'running' ? 'Running...' : 'N/A');

            $rows[] = [
                $batch->batch_id,
                $this->colorizeStatus($batch->status),
                $batch->started_at->format('Y-m-d H:i:s'),
                $duration,
                "{$progress} ({$percentage}%)",
                $batch->failed_tables,
            ];
        }

        $this->table($headers, $rows);
        $this->line('');
        $this->comment('Use: php artisan app:sync-status {batch_id} to see details');
        $this->comment('Use: php artisan app:sync-all --resume={batch_id} to resume a batch');
    }

    private function showBatchDetails(SyncBatch $batch, bool $showFailedOnly = false)
    {
        $this->info('====================================================================');
        $this->info("Sync Batch Details: {$batch->batch_id}");
        $this->info('====================================================================');
        $this->line('');

        // Batch summary
        $this->info('Batch Summary:');
        $this->line("  Status: " . $this->colorizeStatus($batch->status));
        $this->line("  Started: {$batch->started_at->format('Y-m-d H:i:s')}");
        
        if ($batch->completed_at) {
            $this->line("  Completed: {$batch->completed_at->format('Y-m-d H:i:s')}");
        }
        
        if ($batch->duration_seconds) {
            $duration = gmdate('H:i:s', $batch->duration_seconds);
            $this->line("  Duration: {$duration}");
        }
        
        $percentage = $batch->getProgressPercentage();
        $this->line("  Progress: {$batch->completed_tables}/{$batch->total_tables} tables ({$percentage}%)");
        $this->line("  Failed: {$batch->failed_tables} tables");
        $this->line('');

        // Command options
        if ($batch->command_options) {
            $this->info('Command Options:');
            foreach ($batch->command_options as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value) ?: 'none';
                }
                $this->line("  {$key}: " . ($value ?: 'none'));
            }
            $this->line('');
        }

        // Table progress
        $query = SyncProgress::where('batch_id', $batch->batch_id);
        
        if ($showFailedOnly) {
            $query->where('status', 'failed');
            $this->info('Failed Tables:');
        } else {
            $this->info('Table Progress:');
        }

        $progress = $query->orderBy('started_at', 'desc')->get();

        if ($progress->isEmpty()) {
            $this->warn($showFailedOnly ? 'No failed tables.' : 'No progress data available.');
            return;
        }

        $headers = ['Connection', 'Table', 'Status', 'Records', 'Duration', 'Error'];
        $rows = [];

        foreach ($progress as $p) {
            $duration = $p->duration_seconds 
                ? gmdate('H:i:s', $p->duration_seconds) 
                : ($p->status === 'in_progress' ? 'Running...' : 'N/A');
            
            $records = $p->records_processed 
                ? number_format($p->records_processed) . ($p->records_skipped ? " (-{$p->records_skipped})" : '')
                : 'N/A';

            $error = $p->error_message 
                ? (strlen($p->error_message) > 50 ? substr($p->error_message, 0, 47) . '...' : $p->error_message)
                : '-';

            $rows[] = [
                $p->connection_name,
                $p->table_name,
                $this->colorizeStatus($p->status),
                $records,
                $duration,
                $error,
            ];
        }

        $this->table($headers, $rows);
        $this->line('');

        // Show resume command if batch is interrupted or has failures
        if ($batch->status === 'interrupted' || $batch->failed_tables > 0) {
            $this->comment('To resume this batch, run:');
            $this->line("  php artisan app:sync-all --resume={$batch->batch_id}");
            $this->line('');
        }

        // Show failed tables command if there are failures
        if ($batch->failed_tables > 0 && !$showFailedOnly) {
            $this->comment('To see only failed tables, run:');
            $this->line("  php artisan app:sync-status {$batch->batch_id} --failed");
            $this->line('');
        }
    }

    private function colorizeStatus(string $status): string
    {
        return match($status) {
            'completed' => "<fg=green>{$status}</>",
            'running', 'in_progress' => "<fg=yellow>{$status}</>",
            'failed' => "<fg=red>{$status}</>",
            'interrupted' => "<fg=magenta>{$status}</>",
            'pending' => "<fg=gray>{$status}</>",
            'skipped' => "<fg=cyan>{$status}</>",
            default => $status,
        };
    }
}
