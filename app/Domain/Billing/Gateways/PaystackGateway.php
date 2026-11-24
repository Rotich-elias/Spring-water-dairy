<?php

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGateway implements PaymentGateway
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function name(): string
    {
        return 'paystack';
    }

    public function initializeCheckout(array $payload): array
    {
        $response = $this->request()->post('/transaction/initialize', $payload);
        $response->throw();

        $data = $response->json('data');

        if (! is_array($data) || ! filled($data['authorization_url'] ?? null)) {
            throw new RuntimeException('The payment provider did not return a checkout URL.');
        }

        return [
            'authorization_url' => (string) $data['authorization_url'],
            'reference' => (string) ($data['reference'] ?? $payload['reference']),
            'access_code' => isset($data['access_code']) ? (string) $data['access_code'] : null,
        ];
    }

    public function disableSubscription(string $code, ?string $token): bool
    {
        // Paystack requires the email token sent with subscription.create to
        // disable a subscription. Without it the local cancellation still
        // takes effect and the provider webhook can reconcile the rest.
        if ($code === '' || $token === null || $token === '') {
            return false;
        }

        $response = $this->request()->post('/subscription/disable', [
            'code' => $code,
            'token' => $token,
        ]);

        return $response->successful() && $response->json('status') === true;
    }

    public function verifyWebhook(string $rawBody, ?string $signatureHeader, ?string $legacySignature): bool
    {
        if ($this->verifyLegacySignature($rawBody, $legacySignature)) {
            return true;
        }

        return $this->verifySigningSecretSignature($rawBody, $signatureHeader);
    }

    private function request()
    {
        $secret = (string) config('services.paystack.secret_key');

        if ($secret === '') {
            throw new RuntimeException('Paystack is not configured.');
        }

        return Http::baseUrl((string) config('services.paystack.base_uri', 'https://api.paystack.co'))
            ->withToken($secret)
            ->acceptJson()
            ->asJson();
    }

    private function verifyLegacySignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key');
        $signature = $signature === null ? '' : strtolower(trim($signature));

        if ($secret === '' || ! preg_match('/^[0-9a-f]{128}$/D', $signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }

    private function verifySigningSecretSignature(string $rawBody, ?string $header): bool
    {
        if ($header === null || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(array_map('trim', explode('=', trim($part), 2)), 2, '');

            if ($key === 't') {
                if ($timestamp !== null || ! preg_match('/^[0-9]+$/D', $value)) {
                    return false;
                }
                $timestamp = (int) $value;

                continue;
            }

            if ($key === 'v1') {
                [$keyId, $signature] = array_pad(explode(':', $value, 2), 2, '');
                if ($keyId !== '' && $signature !== '') {
                    $signatures[] = strtolower($signature);
                }
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $secrets = collect(explode(',', (string) config('services.paystack.webhook_secret')))
            ->map(fn (string $secret): string => trim($secret))
            ->filter()
            ->all();

        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha512', $timestamp.'.'.$rawBody, $secret);

            foreach ($signatures as $signature) {
                if (preg_match('/^[0-9a-f]{128}$/D', $signature) && hash_equals($expected, $signature)) {
                    return true;
                }
            }
        }

        return false;
    }
}
