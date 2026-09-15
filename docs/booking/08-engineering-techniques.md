# 08 — Kỹ thuật triển khai trong booking

Tài liệu này giải thích các kỹ thuật đang được dùng trong booking/payment: vì sao cần, invariant được bảo vệ, cách triển khai và code reference. Nội dung bổ trợ cho [business rules](01-business-rules.md), [flows](02-booking-flows.md) và [test cases](03-test-cases-edge-cases.md).

## 1. Bản đồ kỹ thuật

| Kỹ thuật | Vấn đề giải quyết | Code owner |
|---|---|---|
| Database transaction | Nhiều ghi thay đổi phải all-or-nothing | Booking/commerce/payment actions |
| `lockForUpdate()` | Race condition trên ghế, stock, coupon, payment | Hold, combo, coupon, payment |
| Deterministic lock order | Giảm deadlock giữa các request | Sort/`orderBy` trước lock |
| Idempotency key | Double submit, retry, timeout, duplicate provider call | Booking/payment/refund/inventory |
| Hash payload | Phát hiện reuse key với payload khác | Hold idempotency hash |
| Enum/state machine | Ngăn transition sai và magic text | Booking/payment/refund/ticket |
| Compensation | Hoàn resource khi expiry/cancel/refund | Seat/combo/coupon release |
| Transactional outbox | Không mất event sau DB commit | Outbox/publisher |
| Retry/backoff | Queue/provider transient failure | Webhook/reconciliation/refund |
| Reconciliation | Xử lý provider response không chắc chắn | Payment/refund jobs |
| Webhook signature/dedup | Chống giả mạo và duplicate event | Stripe webhook |
| Minor-unit money/snapshot | Không sai số, giữ giá lịch sử | Booking/Stripe/Money |
| DTO/Form Request/query | Boundary rõ, controller mỏng, read reuse | HTTP/Query/Action |
| Policy/scoped binding | Chống IDOR và sai resource | User/public routes |
| Encryption/hash/signed URL | Bảo vệ secret và ticket token | Payment/ticket |
| Rate limit/no-store | Chống abuse và stale inventory | Mutation/availability |
| Server clock | Expiry nhất quán giữa browser/server | BookingClock/countdown |
| JS guard/abort/polling | Không duplicate listener/request stale | Booking frontend |

## 2. Transaction và row lock

### Vì sao cần

Một mutation có thể đồng thời thay đổi booking, seat, booking item, combo line, stock, coupon reservation và outbox. Không có transaction, một lỗi giữa chừng tạo ra booking một phần hoặc stock lệch. Không có row lock, hai request đều có thể đọc cùng một seat/stock còn trống.

### Cách triển khai

```php
return DB::transaction(function () use ($booking, $quantities): Booking {
    $booking = Booking::query()
        ->whereKey($booking->getKey())
        ->lockForUpdate()
        ->firstOrFail();

    $this->syncLines($booking, $quantities);

    return $booking->refresh();
}, attempts: 3);
```

Code thực tế: `app/Actions/Commerce/Concessions/SyncBookingConcessions.php:27-30`, `HoldSeats.php:151`, `ApplyCoupon.php:22`, `FinalizeRefund.php:25`.

Lock seat được thực hiện trong transaction:

```php
$seats = ScreeningSeat::query()
    ->where('screening_id', $screening->getKey())
    ->whereIn('seat_id', $seatIds)
    ->orderBy('seat_id')
    ->lockForUpdate()
    ->get();
```

Code: `HoldSeats.php:58-80`.

### Quy tắc

- Lock phải nằm trong transaction.
- Không gọi Stripe/HTTP/email trong khi đang giữ lock.
- SQLite không chứng minh được row-lock behavior của MySQL.
- Multi-process test phải chạy trên MySQL: `tests/Feature/MySqlBookingConcurrencyTest.php:24-64`.

## 3. Deterministic lock order và deadlock avoidance

### Vì sao cần

Request A lock seat 1 rồi 2 trong khi request B lock 2 rồi 1 có thể chờ lẫn nhau. Cùng một thứ tự lock giúp giảm deadlock và transaction retry có thể xử lý deadlock transient.

```php
$seatIds = array_values(array_unique(array_map('intval', $seatIds)));
sort($seatIds);

ksort($quantitiesByConcession);
$concessionIds = collect($quantitiesByConcession)
    ->keys()
    ->map(fn (string|int $id): int => (int) $id)
    ->unique()
    ->sort()
    ->values();
```

Code: `HoldSeats.php:26-27`, `SyncBookingConcessions.php:25,58-65`.

Khi action lock nhiều loại resource, thứ tự phải ổn định và được ghi rõ. Không thêm lock mới mà không xem lại lock order của action liên quan.

## 4. Idempotency key và payload hash

### Vì sao cần

HTTP có thể double click/retry; worker có at-least-once delivery; provider có thể nhận request nhưng client bị timeout. Idempotency ngăn duplicate booking, PaymentIntent, refund, inventory movement và ticket effect.

### Booking

```php
$hash = hash('sha256', $screening->id.'|'.implode(',', $seatIds));

$existing = Booking::query()
    ->where('user_id', $user->id)
    ->where('idempotency_key', $idempotencyKey)
    ->lockForUpdate()
    ->first();

if ($existing !== null && $existing->idempotency_hash !== $hash) {
    throw new SeatHoldConflict(__('booking.messages.idempotency_key_reused'));
}
```

Code: `HoldSeats.php:42-53,106-107`, request contract `HoldSeatsRequest.php:28`.

Hash bảo đảm cùng key không bị dùng lại cho seat payload khác.

### Payment và refund

```php
Http::withHeaders(['Idempotency-Key' => $attempt->attempt_key])
    ->post('https://api.stripe.com/v1/payment_intents', $parameters);

$refundKey = config('booking.payment.refund_idempotency_key_prefix').$payment->id;
```

Code: `StripePaymentGateway.php:39-52,65`, attempt claim `PayBooking.php:92-121`, refund claim `RefundBooking.php:83`.

Không tạo key mới khi attempt cũ chưa reconcile. Một key chỉ đại diện cho một logical operation.

### Inventory

```php
$key = 'payment-refund-'.$payment->id.'-'.$line->concession_id;

if (InventoryMovement::query()->where('idempotency_key', $key)->exists()) {
    return;
}
```

Code: `FinalizeRefund.php:79-97`. Database unique index và transaction là lớp bảo vệ bổ sung.

## 5. Enum và state machine

### Vì sao cần

Status là business state, không phải text tự do. State machine ngăn `refunded -> processing`, issue ticket khi payment chưa success hoặc downgrade do webhook cũ.

```php
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case RequiresAction = 'requires_action';
    case Succeeded = 'succeeded';
    case RequiresRefund = 'requires_refund';
    case Refunding = 'refunding';
    case Refunded = 'refunded';
}
```

Project dùng `PaymentStatus`, `BookingStatus`, `RefundAttemptStatus`, `TicketStatus` và `TransitionPayment`. Tham chiếu: `app/Enums/Payment/PaymentStatus.php:5-82`, `app/Actions/Payment/TransitionPayment.php`, `TransitionBooking.php`.

Mọi transition phải có test valid/invalid. Bên trong dùng enum; chỉ dùng raw value tại DB/provider boundary.

## 6. Compensation và workflow nhiều hệ thống

### Vì sao cần

Database transaction không thể rollback Stripe. Payment có thể thành công sau khi local hold đã expired. Hệ thống cần hành động bù:

```text
payment success sau expiry
→ không issue ticket
→ payment = requires_refund
→ refund Stripe
→ provider refund succeeded
→ release seat/combo/coupon và cancel booking
```

Code: `FinalizeSuccessfulPayment.php:173-225`, `RefundBooking.php`, `FinalizeRefund.php`.

Không gọi compensation mù; phải giữ provider ID, attempt key và reconcile trước khi retry.

## 7. Transactional outbox

### Vì sao cần

Nếu commit booking xong nhưng process chết trước khi gửi notification, event bị mất. Nếu dispatch trước commit, worker có thể đọc dữ liệu chưa commit. Outbox ghi event cùng transaction rồi publish sau commit.

```php
$message = OutboxMessage::query()->create([
    'aggregate_type' => Booking::class,
    'aggregate_id' => $booking->id,
    'event_type' => OutboxEventType::BookingConfirmed,
    'payload' => $payload,
]);

PublishOutboxMessage::dispatch($message->id)->afterCommit();
```

Code: `app/Models/Infrastructure/OutboxMessage.php:25`, `PublishOutboxMessage.php`, finalize actions.

Outbox vẫn at-least-once: delivery phải có claim/lease, retry và logical event idempotency.

## 8. Queue retry, backoff và at-least-once

### Vì sao cần

Worker/provider có thể timeout hoặc tạm unavailable. Job có thể chạy lại, vì vậy handler phải idempotent và phân biệt transient với permanent failure.

```php
public function backoff(): array
{
    return config('booking.payment.webhook_backoff_seconds', [5, 10, 20, 30, 60]);
}
```

Code: `ProcessStripeWebhook.php:31-37`, `ReconcilePayment.php:29-36`.

| Failure | Xử lý |
|---|---|
| Network/5xx | Retry/backoff |
| Provider pending | Reconcile sau |
| Amount/currency mismatch | Manual review, không retry vô hạn |
| Duplicate/done | No-op |

## 9. Reconciliation

### Vì sao cần

Webhook có thể chậm/mất và HTTP response có thể timeout sau khi Stripe đã nhận request. Reconciliation hỏi provider bằng PaymentIntent ID, Refund ID hoặc attempt key.

```php
$status = $retriever->retrieve($payment->provider_payment_id);

if ($status->status === StripePaymentIntentStatus::Succeeded->value) {
    $finalize->execute($payment);
}
```

Code: `ReconcilePayment.php:48-177`, `ReconcileRefund.php:23-90`, commands `ReconcilePayments.php` và `RetryUnknownRefunds.php`.

Reconcile phải có deadline/backoff; unknown cuối cùng chuyển manual review/alert, không poll vô hạn.

## 10. Webhook signature và event deduplication

### Vì sao cần

Webhook là public endpoint. Signature xác nhận request đến từ Stripe; unique `(provider,event_id)` ngăn duplicate event tạo duplicate effect.

```php
$payload = $request->getContent();
$signature = (string) $request->header('Stripe-Signature');

abort_unless($verifier->isValid($payload, $signature), 400);
```

Code: `StripeWebhookController.php:18-31`, `IngestStripeWebhook.php`; unique schema `database/migrations/2026_09_10_130007_create_payment_domain_schema.php:121-123`.

Phải verify raw body trước khi tin payload. Persist event trước khi dispatch job.

## 11. Money minor units và pricing snapshot

### Vì sao cần

Floating point gây sai số tiền. Catalog price có thể đổi sau khi user hold, nên booking phải snapshot giá tại thời điểm giao dịch.

```php
$total = $seatPriceMinorUnits + $comboTotalMinorUnits - $discountMinorUnits;
$money = Money::fromMinorUnits($total, $currency);
```

Code: `app/ValueObjects/Money.php`, booking item/concession casts và `StripePaymentGateway.php:27-30`.

Không đọc lại catalog price để tính booking cũ; dùng price/currency snapshot trong booking.

## 12. Form Request, DTO và query object

### Vì sao cần

HTTP input là untrusted array. Form Request validate; DTO normalize; Action nhận contract ổn định. Query object gom read model để controller không tự dựng payload/query.

```php
$selection = BookingSelectionData::fromArray($request->validated());

$booking = $editBookingSelection->execute(
    $user,
    $screening,
    $selection->seatIds,
    $selection->idempotencyKey,
    $selection->quantities,
);
```

Code: `HoldSeatsRequest.php`, `BookingSelectionData.php:13-23`, `ScreeningPageQuery.php`, `ScreeningAvailabilityQuery.php`.

`AvailableConcessionsQuery` và model scopes là read boundary; không mutate trong query.

## 13. Authorization, scoped binding và chống IDOR

### Vì sao cần

Booking ID trong URL không phải quyền sở hữu. User A không được xem/pay/cancel booking của user B.

```php
$this->authorize('changeCombos', $booking);

Route::scopeBindings()->group(function (): void {
    Route::get('/movies/{movie:slug}/showtimes/{screening}', ...);
});
```

Code: `BookingConcessionController.php:21-27`, `BookingPaymentController.php:24-106`, `BookingPolicy.php`, `routes/web.php:21-30`.

UI hide button chỉ là UX; Policy/controller vẫn phải chặn server-side.

## 14. Encryption, token hash và signed URL

### Vì sao cần

- Stripe `client_secret` cần dùng ở browser nhưng không nên plaintext trong DB/admin/log.
- QR token raw không nên lưu để database leak không tạo ticket forgery.
- Ticket public verification cần signed URL/TTL.

```php
protected function casts(): array
{
    return ['client_secret' => 'encrypted'];
}

$item->qr_token_hash = hash('sha256', $rawToken);
```

Code: `app/Models/Payment/Payment.php` casts, `FinalizeSuccessfulPayment.php:129-134`, `routes/web.php:32`, `TicketVerificationController.php`.

Không log Stripe secret, client secret hoặc raw QR token.

## 15. Rate limiting và `Cache-Control: no-store`

Mutation/availability là endpoint dễ bị spam và inventory không được stale cache.

```php
Route::post('/bookings/{booking}/pay', ...)
    ->middleware('throttle:booking-mutations');

return response()->json($payload)
    ->header('Cache-Control', 'no-store');
```

Code: `routes/user.php:42-48`, `routes/web.php:25-29`, `BookingConcessionController.php:34`.

Rate limit không thay thế transaction/idempotency; no-store không thay thế backend revalidation.

## 16. Server clock và deterministic expiry

Browser clock có thể sai. Countdown chỉ là UX; server vẫn quyết định expiry/payment.

```js
const serverClockOffsetMs = serverNow - Date.now();
const seconds = Math.max(
    0,
    Math.ceil((expiresAt - (Date.now() + serverClockOffsetMs)) / 1000),
);
```

Code: `app/Support/Booking/BookingClock.php`, `ExpireBooking.php`, `resources/js/modules/booking.js:13-24`.

## 17. Frontend initialization guard, polling và AbortController

### Vì sao cần

Partial navigation/reload có thể init module nhiều lần; polling request cũ có thể trả sau và ghi đè UI mới.

```js
if (form.dataset.comboTotalsInitialized === 'true') return;
form.dataset.comboTotalsInitialized = 'true';

availabilityController?.abort();
availabilityController = new AbortController();
fetch(url, { cache: 'no-store', signal: availabilityController.signal });
```

Code: `resources/js/app.js:20-32`, `booking.js:69-162`, `seat-picker.js:4-11,171-230`, `payment-status.js:11-39`.

Polling phải dừng khi page hidden/pagehide, có interval/retry giới hạn và luôn để server kiểm tra lại khi submit.

## 18. Testing techniques

| Kỹ thuật | Test cần có |
|---|---|
| Transaction/lock | MySQL multi-process race |
| Idempotency | Repeat request/provider timeout |
| State machine | Valid/invalid transition |
| Webhook | Signature, duplicate, delayed, orphan, mismatch |
| Refund | Pending/succeeded/failed/reconcile |
| Frontend guard | Static test + browser click-through |
| Server clock | Expiry với clock/timezone khác |
| Query/scope | Feature response và ownership |

Test source: `tests/Feature/MovieBookingFeatureTest.php`, `BookingPaymentReliabilityTest.php`, `MySqlBookingConcurrencyTest.php`, `tests/frontend/*.test.js`, `tests/e2e/booking.spec.js`.

## 19. Khi nào không nên dùng

- Không bao network call trong transaction giữ lock.
- Không lock nếu invariant không cạnh tranh.
- Không dùng key cố định cho hai business operation khác nhau.
- Không retry validation/amount mismatch như transient error.
- Không dùng cache làm nguồn sự thật cho seat/stock.
- Không thêm enum state nếu chưa cập nhật transition matrix, UI, translation, test và tài liệu.

## 20. Checklist khi thêm kỹ thuật mới

- [ ] Vấn đề/race/failure cụ thể là gì?
- [ ] Invariant nào được bảo vệ?
- [ ] Boundary nằm ở action, job, model hay provider?
- [ ] Có unique/index/database constraint hỗ trợ không?
- [ ] Retry có duplicate side effect không?
- [ ] Có deadline/manual review/alert không?
- [ ] Có test failure, duplicate, timeout và concurrency không?
- [ ] Có cập nhật flow, business rule và README không?

