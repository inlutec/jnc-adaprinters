<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumable_id',
        'site_id',
        'department_id',
        'quantity',
        'minimum_quantity',
        'managed_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(ConsumableInstallation::class);
    }
}
