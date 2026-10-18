<?php

namespace App\Domain\Alerts\Models;

use Database\Factories\AlertRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'conditions',
        'channels',
        'recipients',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'channels' => 'array',
        'recipients' => 'array',
        'is_active' => 'boolean',
    ];

    public static function newFactory()
    {
        return AlertRuleFactory::new();
    }
}
