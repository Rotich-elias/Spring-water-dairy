<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Settings\Services\SettingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetupCompleteRequest;
use App\Models\Lookup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

class SetupController extends Controller
{
    public function status()
    {
        $this->authorize('setup.view');

        $steps = $this->steps();

        return response()->json([
            'data' => [
                'completed' => Setting::query()
                    ->where('key', 'setup_completed')
                    ->where('value', '1')
                    ->whereNull('deleted_at')
                    ->exists(),
                'steps' => $steps,
            ],
        ]);
    }

    public function complete(SetupCompleteRequest $request)
    {
        $data = $request->validated();

        $settings = collect($data)
            ->only(['farm_name', 'timezone', 'currency', 'units'])
            ->filter(fn ($value) => ! is_null($value))
            ->all();

        if ($settings !== []) {
            app(SettingService::class)->update($settings);
        }

        if (array_key_exists('pens', $data)) {
            $this->syncLookups('pens', $data['pens']);
        }

        if (array_key_exists('breeds', $data)) {
            $this->syncLookups('breeds', $data['breeds']);
        }

        app(SettingService::class)->update(['setup_completed' => true]);

        return response()->json([
            'data' => [
                'completed' => true,
                'steps' => $this->steps(),
            ],
        ]);
    }

    /**
     * @return array<int, array{name: string, done: bool}>
     */
    private function steps(): array
    {
        $steps = [
            'farm_profile' => Setting::query()
                ->where('key', 'farm_name')
                ->whereNotNull('value')
                ->whereNull('deleted_at')
                ->exists(),
            'units_timezone' => Setting::query()
                ->whereIn('key', ['timezone', 'currency', 'units'])
                ->whereNull('deleted_at')
                ->count() === 3,
            'pens' => Lookup::query()->where('type', 'pens')->whereNull('deleted_at')->exists(),
            'breeds' => Lookup::query()->where('type', 'breeds')->whereNull('deleted_at')->exists(),
            'users' => User::query()->whereNull('deleted_at')->exists(),
        ];

        return (new Collection($steps))
            ->map(fn (bool $done, string $name) => ['name' => $name, 'done' => $done])
            ->values()
            ->all();
    }

    /**
     * Replace the active list of lookups for a type, restoring soft-deleted
     * entries when the same name is added again.
     *
     * @param  array<int, string>  $names
     */
    private function syncLookups(string $type, array $names): void
    {
        $names = (new Collection($names))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return;
        }

        $keptIds = [];

        foreach ($names as $index => $name) {
            $lookup = Lookup::withTrashed()->firstOrNew([
                'type' => $type,
                'name' => $name,
            ]);

            if ($lookup->trashed()) {
                $lookup->restore();
            }

            $lookup->fill([
                'active' => true,
                'sort_order' => $index + 1,
            ])->save();

            $keptIds[] = $lookup->id;
        }

        Lookup::query()
            ->where('type', $type)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }
}
