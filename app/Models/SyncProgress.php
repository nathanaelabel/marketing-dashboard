<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncProgress extends Model
{
    protected $table = 'sync_progress';

    protected $fillable = [
        'batch_id',
        'connection_name',
        'table_name',
        'model_name',
        'status',
        'records_processed',
        'records_skipped',
        'error_message',
        'started_at',
        'completed_at',
        'duration_seconds',
        'retry_count',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'records_processed' => 'integer',
        'records_skipped' => 'integer',
        'duration_seconds' => 'integer',
        'retry_count' => 'integer',
    ];

    /**
     * Get the batch that owns this progress entry
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class, 'batch_id', 'batch_id');
    }

    /**
     * Mark as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(int $recordsProcessed, int $recordsSkipped = 0): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;
        
        $this->update([
            'status' => 'completed',
            'records_processed' => $recordsProcessed,
            'records_skipped' => $recordsSkipped,
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;
        
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
            'duration_seconds' => $duration,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark as skipped
     */
    public function markAsSkipped(string $reason): void
    {
        $this->update([
            'status' => 'skipped',
            'error_message' => $reason,
            'completed_at' => now(),
        ]);
    }
}
