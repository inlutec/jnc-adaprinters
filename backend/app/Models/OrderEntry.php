<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'site_id',
        'department_id',
        'received_at',
        'delivery_note_path',
        'delivery_note_mime_type',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderEntryItem::class);
    }
}
