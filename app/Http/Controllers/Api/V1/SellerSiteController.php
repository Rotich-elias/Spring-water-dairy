<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettingUpdateRequest;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

class SellerSiteController extends Controller
{
    public function index()
    {
        $this->authorize('site.view');

        return response()->json(['data' => $this->allSettings()->all()]);
    }

    public function update(SiteSettingUpdateRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $definition = config('site.defaults.'.$key);

            SiteSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'is_public' => $definition['is_public'],
                ],
            );
        }

        return response()->json(['data' => $this->allSettings()->all()]);
    }

    /**
     * @return Collection<string, mixed>
     */
    private function allSettings(): Collection
    {
        $settings = collect(config('site.defaults', []))
            ->map(fn (array $definition) => $definition['value']);

        SiteSetting::query()->orderBy('key')->get()
            ->each(fn (SiteSetting $setting) => $settings->put($setting->key, $setting->value));

        return $settings;
    }
}
