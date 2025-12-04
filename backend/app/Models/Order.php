<?php

namespace App\Models;

use App\Models\Traits\HasCustomFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, HasCustomFields;

    protected $fillable = [
        'uuid',
        'printer_id',
        'consumable_id',
        'status',
        'requested_at',
        'sent_at',
        'received_at',
        'email_sent_at',
        'email_to',
        'supplier_name',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->uuid ??= Str::uuid()->toString();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(OrderEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(OrderComment::class)->latest();
    }
}
