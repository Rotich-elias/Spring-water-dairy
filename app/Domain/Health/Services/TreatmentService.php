<?php

namespace App\Domain\Health\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Treatment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TreatmentService
{
    /**
     * Record a treatment for an animal.
     *
     * @param  array{
     *   client_uuid: string,
     *   animal_id: int,
     *   treatment_date: string,
     *   condition_id?: int|null,
     *   drug_name: string,
     *   dose: string,
     *   route: string,
     *   milk_withholding_days: int,
     *   meat_withholding_days: int,
     * }  $data
     *
     * @throws ValidationException if animal is male or withholding override rules violated
     */
    public function recordTreatment(array $data): Treatment
    {
        return DB::transaction(function () use ($data) {
            $clientUuid = $data['client_uuid'];

            // Idempotency: repeat client_uuid returns the original result.
            $existing = Treatment::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return $existing->fresh();
            }

            $animal = Animal::query()->where('id', $data['animal_id'])->firstOrFail();

            if ($animal->sex !== 'F') {
                throw ValidationException::withMessages([
                    'animal_id' => ['Only female animals can have treatments recorded.'],
                ]);
            }

            $withholdingService = new WithholdingService;
            $safeDates = $withholdingService->computeSafeDates(
                $data['treatment_date'],
                $data['milk_withholding_days'] ?? 0,
                $data['meat_withholding_days'] ?? 0
            );

            $treatment = Treatment::create(array_merge($data, $safeDates, [
                'version' => 1,
            ]));

            return $treatment->fresh();
        });
    }

    /**
     * Get all animals currently in withholding (sick bay).
     */
    public function sickBay(): array
    {
        $withholdingService = new WithholdingService;

        return $withholdingService->sickBay();
    }
}
