<?php

namespace Database\Seeders;

use App\Domain\Settings\Services\SettingService;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class BenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingService::defaults() as $key => [$value, $type, $group, $isPublic]) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => $group, 'is_public' => $isPublic]
            );
        }
    }
}
