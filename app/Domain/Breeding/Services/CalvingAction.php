<?php

namespace App\Domain\Breeding\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Calving;
use App\Domain\Calves\Models\CalfRecord;
use App\Domain\Milk\Models\Lactation;
use App\Models\Lookup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CalvingAction
{
    /**
     * One transaction: record calving, create calves, start lactation,
     * move dam, update reproductive status.
     *
     * @param  array{
     *   client_uuid: string,
     *   dam_id: int,
     *   calving_date: string,
     *   ease_score_id?: int|null,
     *   retained_placenta?: bool,
     *   calves: list<{
     *     ear_tag: string,
     *     sex: 'M'|'F',
     *     birth_weight?: float|null,
     *     outcome: string,
     *     colostrum_litres?: float|null,
     *     colostrum_hours?: int|null,
     *   }>,
     *   notes?: string|null,
     * }  $data
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $dam = Animal::query()->where('id', $data['dam_id'])->firstOrFail();

            if ($dam->sex !== 'F') {
                throw ValidationException::withMessages([
                    'dam_id' => ['Dam must be female.'],
                ]);
            }

            // Record calving
            $calving = Calving::create([
                'client_uuid' => $data['client_uuid'],
                'dam_id' => $dam->id,
                'calving_date' => $data['calving_date'],
                'ease_score_id' => $data['ease_score_id'] ?? null,
                'retained_placenta' => $data['retained_placenta'] ?? false,
                'notes' => $data['notes'] ?? null,
                'version' => 1,
            ]);

            // Create calf animals + calf records
            $calfAnimals = [];
            foreach ($data['calves'] as $calfData) {
                $calf = Animal::create([
                    'client_uuid' => Str::uuid(),
                    'ear_tag' => $calfData['ear_tag'],
                    'name' => null,
                    'category_id' => $this->categoryIdFor('Calves'),
                    'pen_id' => $this->penIdFor('Calf Nursery'),
                    'status_id' => $this->statusIdFor('Active'),
                    'breed_id' => $dam->breed_id,
                    'sex' => $calfData['sex'],
                    'birth_date' => Carbon::parse($data['calving_date'])->toDateString(),
                    'dam_id' => $dam->id,
                    'sire_id' => null,
                    'version' => 1,
                ]);

                CalfRecord::create([
                    'animal_id' => $calf->id,
                    'colostrum_litres' => $calfData['colostrum_litres'] ?? null,
                    'colostrum_hours' => $calfData['colostrum_hours'] ?? null,
                    'navel_dip' => false,
                    'disbudding' => false,
                    'weaning_date' => null,
                    'weaning_weight' => null,
                    'birth_weight' => $calfData['birth_weight'] ?? null,
                    'outcome' => $calfData['outcome'] ?? 'live',
                ]);

                $calfAnimals[] = $calf->fresh();
            }

            // Start lactation for dam — count existing calvings before this one
            $existingCalvings = $dam->calvings()->where('id', '!=', $calving->id)->count();
            $lactationNumber = $existingCalvings + 1;
            $lactation = Lactation::create([
                'animal_id' => $dam->id,
                'lactation_number' => $lactationNumber,
                'started_at' => Carbon::parse($data['calving_date'])->toDateString(),
                'total_litres' => 0,
            ]);

            // Move dam to milking category / parlour pen
            $milkingCategory = $this->categoryIdFor('Milking Cows');
            $parlourPen = $this->penIdFor('Parlour A');
            if ($milkingCategory) {
                $dam->category_id = $milkingCategory;
            }
            if ($parlourPen) {
                $dam->pen_id = $parlourPen;
            }
            $dam->status_id = $this->statusIdFor('In Heat'); // or 'Active'
            $dam->increment('version');
            $dam->save();

            // Update dam's reproductive status to pregnant (just calved -> will be rebred)
            // Actually after calving, cow is open again but lactating. Set status to Active.
            $statusId = $this->statusIdFor('Active');
            if ($statusId) {
                $dam->status_id = $statusId;
                $dam->increment('version');
                $dam->save();
            }

            return [
                'calving' => $calving->fresh(),
                'calves' => $calfAnimals,
                'lactation' => $lactation->fresh(),
                'dam' => $dam->fresh(),
            ];
        });
    }

    protected function categoryIdFor(string $name): ?int
    {
        return Lookup::where('type', 'categories')->where('name', $name)->whereNull('deleted_at')->value('id');
    }

    protected function penIdFor(string $name): ?int
    {
        return Lookup::where('type', 'pens')->where('name', $name)->whereNull('deleted_at')->value('id');
    }

    protected function statusIdFor(string $name): ?int
    {
        return Lookup::where('type', 'animal-statuses')->where('name', $name)->whereNull('deleted_at')->value('id');
    }
}
