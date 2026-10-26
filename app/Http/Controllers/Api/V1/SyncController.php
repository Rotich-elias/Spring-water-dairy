<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Animals\Models\Animal;
use App\Domain\Milk\Models\MilkRecord;
use App\Domain\Milk\Models\SyncState;
use App\Domain\Milk\SyncStateObserver;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class SyncController extends Controller
{
    /**
     * GET /sync/changes?since=<ISO timestamp>
     * Returns lookups, active animals, and today's milk sheet changed since then,
     * plus deleted records.
     */
    public function changes(Request $request)
    {
        Gate::authorize('animals.view');

        $since = $request->input('since');
        if (blank($since)) {
            $since = now()->subDays(7)->toISOString();
        }

        $since = Carbon::parse($since);

        $changed = SyncState::query()
            ->where('changed_at', '>=', $since)
            ->orderByDesc('changed_at')
            ->get()
            ->unique(fn (SyncState $state) => $state->model.'#'.$state->record_id)
            ->values();

        $deleted = $changed
            ->where('action', 'deleted')
            ->map(fn (SyncState $state) => [
                'type' => SyncStateObserver::typeFor($state->model),
                'id' => $state->record_id,
            ])
            ->values()
            ->all();

        $updated = $this->updatedRecords($changed->where('action', '!=', 'deleted'));

        $animals = Animal::query()
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get();

        $milkSheet = MilkRecord::query()
            ->where('record_date', now()->toDateString())
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get();

        return response()->json(['data' => [
            'since' => $since->toISOString(),
            'updated' => $updated,
            'deleted' => $deleted,
            'animals' => $animals,
            'milk_sheet' => $milkSheet,
        ]]);
    }

    /**
     * Hydrate sync-state rows into full records for offline caching.
     *
     * @param  Collection<int, SyncState>  $states
     * @return array<int, array{type: string, id: int, changed_at: string|null, data: array<string, mixed>|null}>
     */
    private function updatedRecords(Collection $states): array
    {
        $updated = [];

        foreach ($states->groupBy('model') as $modelClass => $rows) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $records = $modelClass::query()
                ->whereIn((new $modelClass)->getKeyName(), $rows->pluck('record_id'))
                ->get()
                ->keyBy(fn ($record) => $record->getKey());

            foreach ($rows as $state) {
                $record = $records->get($state->record_id);

                $updated[] = [
                    'type' => SyncStateObserver::typeFor($modelClass),
                    'id' => $state->record_id,
                    'changed_at' => $state->changed_at?->toISOString(),
                    'data' => $record?->toArray(),
                ];
            }
        }

        return $updated;
    }
}
