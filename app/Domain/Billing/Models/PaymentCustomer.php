<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentCustomer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'provider',
        'provider_customer_id',
        'email',
    ];
}
