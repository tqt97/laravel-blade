<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, FinalizeSuccessfulPayment $finalizeSuccessfulPayment): Response|JsonResponse
    {
        $payload = $request->getContent();
        abort_unless($this->validSignature($payload, (string) $request->header('Stripe-Signature')), 400, 'Invalid webhook signature.');
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $eventId = (string) ($data['id'] ?? '');
        abort_unless($eventId !== '', 400, 'Missing webhook event id.');

        $mismatch = DB::transaction(function () use ($data, $eventId, $finalizeSuccessfulPayment): bool {
            $event = PaymentWebhookEvent::query()->firstOrCreate(['provider' => 'stripe', 'event_id' => $eventId], ['payload' => $data]);
            if ($event->processed_at !== null) {
                return false;
            }
            if ($event->failed_at !== null) {
                return true;
            }
            $object = $data['data']['object'] ?? [];
            $providerPaymentId = is_array($object) ? ($object['id'] ?? null) : null;
            if (! is_string($providerPaymentId) || $providerPaymentId === '') {
                $event->forceFill(['failed_at' => now()->utc(), 'failure_message' => 'Stripe webhook is missing a provider payment ID.'])->save();

                return true;
            }
            $payment = Payment::query()->where('provider', 'stripe')->where('provider_payment_id', $providerPaymentId)->lockForUpdate()->first();
            if ($payment === null) {
                throw new \RuntimeException('Stripe payment is not available for webhook processing yet.');
            }
            if ($payment->getAttribute('payable_type') !== Booking::class) {
                $event->forceFill(['processed_at' => now()->utc()])->save();

                return false;
            }
            $eventType = (string) ($data['type'] ?? '');
            if (in_array($eventType, ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.processing', 'payment_intent.requires_action', 'payment_intent.canceled'], true)
                && ! $this->matchesPayment($payment, $object, $eventType === 'payment_intent.succeeded')) {
                $event->forceFill([
                    'failed_at' => now()->utc(),
                    'failure_message' => 'Stripe webhook amount or currency does not match the local payment.',
                ])->save();

                return true;
            }
            if (! $this->shouldApplyTransition($payment, $eventType, $data)) {
                $event->forceFill(['processed_at' => now()->utc()])->save();

                return false;
            }
            $metadata = $payment->getAttribute('metadata');
            $metadata = is_array($metadata) ? $metadata : [];
            if (isset($data['created']) && is_numeric($data['created'])) {
                $metadata['stripe_last_event_created'] = (int) $data['created'];
                $payment->setAttribute('metadata', $metadata);
            }
            if ($eventType === 'payment_intent.succeeded') {
                if ($payment->getAttribute('status') === PaymentStatus::Refunded) {
                    $event->forceFill(['processed_at' => now()->utc()])->save();

                    return false;
                }
                $payment->setAttribute('status', PaymentStatus::Succeeded);
                $payment->setAttribute('paid_at', now()->utc());
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
                $payment->setAttribute('processing_started_at', $payment->processing_started_at ?? now()->utc());
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
            $event->forceFill(['processed_at' => now()->utc()])->save();

            return false;
        }, 3);

        if ($mismatch) {
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
            && ((string) ($metadata['payable_id'] ?? $payment->payable_id) === (string) $payment->payable_id)
            && ((string) ($metadata['payable_type'] ?? Booking::class) === Booking::class);
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
