<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsumableInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'printer_id',
        'stock_id',
        'quantity',
        'observations',
        'installed_by',
        'installed_at',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ConsumableInstallationPhoto::class);
    }
}
