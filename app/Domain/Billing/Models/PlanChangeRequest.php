<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Database\Factories\PlanChangeRequestFactory;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanChangeRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_PAID = 'paid';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_INVOICED,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'tenant_id',
        'tenant_name',
        'plan_id',
        'plan_code',
        'plan_name',
        'current_plan_id',
        'current_plan_code',
        'current_plan_name',
        'requester_id',
        'requester_name',
        'status',
        'amount_minor',
        'currency',
        'note',
        'invoice_number',
        'invoiced_at',
        'payment_reference',
        'payment_date',
        'rejection_reason',
        'rejected_at',
        'approver_id',
        'approver_name',
        'approved_at',
        'canceled_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'invoiced_at' => 'datetime',
        'payment_date' => 'datetime',
        'rejected_at' => 'datetime',
        'approved_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'currency' => 'KES',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public static function newFactory(): PlanChangeRequestFactory
    {
        return PlanChangeRequestFactory::new();
    }
}
