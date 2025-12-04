<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'consumable_id',
        'consumable_reference_id',
        'description',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    public function consumableReference(): BelongsTo
    {
        return $this->belongsTo(ConsumableReference::class);
    }
}
