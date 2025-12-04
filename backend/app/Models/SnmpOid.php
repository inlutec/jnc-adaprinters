<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SnmpOid extends Model
{
    use HasFactory;

    protected $fillable = [
        'oid',
        'name',
        'description',
        'category',
        'data_type',
        'unit',
        'color',
        'is_system',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
