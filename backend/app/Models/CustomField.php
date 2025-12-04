<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'name',
        'slug',
        'type',
        'options',
        'is_required',
        'order',
        'help_text',
        'is_active',
        'show_in_table',
        'table_order',
        'show_in_creation_wizard',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'order' => 'integer',
        'is_active' => 'boolean',
        'show_in_table' => 'boolean',
        'table_order' => 'integer',
        'show_in_creation_wizard' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
