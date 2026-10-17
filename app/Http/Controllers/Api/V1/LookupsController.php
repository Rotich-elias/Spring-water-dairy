<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Lookups\Services\LookupService;

class LookupsController extends Controller
{
    public function __construct(private LookupService $lookups) {}

    public function all()
    {
        $this->authorize('lookups.view');

        return response()->json(['data' => $this->lookups->all()]);
    }

    public function byType(string $type)
    {
        $this->authorize('lookups.view');

        $data = $this->lookups->byType($type);

        if ($data->isEmpty() && ! $this->typeExists($type)) {
            abort(404, 'Lookup type not found.');
        }

        return response()->json(['data' => $data->values()->all()]);
    }

    private function typeExists(string $type): bool
    {
        return \App\Models\Lookup::query()
            ->whereNull('deleted_at')
            ->where('type', $type)
            ->exists();
    }
}
