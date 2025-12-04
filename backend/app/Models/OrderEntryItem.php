<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEntryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_entry_id',
        'consumable_reference_id',
        'quantity',
    ];

    public function orderEntry(): BelongsTo
    {
        return $this->belongsTo(OrderEntry::class);
    }

    public function consumableReference(): BelongsTo
    {
        return $this->belongsTo(ConsumableReference::class);
    }
}
