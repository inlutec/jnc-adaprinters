<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'name',
        'code',
        'address',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'contact_email',
        'contact_phone',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
