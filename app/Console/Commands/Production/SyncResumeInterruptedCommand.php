<?php

namespace App\Console\Commands\Production;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Models\SyncBatch;

class SyncResumeInterruptedCommand extends Command
{
    protected $signature = 'app:sync-resume-interrupted
                           {--force : Force resume even if batch is marked as completed}
                           {--max-age=24 : Maximum age in hours for interrupted batches to resume (default: 24)}';

    protected $description = 'Automatically detect and resume interrupted sync batches (useful for auto-recovery after power outage)';

    public function handle()
    {
        $maxAgeHours = (int) $this->option('max-age');
        $force = $this->option('force');

        $this->info('====================================================================');
        $this->info('Checking for Interrupted Sync Batches');
        $this->info('====================================================================');
        $this->line('');

        // Find interrupted batches within the max age
        $query = SyncBatch::where('status', 'interrupted')
            ->where('started_at', '>=', now()->subHours($maxAgeHours))
            ->orderBy('started_at', 'desc');

        // If force option, also include running batches that are stuck (older than 6 hours)
        if ($force) {
            $query->orWhere(function ($q) use ($maxAgeHours) {
                $q->where('status', 'running')
                    ->where('started_at', '>=', now()->subHours($maxAgeHours))
                    ->where('started_at', '<=', now()->subHours(6)); // Stuck for more than 6 hours
            });
        }

        $interruptedBatches = $query->get();

        if ($interruptedBatches->isEmpty()) {
            $this->info('✅ No interrupted batches found.');
            $this->line('');
            $this->comment('All sync operations are either completed or running normally.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$interruptedBatches->count()} interrupted batch(es):");
        $this->line('');

        // Display interrupted batches
        $headers = ['Batch ID', 'Status', 'Started At', 'Progress', 'Failed Tables'];
        $rows = [];

        foreach ($interruptedBatches as $batch) {
            $progress = $batch->total_tables > 0
                ? round(($batch->completed_tables / $batch->total_tables) * 100, 1) . '%'
                : '0%';

            $rows[] = [
                $batch->batch_id,
                $batch->status,
                $batch->started_at->format('Y-m-d H:i:s'),
                "{$batch->completed_tables}/{$batch->total_tables} ({$progress})",
                $batch->failed_tables,
            ];
        }

        $this->table($headers, $rows);
        $this->line('');

        // Resume the most recent interrupted batch
        $latestBatch = $interruptedBatches->first();

        $this->info("Resuming latest interrupted batch: {$latestBatch->batch_id}");
        $this->line('');

        // Call sync-all with resume option
        $exitCode = Artisan::call('app:sync-all', [
            '--resume' => $latestBatch->batch_id,
        ], $this->output);

        if ($exitCode === Command::SUCCESS) {
            $this->line('');
            $this->info('✅ Resume completed successfully!');
            return Command::SUCCESS;
        } else {
            $this->line('');
            $this->error('❌ Resume failed. Check logs for details.');
            return Command::FAILURE;
        }
    }
}
