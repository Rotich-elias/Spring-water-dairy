<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboard\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    /**
     * GET /dashboard/summary - All tile values and herd composition.
     */
    public function summary()
    {
        Gate::authorize('dashboard.view');

        return response()->json([
            'data' => $this->dashboard->summary(),
        ]);
    }

    /**
     * GET /dashboard/charts/milk-trend?days=30
     */
    public function milkTrend(Request $request)
    {
        Gate::authorize('dashboard.view');

        $days = $request->input('days', 30);

        return response()->json([
            'data' => $this->dashboard->milkTrend($days),
        ]);
    }

    /**
     * GET /dashboard/charts/breed-share
     */
    public function breedShare()
    {
        Gate::authorize('dashboard.view');

        return response()->json([
            'data' => $this->dashboard->breedShare(),
        ]);
    }

    /**
     * GET /dashboard/charts/composition
     */
    public function composition()
    {
        Gate::authorize('dashboard.view');

        return response()->json([
            'data' => $this->dashboard->compositionChart(),
        ]);
    }

    /**
     * GET /dashboard/upcoming - Next 14 days of due events.
     */
    public function upcoming()
    {
        Gate::authorize('dashboard.view');

        return response()->json([
            'data' => $this->dashboard->upcoming(),
        ]);
    }
}
