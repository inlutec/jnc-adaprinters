<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'region',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }
}
