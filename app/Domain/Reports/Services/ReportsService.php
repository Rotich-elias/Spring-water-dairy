<?php

namespace App\Domain\Reports\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Calving;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Models\Service as BreedingService;
use App\Domain\Health\Models\Treatment;
use App\Domain\Milk\Models\MilkRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    /**
     * Available report definitions (Phase 7 scope).
     *
     * @return Collection<int, array{id: string, name: string, description: string, type: string}>
     */
    public function list(): Collection
    {
        return collect([
            [
                'id' => 'animal-register',
                'name' => 'Animal Register',
                'description' => 'All animals grouped by category and status.',
                'type' => 'inventory',
            ],
            [
                'id' => 'breeding-pregnancy',
                'name' => 'Breeding & Pregnancy Report',
                'description' => 'Heats, services, pregnancies and expected calvings.',
                'type' => 'breeding',
            ],
            [
                'id' => 'calving',
                'name' => 'Calving Report',
                'description' => 'Calving events within a date range.',
                'type' => 'calving',
            ],
            [
                'id' => 'milk-production',
                'name' => 'Milk Production Report',
                'description' => 'Daily milk yield totals.',
                'type' => 'milk',
            ],
            [
                'id' => 'health-treatment',
                'name' => 'Health & Treatment Report',
                'description' => 'Animals currently under treatment or in withholding.',
                'type' => 'health',
            ],
            [
                'id' => 'mortality',
                'name' => 'Mortality Report',
                'description' => 'Exited animals and deaths within a date range.',
                'type' => 'mortality',
            ],
        ]);
    }

    public function generate(string $type, array $filters = []): array
    {
        return match ($type) {
            'animal-register' => $this->animalRegister(),
            'breeding-pregnancy' => $this->breedingPregnancy($filters),
            'calving' => $this->calvingReport($filters),
            'milk-production' => $this->milkProduction($filters),
            'health-treatment' => $this->healthTreatment($filters),
            'mortality' => $this->mortalityReport($filters),
            default => [],
        };
    }

    protected function animalRegister(): array
    {
        return Animal::query()->active()
            ->with(['category', 'status', 'pen', 'breed'])
            ->get()
            ->map(fn (Animal $a) => [
                'id' => $a->id,
                'ear_tag' => $a->ear_tag,
                'name' => $a->name,
                'category' => $a->category?->name,
                'status' => $a->status?->name,
                'pen' => $a->pen?->name,
                'breed' => $a->breed?->name,
                'sex' => $a->sex,
                'age' => $a->age,
            ])->toArray();
    }

    protected function breedingPregnancy(array $filters): array
    {
        $from = $filters['from'] ?? Carbon::today()->startOfWeek()->toDateString();
        $to = $filters['to'] ?? Carbon::today()->endOfWeek()->toDateString();

        $latestServices = BreedingService::query()
            ->whereNull('deleted_at')
            ->whereIn('id', BreedingService::query()
                ->whereNull('deleted_at')
                ->selectRaw('MAX(id)')
                ->groupBy('animal_id'));

        $notCalved = function ($query) {
            $query->select(DB::raw(1))
                ->from('calvings')
                ->whereNull('calvings.deleted_at')
                ->whereColumn('calvings.dam_id', 'services.animal_id')
                ->whereColumn('calvings.calving_date', '>=', 'services.service_date');
        };

        return [
            'heats_this_week' => Heat::query()
                ->whereBetween('heat_date', [$from, $to])
                ->count(),
            'services_this_week' => BreedingService::query()
                ->whereBetween('service_date', [$from, $to])
                ->count(),
            'pregnancies_pending' => (clone $latestServices)
                ->whereIn('reproductive_status', ['open', 'recheck'])
                ->where('service_date', '<=', Carbon::today())
                ->whereNotExists($notCalved)
                ->count(),
            'pregnancies_confirmed' => (clone $latestServices)
                ->where('reproductive_status', 'pregnant')
                ->whereNotExists($notCalved)
                ->count(),
            'expected_calvings' => (clone $latestServices)
                ->where('reproductive_status', 'pregnant')
                ->whereBetween('expected_calving_date', [$from, $to])
                ->whereNotExists($notCalved)
                ->count(),
        ];
    }

    protected function calvingReport(array $filters): array
    {
        $from = $filters['from'] ?? Carbon::today()->subDays(30)->toDateString();
        $to = $filters['to'] ?? Carbon::today()->toDateString();

        return Calving::query()
            ->with('dam')
            ->with('easeScore')
            ->whereBetween('calving_date', [$from, $to])
            ->orderBy('calving_date')
            ->get()
            ->map(fn (Calving $c) => [
                'id' => $c->id,
                'dam_ear_tag' => $c->dam->ear_tag,
                'calving_date' => $c->calving_date?->toDateString(),
                'ease_score' => $c->easeScore?->name,
                'retained_placenta' => $c->retained_placenta,
                'notes' => $c->notes,
            ])->toArray();
    }

    protected function milkProduction(array $filters): array
    {
        $from = $filters['from'] ?? Carbon::today()->subDays(7)->toDateString();
        $to = $filters['to'] ?? Carbon::today()->toDateString();

        return MilkRecord::query()
            ->whereNull('deleted_at')
            ->whereBetween('record_date', [$from, $to])
            ->where('excluded_from_sales', false)
            ->selectRaw('record_date as date, SUM(litres) as total_litres, COUNT(DISTINCT animal_id) as cows, COUNT(*) as records')
            ->groupBy('record_date')
            ->orderBy('record_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->toDateString(),
                'total_litres' => round((float) $row->total_litres, 2),
                'cows' => (int) $row->cows,
                'records' => (int) $row->records,
            ])->toArray();
    }

    protected function healthTreatment(array $filters): array
    {
        $from = $filters['from'] ?? Carbon::today()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? Carbon::today()->toDateString();

        return Treatment::query()
            ->with(['animal', 'condition'])
            ->whereNull('deleted_at')
            ->whereBetween('treatment_date', [$from, $to])
            ->whereHas('animal', fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy('treatment_date')
            ->get()
            ->map(fn (Treatment $t) => [
                'id' => $t->id,
                'animal_ear_tag' => $t->animal?->ear_tag,
                'condition' => $t->condition?->name,
                'drug_name' => $t->drug_name,
                'treatment_date' => $t->treatment_date?->toDateString(),
                'milk_safe_date' => $t->milk_safe_date?->toDateString(),
                'meat_safe_date' => $t->meat_safe_date?->toDateString(),
                'in_withholding' => $t->isInWithholding(),
            ])->toArray();
    }

    protected function mortalityReport(array $filters): array
    {
        $from = $filters['from'] ?? Carbon::today()->subDays(30)->toDateString();
        $to = $filters['to'] ?? Carbon::today()->toDateString();

        return Animal::query()
            ->whereNotNull('exit_date')
            ->whereBetween('exit_date', [$from, $to])
            ->with(['category', 'status'])
            ->get()
            ->map(fn (Animal $a) => [
                'id' => $a->id,
                'ear_tag' => $a->ear_tag,
                'exit_date' => $a->exit_date?->toDateString(),
                'exit_reason' => $a->exit_reason,
                'exit_price' => $a->exit_price,
                'category' => $a->category?->name,
                'status' => $a->status?->name,
            ])->toArray();
    }
}
