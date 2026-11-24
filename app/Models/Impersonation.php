<?php

namespace App\Models;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Impersonation extends Model
{
    protected $fillable = [
        'impersonator_id',
        'user_id',
        'tenant_id',
        'token_id',
        'reason',
        'expires_at',
        'ended_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
