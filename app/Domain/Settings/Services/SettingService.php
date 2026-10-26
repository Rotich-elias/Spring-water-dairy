<?php

namespace App\Domain\Settings\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

class SettingService
{
    public function all(): Collection
    {
        return $this->map(
            Setting::query()->whereNull('deleted_at')->get()
        );
    }

    /**
     * Settings safe to expose to every signed-in user (used by operational
     * screens such as the milk session sheet).
     */
    public function public(): Collection
    {
        return $this->map(
            Setting::query()
                ->whereNull('deleted_at')
                ->where('is_public', true)
                ->get()
        );
    }

    /**
     * @param  Collection<int, Setting>  $settings
     * @return Collection<string, mixed>
     */
    private function map(Collection $settings): Collection
    {
        return $settings
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value])
            ->sortKeys();
    }

    public function update(array $settings): array
    {
        foreach ($settings as $key => $value) {
            $setting = Setting::firstOrNew(['key' => $key]);
            $setting->value = $value;
            $setting->save();
        }

        cache()->forget('settings.all');

        return $this->all()->toArray();
    }
}
