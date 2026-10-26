<?php

namespace App\Domain\Calves\Models;

use App\Domain\Animals\Models\Animal;
use Database\Factories\CalfRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalfRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'animal_id',
        'colostrum_litres',
        'colostrum_hours',
        'navel_dip',
        'disbudding',
        'weaning_date',
        'weaning_weight',
        'birth_weight',
        'outcome',
    ];

    protected $casts = [
        'colostrum_litres' => 'decimal:2',
        'colostrum_hours' => 'integer',
        'navel_dip' => 'boolean',
        'disbudding' => 'boolean',
        'weaning_date' => 'date',
        'weaning_weight' => 'decimal:2',
        'birth_weight' => 'decimal:2',
        'outcome' => 'string', // live, stillborn, deceased
    ];

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public static function newFactory()
    {
        return CalfRecordFactory::new();
    }
}
