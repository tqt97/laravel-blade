<?php

namespace App\Support\Payment;

final class StripeWebhookSignatureVerifier
{
    public function isValid(string $payload, string $header): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '' || ! preg_match('/(?:^|,)t=(\d+)(?:,|$)/', $header, $time) || ! preg_match('/(?:^|,)v1=([^,]+)(?:,|$)/', $header, $signature)) {
            return false;
        }

        if (abs(time() - (int) $time[1]) > (int) config('booking.payment.webhook_signature_tolerance_seconds', 300)) {
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
