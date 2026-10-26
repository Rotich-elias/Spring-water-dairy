<?php

namespace App\Domain\Health\Models;

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;
use Carbon\Carbon;
use Database\Factories\TreatmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Treatment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'animal_id',
        'treatment_date',
        'condition_id',
        'drug_name',
        'dose',
        'route',
        'milk_withholding_days',
        'meat_withholding_days',
        'milk_safe_date',
        'meat_safe_date',
    ];

    protected $casts = [
        'treatment_date' => 'date',
        'milk_withholding_days' => 'integer',
        'meat_withholding_days' => 'integer',
        'milk_safe_date' => 'date',
        'meat_safe_date' => 'date',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Treatment $treatment) {
            if (blank($treatment->client_uuid)) {
                $treatment->client_uuid = (string) Str::uuid();
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

    public static function newFactory()
    {
        return TreatmentFactory::new();
    }

    public function isInWithholding(): bool
    {
        if ($this->milk_safe_date && Carbon::today()->lte($this->milk_safe_date)) {
            return true;
        }
        if ($this->meat_safe_date && Carbon::today()->lte($this->meat_safe_date)) {
            return true;
        }

        return false;
    }
}
