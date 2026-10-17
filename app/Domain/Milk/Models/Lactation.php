<?php

namespace App\Domain\Milk\Models;

use App\Domain\Animals\Models\Animal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Lactation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'animal_id',
        'lactation_number',
        'started_at',
        'ended_at',
        'total_litres',
        'peak_yield',
        'peak_day',
        'drying_off_date',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
        'total_litres' => 'decimal:2',
        'peak_yield' => 'decimal:2',
        'peak_day' => 'integer',
        'drying_off_date' => 'date',
    ];

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    public function getDaysInMilkAttribute(): ?int
    {
        $end = $this->ended_at ?? now()->toDateString();

        return (int) Carbon::parse($this->started_at)->diffInDays($end, false);
    }

    public function getIsDryOffAttribute(): bool
    {
        return (bool) $this->drying_off_date;
    }

    public static function newFactory()
    {
        return \Database\Factories\LactationFactory::new();
    }
}
