<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'ends_at',
        'canceled_at',
        'current_period_end',
        'provider',
        'provider_customer_id',
        'provider_subscription_id',
        'provider_email_token',
        'provider_plan_code',
        'payment_status',
        'last_payment_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'current_period_end' => 'datetime',
        'last_payment_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}
