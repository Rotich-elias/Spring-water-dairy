<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    /**
     * Models that should not be audited (avoid recursive/noisy logs).
     *
     * @var list<string>
     */
    protected static $ignoreModels = [
        AuditLog::class,
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored');
    }

    protected function record(Model $model, string $action): void
    {
        if (in_array($model::class, self::$ignoreModels, true)) {
            return;
        }

        // Skip if no authenticated user (e.g. seeders, console commands).
        $user = auth()->guard('api')->user();
        if (! $user) {
            return;
        }

        AuditLog::create([
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'user_id' => $user->id,
            'action' => $action,
            'old_values' => $action === 'created' ? null : $model->getOriginal(),
            'new_values' => $action === 'deleted' ? null : $model->getAttributes(),
            'ip_address' => request()->ip(),
            'device' => request()->header('User-Agent'),
        ]);
    }
}
