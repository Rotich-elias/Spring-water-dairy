<?php

namespace App\Domain\Milk\Models;

use App\Domain\Animals\Models\Animal;
use Database\Factories\MilkRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MilkRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'animal_id',
        'record_date',
        'session',
        'litres',
        'cmt_score',
        'scc',
        'in_withholding',
        'excluded_from_sales',
    ];

    protected $casts = [
        'record_date' => 'date',
        'litres' => 'decimal:2',
        'cmt_score' => 'integer',
        'scc' => 'integer',
        'in_withholding' => 'boolean',
        'excluded_from_sales' => 'boolean',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (MilkRecord $record) {
            if (blank($record->client_uuid)) {
                $record->client_uuid = (string) Str::uuid();
            }
        });
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function scopeForDateAndSession($query, string $date, string $session)
    {
        return $query->whereDate('record_date', $date)->where('session', $session);
    }

    public function scopeInWithholding($query)
    {
        return $query->where('in_withholding', true);
    }

    public function scopeInSales($query)
    {
        return $query->where('excluded_from_sales', false);
    }

    public static function newFactory()
    {
        return MilkRecordFactory::new();
    }
}
