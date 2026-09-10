<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
use App\Support\Payment\PaymentStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use JsonException;

final class StripeWebhookController extends Controller
{
    public function __construct(private readonly PaymentStateMachine $stateMachine) {}

    public function __invoke(Request $request, FinalizeSuccessfulPayment $finalizeSuccessfulPayment): Response|JsonResponse
    {
        $payload = $request->getContent();

        abort_unless($this->validSignature($payload, (string) $request->header('Stripe-Signature')), 400, 'Invalid webhook signature.');

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid webhook JSON payload.'], 400);
        }
        $eventId = (string) ($data['id'] ?? '');

        abort_unless($eventId !== '', 400, 'Missing webhook event id.');

        $result = DB::transaction(function () use ($data, $eventId, $finalizeSuccessfulPayment): int {
            $event = PaymentWebhookEvent::query()->firstOrCreate([
                'provider' => 'stripe', 'event_id' => $eventId], ['payload' => $data]);
            if ($event->processed_at !== null) {
                return 0;
            }
            if ($event->failed_at !== null) {
                return 1;
            }
            $object = $data['data']['object'] ?? [];
            $providerPaymentId = is_array($object) ? ($object['id'] ?? null) : null;
            if (! is_string($providerPaymentId) || $providerPaymentId === '') {
                $event->forceFill(['failed_at' => now(), 'failure_message' => 'Stripe webhook is missing a provider payment ID.'])->save();

                return 2;
            }
            $payment = Payment::query()->where('provider', 'stripe')->where('provider_payment_id', $providerPaymentId)->lockForUpdate()->first();

            if ($payment === null) {
                throw new \RuntimeException('Stripe payment is not available for webhook processing yet.');
            }
            if ($payment->getAttribute('payable_type') !== Booking::class) {
                $event->forceFill(['processed_at' => now()])->save();

                return 0;
            }
            $eventType = (string) ($data['type'] ?? '');

            if (in_array($eventType, ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.processing', 'payment_intent.requires_action', 'payment_intent.canceled'], true)
                && ! $this->matchesPayment($payment, $object, $eventType === 'payment_intent.succeeded')) {
                $event->forceFill([
                    'failed_at' => now(),
                    'failure_message' => 'Stripe webhook amount or currency does not match the local payment.',
                ])->save();

                return 1;
            }
            if (! $this->shouldApplyTransition($payment, $eventType, $data)) {
                $event->forceFill(['processed_at' => now()])->save();

                return 0;
            }
            $metadata = $payment->getAttribute('metadata');
            $metadata = is_array($metadata) ? $metadata : [];
            if (isset($data['created']) && is_numeric($data['created'])) {
                $metadata['stripe_last_event_created'] = (int) $data['created'];
                $payment->setAttribute('metadata', $metadata);
            }
            if ($eventType === 'payment_intent.succeeded') {
                if ($payment->getAttribute('status') === PaymentStatus::Refunded) {
                    $event->forceFill(['processed_at' => now()])->save();

                    return 0;
                }
                $payment->setAttribute('status', PaymentStatus::Succeeded);
                $payment->setAttribute('paid_at', now());
                $payment->syncLatestAttempt(PaymentAttemptStatus::Succeeded, (string) ($object['id'] ?? null));
                $payment->save();
                $finalizeSuccessfulPayment->execute($payment);
            } elseif ($eventType === 'payment_intent.payment_failed') {
                $payment->setAttribute('status', PaymentStatus::Failed);
                $payment->setAttribute('failure_message', $object['last_payment_error']['message'] ?? 'Payment failed.');
                $payment->syncLatestAttempt(PaymentAttemptStatus::Failed, (string) ($object['id'] ?? null), (string) ($payment->failure_message ?? null));
                $payment->save();
            } elseif ($eventType === 'payment_intent.processing') {
                $payment->setAttribute('status', PaymentStatus::Pending);
                $payment->setAttribute('processing_started_at', $payment->processing_started_at ?? now());
                $payment->syncLatestAttempt(PaymentAttemptStatus::Processing, (string) ($object['id'] ?? null));
                $payment->save();
            } elseif ($eventType === 'payment_intent.requires_action') {
                $payment->setAttribute('status', PaymentStatus::RequiresAction);
                $payment->setAttribute('metadata', array_merge($metadata, ['client_secret' => $object['client_secret'] ?? null]));
                $payment->setAttribute('processing_started_at', null);
                $payment->syncLatestAttempt(PaymentAttemptStatus::RequiresAction, (string) ($object['id'] ?? null));
                $payment->save();
            } elseif ($eventType === 'payment_intent.canceled') {
                $payment->setAttribute('status', PaymentStatus::Failed);
                $payment->setAttribute('failure_message', 'Payment intent was canceled.');
                $payment->setAttribute('processing_started_at', null);
                $payment->syncLatestAttempt(PaymentAttemptStatus::Failed, (string) ($object['id'] ?? null), (string) ($payment->failure_message ?? null));
                $payment->save();
            }
            $event->forceFill(['processed_at' => now()])->save();

            return 0;
        }, 3);

        if ($result === 2) {
            return response()->json(['message' => 'Webhook payload is missing a provider payment ID.'], 400);
        }

        if ($result === 1) {
            return response()->json(['message' => 'Webhook payment data does not match the local payment.'], 422);
        }

        return response()->noContent();
    }

    /** @param array<string, mixed> $data */
    private function shouldApplyTransition(Payment $payment, string $eventType, array $data): bool
    {
        $current = $payment->getAttribute('status');
        $metadata = $payment->getAttribute('metadata');
        $lastCreated = is_array($metadata) ? (int) ($metadata['stripe_last_event_created'] ?? 0) : 0;
        $created = is_numeric($data['created'] ?? null) ? (int) $data['created'] : 0;

        if ($created > 0 && $lastCreated > $created) {
            return false;
        }

        if (in_array($current, [PaymentStatus::Refunded, PaymentStatus::RequiresRefund], true)) {
            return false;
        }

        $target = match ($eventType) {
            'payment_intent.succeeded' => PaymentStatus::Succeeded,
            'payment_intent.payment_failed', 'payment_intent.canceled' => PaymentStatus::Failed,
            'payment_intent.processing' => PaymentStatus::Pending,
            'payment_intent.requires_action' => PaymentStatus::RequiresAction,
            default => PaymentStatus::tryFrom((string) $payment->getRawOriginal('status')),
        };

        if ($target === null || ! $this->stateMachine->canTransition(PaymentStatus::from((string) $payment->getRawOriginal('status')), $target)) {
            return false;
        }

        if ($current === PaymentStatus::Succeeded && $eventType !== 'payment_intent.succeeded') {
            return false;
        }

        if ($current === PaymentStatus::Failed && in_array($eventType, ['payment_intent.processing', 'payment_intent.requires_action'], true)) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $object */
    private function matchesPayment(Payment $payment, array $object, bool $successful): bool
    {
        $amount = $successful ? ($object['amount_received'] ?? null) : ($object['amount'] ?? null);
        $currency = strtoupper((string) ($object['currency'] ?? ''));
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

        return is_numeric($amount)
            && (int) $amount === (int) $payment->amount_minor_units
            && $currency === strtoupper((string) $payment->currency)
            && array_key_exists('payable_id', $metadata)
            && array_key_exists('payable_type', $metadata)
            && ((string) $metadata['payable_id'] === (string) $payment->payable_id)
            && ((string) $metadata['payable_type'] === Booking::class);
    }

    private function validSignature(string $payload, string $header): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '' || ! preg_match('/(?:^|,)t=(\d+)(?:,|$)/', $header, $time) || ! preg_match('/(?:^|,)v1=([^,]+)(?:,|$)/', $header, $signature)) {
            return false;
        }
        if (abs(time() - (int) $time[1]) > 300) {
            return false;
        }

        foreach (explode(',', $header) as $part) {
            if (str_starts_with(trim($part), 'v1=')) {
                $candidate = substr(trim($part), 3);
                if (hash_equals(hash_hmac('sha256', $time[1].'.'.$payload, $secret), $candidate)) {
                    return true;
                }
            }
        }

        return false;
    }
}
