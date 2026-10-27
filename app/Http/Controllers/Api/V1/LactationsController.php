<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Animals\Models\Animal;
use App\Domain\Milk\MilkSessionSchedule;
use App\Domain\Milk\Models\Lactation;
use App\Domain\Milk\Models\MilkRecord;
use App\Domain\Milk\Services\LactationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\LactationDryOffRequest;
use App\Http\Resources\LactationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LactationsController extends Controller
{
    public function __construct(private LactationService $lactations) {}

    public function index(Request $request)
    {
        Gate::authorize('milk.view');

        $query = Lactation::query()->with('animal');

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }
        if ($request->filled('active_only') && $request->boolean('active_only')) {
            $query->active();
        }

        $lactations = $query->orderByDesc('started_at')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => LactationResource::collection($lactations),
            'meta' => [
                'page' => $lactations->currentPage(),
                'per_page' => $lactations->perPage(),
                'total' => $lactations->total(),
                'last_page' => $lactations->lastPage(),
            ],
        ]);
    }

    public function show(Lactation $lactation)
    {
        Gate::authorize('milk.view', $lactation);

        return response()->json(['data' => new LactationResource($lactation->load('animal'))]);
    }

    public function dryOff(LactationDryOffRequest $request, Lactation $lactation)
    {
        Gate::authorize('milk.update', $lactation);

        $updated = $this->lactations->dryOff($lactation->id, $request->validated()['drying_off_date']);

        return response()->json(['data' => new LactationResource($updated)]);
    }

    /**
     * GET /animals/{id}/milk - Cow history and lactation curve data.
     */
    public function animalMilk(Animal $animal)
    {
        Gate::authorize('milk.view', $animal);

        $records = MilkRecord::query()
            ->where('animal_id', $animal->id)
            ->orderBy('record_date')
            ->orderBy('session')
            ->get();

        $lactation = $this->lactations->summary($animal);

        return response()->json(['data' => [
            'animal_id' => $animal->id,
            'ear_tag' => $animal->ear_tag,
            'days_in_milk' => $animal->days_in_milk,
            'lactation' => $lactation,
            'daily_records' => $records->groupBy(function ($record) {
                return \Carbon\Carbon::parse($record->record_date)->toDateString();
            })->map(function ($dayRecords, $date) {
                $row = ['date' => $date];

                foreach (MilkSessionSchedule::ALL as $session) {
                    $row[$session] = $dayRecords->firstWhere('session', $session)?->litres;
                }

                $row['total'] = $dayRecords->sum('litres');

                return $row;
            })->values()->all(),
        ]]);
    }
}
