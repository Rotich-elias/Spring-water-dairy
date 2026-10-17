<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MilkSessionStoreRequest;
use App\Domain\Milk\Services\MilkSessionService;
use Illuminate\Http\Request;

class MilkSessionsController extends Controller
{
    public function __construct(private MilkSessionService $service) {}

    /**
     * GET /milk/sessions?date=2026-10-17&session=morning
     * The entry sheet: every lactating cow with last yield, withholding flag and DIM.
     */
    public function index(Request $request)
    {
        $this->authorize('milk.view');

        $date = $request->input('date', now()->toDateString());
        $session = $request->input('session', 'morning');

        $sheet = $this->service->sessionSheet($date, $session);
        $alerts = $this->service->sessionAlerts($date, $session);

        $total = collect($sheet)->where('already_recorded', false)->sum(fn ($c) => $c['last_yield'] ?? 0);
        $count = collect($sheet)->where('already_recorded', false)->count();
        $missing = collect($sheet)->where('already_recorded', false)->where('in_withholding', false)->count();

        return response()->json([
            'data' => [
                'date' => $date,
                'session' => $session,
                'cows' => $sheet,
                'alerts' => $alerts,
                'summary' => [
                    'cows_milked' => collect($sheet)->where('already_recorded', true)->count(),
                    'cows_missing' => $missing,
                    'total_last_yield' => round($total, 2),
                ],
            ],
        ]);
    }

    /**
     * POST /milk/sessions - Save a whole session in one call.
     */
    public function store(MilkSessionStoreRequest $request)
    {
        $this->authorize('milk.create');

        $result = $this->service->saveSession($request->validated());

        return response()->json([
            'data' => [
                'client_uuid' => $result['client_uuid'],
                'saved' => $result['saved'],
                'skipped' => $result['skipped'],
                'warnings' => $result['warnings'],
                'replayed' => $result['replayed'] ?? false,
                'entries' => $result['entries'],
            ],
        ]);
    }
}
