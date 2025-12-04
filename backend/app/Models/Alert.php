<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'type',
        'severity',
        'status',
        'source',
        'title',
        'message',
        'printer_id',
        'consumable_id',
        'stock_id',
        'site_id',
        'department_id',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
        'payload',
        'channel_logs',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'payload' => 'array',
        'channel_logs' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $alert) {
            $alert->uuid ??= Str::uuid()->toString();
        });
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
