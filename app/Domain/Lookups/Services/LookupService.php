<?php

namespace App\Domain\Lookups\Services;

use App\Models\Lookup;
use Illuminate\Support\Collection;

class LookupService
{
    public function all(): Collection
    {
        return Lookup::query()
            ->whereNull('deleted_at')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('type')
            ->map(fn (Collection $rows) => $rows->values()->map(fn (Lookup $row) => [
                'id' => $row->id,
                'name' => $row->name,
                'active' => $row->active,
                'sort_order' => $row->sort_order,
            ])->toArray());
    }

    public function byType(string $type): Collection
    {
        return Lookup::query()
            ->whereNull('deleted_at')
            ->ofType($type)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Lookup $row) => [
                'id' => $row->id,
                'name' => $row->name,
                'active' => $row->active,
                'sort_order' => $row->sort_order,
            ]);
    }
}
