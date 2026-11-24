<?php

namespace App\Domain\Settings\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

class SettingService
{
    /**
     * Default settings shared by fresh installs and newly created tenants.
     *
     * @return array<string, array{0: mixed, 1: string, 2: string, 3: bool}>
     */
    public static function defaults(): array
    {
        return [
            'farm_name' => ['Spring Water Dairy', 'string', 'general', true],
            'timezone' => ['Africa/Nairobi', 'string', 'general', true],
            'currency' => ['KES', 'string', 'general', true],
            'units' => ['metric', 'string', 'general', true],
            'ear_tag_pattern' => ['^[A-Z]{3,5}-\d{1,5}$', 'string', 'general', true],
            'gestation_days' => ['280', 'integer', 'breeding', true],
            'voluntary_waiting_period_days' => ['60', 'integer', 'breeding', true],
            'target_dry_days_before_calving' => ['60', 'integer', 'breeding', true],
            'pd_window_start_days' => ['35', 'integer', 'breeding', true],
            'pd_window_end_days' => ['60', 'integer', 'breeding', true],
            'milk_withholding_default_days' => ['4', 'integer', 'health', true],
            'meat_withholding_default_days' => ['14', 'integer', 'health', true],
            'milk_sessions_per_day' => ['2', 'integer', 'milk', true],
            'dashboard_cache_ttl_minutes' => ['15', 'integer', 'dashboard', true],
            'farm_address' => ['Nairobi, Kenya', 'string', 'branding', true],
            'farm_contact_phone' => ['+254 700 000 000', 'string', 'branding', true],
            'farm_contact_email' => ['info@springwaterdairy.local', 'string', 'branding', true],
            'farm_logo_path' => ['/images/farm-logo.png', 'string', 'branding', true],
            'theme_mode' => ['light', 'string', 'appearance', true],
            'theme_preset' => ['evergreen', 'string', 'appearance', true],
            'theme_primary' => ['#059669', 'string', 'appearance', true],
            'theme_accent' => ['#0f766e', 'string', 'appearance', true],
        ];
    }

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
