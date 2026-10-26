<?php

namespace App\Domain\Health\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Treatment;
use Carbon\Carbon;

class WithholdingService
{
    /**
     * Compute safe dates from a treatment.
     *
     * @return array{milk_safe_date: string|null, meat_safe_date: string|null}
     */
    public function computeSafeDates(string $treatmentDate, int $milkWithholdingDays, int $meatWithholdingDays): array
    {
        $milkSafeDate = $milkWithholdingDays > 0
            ? Carbon::parse($treatmentDate)->addDays($milkWithholdingDays)->toDateString()
            : null;
        $meatSafeDate = $meatWithholdingDays > 0
            ? Carbon::parse($treatmentDate)->addDays($meatWithholdingDays)->toDateString()
            : null;

        return [
            'milk_safe_date' => $milkSafeDate,
            'meat_safe_date' => $meatSafeDate,
        ];
    }

    /**
     * Check if an animal is currently in withholding (milk or meat).
     */
    public function isInWithholding(Animal $animal): bool
    {
        return $animal->treatments()
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('milk_safe_date')
                    ->where('milk_safe_date', '>=', today())
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('meat_safe_date')
                            ->where('meat_safe_date', '>=', today());
                    });
            })
            ->exists();
    }

    /**
     * Get all animals currently in withholding, with their safe dates.
     */
    public function sickBay(): array
    {
        $animals = Animal::query()
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->where('sex', 'F')
            ->with(['treatments' => function ($q) {
                $q->whereNull('deleted_at')
                    ->where(function ($q2) {
                        $q2->whereNotNull('milk_safe_date')
                            ->where('milk_safe_date', '>=', today())
                            ->orWhere(function ($q3) {
                                $q3->whereNotNull('meat_safe_date')
                                    ->where('meat_safe_date', '>=', today());
                            });
                    })
                    ->orderBy('created_at', 'desc');
            }])
            ->get();

        $sickBay = [];
        foreach ($animals as $animal) {
            $activeTreatments = $animal->treatments->filter(function ($t) {
                return $t->isInWithholding();
            });

            if ($activeTreatments->isEmpty()) {
                continue;
            }

            $latest = $activeTreatments->first();
            $sickBay[] = [
                'id' => $animal->id,
                'ear_tag' => $animal->ear_tag,
                'category' => $animal->category?->name,
                'pen' => $animal->pen?->name,
                'milk_safe_date' => $latest->milk_safe_date,
                'meat_safe_date' => $latest->meat_safe_date,
                'days_remaining_milk' => $latest->milk_safe_date
                    ? Carbon::parse($latest->milk_safe_date)->diffInDays(today(), false)
                    : null,
                'days_remaining_meat' => $latest->meat_safe_date
                    ? Carbon::parse($latest->meat_safe_date)->diffInDays(today(), false)
                    : null,
                'drug_name' => $latest->drug_name,
            ];
        }

        return $sickBay;
    }
}
