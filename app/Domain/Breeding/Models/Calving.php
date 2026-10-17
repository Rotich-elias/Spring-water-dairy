<?php

namespace App\Domain\Breeding\Models;

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Calving extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'dam_id',
        'calving_date',
        'ease_score_id',
        'retained_placenta',
        'notes',
    ];

    protected $casts = [
        'calving_date' => 'datetime',
        'retained_placenta' => 'boolean',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Calving $calving) {
            if (blank($calving->client_uuid)) {
                $calving->client_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'dam_id');
    }

    public function easeScore(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'ease_score_id');
    }

    public static function newFactory()
    {
        return \Database\Factories\CalvingFactory::new();
    }
}
