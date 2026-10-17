<?php

namespace App\Domain\Settings\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

class SettingService
{
    public function all(): Collection
    {
        return Setting::query()
            ->whereNull('deleted_at')
            ->get()
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
