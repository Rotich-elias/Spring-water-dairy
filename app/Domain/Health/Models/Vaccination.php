<?php

namespace App\Domain\Health\Models;

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;
use Database\Factories\VaccinationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Vaccination extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'animal_id',
        'condition_id',
        'vaccination_date',
        'vaccine_name',
        'dose',
        'route',
        'pen_id',
        'next_due_date',
    ];

    protected $casts = [
        'vaccination_date' => 'date',
        'next_due_date' => 'date',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Vaccination $vaccination) {
            if (blank($vaccination->client_uuid)) {
                $vaccination->client_uuid = (string) Str::uuid();
            }
        });
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'condition_id');
    }

    public function pen(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'pen_id');
    }

    public static function newFactory()
    {
        return VaccinationFactory::new();
    }
}
