<?php

namespace App\Domain\Billing\Contracts;

interface PaymentGateway
{
    /**
     * Create a hosted checkout and return the redirect URL plus provider
     * identifiers. Amounts and pricing are always decided server-side.
     *
     * @param  array<string, mixed>  $payload
     * @return array{authorization_url: string, reference: string, access_code?: string|null}
     */
    public function initializeCheckout(array $payload): array;

    /**
     * Stop recurring charges while allowing the current period to finish.
     */
    public function disableSubscription(string $code, ?string $token): bool;

    /**
     * Verify a signed webhook from the raw request body.
     */
    public function verifyWebhook(string $rawBody, ?string $signatureHeader, ?string $legacySignature): bool;

    public function name(): string;
}
