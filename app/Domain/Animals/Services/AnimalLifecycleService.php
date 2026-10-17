<?php

namespace App\Domain\Animals\Services;

use App\Domain\Animals\Models\Animal;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnimalLifecycleService
{
    /**
     * Move an animal: change category, pen and/or status.
     *
     * @param  array{category_id?: int, pen_id?: int, status_id?: int, reason?: string}  $data
     */
    public function move(Animal $animal, array $data): Animal
    {
        return DB::transaction(function () use ($animal, $data) {
            $old = $animal->getOriginal();

            $animal->fill(array_filter($data, fn ($v) => $v !== null, ARRAY_FILTER_USE_BOTH));
            $animal->increment('version');
            $animal->save();

            $this->audit($animal, 'moved', $old, $animal->getAttributes(), [
                'reason' => $data['reason'] ?? null,
            ]);

            return $animal->fresh();
        });
    }

    /**
     * Exit an animal (sold, culled, deceased).
     *
     * Blocked during withholding unless the caller passes override=true with a reason
     * and is an Owner or Manager.
     *
     * @param  array{exit_date: string, reason: string, price?: float|string|null, override?: bool, override_reason?: string}  $data
     */
    public function exit(Animal $animal, array $data): Animal
    {
        return DB::transaction(function () use ($animal, $data) {
            if ($animal->is_exit_blocked_by_withholding) {
                $override = filter_var($data['override'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $overrideReason = $data['override_reason'] ?? null;

                if (! $override) {
                    throw ValidationException::withMessages([
                        'exit' => ['This animal is in milk or meat withholding and cannot be exited unless overridden.'],
                    ])->errorBag('withholding_active');
                }

                if (! Auth::user()->hasPermissionTo('animals.exit')) {
                    throw ValidationException::withMessages([
                        'override' => ['Only Owner and Manager may override a withholding block.'],
                    ]);
                }

                if (blank($overrideReason)) {
                    throw ValidationException::withMessages([
                        'override_reason' => ['An override reason is required.'],
                    ]);
                }
            }

            $old = $animal->getOriginal();

            $animal->exit_date = $data['exit_date'];
            $animal->exit_reason = $data['reason'];
            $animal->exit_price = $data['price'] ?? null;
            $animal->increment('version');
            $animal->save();

            $this->audit($animal, 'exited', $old, $animal->getAttributes(), [
                'reason' => $data['reason'],
                'exit_date' => $data['exit_date'],
                'price' => $data['price'] ?? null,
                'override' => $data['override'] ?? false,
                'override_reason' => $data['override_reason'] ?? null,
            ]);

            return $animal->fresh();
        });
    }

    protected function audit(Animal $animal, string $action, array $old, array $new, array $context = []): void
    {
        AuditLog::create([
            'auditable_type' => Animal::class,
            'auditable_id' => $animal->getKey(),
            'user_id' => Auth::id(),
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'overrides' => $context,
            'ip_address' => request()->ip(),
            'device' => request()->header('User-Agent'),
        ]);
    }
}
