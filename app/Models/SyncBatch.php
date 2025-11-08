<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncBatch extends Model
{
    protected $table = 'sync_batches';
    protected $primaryKey = 'batch_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'batch_id',
        'status',
        'total_tables',
        'completed_tables',
        'failed_tables',
        'command_options',
        'started_at',
        'completed_at',
        'duration_seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_tables' => 'integer',
        'completed_tables' => 'integer',
        'failed_tables' => 'integer',
        'duration_seconds' => 'integer',
        'command_options' => 'array',
    ];

    /**
     * Get all progress entries for this batch
     */
    public function progress(): HasMany
    {
        return $this->hasMany(SyncProgress::class, 'batch_id', 'batch_id');
    }

    /**
     * Get pending progress entries
     */
    public function pendingProgress(): HasMany
    {
        return $this->progress()->where('status', 'pending');
    }

    /**
     * Get failed progress entries
     */
    public function failedProgress(): HasMany
    {
        return $this->progress()->where('status', 'failed');
    }

    /**
     * Mark batch as completed
     */
    public function markAsCompleted(): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;
        
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Mark batch as failed
     */
    public function markAsFailed(): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;
        
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Mark batch as interrupted
     */
    public function markAsInterrupted(): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;
        
        $this->update([
            'status' => 'interrupted',
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Increment completed tables counter
     */
    public function incrementCompleted(): void
    {
        $this->increment('completed_tables');
    }

    /**
     * Increment failed tables counter
     */
    public function incrementFailed(): void
    {
        $this->increment('failed_tables');
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_tables === 0) {
            return 0;
        }
        
        return round(($this->completed_tables / $this->total_tables) * 100, 2);
    }
}
