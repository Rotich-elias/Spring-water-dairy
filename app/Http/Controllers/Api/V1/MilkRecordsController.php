<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MilkRecordStoreRequest;
use App\Http\Requests\MilkRecordUpdateRequest;
use App\Http\Resources\MilkRecordResource;
use App\Domain\Milk\Models\MilkRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MilkRecordsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('milk.view');

        $query = MilkRecord::query()->with('animal');

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }
        if ($request->filled('record_date')) {
            $query->whereDate('record_date', $request->record_date);
        }
        if ($request->filled('session')) {
            $query->where('session', $request->session);
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereDate('record_date', '>=', $request->from)
                  ->whereDate('record_date', '<=', $request->to);
        }

        $records = $query->orderByDesc('record_date')
            ->orderBy('session')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => MilkRecordResource::collection($records),
            'meta' => [
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function store(MilkRecordStoreRequest $request)
    {
        Gate::authorize('milk.create');

        $validated = $request->validated();
        $clientUuid = $validated['client_uuid'] ?? null;

        // Idempotency: check if client_uuid already exists
        if ($clientUuid) {
            $existing = MilkRecord::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return response()->json(['data' => new MilkRecordResource($existing)]);
            }
        }

        $record = MilkRecord::create(array_merge($validated, [
            'client_uuid' => $clientUuid ?? (string) \Illuminate\Support\Str::uuid(),
        ]));

        return response()->json(['data' => new MilkRecordResource($record->load('animal'))], 201);
    }

    public function show(MilkRecord $record)
    {
        Gate::authorize('milk.view', $record);

        return response()->json(['data' => new MilkRecordResource($record->load('animal'))]);
    }

    public function update(MilkRecordUpdateRequest $request, MilkRecord $record)
    {
        Gate::authorize('milk.update', $record);

        // Milkers can only edit entries created today by themselves (enforced in policy)
        $ifMatch = $request->header('If-Match');
        if ($ifMatch !== null && (int) $ifMatch !== (int) $record->version) {
            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'This record was edited elsewhere since you last read it.',
                    'fields' => ['_record' => ['The current version is '.$record->version.'.']],
                ],
            ], 409);
        }

        $record->fill($request->validated());
        $record->increment('version');
        $record->save();

        return response()->json(['data' => new MilkRecordResource($record->fresh()->load('animal'))]);
    }

    public function destroy(MilkRecord $record)
    {
        Gate::authorize('milk.delete', $record);

        $record->delete();

        return response()->json(null, 204);
    }
}
