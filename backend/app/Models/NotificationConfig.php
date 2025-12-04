<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'from_address',
        'from_name',
        'alert_thresholds',
        'recipients',
        'is_active',
    ];

    protected $casts = [
        'smtp_port' => 'integer',
        'alert_thresholds' => 'array',
        'recipients' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'smtp_password',
    ];
}
