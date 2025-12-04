<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableInstallationPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumable_installation_id',
        'photo_path',
        'mime_type',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(ConsumableInstallation::class);
    }
}
