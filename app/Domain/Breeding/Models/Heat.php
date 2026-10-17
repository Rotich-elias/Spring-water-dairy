<?php

namespace App\Domain\Breeding\Models;

use App\Domain\Animals\Models\Animal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Heat extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'animal_id',
        'heat_date',
        'observed_by',
        'notes',
    ];

    protected $casts = [
        'heat_date' => 'date',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Heat $heat) {
            if (blank($heat->client_uuid)) {
                $heat->client_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public static function newFactory()
    {
        return \Database\Factories\HeatFactory::new();
    }
}
