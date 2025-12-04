<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnmpSyncHistory extends Model
{
    protected $table = 'snmp_sync_history';

    protected $fillable = [
        'type',
        'total_printers',
        'dispatched',
        'completed',
        'failed',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(int $completed = 0, int $failed = 0): void
    {
        $this->update([
            'status' => 'completed',
            'completed' => $completed,
            'failed' => $failed,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }
}
