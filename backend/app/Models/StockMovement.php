<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'movement_type',
        'quantity',
        'note',
        'reference_type',
        'reference_id',
        'performed_by',
        'movement_at',
        'metadata',
    ];

    protected $casts = [
        'movement_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
