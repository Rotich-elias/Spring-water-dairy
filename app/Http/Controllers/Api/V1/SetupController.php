<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Lookup;

class SetupController extends Controller
{
    public function status()
    {
        $this->authorize('setup.view');

        $steps = [
            'farm_profile' => Setting::where('key', 'farm_name')->whereNull('deleted_at')->exists(),
            'units_timezone' => Setting::where('key', 'timezone')->whereNull('deleted_at')->exists(),
            'pens' => Lookup::where('type', 'pens')->whereNull('deleted_at')->exists(),
            'breeds' => Lookup::where('type', 'breeds')->whereNull('deleted_at')->exists(),
            'users' => \App\Models\User::query()->whereNull('deleted_at')->exists(),
        ];

        $completed = ! in_array(false, $steps, true);

        return response()->json([
            'data' => [
                'completed' => $completed,
                'steps' => array_map(fn ($name, $done) => ['name' => $name, 'done' => $done], array_keys($steps), array_values($steps)),
            ],
        ]);
    }

    public function complete()
    {
        $this->authorize('setup.complete');

        // Validation is handled by a form request in later phases; this keeps
        // the wizard resumable — each step saves independently.
        return response()->json(['data' => ['completed' => true]]);
    }
}
