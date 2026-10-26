<?php

namespace App\Domain\Milk\Models;

use Database\Factories\BulkTankRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BulkTankRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'record_date',
        'session',
        'litres_collected',
        'litres_sold',
        'litres_discarded',
        'buyer',
        'price_per_litre',
    ];

    protected $casts = [
        'record_date' => 'date',
        'litres_collected' => 'decimal:2',
        'litres_sold' => 'decimal:2',
        'litres_discarded' => 'decimal:2',
        'price_per_litre' => 'decimal:2',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (BulkTankRecord $record) {
            if (blank($record->client_uuid)) {
                $record->client_uuid = (string) Str::uuid();
            }
        });
    }

    public static function newFactory()
    {
        return BulkTankRecordFactory::new();
    }
}
