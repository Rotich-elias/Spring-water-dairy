<?php

namespace App\Domain\Animals\Models;

use App\Domain\Breeding\Models\Calving;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Models\PregnancyCheck;
use App\Domain\Breeding\Models\Service;
use App\Domain\Calves\Models\CalfRecord;
use App\Domain\Calves\Models\Weight;
use App\Domain\Health\Models\Treatment;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Milk\Models\MilkRecord;
use App\Models\Lookup;
use Carbon\Carbon;
use Database\Factories\AnimalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Animal extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'ear_tag',
        'name',
        'category_id',
        'pen_id',
        'status_id',
        'breed_id',
        'sex',
        'birth_date',
        'exit_date',
        'exit_reason',
        'exit_price',
        'dam_id',
        'sire_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'exit_date' => 'date',
        'exit_price' => 'decimal:2',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Animal $animal) {
            if (blank($animal->client_uuid)) {
                $animal->client_uuid = (string) Str::uuid();
            }
        });
    }

    public static function newFactory()
    {
        return AnimalFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }

    public function pen(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'pen_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'status_id');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'breed_id');
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'dam_id');
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sire_id');
    }

    public function offspring(): HasMany
    {
        return $this->hasMany(Animal::class, 'dam_id');
    }

    public function calvings(): HasMany
    {
        return $this->hasMany(Calving::class, 'dam_id');
    }

    public function milkRecords(): HasMany
    {
        return $this->hasMany(MilkRecord::class, 'animal_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'animal_id');
    }

    public function heats(): HasMany
    {
        return $this->hasMany(Heat::class, 'animal_id');
    }

    public function pregnancyChecks(): HasMany
    {
        return $this->hasMany(PregnancyCheck::class, 'animal_id');
    }

    public function weights(): HasMany
    {
        return $this->hasMany(Weight::class, 'animal_id');
    }

    public function calfRecord(): HasOne
    {
        return $this->hasOne(CalfRecord::class, 'animal_id');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class, 'animal_id');
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class, 'animal_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Computed accessors (never stored; backend calculates these) */
    /* ------------------------------------------------------------------ */

    public function getAgeAttribute(): ?int
    {
        if (! $this->birth_date) {
            return null;
        }

        return Carbon::parse($this->birth_date)->age;
    }

    public function getDaysInMilkAttribute(): ?int
    {
        if (! Schema::hasTable('calvings')) {
            return null;
        }

        $latestCalving = $this->calvings()->latest('calving_date')->first();
        if (! $latestCalving) {
            return null;
        }

        return (int) Carbon::parse($latestCalving->calving_date)->diffInDays(now(), false);
    }

    public function getParityAttribute(): ?int
    {
        if (! Schema::hasTable('calvings')) {
            return null;
        }

        return $this->calvings()->count();
    }

    public function getInWithholdingAttribute(): bool
    {
        if (! Schema::hasTable('treatments')) {
            return false;
        }

        return (bool) $this->treatments()
            ->where(function ($q) {
                $q->whereNull('milk_safe_date')
                    ->orWhere('milk_safe_date', '>', now()->toDateString());
            })
            ->whereNull('deleted_at')
            ->exists();
    }

    public function getReproductiveStatusAttribute(): string
    {
        if (! Schema::hasTable('services')) {
            return 'unknown';
        }

        $latestService = $this->services()->latest('service_date')->first();
        if (! $latestService) {
            return 'unknown';
        }

        return $latestService->reproductive_status ?? 'unknown';
    }

    public function getIsLactatingAttribute(): bool
    {
        return $this->sex === 'F' && $this->days_in_milk !== null && $this->days_in_milk > 0;
    }

    public function getIsExitBlockedByWithholdingAttribute(): bool
    {
        return $this->in_withholding;
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes */
    /* ------------------------------------------------------------------ */

    public function scopeActive($query)
    {
        return $query->whereNull('exit_date')->whereNull('deleted_at');
    }

    public function scopeLactating($query)
    {
        return $query->where('sex', 'F');
    }

    public function scopeOfCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeOfStatus($query, int $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    public function scopeOfPen($query, int $penId)
    {
        return $query->where('pen_id', $penId);
    }

    public function scopeOfBreed($query, int $breedId)
    {
        return $query->where('breed_id', $breedId);
    }

    public function scopeSearch($query, string $text)
    {
        return $query->where(function ($q) use ($text) {
            $q->where('ear_tag', 'like', '%'.$text.'%')
                ->orWhere('name', 'like', '%'.$text.'%');
        });
    }
}
