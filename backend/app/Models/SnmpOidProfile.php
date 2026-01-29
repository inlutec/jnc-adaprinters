<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SnmpOidProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
        'model',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * OIDs asociados a este perfil
     */
    public function oids(): BelongsToMany
    {
        return $this->belongsToMany(SnmpOid::class, 'snmp_oid_profile_oid')
            ->withPivot('order', 'is_required', 'display_name', 'display_color', 'display_unit', 'display_category')
            ->orderByPivot('order');
    }

    /**
     * Obtener OIDs activos del perfil ordenados
     */
    public function activeOids()
    {
        return $this->oids()
            ->where('snmp_oids.is_active', true)
            ->orderByPivot('order');
    }

    /**
     * Añadir un OID al perfil
     */
    public function addOid(SnmpOid $oid, int $order = 0, bool $isRequired = false): void
    {
        $this->oids()->syncWithoutDetaching([
            $oid->id => [
                'order' => $order,
                'is_required' => $isRequired,
            ],
        ]);
    }

    /**
     * Remover un OID del perfil
     */
    public function removeOid(SnmpOid $oid): void
    {
        $this->oids()->detach($oid->id);
    }

    /**
     * Obtener el perfil por defecto
     */
    public static function getDefault(): ?self
    {
        return self::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}

