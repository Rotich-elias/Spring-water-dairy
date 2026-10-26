<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Breeding\Models\SemenStraw;
use App\Http\Controllers\Controller;
use App\Http\Requests\SemenStrawStoreRequest;
use App\Http\Resources\SemenStrawResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SemenStrawsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('breeding.view');

        $straws = SemenStraw::query()
            ->orderBy('bull_code')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => SemenStrawResource::collection($straws),
            'meta' => [
                'page' => $straws->currentPage(),
                'per_page' => $straws->perPage(),
                'total' => $straws->total(),
                'last_page' => $straws->lastPage(),
            ],
        ]);
    }

    public function store(SemenStrawStoreRequest $request)
    {
        Gate::authorize('breeding.create');

        $straw = SemenStraw::create($request->validated());

        return response()->json(['data' => new SemenStrawResource($straw)], 201);
    }

    public function show(SemenStraw $straw)
    {
        Gate::authorize('breeding.view', $straw);

        return response()->json(['data' => new SemenStrawResource($straw)]);
    }

    public function update(Request $request, SemenStraw $straw)
    {
        Gate::authorize('breeding.update', $straw);

        $straw->update($request->validate([
            'bull_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'breed' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
        ]));

        return response()->json(['data' => new SemenStrawResource($straw->fresh())]);
    }

    public function destroy(SemenStraw $straw)
    {
        Gate::authorize('breeding.delete', $straw);

        $straw->delete();

        return response()->json(null, 204);
    }
}
