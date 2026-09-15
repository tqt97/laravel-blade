# 07 — Stripe, 3DS và vận hành payment

## 1. Configuration

| Variable | Frontend | Backend | Mục đích |
|---|---|---|---|
| `STRIPE_KEY` | Có | Có thể đọc config | Publishable key cho Stripe.js |
| `STRIPE_SECRET` | Không | Có | Gọi PaymentIntent/Refund/GET provider |
| `STRIPE_WEBHOOK_SECRET` | Không | Có | Verify `Stripe-Signature` |
| `BOOKING_PAYMENT_PROVIDER` | Không | Có | `stripe` production; `fake` chỉ local/test |

Config: `config/services.php:31-41`, `config/booking.php:52-81`.

Hướng dẫn CLI cài đặt/key vẫn ở `docs/stripe-laravel-webhook-3ds-guide.md`; file này tập trung vào runtime behavior của project.

## 2. Local development

```bash
composer run dev
```

Script này khởi chạy Laravel dev stack và Vite/Stripe stack theo `composer.json` và `package.json`. Nếu Stripe CLI không đăng nhập hoặc webhook secret sai, phần HTTP booking vẫn chạy nhưng webhook payment sẽ fail signature.

Kiểm tra webhook local:

```bash
stripe listen --forward-to http://127.0.0.1:8000/webhooks/stripe
```

Production có HTTPS nên tạo endpoint trong Stripe Dashboard; không phụ thuộc Stripe CLI.

## 3. PaymentIntent lifecycle

### Payment Element path

1. User POST `/user/bookings/{booking}/pay`.
2. Backend tạo PaymentAttempt bất biến.
3. `StripePaymentGateway::charge()` tạo PaymentIntent chưa confirm và lưu encrypted `client_secret`.
4. User vào `payment-action`.
5. `payment-status.js` mount Payment Element.
6. Stripe.js `confirmPayment()` thực hiện card/3DS.
7. Browser gọi payment sync nếu cần.
8. Stripe webhook hoặc reconciliation finalize local booking.

Code: `StripePaymentGateway.php:20-70`, `BookingPaymentController.php:24-106`, `resources/js/modules/payment-status.js:109-139`.

### Status meaning

| Stripe | Internal | Browser behavior | Server behavior |
|---|---|---|---|
| `succeeded` | `succeeded` | Redirect success | Finalize ticket/seat |
| `requires_action` | `requires_action` | Show/continue 3DS | Wait webhook/reconcile |
| `requires_payment_method` | `requires_payment_method` | Re-enter method | Recoverable, no charge retry mù |
| `processing` | `processing` | Poll | Reconcile |
| unknown/error | `unknown` | Bounded polling/manual notice | Alert/reconcile |
| late success | `requires_refund` | Manual/refund state | Refund provider before local finalize |

## 4. Webhook contract

Endpoint: `POST /webhooks/stripe`, `routes/web.php:33`.

Mandatory behavior:

- Verify raw body signature before parse/trust.
- Persist `(provider,event_id)` unique.
- Return quickly and process asynchronously.
- Match provider ID, amount, currency, metadata.
- Do not downgrade a succeeded local payment due old event.
- Mark orphan when local payment is not available.
- Retry failed/orphan events via `ReconcilePayments`/`ReplayStripeWebhooks`.

Supported event families are mapped by `StripeWebhookEventType`; processing code `ProcessStripeWebhook.php:44-314`.

## 5. Refund contract

Refund request uses:

- provider PaymentIntent ID;
- fixed idempotency key `config('booking.payment.refund_idempotency_key_prefix').$payment->id`;
- local `RefundAttempt` before/around provider operation.

Provider status mapping:

```text
Stripe succeeded       -> RefundAttempt succeeded -> FinalizeRefund
Stripe pending/action   -> RefundAttempt pending -> ReconcileRefund
Stripe failed/canceled  -> RefundAttempt failed -> RequiresRefund/manual review
HTTP/network unknown    -> Unknown/retry/reconcile
```

Never map a generic HTTP success response directly to local `Refunded`.

## 6. Operational commands

```bash
php artisan payments:reconcile
php artisan payments:recover-stuck
php artisan payments:retry-refunds
php artisan payments:replay-webhooks
php artisan payments:alert-stuck
```

Schedule: `routes/console.php:14-20`.

Alert command reports structured `payments.anomalies` with:

- `stuck_processing`;
- `unknown`;
- `requires_refund`;
- `orphan_webhooks`.

Production logging must route this event to alerting. A log file alone is insufficient.

## 7. Stripe test matrix runbook

### Automated local matrix

```bash
DB_CONNECTION=sqlite DB_DATABASE=':memory:' php artisan test --compact \
    tests/Feature/BookingPaymentReliabilityTest.php \
    tests/Feature/MovieBookingFeatureTest.php
```

This validates provider mapping through `Http::fake`, webhook signature/event processing and failure state. It is not a live Stripe charge.

### Live test mode matrix

Run against a seeded test database and Stripe test keys. Keep Stripe CLI forwarding active.

| Scenario | Action | Verify |
|---|---|---|
| Success | Use Stripe success test card | Booking confirmed, ticket issued once |
| 3DS | Use `4000002500003155` | Payment Element/3DS completes, webhook finalizes |
| Requires method | Use failing/payment-method test card | User can provide method again; no duplicate intent |
| Delayed webhook | Complete payment, pause/stop listener, restart | Reconcile finds provider success and finalizes |
| Duplicate webhook | Replay same event | One local transition/ticket/outbox effect |
| Refund pending | Trigger refund returning pending | Admin sees in-progress; no local refund finalize |
| Refund succeeded | Complete provider refund | Ticket/seat/combo/coupon released once |
| Refund failed | Trigger failed refund | Requires manual review; payment not `refunded` |
| Stripe timeout | Block/timeout provider request in test harness | Unknown/processing and reconciliation, no blind second charge |

For every scenario record:

- booking ID;
- local payment ID and attempt key;
- Stripe PaymentIntent/Refund ID;
- webhook event ID;
- local status before/after;
- ticket/seat/combo/coupon side effects;
- logs/alerts produced.

## 8. Release checklist

- [ ] `STRIPE_SECRET` and webhook secret belong to the same Stripe mode/account.
- [ ] Production provider is not fake.
- [ ] Webhook endpoint signature verified.
- [ ] Queue worker running.
- [ ] Scheduler leader running.
- [ ] Reconciliation commands scheduled.
- [ ] Alert log shipped to monitoring.
- [ ] MySQL concurrency test passed in CI.
- [ ] Browser booking/3DS E2E passed with fixture.
- [ ] Manual review process exists for `unknown`/`requires_refund`.

