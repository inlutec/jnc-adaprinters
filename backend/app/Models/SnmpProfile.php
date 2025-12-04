<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SnmpProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'community',
        'security_level',
        'security_username',
        'auth_protocol',
        'auth_password',
        'priv_protocol',
        'priv_password',
        'context_name',
        'port',
        'timeout_ms',
        'retries',
        'is_default',
        'description',
        'oid_map',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'oid_map' => 'array',
    ];

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }
}
