<?php

namespace App\Domain\Alerts\Services;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Breeding\Models\Service as BreedingService;
use App\Domain\Health\Models\Treatment;
use App\Domain\Health\Models\Vaccination;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AlertsService
{
    public function list(): Collection
    {
        return Alert::query()->active()->unread()->with('animal')->orderByDesc('created_at')->get();
    }

    public function generateFromData(): int
    {
        $created = 0;

        // Withholding alerts: animals with treatments still in withholding.
        $withholding = Treatment::query()
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('milk_safe_date')
                    ->orWhere('milk_safe_date', '>=', Carbon::today());
            })
            ->with('animal')
            ->get();

        foreach ($withholding as $treatment) {
            if ($this->exists('withholding', $treatment->animal_id)) {
                continue;
            }

            Alert::create([
                'type' => 'withholding',
                'title' => 'Animal in withholding',
                'message' => "{$treatment->animal->ear_tag} is in withholding ({$treatment->drug_name}).",
                'animal_id' => $treatment->animal_id,
            ]);
            $created++;
        }

        // Overdue calving alerts: pregnant cows past expected calving date.
        $overdue = BreedingService::query()
            ->where('reproductive_status', 'pregnant')
            ->where('expected_calving_date', '<', Carbon::today())
            ->with('animal')
            ->get();

        foreach ($overdue as $service) {
            if ($this->exists('overdue_calving', $service->animal_id)) {
                continue;
            }

            Alert::create([
                'type' => 'overdue_calving',
                'title' => 'Overdue calving',
                'message' => "{$service->animal->ear_tag} is past expected calving date.",
                'animal_id' => $service->animal_id,
            ]);
            $created++;
        }

        // Vaccine due alerts.
        $due = Vaccination::query()
            ->whereNull('deleted_at')
            ->where('next_due_date', '<=', Carbon::today())
            ->with('animal')
            ->get();

        foreach ($due as $vaccination) {
            if ($this->exists('vaccine_due', $vaccination->animal_id)) {
                continue;
            }

            Alert::create([
                'type' => 'vaccine_due',
                'title' => 'Vaccine due',
                'message' => "{$vaccination->animal->ear_tag} vaccine ({$vaccination->vaccine_name}) is due.",
                'animal_id' => $vaccination->animal_id,
            ]);
            $created++;
        }

        return $created;
    }

    public function dismiss(Alert $alert): void
    {
        $alert->update(['dismissed_at' => Carbon::now()]);
    }

    public function markRead(Alert $alert): void
    {
        if (is_null($alert->read_at)) {
            $alert->update(['read_at' => Carbon::now()]);
        }
    }

    protected function exists(string $type, int $animalId): bool
    {
        return Alert::query()
            ->where('type', $type)
            ->where('animal_id', $animalId)
            ->whereNull('dismissed_at')
            ->whereNull('deleted_at')
            ->exists();
    }
}
