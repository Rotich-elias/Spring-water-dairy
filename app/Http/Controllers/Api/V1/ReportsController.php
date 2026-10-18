<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\Services\ReportsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(private ReportsService $reports) {}

    public function index()
    {
        $this->authorize('reports.view');

        return response()->json(['data' => $this->reports->list()]);
    }

    public function show(string $type, Request $request)
    {
        $this->authorize('reports.view');

        $filters = $request->only(['from', 'to']);

        return response()->json(['data' => $this->reports->generate($type, $filters)]);
    }
}
