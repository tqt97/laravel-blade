<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Booking\FinalizeSuccessfulPayment;
use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cinema\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, FinalizeSuccessfulPayment $finalizeSuccessfulPayment): Response
    {
        $payload = $request->getContent();
        abort_unless($this->validSignature($payload, (string) $request->header('Stripe-Signature')), 400, 'Invalid webhook signature.');
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $eventId = (string) ($data['id'] ?? '');
        abort_unless($eventId !== '', 400, 'Missing webhook event id.');

        DB::transaction(function () use ($data, $eventId, $finalizeSuccessfulPayment): void {
            $event = PaymentWebhookEvent::query()->firstOrCreate(['provider' => 'stripe', 'event_id' => $eventId], ['payload' => $data]);
            if ($event->processed_at !== null) {
                return;
            }
            $object = $data['data']['object'] ?? [];
            $payment = Payment::query()->where('provider_payment_id', $object['id'] ?? null)->lockForUpdate()->first();
            if ($payment === null) {
                throw new \RuntimeException('Stripe payment is not available for webhook processing yet.');
            }
            if ($payment->getAttribute('payable_type') !== Booking::class) {
                return;
            }
            if (($data['type'] ?? '') === 'payment_intent.succeeded') {
                if ($payment->getAttribute('status') === PaymentStatus::Refunded) {
                    $event->forceFill(['processed_at' => now()->utc()])->save();

                    return;
                }
                $payment->setAttribute('status', PaymentStatus::Succeeded);
                $payment->setAttribute('paid_at', now()->utc());
                $payment->save();
                $finalizeSuccessfulPayment->execute($payment);
            } elseif (($data['type'] ?? '') === 'payment_intent.payment_failed') {
                $payment->setAttribute('status', PaymentStatus::Failed);
                $payment->setAttribute('failure_message', $object['last_payment_error']['message'] ?? 'Payment failed.');
                $payment->save();
            }
            $event->forceFill(['processed_at' => now()->utc()])->save();
        }, 3);

        return response()->noContent();
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
