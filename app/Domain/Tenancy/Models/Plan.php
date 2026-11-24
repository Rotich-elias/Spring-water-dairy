<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_monthly',
        'limits',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'limits' => 'array',
        'is_active' => 'boolean',
        'price_monthly' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
