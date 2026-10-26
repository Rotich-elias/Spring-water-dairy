<?php

namespace App\Domain\Breeding\Models;

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;
use Database\Factories\PregnancyCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PregnancyCheck extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'animal_id',
        'check_date',
        'pd_result_id',
        'result',
        'notes',
    ];

    protected $casts = [
        'check_date' => 'date',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PregnancyCheck $check) {
            if (blank($check->client_uuid)) {
                $check->client_uuid = (string) Str::uuid();
            }
        });
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function pdResult(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'pd_result_id');
    }

    public static function newFactory()
    {
        return PregnancyCheckFactory::new();
    }
}
