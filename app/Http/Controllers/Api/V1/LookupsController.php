<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Lookups\Services\LookupService;
use App\Http\Controllers\Controller;
use App\Http\Requests\LookupStoreRequest;
use App\Http\Requests\LookupUpdateRequest;
use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LookupsController extends Controller
{
    public function __construct(private LookupService $lookups) {}

    public function all()
    {
        $this->authorize('lookups.view');

        return response()->json(['data' => $this->lookups->all()]);
    }

    public function byType(Request $request, string $type)
    {
        $this->authorize('lookups.view');

        $data = $this->lookups->byType($type, $request->boolean('include_inactive'));

        if ($data->isEmpty() && ! $this->typeExists($type)) {
            abort(404, 'Lookup type not found.');
        }

        return response()->json(['data' => $data->values()->all()]);
    }

    public function store(LookupStoreRequest $request)
    {
        $data = $request->validated();

        $exists = Lookup::query()
            ->where('type', $data['type'])
            ->where('name', $data['name'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A lookup with this name already exists for this type.'],
            ]);
        }

        $lookup = Lookup::withTrashed()->firstOrNew([
            'type' => $data['type'],
            'name' => $data['name'],
        ]);

        if ($lookup->trashed()) {
            $lookup->restore();
        }

        $lookup->fill([
            'active' => $data['active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ])->save();

        return response()->json(['data' => $this->present($lookup->fresh())], 201);
    }

    public function update(LookupUpdateRequest $request, Lookup $lookup)
    {
        $data = $request->validated();

        if (isset($data['name'])) {
            $duplicate = Lookup::query()
                ->where('type', $lookup->type)
                ->where('name', $data['name'])
                ->whereKeyNot($lookup->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => ['A lookup with this name already exists for this type.'],
                ]);
            }
        }

        $lookup->update($data);

        return response()->json(['data' => $this->present($lookup->fresh())]);
    }

    public function destroy(Lookup $lookup)
    {
        $this->authorize('lookups.edit');

        $lookup->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{id: int, type: string, name: string, active: bool, sort_order: int}
     */
    private function present(Lookup $lookup): array
    {
        return [
            'id' => $lookup->id,
            'type' => $lookup->type,
            'name' => $lookup->name,
            'active' => (bool) $lookup->active,
            'sort_order' => (int) $lookup->sort_order,
        ];
    }

    private function typeExists(string $type): bool
    {
        return Lookup::query()
            ->whereNull('deleted_at')
            ->where('type', $type)
            ->exists();
    }
}
