<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\Services\PdfReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PdfReportController extends Controller
{
    public function __construct(private PdfReportService $pdfReports) {}

    /**
     * Generate a PDF report (returns report data + farm branding;
     * PDF rendering requires a PDF library such as dompdf).
     */
    public function generate(string $type, Request $request)
    {
        $this->authorize('reports.view');

        $filters = $request->only(['from', 'to']);

        $report = $this->pdfReports->prepare($type, $filters);

        return response()->json([
            'data' => $report,
            'meta' => [
                'pdf_rendering' => 'pending_library',
                'message' => 'Report data prepared. Install a PDF library (e.g. dompdf/barryvdh-laravel-snappy) to render PDF.',
            ],
        ]);
    }
}
