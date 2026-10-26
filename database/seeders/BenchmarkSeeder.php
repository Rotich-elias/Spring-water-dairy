<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class BenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        $benchmarks = [
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
        ];

        foreach ($benchmarks as $key => $row) {
            [$value, $type, $group, $isPublic] = $row;
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => $group, 'is_public' => $isPublic]
            );
        }
    }
}
