<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrinterStatusSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'printer_id',
        'status',
        'error_code',
        'total_pages',
        'color_pages',
        'bw_pages',
        'lifetime_pages',
        'uptime_seconds',
        'consumables',
        'counters',
        'environment',
        'raw_payload',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'consumables' => 'array',
        'counters' => 'array',
        'environment' => 'array',
        'raw_payload' => 'array',
    ];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(PrinterPrintLog::class, 'snapshot_id');
    }
}
