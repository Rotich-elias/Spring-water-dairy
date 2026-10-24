<?php

namespace App\Domain\Dashboard\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Models\Service;
use App\Domain\Calves\Services\CalfGrowthService;
use App\Domain\Health\Services\WithholdingService;
use App\Domain\Milk\Models\MilkRecord;
use App\Models\Lookup;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private CalfGrowthService $calfGrowth,
        private WithholdingService $withholding,
    ) {}

    /**
     * Compute dashboard tiles and composition.
     */
    public function summary(): array
    {
        $today = today()->toDateString();

        $totalHerd = Animal::query()->whereNull('exit_date')->whereNull('deleted_at')->count();

        $milking = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('status_id', [$this->statusIdFor('Milking Cows')])
            ->count();

        $dry = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('status_id', [$this->statusIdFor('Dry Cows')])
            ->count();

        $heifers = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('status_id', [$this->statusIdFor('Heifers')])
            ->count();

        $calves = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('category_id', [$this->categoryIdFor('Calves')])
            ->count();

        // Latest milk date & total
        $latestMilk = MilkRecord::query()
            ->orderByDesc('record_date')
            ->first();

        $latestMilkDate = $latestMilk?->record_date;
        $latestMilkLitres = 0.0;
        $avgYield = 0.0;
        $todaysMilkLitres = 0.0;

        if ($latestMilkDate) {
            $latestMilkLitres = MilkRecord::query()
                ->where('record_date', $latestMilkDate)
                ->where('session', 'morning')
                ->where('excluded_from_sales', false)
                ->sum('litres');

            $eveningLitres = MilkRecord::query()
                ->where('record_date', $latestMilkDate)
                ->where('session', 'evening')
                ->where('excluded_from_sales', false)
                ->sum('litres');

            $latestMilkLitres += $eveningLitres;

            $cowCount = MilkRecord::query()
                ->where('record_date', $latestMilkDate)
                ->where('excluded_from_sales', false)
                ->distinct('animal_id')
                ->count('animal_id');

            $avgYield = $cowCount > 0 ? $latestMilkLitres / $cowCount : 0.0;

            // Today's milk
            $todaysMilkLitres = MilkRecord::query()
                ->where('record_date', $today)
                ->where('excluded_from_sales', false)
                ->sum('litres');
        }

        // Confirmed in calf: animals with pregnant reproductive_status from latest service
        $confirmedInCalf = Service::query()
            ->where('reproductive_status', 'pregnant')
            ->where('service_date', '<=', today())
            ->distinct('animal_id')
            ->count('animal_id');

        // Calf ADG
        $calfAdg = $this->calfGrowth->overallAdgAggregate() ?? 0.0;

        // Sick bay count
        $sickBay = count($this->withholding->sickBay());

        // Heats this week
        $heatsThisWeek = Heat::query()
            ->whereBetween('heat_date', [today()->subDays(7)->toDateString(), today()])
            ->count();

        // Services this week
        $servicesThisWeek = Service::query()
            ->whereBetween('service_date', [today()->subDays(7)->toDateString(), today()])
            ->count();

        // Pending PDs
        $pregnanciesPending = Service::query()
            ->where('reproductive_status', 'open')
            ->where('service_date', '<=', today())
            ->count();

        // Calvings due this week
        $calvingsDueThisWeek = Service::query()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('expected_calving_date', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->count();

        return [
            'total_animals' => $totalHerd,
            'animals_by_status' => [
                'Milking Cows' => $milking,
                'Dry Cows' => $dry,
                'Heifers' => $heifers,
                'Calves' => $calves,
            ],
            'animals_by_category' => [
                'Milking Cows' => $milking,
                'Dry Cows' => $dry,
                'Heifers' => $heifers,
                'Calves' => $calves,
            ],
            'lactating_cows' => $milking,
            'todays_milk_litres' => round($todaysMilkLitres, 2),
            'heats_this_week' => $heatsThisWeek,
            'services_this_week' => $servicesThisWeek,
            'pregnancies_pending' => $pregnanciesPending,
            'calvings_expected_this_week' => $calvingsDueThisWeek,
            'treatments_in_withholding' => $sickBay,
            'vaccines_due' => 0,
        ];
    }

    /**
     * Herd composition table with targets.
     */
    public function composition(): array
    {
        $totalHerd = Animal::query()->whereNull('exit_date')->whereNull('deleted_at')->count();

        $categories = [
            ['name' => 'Milking Cows', 'target' => 70, 'status_id' => $this->statusIdFor('Milking Cows')],
            ['name' => 'Dry Cows', 'target' => 20, 'status_id' => $this->statusIdFor('Dry Cows')],
            ['name' => 'Heifers', 'target' => 50, 'status_id' => $this->statusIdFor('Heifers')],
            ['name' => 'Calves', 'target' => 40, 'status_id' => $this->statusIdFor('Calves')],
        ];

        $composition = [];
        foreach ($categories as $c) {
            $count = Animal::query()
                ->where('category_id', $c['status_id'])
                ->whereNull('exit_date')
                ->whereNull('deleted_at')
                ->count();

            $percent = $totalHerd > 0 ? round(($count / $totalHerd) * 100, 1) : 0.0;
            $diff = $c['target'] - $count;
            $note = $diff > 0 ? "$diff below target" : ($diff < 0 ? abs($diff).' above target' : 'On target');

            $composition[] = [
                'category' => $c['name'],
                'target' => $c['target'],
                'count' => $count,
                'percent' => $percent,
                'note' => $note,
            ];
        }

        return $composition;
    }

    /**
     * Breed distribution.
     */
    public function breeds(): array
    {
        $totalHerd = Animal::query()->whereNull('exit_date')->whereNull('deleted_at')->count();

        $breeds = Lookup::query()
            ->where('type', 'breeds')
            ->whereNull('deleted_at')
            ->get();

        $result = [];
        foreach ($breeds as $breed) {
            $count = Animal::query()
                ->where('breed_id', $breed->id)
                ->whereNull('exit_date')
                ->whereNull('deleted_at')
                ->count();

            $percent = $totalHerd > 0 ? round(($count / $totalHerd) * 100, 1) : 0.0;

            $result[] = [
                'breed' => $breed->name,
                'count' => $count,
                'percent' => $percent,
            ];
        }

        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * 30-day milk trend series.
     */
    public function milkTrend(string $days = '30'): array
    {
        $days = (int) $days;
        $startDate = today()->subDays($days - 1)->toDateString();
        $endDate = today()->toDateString();

        $daily = MilkRecord::query()
            ->where('record_date', '>=', $startDate)
            ->where('record_date', '<=', $endDate)
            ->where('excluded_from_sales', false)
            ->select('record_date', DB::raw('SUM(litres) as total_litres'))
            ->groupBy('record_date')
            ->orderBy('record_date')
            ->get()
            ->keyBy('record_date');

        $series = [];
        $current = today()->subDays($days - 1);
        while ($current->lte(today())) {
            $date = $current->toDateString();
            $series[] = [
                'date' => $date,
                'litres' => round((float) ($daily[$date]->total_litres ?? 0), 2),
            ];
            $current = $current->addDay();
        }

        return $series;
    }

    /**
     * Breed distribution for chart.
     */
    public function breedShare(): array
    {
        return $this->breeds();
    }

    /**
     * Herd composition for chart/table.
     */
    public function compositionChart(): array
    {
        return $this->composition();
    }

    /**
     * Upcoming events (next 14 days).
     */
    public function upcoming(): array
    {
        $today = today();
        $end = today()->addDays(14);

        $events = [];

        // PD due: services in PD window
        $pdServices = Service::query()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('pd_window_end', [$today->toDateString(), $end->toDateString()])
            ->with('animal')
            ->get();

        foreach ($pdServices as $s) {
            $events[] = [
                'type' => 'pd_due',
                'title' => 'PD Due',
                'animal_id' => $s->animal_id,
                'ear_tag' => $s->animal?->ear_tag,
                'date' => $s->pd_window_end,
                'priority' => 'high',
            ];
        }

        // Dry-off due: target_dry_date in range
        $dryServices = Service::query()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('target_dry_date', [$today->toDateString(), $end->toDateString()])
            ->with('animal')
            ->get();

        foreach ($dryServices as $s) {
            $events[] = [
                'type' => 'dry_off_due',
                'title' => 'Dry-off Due',
                'animal_id' => $s->animal_id,
                'ear_tag' => $s->animal?->ear_tag,
                'date' => $s->target_dry_date,
                'priority' => 'high',
            ];
        }

        // Calving due: expected_calving_date in range
        $calvingServices = Service::query()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('expected_calving_date', [$today->toDateString(), $end->toDateString()])
            ->with('animal')
            ->get();

        foreach ($calvingServices as $s) {
            $events[] = [
                'type' => 'calving_due',
                'title' => 'Calving Due',
                'animal_id' => $s->animal_id,
                'ear_tag' => $s->animal?->ear_tag,
                'date' => $s->expected_calving_date,
                'priority' => 'high',
            ];
        }

        // Calves due for weaning
        $calves = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('category_id', [$this->categoryIdFor('Calves')])
            ->with('calfRecord')
            ->get();

        foreach ($calves as $calf) {
            $readiness = $this->calfGrowth->weaningReadiness($calf);
            if ($readiness['ready_for_weaning'] && ! $readiness['already_weaned']) {
                $events[] = [
                    'type' => 'calf_due_weaning',
                    'title' => 'Calf Due for Weaning',
                    'animal_id' => $calf->id,
                    'ear_tag' => $calf->ear_tag,
                    'date' => today()->toDateString(),
                    'priority' => 'medium',
                ];
            }
        }

        // Sort by date then priority
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($events, function ($a, $b) use ($priorityOrder) {
            $dateCmp = $a['date'] <=> $b['date'];
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return ($priorityOrder[$a['priority']] ?? 1) <=> ($priorityOrder[$b['priority']] ?? 1);
        });

        return $events;
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

    /**
     * Helper: get lookups ID for status name.
     */
    private function statusIdFor(string $name): ?int
    {
        return Lookup::query()
            ->where('type', 'animal-statuses')
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->value('id');
    }
}
