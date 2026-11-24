<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'checkout_session_id',
        'subscription_id',
        'provider',
        'provider_reference',
        'provider_transaction_id',
        'provider_invoice_id',
        'amount_minor',
        'currency',
        'status',
        'paid_at',
        'receipt_url',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
