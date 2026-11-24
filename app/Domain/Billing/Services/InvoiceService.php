<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\PlanChangeRequest;
use App\Models\Setting;
use App\Models\SiteSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function render(PlanChangeRequest $request): Response
    {
        if ($request->invoice_number === null || $request->invoice_number === '') {
            throw ValidationException::withMessages([
                'request' => 'An invoice has not been issued for this request.',
            ]);
        }

        $request->loadMissing(['tenant', 'plan', 'currentPlan', 'requester']);
        $platform = $this->platformBranding();
        $farm = $this->farmBranding($request->tenant_id, $request->tenant_name);

        $pdf = Pdf::loadView('billing.invoice-pdf', [
            'platformName' => (string) ($platform['platform_name'] ?? 'TetoDairy'),
            'parentCompany' => (string) ($platform['parent_company'] ?? 'Teto Technologies'),
            'poweredByLabel' => (string) ($platform['powered_by_label'] ?? 'Powered by Teto Technologies'),
            'businessName' => (string) ($platform['business_name'] ?? $platform['platform_name'] ?? 'TetoDairy'),
            'businessAddress' => (string) ($platform['business_address'] ?? ''),
            'businessEmail' => (string) ($platform['business_email'] ?? ''),
            'businessPhone' => (string) ($platform['business_phone'] ?? ''),
            'invoiceNumber' => $request->invoice_number,
            'issuedAt' => ($request->invoiced_at ?? now())->format('d M Y'),
            'status' => Str::headline($request->status),
            'farmName' => $farm['name'],
            'farmAddress' => $farm['address'],
            'farmPhone' => $farm['phone'],
            'farmEmail' => $farm['email'],
            'requestedBy' => $request->requester_name ?? 'Farm owner',
            'planName' => $request->plan_name,
            'currentPlanName' => $request->current_plan_name ?? 'Starter',
            'note' => $request->note,
            'amount' => round(((int) $request->amount_minor) / 100, 2),
            'currency' => $request->currency,
            'paymentReference' => $request->payment_reference,
            'paymentDate' => $request->payment_date?->format('d M Y'),
            'instructions' => (string) ($platform['invoice_payment_instructions'] ?? ''),
        ])->setPaper('a4');

        return $pdf->download(Str::lower(Str::slug($request->invoice_number)).'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function platformBranding(): array
    {
        $defaults = collect(config('site.defaults', []))
            ->map(fn (array $definition): mixed => $definition['value'])
            ->all();

        $overrides = SiteSetting::query()
            ->whereIn('key', array_keys($defaults))
            ->pluck('value', 'key')
            ->all();

        return array_merge($defaults, $overrides);
    }

    /**
     * @return array{name: string, address: string, phone: string, email: string}
     */
    private function farmBranding(int $tenantId, string $fallbackName): array
    {
        $settings = Setting::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereIn('key', [
                'farm_name',
                'farm_address',
                'farm_contact_phone',
                'farm_contact_email',
            ])
            ->pluck('value', 'key')
            ->all();

        return [
            'name' => (string) ($settings['farm_name'] ?? $fallbackName),
            'address' => (string) ($settings['farm_address'] ?? ''),
            'phone' => (string) ($settings['farm_contact_phone'] ?? ''),
            'email' => (string) ($settings['farm_contact_email'] ?? ''),
        ];
    }
}
