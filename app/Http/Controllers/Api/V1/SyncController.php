<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Milk\Models\SyncState;
use App\Domain\Animals\Models\Animal;
use App\Domain\Milk\Models\MilkRecord;
use Illuminate\Http\Request;
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
        Gate::authorize('milk.view');

        $since = $request->input('since');
        if (blank($since)) {
            $since = now()->subDays(7)->toISOString();
        }

        $since = \Carbon\Carbon::parse($since);

        // Changed records since the timestamp
        $changed = SyncState::query()
            ->where('changed_at', '>=', $since)
            ->orderBy('changed_at')
            ->get();

        $deleted = $changed->where('action', 'deleted')->map(fn ($s) => [
            'type' => $s->model,
            'id' => $s->record_id,
        ])->values()->all();

        $updated = $changed->where('action', '!=', 'deleted')->map(fn ($s) => [
            'type' => $s->model,
            'id' => $s->record_id,
            'changed_at' => $s->changed_at,
        ])->values()->all();

        // Active animals (lightweight for cache refresh)
        $animals = Animal::query()
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->select(['id', 'ear_tag', 'category_id', 'status_id', 'pen_id', 'updated_at'])
            ->get();

        // Today's milk sheet
        $today = now()->toDateString();
        $milkSheet = MilkRecord::query()
            ->where('record_date', $today)
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get();

        return response()->json(['data' => [
            'since' => $since->toISOString(),
            'updated' => $updated,
            'deleted' => $deleted,
            'animals' => $animals,
            'milk_sheet' => $milkSheet,
        ]]);
    }
}
