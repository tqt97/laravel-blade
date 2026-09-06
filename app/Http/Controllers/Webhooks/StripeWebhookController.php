<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Booking\BookingStatus;
use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cinema\Booking;
use App\Models\Cinema\ScreeningSeat;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        abort_unless($this->validSignature($payload, (string) $request->header('Stripe-Signature')), 400, 'Invalid webhook signature.');
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $eventId = (string) ($data['id'] ?? '');
        abort_unless($eventId !== '', 400, 'Missing webhook event id.');

        DB::transaction(function () use ($data, $eventId): void {
            $event = PaymentWebhookEvent::query()->firstOrCreate(['provider' => 'stripe', 'event_id' => $eventId], ['payload' => $data]);
            if ($event->processed_at !== null) {
                return;
            }
            $object = $data['data']['object'] ?? [];
            $payment = Payment::query()->where('provider_payment_id', $object['id'] ?? null)->lockForUpdate()->first();
            if ($payment === null) {
                return;
            }
            if ($payment->getAttribute('payable_type') !== Booking::class) {
                return;
            }
            if (($data['type'] ?? '') === 'payment_intent.succeeded') {
                $wasSucceeded = $payment->getAttribute('status') === PaymentStatus::Succeeded;
                if ($payment->getAttribute('status') === PaymentStatus::Refunded) {
                    $event->forceFill(['processed_at' => now()->utc()])->save();

                    return;
                }
                $payment->setAttribute('status', PaymentStatus::Succeeded);
                $payment->setAttribute('paid_at', now()->utc());
                $booking = Booking::query()->whereKey($payment->getAttribute('payable_id'))->lockForUpdate()->first();
                if ($booking !== null && BookingStatus::from((string) $booking->getRawOriginal('status')) === BookingStatus::PendingPayment) {
                    $booking->transitionTo(BookingStatus::Confirmed);
                    $booking->setAttribute('expires_at', null);
                    $booking->save();
                }
                $payment->save();
                foreach ($booking?->items()->get() ?? [] as $item) {
                    $seat = ScreeningSeat::query()->find($item->getAttribute('screening_seat_id'));
                    if ($seat !== null) {
                        $seat->forceFill(['status' => ScreeningSeatStatus::Sold, 'hold_token' => null, 'held_until' => null, 'sold_at' => now()->utc()])->save();
                    }
                    $item->setAttribute('qr_token_hash', hash('sha256', (string) $item->getAttribute('ticket_code')));
                    $item->save();
                }
                if (! $wasSucceeded) {
                    OutboxMessage::query()->create([
                        'aggregate_type' => Booking::class, 'aggregate_id' => $payment->getAttribute('payable_id'),
                        'event_type' => 'booking.payment_succeeded', 'payload' => ['booking_id' => $payment->getAttribute('payable_id'), 'payment_id' => $payment->id],
                    ]);
                }
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

        return hash_equals(hash_hmac('sha256', $time[1].'.'.$payload, $secret), $signature[1]);
    }
}
