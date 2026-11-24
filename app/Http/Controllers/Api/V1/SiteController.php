<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

class SiteController extends Controller
{
    public function index()
    {
        $settings = $this->publicDefaults();

        SiteSetting::query()->orderBy('key')->get()
            ->each(function (SiteSetting $setting) use ($settings): void {
                if ($setting->is_public) {
                    $settings->put($setting->key, $setting->value);
                } else {
                    $settings->forget($setting->key);
                }
            });

        return response()->json(['data' => $settings->all()]);
    }

    /**
     * @return Collection<string, mixed>
     */
    private function publicDefaults(): Collection
    {
        return collect(config('site.defaults', []))
            ->filter(fn (array $definition): bool => (bool) $definition['is_public'])
            ->map(fn (array $definition) => $definition['value']);
    }
}
