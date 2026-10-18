<?php

namespace App\Domain\Calves\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Calves\Models\Weight;
use App\Models\Lookup;
use App\Models\Setting;
use Carbon\Carbon;

class CalfGrowthService
{
    /**
     * Compute ADG between two weighings.
     */
    public function computeAdg(Weight $previous, Weight $current): float
    {
        $days = Carbon::parse($previous->weight_date)->diffInDays($current->weight_date, false);
        if ($days <= 0) {
            return 0.0;
        }

        return round((float) $current->weight_kg - (float) $previous->weight_kg, 3) / $days;
    }

    /**
     * Compute overall ADG from birth to latest weight.
     */
    public function computeOverallAdg(Animal $animal): ?float
    {
        $birthWeightRecord = $animal->calfRecord?->birth_weight;
        $latestWeight = $animal->weights()->latest('weight_date')->first();

        if (! $birthWeightRecord || ! $latestWeight) {
            return null;
        }

        $days = Carbon::parse($animal->birth_date)->diffInDays($latestWeight->weight_date, false);
        if ($days <= 0) {
            return null;
        }

        return round(((float) $latestWeight->weight_kg - (float) $birthWeightRecord) / $days, 3);
    }

    /**
     * Check if calf is on target based on benchmark.
     */
    public function checkOnTarget(Animal $animal, Weight $weight): bool
    {
        $benchmark = $this->benchmarkWeight($animal, $weight->weight_date);
        if (! $benchmark) {
            return false;
        }

        // On target if within 10% of benchmark
        return (float) $weight->weight_kg >= ($benchmark * 0.9);
    }

    /**
     * Benchmark weight for a calf of given age and breed.
     * Looks up from settings; simple linear gain model.
     */
    public function benchmarkWeight(Animal $animal, string $date): ?float
    {
        $dailyGain = (float) Setting::where('key', 'calf_adg_target')->value('value') ?? 0.7;
        $birthWeight = $animal->calfRecord?->birth_weight;

        if (! $birthWeight || ! $animal->birth_date) {
            return null;
        }

        $days = Carbon::parse($animal->birth_date)->diffInDays($date, false);
        if ($days < 0) {
            return null;
        }

        return round((float) $birthWeight + ($dailyGain * $days), 2);
    }

    /**
     * Get weight series for an animal's growth chart.
     */
    public function weightSeries(Animal $animal): array
    {
        $weights = $animal->weights()->orderBy('weight_date')->get();

        return $weights->map(fn (Weight $w) => [
            'date' => $w->weight_date,
            'weight_kg' => $w->weight_kg,
            'adg' => $w->adg,
            'on_target' => $w->on_target,
            'benchmark' => $this->benchmarkWeight($animal, $w->weight_date),
        ])->values()->all();
    }

    /**
     * Check weaning readiness: age >= target weaning age and weight >= target weaning weight.
     */
    public function weaningReadiness(Animal $animal): array
    {
        $weaningAgeDays = (int) Setting::where('key', 'weaning_age_days')->value('value') ?? 56;
        $weaningWeightKg = (float) Setting::where('key', 'weaning_weight_kg')->value('value') ?? 65.0;

        $ageDays = $animal->birth_date
            ? Carbon::parse($animal->birth_date)->diffInDays(now(), false)
            : 0;
        $latestWeight = $animal->weights()->latest('weight_date')->first()?->weight_kg ?? 0;

        return [
            'age_days' => $ageDays,
            'weight_kg' => (float) $latestWeight,
            'target_age_days' => $weaningAgeDays,
            'target_weight_kg' => $weaningWeightKg,
            'ready_for_weaning' => $ageDays >= $weaningAgeDays && (float) $latestWeight >= $weaningWeightKg,
            'already_weaned' => $animal->calfRecord?->weaning_date !== null,
        ];
    }

    /**
     * Compute overall ADG aggregate across all calves.
     */
    public function overallAdgAggregate(): ?float
    {
        $calves = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('category_id', [$this->categoryIdFor('Calves')])
            ->with('calfRecord', 'weights')
            ->get();

        $adgs = [];
        foreach ($calves as $calf) {
            $adg = $this->computeOverallAdg($calf);
            if ($adg !== null) {
                $adgs[] = $adg;
            }
        }

        return empty($adgs) ? null : round(array_sum($adgs) / count($adgs), 3);
    }

    /**
     * Helper: get lookups ID for category name.
     */
    private function categoryIdFor(string $name): ?int
    {
        return Lookup::query()
            ->where('type', 'categories')
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->value('id');
    }
}
