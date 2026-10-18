<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('audit.view');

        $filters = $request->only(['user_id', 'action', 'from', 'to', 'page', 'per_page']);

        $logs = $this->audit->list($filters);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function actions()
    {
        $this->authorize('audit.view');

        return response()->json(['data' => $this->audit->actions()]);
    }
}
