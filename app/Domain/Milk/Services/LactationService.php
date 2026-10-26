<?php

namespace App\Domain\Milk\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Milk\Models\Lactation;
use App\Domain\Milk\Models\MilkRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LactationService
{
    /**
     * Start a new lactation for an animal (called from calving flow in Phase 4).
     */
    public function start(Animal $animal, string $startedAt, int $lactationNumber): Lactation
    {
        return DB::transaction(function () use ($animal, $startedAt, $lactationNumber) {
            // Close any open lactation for this animal
            Lactation::query()
                ->where('animal_id', $animal->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $startedAt]);

            $lactation = Lactation::create([
                'animal_id' => $animal->id,
                'lactation_number' => $lactationNumber,
                'started_at' => $startedAt,
                'total_litres' => 0,
            ]);

            return $lactation;
        });
    }

    /**
     * Close a lactation via dry-off.
     */
    public function dryOff(int $lactationId, string $dryingOffDate): Lactation
    {
        $lactation = Lactation::query()->where('id', $lactationId)->firstOrFail();

        $lactation->update([
            'ended_at' => $dryingOffDate,
            'drying_off_date' => $dryingOffDate,
        ]);

        return $lactation->fresh();
    }

    /**
     * Update totals for a lactation based on milk records.
     */
    public function recomputeTotals(int $lactationId): void
    {
        $lactation = Lactation::query()->where('id', $lactationId)->firstOrFail();

        $total = MilkRecord::query()
            ->where('animal_id', $lactation->animal_id)
            ->whereBetween('record_date', [$lactation->started_at, $lactation->ended_at ?? now()->toDateString()])
            ->where('excluded_from_sales', false)
            ->sum('litres');

        $peak = MilkRecord::query()
            ->where('animal_id', $lactation->animal_id)
            ->whereBetween('record_date', [$lactation->started_at, $lactation->ended_at ?? now()->toDateString()])
            ->orderByDesc('litres')
            ->first();

        $lactation->update([
            'total_litres' => $total,
            'peak_yield' => $peak?->litres,
            'peak_day' => $peak?->record_date ? Carbon::parse($lactation->started_at)->diffInDays($peak->record_date, false) : null,
        ]);
    }

    /**
     * Get lactation summary for an animal.
     */
    public function summary(Animal $animal): array
    {
        $lactations = Lactation::query()
            ->where('animal_id', $animal->id)
            ->orderByDesc('started_at')
            ->get();

        $current = $lactations->first(fn ($l) => is_null($l->ended_at));

        return [
            'current' => $current ? [
                'id' => $current->id,
                'lactation_number' => $current->lactation_number,
                'started_at' => $current->started_at,
                'days_in_milk' => $current->days_in_milk,
                'total_litres' => $current->total_litres,
                'peak_yield' => $current->peak_yield,
                'peak_day' => $current->peak_day,
            ] : null,
            'history' => $lactations->map(fn (Lactation $l) => [
                'id' => $l->id,
                'lactation_number' => $l->lactation_number,
                'started_at' => $l->started_at,
                'ended_at' => $l->ended_at,
                'total_litres' => $l->total_litres,
                'days_in_milk' => $l->days_in_milk,
            ])->all(),
        ];
    }
}
