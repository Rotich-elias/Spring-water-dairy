<?php

namespace App\Domain\Alerts\Models;

use App\Domain\Animals\Models\Animal;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alert extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'title',
        'message',
        'animal_id',
        'read_at',
        'dismissed_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('dismissed_at')->whereNull('deleted_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at')->whereNull('dismissed_at')->whereNull('deleted_at');
    }

    public function isDismissed(): bool
    {
        return ! is_null($this->dismissed_at);
    }

    public static function newFactory()
    {
        return AlertFactory::new();
    }
}
