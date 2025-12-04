<?php

namespace App\Models;

use App\Models\Traits\HasCustomFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumable extends Model
{
    use HasFactory, HasCustomFields;

    protected $fillable = [
        'name',
        'sku',
        'type',
        'brand',
        'color',
        'is_color',
        'average_yield',
        'compatible_models',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'is_color' => 'boolean',
        'is_active' => 'boolean',
        'average_yield' => 'integer',
        'compatible_models' => 'array',
        'metadata' => 'array',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
