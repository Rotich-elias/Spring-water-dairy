<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Settings\Services\SettingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppearanceUpdateRequest;
use App\Models\Setting;
use Illuminate\Support\Collection;

class AppearanceController extends Controller
{
    /** @var list<string> */
    private const KEYS = ['theme_mode', 'theme_preset', 'theme_primary', 'theme_accent', 'farm_name'];

    /** @var array<string, string> */
    private const DEFAULTS = [
        'theme_mode' => 'light',
        'theme_preset' => 'evergreen',
        'theme_primary' => '#059669',
        'theme_accent' => '#0f766e',
        'farm_name' => 'Spring Water Dairy',
    ];

    public function __construct(private SettingService $settings) {}

    public function index()
    {
        $this->authorize('appearance.view');

        return response()->json(['data' => $this->appearance()]);
    }

    public function update(AppearanceUpdateRequest $request)
    {
        $this->authorize('appearance.update');

        $this->settings->update($request->validated());

        // Appearance values must remain available to the public settings feed
        // used by the frontend theme store.
        Setting::query()
            ->whereIn('key', self::KEYS)
            ->update(['is_public' => true]);

        return response()->json(['data' => $this->appearance()]);
    }

    /**
     * @return array<string, string>
     */
    private function appearance(): array
    {
        $values = $this->settings->all();

        return collect(self::DEFAULTS)
            ->mapWithKeys(fn (string $default, string $key): array => [
                $key => $this->value($values, $key, $default),
            ])
            ->all();
    }

    private function value(Collection $values, string $key, string $default): string
    {
        $value = $values->get($key);

        return is_scalar($value) ? (string) $value : $default;
    }
}
