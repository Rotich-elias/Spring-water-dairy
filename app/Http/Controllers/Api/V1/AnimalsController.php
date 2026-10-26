<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Animals\Models\Animal;
use App\Domain\Animals\Services\AnimalLifecycleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnimalBulkStoreRequest;
use App\Http\Requests\AnimalExitRequest;
use App\Http\Requests\AnimalMoveRequest;
use App\Http\Requests\AnimalStoreRequest;
use App\Http\Requests\AnimalUpdateRequest;
use App\Http\Resources\AnimalResource;
use App\Models\Lookup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AnimalsController extends Controller
{
    public function __construct(private AnimalLifecycleService $lifecycle) {}

    public function index(Request $request)
    {
        Gate::authorize('animals.view');

        $query = Animal::query()
            ->with(['category', 'pen', 'status', 'breed'])
            ->active();

        if ($request->filled('category_id')) {
            $query->ofCategory((int) $request->category_id);
        }
        if ($request->filled('status_id')) {
            $query->ofStatus((int) $request->status_id);
        }
        if ($request->filled('pen_id')) {
            $query->ofPen((int) $request->pen_id);
        }
        if ($request->filled('breed_id')) {
            $query->ofBreed((int) $request->breed_id);
        }
        if ($request->filled('sex')) {
            $query->where('sex', $request->sex);
        }
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        $sort = $request->input('sort', 'ear_tag');
        foreach (explode(',', $sort) as $field) {
            $direction = 'asc';
            $column = $field;
            if (str_starts_with($field, '-')) {
                $direction = 'desc';
                $column = ltrim($field, '-');
            }
            $query->orderBy($column, $direction);
        }

        $animals = $query->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => AnimalResource::collection($animals),
            'meta' => [
                'page' => $animals->currentPage(),
                'per_page' => $animals->perPage(),
                'total' => $animals->total(),
                'last_page' => $animals->lastPage(),
            ],
        ]);
    }

    public function store(AnimalStoreRequest $request)
    {
        Gate::authorize('animals.create');

        $animal = Animal::create($request->validated())->fresh();

        return response()->json(['data' => new AnimalResource($animal->load(['category', 'pen', 'status', 'breed']))], 201);
    }

    public function bulk(AnimalBulkStoreRequest $request)
    {
        Gate::authorize('animals.create');

        $rows = $request->validated()['animals'];
        $tagPattern = config('app.ear_tag_pattern', '^[A-Z]{3,5}-\d{1,5}$');

        $created = 0;
        $failed = 0;
        $results = [];
        $seenEarTags = [];
        $seenClientUuids = [];

        foreach ($rows as $index => $row) {
            $clientUuid = $row['client_uuid'] ?? (string) Str::uuid();
            $rowData = $row;
            unset($rowData['client_uuid']);

            // Idempotency: repeat client_uuid returns the original result.
            $existing = Animal::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                $results[] = ['index' => $index, 'status' => 'created', 'id' => $existing->id];
                $created++;

                continue;
            }

            // Per-row validation.
            $errors = [];

            if (blank($rowData['ear_tag'] ?? null)) {
                $errors['ear_tag'][] = 'The ear tag field is required.';
            } elseif (! preg_match('/'.$tagPattern.'/', $rowData['ear_tag'])) {
                $errors['ear_tag'][] = 'The ear tag format is invalid.';
            } elseif (isset($seenEarTags[$rowData['ear_tag']])) {
                $errors['ear_tag'][] = 'The ear tag has already been taken.';
            } elseif (Animal::where('ear_tag', $rowData['ear_tag'])->whereNull('deleted_at')->exists()) {
                $errors['ear_tag'][] = 'The ear tag has already been taken.';
            } else {
                $seenEarTags[$rowData['ear_tag']] = true;
            }

            if (isset($rowData['category_id']) && ! Lookup::where('id', $rowData['category_id'])->whereNull('deleted_at')->exists()) {
                $errors['category_id'][] = 'The selected category is invalid.';
            }

            if (isset($rowData['sex']) && ! in_array($rowData['sex'], ['M', 'F'])) {
                $errors['sex'][] = 'Sex must be M (male) or F (female).';
            }

            if (isset($rowData['birth_date']) && $rowData['birth_date'] !== null) {
                if (! Carbon::parse($rowData['birth_date'])->lte(Carbon::today())) {
                    $errors['birth_date'][] = 'The birth date cannot be in the future.';
                }
            }

            if (! empty($errors)) {
                $results[] = ['index' => $index, 'status' => 'failed', 'errors' => $errors];
                $failed++;

                continue;
            }

            try {
                $animal = Animal::create(array_merge($rowData, ['client_uuid' => $clientUuid]));
                $results[] = ['index' => $index, 'status' => 'created', 'id' => $animal->id];
                $created++;
            } catch (\Throwable $e) {
                $results[] = [
                    'index' => $index,
                    'status' => 'failed',
                    'errors' => ['__general' => [$e->getMessage()]],
                ];
                $failed++;
            }
        }

        return response()->json(['data' => [
            'created' => $created,
            'failed' => $failed,
            'results' => $results,
        ]]);
    }

    public function show(Animal $animal)
    {
        Gate::authorize('animals.view', $animal);

        return response()->json(['data' => new AnimalResource($animal->load(['category', 'pen', 'status', 'breed']))]);
    }

    public function update(AnimalUpdateRequest $request, Animal $animal)
    {
        Gate::authorize('animals.update', $animal);

        // Optimistic locking via If-Match.
        $ifMatch = $request->header('If-Match');
        if ($ifMatch !== null && (int) $ifMatch !== (int) $animal->version) {
            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'This record was edited elsewhere since you last read it.',
                    'fields' => ['_record' => ['The current version is '.$animal->version.'.']],
                ],
            ], 409);
        }

        $animal->fill($request->validated());
        $animal->increment('version');
        $animal->save();

        return response()->json(['data' => new AnimalResource($animal->fresh()->load(['category', 'pen', 'status', 'breed']))]);
    }

    public function move(AnimalMoveRequest $request, Animal $animal)
    {
        Gate::authorize('animals.update', $animal);

        $updated = $this->lifecycle->move($animal, $request->validated());

        return response()->json(['data' => new AnimalResource($updated->load(['category', 'pen', 'status', 'breed']))]);
    }

    public function exit(AnimalExitRequest $request, Animal $animal)
    {
        Gate::authorize('animals.delete', $animal);

        $updated = $this->lifecycle->exit($animal, $request->validated());

        return response()->json(['data' => new AnimalResource($updated->load(['category', 'pen', 'status', 'breed']))]);
    }

    public function timeline(Animal $animal)
    {
        Gate::authorize('animals.view', $animal);

        $events = collect();

        if (Schema::hasTable('milk_records')) {
            foreach ($animal->milkRecords()->orderBy('record_date')->get() as $record) {
                $events->push([
                    'type' => 'milk',
                    'date' => $record->record_date,
                    'description' => 'Milk recorded: '.$record->litres.' L',
                    'data' => $record,
                ]);
            }
        }

        if (Schema::hasTable('treatments')) {
            foreach ($animal->treatments()->orderBy('treatment_date')->get() as $treatment) {
                $events->push([
                    'type' => 'treatment',
                    'date' => $treatment->treatment_date,
                    'description' => 'Treatment: '.$treatment->drug_name,
                    'data' => $treatment,
                ]);
            }
        }

        if (Schema::hasTable('services')) {
            foreach ($animal->services()->orderBy('service_date')->get() as $service) {
                $events->push([
                    'type' => 'service',
                    'date' => $service->service_date,
                    'description' => 'Breeding service recorded',
                    'data' => $service,
                ]);
            }
        }

        return response()->json(['data' => $events->sortBy('date')->values()->all()]);
    }

    public function lineage(Animal $animal)
    {
        Gate::authorize('animals.view', $animal);

        return response()->json(['data' => [
            'dam' => $animal->dam ? new AnimalResource($animal->dam) : null,
            'sire' => $animal->sire ? new AnimalResource($animal->sire) : null,
            'offspring' => AnimalResource::collection($animal->offspring()->get()),
        ]]);
    }

    public function lookup(Request $request)
    {
        Gate::authorize('animals.view');

        $tag = $request->input('tag');
        if (blank($tag)) {
            return response()->json(['data' => []]);
        }

        $animals = Animal::active()
            ->where('ear_tag', 'like', '%'.$tag.'%')
            ->orderBy('ear_tag')
            ->limit(10)
            ->get();

        return response()->json(['data' => AnimalResource::collection($animals)]);
    }
}
