<?php

namespace App\Domain\Breeding\Models;

use Database\Factories\SemenStrawFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SemenStraw extends Model
{
    use HasFactory;

    protected $fillable = [
        'bull_code',
        'bull_name',
        'breed',
        'quantity',
        'unit_price',
        'expiry_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public static function newFactory()
    {
        return SemenStrawFactory::new();
    }
}
