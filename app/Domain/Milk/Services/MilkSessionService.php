<?php

namespace App\Domain\Milk\Services;

use App\Domain\Animals\Models\Animal;
use App\Domain\Milk\Models\MilkRecord;
use App\Models\Lookup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class MilkSessionService
{
    /**
     * Save a whole milk session in one call. Upserts one record per animal per date
     * and session, so retries are safe (idempotent via the session UUID).
     *
     * @param  array{
     *   client_uuid: string,
     *   record_date: string,
     *   session: 'morning'|'evening',
     *   entries: list<{animal_id: int, litres: float, cmt_score: float|null, scc: int|null}>
     * }  $data
     * @return array{saved: int, skipped: int, warnings: int, entries: list<array>}
     */
    public function saveSession(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                $sessionUuid = $data['client_uuid'];
                $recordDate = $data['record_date'];
                $session = $data['session'];
                $entries = $data['entries'];

                // Idempotency: if this session UUID was already processed, return the original result.
                $existing = MilkRecord::where('session_uuid', $sessionUuid)->first();
                if ($existing) {
                    return $this->buildResponseFromExisting($existing, $recordDate, $session);
                }

                $saved = 0;
                $skipped = 0;
                $warnings = 0;
                $entryResults = [];

                foreach ($entries as $entry) {
                    $animalId = $entry['animal_id'];
                    $litres = (float) $entry['litres'];
                    $cmtScore = $entry['cmt_score'] ?? null;
                    $scc = $entry['scc'] ?? null;

                    $animal = Animal::query()->where('id', $animalId)->first();

                    if (! $animal || $animal->sex !== 'F') {
                        $entryResults[] = [
                            'animal_id' => $animalId,
                            'status' => 'skipped',
                            'reason' => $animal ? 'male_animal' : 'animal_not_found',
                        ];
                        $skipped++;

                        continue;
                    }

                    // Check withholding status
                    $inWithholding = (bool) $animal->in_withholding;
                    $excludedFromSales = $inWithholding;

                    if ($inWithholding) {
                        $warnings++;
                    }

                    // Upsert: find existing by animal/date/session, or create new
                    $record = MilkRecord::query()
                        ->where('animal_id', $animalId)
                        ->where('record_date', $recordDate)
                        ->where('session', $session)
                        ->first();

                    if ($record) {
                        $record->update([
                            'session_uuid' => $sessionUuid,
                            'litres' => $litres,
                            'cmt_score' => $cmtScore,
                            'scc' => $scc,
                            'in_withholding' => $inWithholding,
                            'excluded_from_sales' => $excludedFromSales,
                        ]);
                        $record->increment('version');
                    } else {
                        $record = MilkRecord::create([
                            'session_uuid' => $sessionUuid,
                            'animal_id' => $animalId,
                            'record_date' => $recordDate,
                            'session' => $session,
                            'litres' => $litres,
                            'cmt_score' => $cmtScore,
                            'scc' => $scc,
                            'in_withholding' => $inWithholding,
                            'excluded_from_sales' => $excludedFromSales,
                            'version' => 1,
                        ]);
                        $saved++;
                    }

                    $entryResults[] = [
                        'animal_id' => $animalId,
                        'status' => 'saved',
                        'id' => $record->id,
                        'warning' => $inWithholding ? 'withholding_active' : null,
                    ];
                }

                return [
                    'saved' => $saved,
                    'skipped' => $skipped,
                    'warnings' => $warnings,
                    'entries' => $entryResults,
                    'client_uuid' => $sessionUuid,
                ];
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A retry can race the pre-check above. Return the already stored
            // session instead of surfacing a database error to the client.
            $existing = MilkRecord::where('session_uuid', $data['client_uuid'])->first();

            if ($existing) {
                return $this->buildResponseFromExisting($existing, $data['record_date'], $data['session']);
            }

            throw $exception;
        }
    }

    /**
     * Build a response from an existing record (idempotent replay).
     */
    protected function buildResponseFromExisting(MilkRecord $existing, string $recordDate, string $session): array
    {
        $records = MilkRecord::forDateAndSession($recordDate, $session)
            ->where('session_uuid', $existing->session_uuid)
            ->get();

        $entryResults = $records->map(fn (MilkRecord $r) => [
            'animal_id' => $r->animal_id,
            'status' => 'saved',
            'id' => $r->id,
            'warning' => $r->in_withholding ? 'withholding_active' : null,
        ])->all();

        return [
            'saved' => $records->count(),
            'skipped' => 0,
            'warnings' => $records->where('in_withholding', true)->count(),
            'entries' => $entryResults,
            'client_uuid' => $existing->session_uuid,
            'replayed' => true,
        ];
    }

    /**
     * Get the session sheet: every lactating cow with last yield, withholding flag and DIM.
     */
    public function sessionSheet(string $date, string $session): array
    {
        $milkingCategoryId = Lookup::query()
            ->where('type', 'categories')
            ->where('name', 'Milking Cows')
            ->whereNull('deleted_at')
            ->value('id');

        $lactatingAnimals = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->when($milkingCategoryId, fn ($query) => $query->where('category_id', $milkingCategoryId))
            ->with(['milkRecords' => fn ($q) => $q->latest('record_date')])
            ->orderBy('ear_tag')
            ->get();

        $sheet = [];

        foreach ($lactatingAnimals as $animal) {
            $lastRecord = $animal->milkRecords->first();
            $alreadyRecorded = MilkRecord::query()
                ->where('animal_id', $animal->id)
                ->whereDate('record_date', $date)
                ->where('session', $session)
                ->exists();

            $sheet[] = [
                'id' => $animal->id,
                'ear_tag' => $animal->ear_tag,
                'name' => $animal->name,
                // decimal casts serialise as strings; the API contract promises numbers.
                'last_yield' => $lastRecord ? (float) $lastRecord->litres : null,
                'last_record_date' => $lastRecord?->record_date,
                'dims' => $animal->days_in_milk,
                'in_withholding' => (bool) $animal->in_withholding,
                'already_recorded' => $alreadyRecorded,
            ];
        }

        return $sheet;
    }

    /**
     * Compute CMT and SCC alerts for a session.
     */
    public function sessionAlerts(string $date, string $session): array
    {
        $alerts = MilkRecord::forDateAndSession($date, $session)
            ->inWithholding()
            ->with('animal')
            ->get()
            ->map(fn (MilkRecord $r) => [
                'animal_id' => $r->animal_id,
                'ear_tag' => $r->animal->ear_tag,
                'litres' => $r->litres,
                'cmt_score' => $r->cmt_score,
                'scc' => $r->scc,
                'alert' => $r->cmt_score && $r->cmt_score >= 3 ? 'high_cmt' : null,
                'scc_alert' => $r->scc && $r->scc >= 400 ? 'high_scc' : null,
            ])->filter(fn ($a) => $a['alert'] || $a['scc_alert']);

        return $alerts->values()->all();
    }
}
