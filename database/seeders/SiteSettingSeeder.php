<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('site.defaults', []) as $key => $definition) {
            SiteSetting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => $definition['value'],
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'is_public' => $definition['is_public'],
                ],
            );
        }
    }
}
