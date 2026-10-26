<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\Services\PdfReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PdfReportController extends Controller
{
    public function __construct(private PdfReportService $pdfReports) {}

    /**
     * Generate a downloadable PDF report for the farm.
     */
    public function generate(string $type, Request $request)
    {
        $this->authorize('reports.view');

        $filters = $request->only(['from', 'to']);

        return $this->pdfReports->render($type, $filters);
    }
}
