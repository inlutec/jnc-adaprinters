<?php

namespace App\Models;

use App\Models\Traits\HasCustomFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Printer extends Model
{
    use HasFactory, HasCustomFields;

    protected $fillable = [
        'uuid',
        'snmp_profile_id',
        'province_id',
        'site_id',
        'department_id',
        'name',
        'hostname',
        'ip_address',
        'mac_address',
        'serial_number',
        'brand',
        'model',
        'firmware_version',
        'status',
        'is_color',
        'supports_snmp',
        'installed_at',
        'last_sync_at',
        'last_seen_at',
        'discovery_source',
        'snmp_data',
        'metrics',
        'notes',
        'photo_path',
    ];

    protected $casts = [
        'is_color' => 'boolean',
        'supports_snmp' => 'boolean',
        'installed_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'snmp_data' => 'array',
        'metrics' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $printer) {
            $printer->uuid ??= Str::uuid()->toString();
        });
    }

    public function snmpProfile(): BelongsTo
    {
        return $this->belongsTo(SnmpProfile::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PrinterStatusSnapshot::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(ConsumableInstallation::class)->latest('installed_at');
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(PrinterPrintLog::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) {
            return null;
        }

        // Usar URL relativa para evitar problemas con diferentes dominios
        return '/storage/' . $this->photo_path;
    }
}
