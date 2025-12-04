<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'code',
        'floor',
        'contact_email',
        'contact_phone',
        'is_active',
        'is_warehouse',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_warehouse' => 'boolean',
        'metadata' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
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
