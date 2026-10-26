<?php

namespace App\Domain\Dashboard\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Models\Service;
use App\Domain\Calves\Services\CalfGrowthService;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Health\Services\WithholdingService;
use App\Domain\Milk\Models\MilkRecord;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Builder;
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
        $weekAgo = today()->subDays(7)->toDateString();

        $totalHerd = Animal::query()->whereNull('exit_date')->whereNull('deleted_at')->count();

        $animalsByCategory = [];
        foreach (['Milking Cows', 'Dry Cows', 'Heifers', 'Calves', 'Bulls'] as $category) {
            $animalsByCategory[$category] = $this->activeCategoryCount($category);
        }

        $statusCounts = Animal::query()
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereNotNull('status_id')
            ->select('status_id', DB::raw('count(*) as total'))
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $animalsByStatus = Lookup::query()
            ->where('type', 'animal-statuses')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Lookup $status) => [$status->name => (int) ($statusCounts[$status->id] ?? 0)])
            ->all();

        $milking = $animalsByCategory['Milking Cows'];
        $todaysMilkLitres = MilkRecord::query()
            ->whereDate('record_date', $today)
            ->where('excluded_from_sales', false)
            ->sum('litres');

        $heatsThisWeek = Heat::query()
            ->whereBetween('heat_date', [$weekAgo, $today])
            ->count();

        $servicesThisWeek = Service::query()
            ->whereBetween('service_date', [$weekAgo, $today])
            ->count();

        $pregnanciesPending = $this->latestServices()
            ->whereIn('reproductive_status', ['open', 'recheck'])
            ->where('service_date', '<=', $today)
            ->whereNotExists($this->calvedSinceService())
            ->count();

        $calvingsDueThisWeek = $this->latestServices()
            ->where('reproductive_status', 'pregnant')
            ->whereBetween('expected_calving_date', [$today, today()->addDays(7)->toDateString()])
            ->whereNotExists($this->calvedSinceService())
            ->count();

        $vaccinesDue = Vaccination::query()
            ->whereNull('deleted_at')
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', today()->addDays(7)->toDateString())
            ->whereHas('animal', fn (Builder $query) => $query->whereNull('exit_date')->whereNull('deleted_at'))
            ->count();

        return [
            'total_animals' => $totalHerd,
            'animals_by_status' => $animalsByStatus,
            'animals_by_category' => $animalsByCategory,
            'lactating_cows' => $milking,
            'todays_milk_litres' => round((float) $todaysMilkLitres, 2),
            'heats_this_week' => $heatsThisWeek,
            'services_this_week' => $servicesThisWeek,
            'pregnancies_pending' => $pregnanciesPending,
            'calvings_expected_this_week' => $calvingsDueThisWeek,
            'treatments_in_withholding' => count($this->withholding->sickBay()),
            'vaccines_due' => $vaccinesDue,
        ];
    }

    /**
     * Herd composition table with targets.
     */
    public function composition(): array
    {
        $totalHerd = Animal::query()->whereNull('exit_date')->whereNull('deleted_at')->count();

        $categories = [
            ['name' => 'Milking Cows', 'target' => 70],
            ['name' => 'Dry Cows', 'target' => 20],
            ['name' => 'Heifers', 'target' => 50],
            ['name' => 'Calves', 'target' => 40],
        ];

        $composition = [];
        foreach ($categories as $c) {
            $count = $this->activeCategoryCount($c['name']);

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
        $pdServices = $this->latestServices()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('pd_window_end', [$today->toDateString(), $end->toDateString()])
            ->whereNotExists($this->calvedSinceService())
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
        $dryServices = $this->latestServices()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('target_dry_date', [$today->toDateString(), $end->toDateString()])
            ->whereNotExists($this->calvedSinceService())
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
        $calvingServices = $this->latestServices()
            ->where('reproductive_status', 'pregnant')
            ->whereBetween('expected_calving_date', [$today->toDateString(), $end->toDateString()])
            ->whereNotExists($this->calvedSinceService())
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
     * Count active animals in a category, treating a missing lookup as zero.
     */
    private function activeCategoryCount(string $name): int
    {
        $categoryId = $this->categoryIdFor($name);

        if (! $categoryId) {
            return 0;
        }

        return Animal::query()
            ->where('category_id', $categoryId)
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Restrict a service query to the most recent service per animal.
     */
    private function latestServices(): Builder
    {
        $latestIds = Service::query()
            ->whereNull('deleted_at')
            ->selectRaw('MAX(id)')
            ->groupBy('animal_id');

        return Service::query()
            ->whereNull('deleted_at')
            ->whereIn('id', $latestIds);
    }

    /**
     * Exclude services that already resulted in a calving.
     */
    private function calvedSinceService(): \Closure
    {
        return function ($query) {
            $query->select(DB::raw(1))
                ->from('calvings')
                ->whereNull('calvings.deleted_at')
                ->whereColumn('calvings.dam_id', 'services.animal_id')
                ->whereColumn('calvings.calving_date', '>=', 'services.service_date');
        };
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
