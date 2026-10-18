<?php

namespace App\Domain\Reports\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

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
     * List available report types for PDF generation.
     *
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
}
