<?php

namespace App\Domain\Breeding\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Calving;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Models\PregnancyCheck;
use App\Domain\Breeding\Models\Service;
use App\Models\Lookup;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BreedingService
{
    /**
     * Record a heat observation.
     */
    public function recordHeat(array $data): Heat
    {
        return DB::transaction(function () use ($data) {
            $animal = Animal::query()->where('id', $data['animal_id'])->firstOrFail();

            if ($animal->sex !== 'F') {
                throw ValidationException::withMessages([
                    'animal_id' => ['Only females can have heats recorded.'],
                ]);
            }

            $heat = Heat::create(array_merge($data, [
                'version' => 1,
            ]));

            return $heat->fresh();
        });
    }

    /**
     * Record a breeding service. Computes expected_calving_date (+280),
     * target_dry_date (+220), PD window (35 to 60 days before calving).
     * Rejects if cow is already pregnant unless override with reason.
     *
     * @param  array{
     *   client_uuid: string,
     *   animal_id: int,
     *   service_date: string,
     *   bull_id?: int|null,
     *   inseminator?: string|null,
     *   service_type?: 'AI'|'Natural',
     *   override?: bool,
     *   override_reason?: string|null,
     * }  $data
     */
    public function recordService(array $data): Service
    {
        return DB::transaction(function () use ($data) {
            $animal = Animal::query()->where('id', $data['animal_id'])->firstOrFail();

            if ($animal->sex !== 'F') {
                throw ValidationException::withMessages([
                    'animal_id' => ['Only females can be serviced.'],
                ]);
            }

            // Already-pregnant check: most recent service with pregnant status,
            // or pregnancy check with pregnant result, after voluntary waiting period.
            $alreadyPregnant = $this->isAlreadyPregnant($animal);

            if ($alreadyPregnant && ! filter_var($data['override'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages([
                    'reproductive_status' => ['already_pregnant' => 'Animal is already pregnant. Override required to record another service.'],
                ]);
            }

            if ($alreadyPregnant && ! $data['override_reason'] ?? null) {
                throw ValidationException::withMessages([
                    'override_reason' => ['An override reason is required.'],
                ]);
            }

            // Voluntary waiting period check
            $vwpDays = (int) Setting::where('key', 'voluntary_waiting_period_days')->value('value') ?? 60;
            $lastCalving = $animal->calvings()->latest('calving_date')->first();
            if ($lastCalving) {
                $daysSinceCalving = Carbon::parse($lastCalving->calving_date)->diffInDays($data['service_date'], false);
                if ($daysSinceCalving < $vwpDays && ! filter_var($data['override'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    throw ValidationException::withMessages([
                        'service_date' => ['service_too_early' => "Voluntary waiting period of {$vwpDays} days not yet met. Override required."],
                    ]);
                }
            }

            $gestationDays = (int) Setting::where('key', 'gestation_days')->value('value') ?? 280;
            $dryDaysBeforeCalving = (int) Setting::where('key', 'target_dry_days_before_calving')->value('value') ?? 60;
            $pdWindowStart = (int) Setting::where('key', 'pd_window_start_days')->value('value') ?? 35;
            $pdWindowEnd = (int) Setting::where('key', 'pd_window_end_days')->value('value') ?? 60;

            $serviceDate = Carbon::parse($data['service_date']);
            $expectedCalving = $serviceDate->copy()->addDays($gestationDays);
            $targetDry = $expectedCalving->copy()->subDays($dryDaysBeforeCalving);
            $pdWindowStartDate = $expectedCalving->copy()->subDays($pdWindowEnd);
            $pdWindowEndDate = $expectedCalving->copy()->subDays($pdWindowStart);

            $service = Service::create(array_merge($data, [
                'expected_calving_date' => $expectedCalving->toDateString(),
                'target_dry_date' => $targetDry->toDateString(),
                'pd_window_start' => $pdWindowStartDate->toDateString(),
                'pd_window_end' => $pdWindowEndDate->toDateString(),
                'reproductive_status' => 'open',
                'version' => 1,
            ]));

            // Update animal's reproductive status via latest service
            $animal->status_id = $this->statusIdFor('In Heat'); // best effort; may be null
            $animal->save();

            return $service->fresh();
        });
    }

    /**
     * Check if the animal is already pregnant based on latest service or PD check.
     */
    protected function isAlreadyPregnant(Animal $animal): bool
    {
        $latestService = $animal->services()->latest('service_date')->first();
        if ($latestService && in_array($latestService->reproductive_status, ['pregnant', 'bred'])) {
            return true;
        }

        $latestPD = $animal->pregnancyChecks()->latest('check_date')->first();
        if ($latestPD && $latestPD->result === 'pregnant') {
            return true;
        }

        return false;
    }

    protected function statusIdFor(string $name): ?int
    {
        return Lookup::where('type', 'animal-statuses')->where('name', $name)->whereNull('deleted_at')->value('id');
    }

    /**
     * Record a pregnancy check result. Updates animal's reproductive status.
     */
    public function recordPregnancyCheck(array $data): PregnancyCheck
    {
        return DB::transaction(function () use ($data) {
            $animal = Animal::query()->where('id', $data['animal_id'])->firstOrFail();

            $check = PregnancyCheck::create(array_merge($data, [
                'version' => 1,
            ]));

            // Update animal status based on result
            $statusMap = [
                'pregnant' => 'Pregnant',
                'open' => 'Active',
                'recheck' => 'In Heat',
            ];
            $statusName = $statusMap[$data['result']] ?? 'Active';
            $statusId = Lookup::where('type', 'animal-statuses')
                ->where('name', $statusName)
                ->whereNull('deleted_at')
                ->value('id');

            if ($statusId) {
                $animal->status_id = $statusId;
                $animal->increment('version');
                $animal->save();
            }

            return $check->fresh();
        });
    }

    /**
     * Get breeding calendar events between dates.
     */
    public function calendar(string $from, string $to): array
    {
        $events = [];

        // PD due: services whose PD window ends within range
        $pdServices = Service::query()
            ->whereBetween('pd_window_end', [$from, $to])
            ->with('animal')
            ->get();
        foreach ($pdServices as $s) {
            $events[] = [
                'type' => 'pd_due',
                'date' => $s->pd_window_end,
                'animal_id' => $s->animal_id,
                'ear_tag' => $s->animal->ear_tag,
                'title' => 'PD due',
                'animal' => $s->animal->ear_tag,
            ];
        }

        // Dry-off due: target_dry_date within range
        $dryServices = Service::query()
            ->whereBetween('target_dry_date', [$from, $to])
            ->where('reproductive_status', '!=', 'open')
            ->with('animal')
            ->get();
        foreach ($dryServices as $s) {
            $events[] = [
                'type' => 'dry_off_due',
                'date' => $s->target_dry_date,
                'animal_id' => $s->animal_id,
                'ear_tag' => $s->animal->ear_tag,
                'title' => 'Dry-off due',
                'animal' => $s->animal->ear_tag,
            ];
        }

        // Calving due: expected_calving_date within range
        $calvingServices = Service::query()
            ->whereBetween('expected_calving_date', [$from, $to])
            ->where('reproductive_status', '!=', 'open')
            ->with('animal')
            ->get();
        foreach ($calvingServices as $s) {
            $events[] = [
                'type' => 'calving_due',
                'date' => $s->expected_calving_date,
                'animal_id' => $s->animal_id,
                'ear_tag' => $s->animal->ear_tag,
                'title' => 'Calving due',
                'animal' => $s->animal->ear_tag,
            ];
        }

        usort($events, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $events;
    }

    /**
     * Breeding KPIs: conception rate, services per conception, days open, calving interval.
     */
    public function kpis(string $from, string $to): array
    {
        $services = Service::query()
            ->whereBetween('service_date', [$from, $to])
            ->get();

        $conceivedServices = $services->filter(fn ($s) => $s->reproductive_status === 'pregnant');
        $conceptionRate = $services->count() > 0
            ? round(($conceivedServices->count() / $services->count()) * 100, 1)
            : 0;

        $servicesPerConception = $conceivedServices->count() > 0
            ? round($services->count() / $conceivedServices->count(), 2)
            : 0;

        // Days open: average days from calving to first pregnant service
        $daysOpen = $this->computeDaysOpen();

        // Calving interval: average days between consecutive calvings per animal
        $calvingInterval = $this->computeCalvingInterval();

        return [
            'conception_rate' => $conceptionRate,
            'services_per_conception' => $servicesPerConception,
            'days_open' => $daysOpen,
            'calving_interval' => $calvingInterval,
            'period_from' => $from,
            'period_to' => $to,
        ];
    }

    protected function computeDaysOpen(): ?float
    {
        // For each animal with a pregnant service, days from last calving to service date
        $pregnantServices = Service::query()
            ->where('reproductive_status', 'pregnant')
            ->with('animal')
            ->get();

        $totalDays = 0;
        $count = 0;

        foreach ($pregnantServices as $service) {
            $lastCalving = $service->animal->calvings()
                ->where('calving_date', '<=', $service->service_date)
                ->latest('calving_date')
                ->first();
            if ($lastCalving) {
                $totalDays += Carbon::parse($lastCalving->calving_date)->diffInDays($service->service_date, false);
                $count++;
            }
        }

        return $count > 0 ? round($totalDays / $count, 1) : null;
    }

    protected function computeCalvingInterval(): ?float
    {
        // Average days between consecutive calvings per animal
        $calvings = Calving::query()
            ->with('dam')
            ->orderBy('dam_id')
            ->orderBy('calving_date')
            ->get();

        $intervalsByAnimal = $calvings->groupBy('dam_id');

        $totalDays = 0;
        $count = 0;

        foreach ($intervalsByAnimal as $animalCalvings) {
            $sorted = $animalCalvings->sortBy('calving_date');
            for ($i = 1; $i < $sorted->count(); $i++) {
                $prev = $sorted->get($i - 1)->calving_date;
                $curr = $sorted->get($i)->calving_date;
                $totalDays += Carbon::parse($prev)->diffInDays($curr, false);
                $count++;
            }
        }

        return $count > 0 ? round($totalDays / $count, 1) : null;
    }
}
