<?php

namespace App\Domain\Reports\Services;

use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PdfReportService
{
    public function __construct(private ReportsService $reports) {}

    /**
     * Get farm branding settings for PDF reports.
     *
     * @return array<string, string>
     */
    public function branding(): array
    {
        $keys = [
            'farm_name',
            'farm_address',
            'farm_contact_phone',
            'farm_contact_email',
            'farm_logo_path',
        ];

        return Setting::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Prepare report data for PDF generation.
     *
     * @param  string  $type  Report type (animal-register, breeding-pregnancy, calving, milk-production, health-treatment, mortality)
     * @param  array  $filters  Report filters (from, to, etc.)
     * @return array{
     *   branding: array<string, string>,
     *   report_type: string,
     *   generated_at: string,
     *   data: mixed,
     * }
     */
    public function prepare(string $type, array $filters = []): array
    {
        $data = $this->reports->generate($type, $filters);

        return [
            'branding' => $this->branding(),
            'report_type' => $type,
            'generated_at' => now()->toISOString(),
            'data' => $data,
        ];
    }

    /**
     * Render a report as a downloadable PDF.
     *
     * @param  array<string, mixed>  $filters
     */
    public function render(string $type, array $filters = []): Response
    {
        $report = $this->prepare($type, $filters);

        $pdf = Pdf::loadView('reports.pdf', [
            'branding' => $report['branding'],
            'title' => $this->titleFor($type),
            'generatedAt' => now()->format('d M Y H:i'),
            'rows' => $this->rowsFor($report['data']),
            'columns' => $this->columnsFor($report['data']),
            'isSummary' => $this->isSummary($report['data']),
        ])->setPaper('a4');

        return $pdf->download($this->filename($type));
    }

    /**
     * @return Collection<int, array{id: string, name: string, description: string}>
     */
    public function availableTypes(): Collection
    {
        return $this->reports->list()->map(fn ($report) => [
            'id' => $report['id'],
            'name' => $report['name'],
            'description' => $report['description'],
        ]);
    }

    private function titleFor(string $type): string
    {
        return $this->reports->list()->firstWhere('id', $type)['name'] ?? Str::headline($type);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function rowsFor(mixed $data): array
    {
        return is_array($data) ? $data : [];
    }

    /**
     * @return array<int, string>
     */
    private function columnsFor(mixed $data): array
    {
        if (! is_array($data) || $data === [] || $this->isSummary($data)) {
            return [];
        }

        return array_keys(reset($data));
    }

    private function isSummary(mixed $data): bool
    {
        if (! is_array($data) || $data === []) {
            return false;
        }

        return ! is_array(reset($data));
    }

    private function filename(string $type): string
    {
        return Str::slug($type).'-'.now()->format('Y-m-d').'.pdf';
    }
}
