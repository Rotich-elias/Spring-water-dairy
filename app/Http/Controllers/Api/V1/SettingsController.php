<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Settings\Services\SettingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsUpdateRequest;

class SettingsController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function index()
    {
        $this->authorize('settings.view');

        return response()->json(['data' => $this->settings->all()]);
    }

    public function update(SettingsUpdateRequest $request)
    {
        $data = $this->settings->update($request->validated()['settings']);

        return response()->json(['data' => $data]);
    }
}
