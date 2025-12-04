<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Logo extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'path',
        'mime_type',
        'size',
        'width',
        'height',
        'is_active',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'is_active' => 'boolean',
    ];
}
