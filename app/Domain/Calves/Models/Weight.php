<?php

namespace App\Domain\Calves\Models;

use App\Domain\Animals\Models\Animal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Weight extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'animal_id',
        'weight_date',
        'weight_kg',
        'adg',
        'on_target',
    ];

    protected $casts = [
        'weight_date' => 'date',
        'weight_kg' => 'decimal:2',
        'adg' => 'decimal:3',
        'on_target' => 'boolean',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Weight $weight) {
            if (blank($weight->client_uuid)) {
                $weight->client_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public static function newFactory()
    {
        return \Database\Factories\WeightFactory::new();
    }
}
