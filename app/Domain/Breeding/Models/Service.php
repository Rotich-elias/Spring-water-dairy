<?php

namespace App\Domain\Breeding\Models;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\SemenStraw;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'animal_id',
        'service_date',
        'bull_id',
        'inseminator',
        'service_type',
        'expected_calving_date',
        'target_dry_date',
        'pd_window_start',
        'pd_window_end',
        'reproductive_status',
    ];

    protected $casts = [
        'service_date' => 'date',
        'expected_calving_date' => 'date',
        'target_dry_date' => 'date',
        'pd_window_start' => 'date',
        'pd_window_end' => 'date',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            if (blank($service->client_uuid)) {
                $service->client_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function bull(): BelongsTo
    {
        return $this->belongsTo(SemenStraw::class, 'bull_id');
    }

    public static function newFactory()
    {
        return \Database\Factories\ServiceFactory::new();
    }
}
