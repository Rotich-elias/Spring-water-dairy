<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\Services\PaymentWebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WebhookController extends Controller
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentWebhookService $webhooks,
    ) {}

    public function paystack(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        $valid = $this->gateway->verifyWebhook(
            $raw,
            $request->header('Paystack-Signature'),
            $request->header('X-Paystack-Signature'),
        );

        if (! $valid) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            throw new BadRequestHttpException('Invalid webhook payload.');
        }

        $this->webhooks->handle($payload);

        return response()->json(['received' => true]);
    }
}
