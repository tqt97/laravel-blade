<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payment\IngestStripeWebhook;
use App\Enums\Payment\StripeWebhookIngestResult;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessStripeWebhook;
use App\Support\Payment\StripeWebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;

final class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeWebhookSignatureVerifier $signatureVerifier,
        IngestStripeWebhook $ingestStripeWebhook,
    ): Response|JsonResponse {
        $payload = $request->getContent();

        abort_unless(
            $signatureVerifier->isValid($payload, (string) $request->header('Stripe-Signature')),
            400,
            'Invalid webhook signature.',
        );

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid webhook JSON payload.'], 400);
        }

        $eventId = (string) ($data['id'] ?? '');
        abort_unless($eventId !== '', 400, 'Missing webhook event id.');

        $result = $ingestStripeWebhook->execute($data, $eventId);

        if ($result === StripeWebhookIngestResult::MissingProviderPaymentId) {
            return response()->json(['message' => 'Webhook payload is missing a provider payment ID.'], 400);
        }

        if ($result === StripeWebhookIngestResult::Rejected) {
            return response()->json(['message' => 'Webhook payment data does not match the local payment.'], 422);
        }

        ProcessStripeWebhook::dispatch($eventId)->afterCommit();

        if ($result === StripeWebhookIngestResult::Orphan) {
            return response()->json([
                'status' => 'accepted',
                'message' => 'Webhook queued for processing.',
            ], 202);
        }

        return response()->noContent();
    }
}
