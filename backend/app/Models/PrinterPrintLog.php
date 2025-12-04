<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterPrintLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'printer_id',
        'snapshot_id',
        'start_counter',
        'end_counter',
        'color_counter_total',
        'bw_counter_total',
        'total_prints',
        'color_prints',
        'bw_prints',
        'started_at',
        'ended_at',
        'source',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PrinterStatusSnapshot::class, 'snapshot_id');
    }
}
