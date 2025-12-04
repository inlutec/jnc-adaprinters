<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnmpSyncConfig extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public static function get(string $key, $default = null)
    {
        $config = self::where('key', $key)->first();
        return $config ? $config->value : $default;
    }

    public static function set(string $key, $value, ?string $description = null): void
    {
        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'description' => $description,
            ]
        );
    }

    public static function isEnabled(string $key): bool
    {
        return self::get($key, 'false') === 'true';
    }
}
