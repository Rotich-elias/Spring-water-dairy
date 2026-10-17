<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalfRecordStoreRequest;
use App\Http\Requests\CalfWeanRequest;
use App\Http\Resources\CalfRecordResource;
use App\Domain\Animals\Models\Animal;
use App\Domain\Calves\Models\CalfRecord;
use App\Domain\Calves\Services\CalfGrowthService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class CalfRecordsController extends Controller
{
    public function __construct(private CalfGrowthService $growth) {}

    public function index(Request $request)
    {
        Gate::authorize('calves.view');

        $query = CalfRecord::query()->with('animal');

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }

        $records = $query->orderByDesc('id')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => CalfRecordResource::collection($records),
            'meta' => [
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function store(CalfRecordStoreRequest $request)
    {
        Gate::authorize('calves.create');

        $record = CalfRecord::create($request->validated());

        return response()->json(['data' => new CalfRecordResource($record->load('animal'))], 201);
    }

    public function show(CalfRecord $record)
    {
        Gate::authorize('calves.view', $record);

        return response()->json(['data' => new CalfRecordResource($record->load('animal'))]);
    }

    public function update(Request $request, CalfRecord $record)
    {
        Gate::authorize('calves.update', $record);

        $record->update($request->validate([
            'colostrum_litres' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'colostrum_hours' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'navel_dip' => ['sometimes', 'boolean'],
            'disbudding' => ['sometimes', 'boolean'],
            'birth_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'outcome' => ['sometimes', 'required', 'in:live,stillborn,deceased'],
        ]));

        return response()->json(['data' => new CalfRecordResource($record->fresh()->load('animal'))]);
    }

    public function destroy(CalfRecord $record)
    {
        Gate::authorize('calves.delete', $record);

        $record->delete();

        return response()->json(null, 204);
    }

    public function wean(CalfWeanRequest $request, CalfRecord $record)
    {
        Gate::authorize('calves.update', $record);

        $record->update([
            'weaning_date' => $request->validated()['weaning_date'],
            'weaning_weight' => $request->validated()['weaning_weight'],
        ]);

        return response()->json(['data' => new CalfRecordResource($record->fresh()->load('animal'))]);
    }

    /**
     * GET /calves/nursery — calves with flags.
     */
    public function nursery(Request $request)
    {
        Gate::authorize('calves.view');

        $calves = Animal::query()
            ->where('category_id', $this->categoryIdFor('Calves'))
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->with(['calfRecord', 'weights'])
            ->get();

        $nursery = $calves->map(fn ($calf) => [
            'id' => $calf->id,
            'ear_tag' => $calf->ear_tag,
            'age_days' => $calf->birth_date ? Carbon::parse($calf->birth_date)->diffInDays(now(), false) : null,
            'latest_weight' => $calf->weights->first()?->weight_kg,
            'adg' => $this->growth->computeOverallAdg($calf),
            'readiness' => $this->growth->weaningReadiness($calf),
            'flags' => $this->computeFlags($calf),
        ])->values()->all();

        return response()->json(['data' => $nursery]);
    }

    protected function computeFlags(Animal $calf): array
    {
        $flags = [];
        $readiness = $this->growth->weaningReadiness($calf);

        if ($readiness['ready_for_weaning'] && ! $readiness['already_weaned']) {
            $flags[] = 'due_for_weaning';
        }

        $latestWeight = $calf->weights->first();
        if ($latestWeight && ! $latestWeight->on_target) {
            $flags[] = 'behind_target';
        }

        // Due for weighing: no weight in last 30 days
        $lastWeightDate = $calf->weights->first()?->weight_date ?? $calf->birth_date;
        if ($lastWeightDate && Carbon::parse($lastWeightDate)->diffInDays(now(), false) > 30) {
            $flags[] = 'due_for_weighing';
        }

        return $flags;
    }

    protected function categoryIdFor(string $name): ?int
    {
        return \App\Models\Lookup::where('type', 'categories')->where('name', $name)->whereNull('deleted_at')->value('id');
    }
}
