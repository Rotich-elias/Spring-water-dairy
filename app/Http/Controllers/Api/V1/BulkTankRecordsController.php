<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkTankStoreRequest;
use App\Http\Resources\BulkTankRecordResource;
use App\Domain\Milk\Models\BulkTankRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BulkTankRecordsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('milk.view');

        $query = BulkTankRecord::query();

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereDate('record_date', '>=', $request->from)
                  ->whereDate('record_date', '<=', $request->to);
        }

        $records = $query->orderByDesc('record_date')
            ->orderBy('session')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => BulkTankRecordResource::collection($records),
            'meta' => [
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function store(BulkTankStoreRequest $request)
    {
        Gate::authorize('milk.create');

        $validated = $request->validated();
        $clientUuid = $validated['client_uuid'];

        // Idempotency
        $existing = BulkTankRecord::where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return response()->json(['data' => new BulkTankRecordResource($existing)]);
        }

        // Unique constraint on (record_date, session) - upsert behavior
        $record = BulkTankRecord::updateOrCreate(
            ['record_date' => $validated['record_date'], 'session' => $validated['session']],
            array_merge($validated, ['client_uuid' => $clientUuid])
        );

        return response()->json(['data' => new BulkTankRecordResource($record)], 201);
    }

    public function show(BulkTankRecord $record)
    {
        Gate::authorize('milk.view', $record);

        return response()->json(['data' => new BulkTankRecordResource($record)]);
    }
}
