# 05 — Development notes, bug và điểm cần lưu ý

Đây là file bắt buộc đọc trước khi sửa booking/payment. Mục “đã xử lý” giúp tránh reintroduce bug cũ; mục “còn phải theo dõi” là risk hoặc operational requirement, không tự động có nghĩa là defect đang xảy ra.

## 1. Bug đã xử lý

### 1.1 Duplicate combo listener

- **Triệu chứng:** combo total/listener bị khởi tạo cả trong seat picker và booking module.
- **Nguyên nhân:** `booking.js` được load ở mọi trang có `data-combo-total`.
- **Fix:** `resources/js/app.js:20-32` chỉ load booking module cho checkout hoặc standalone combo; seat page chỉ load `seat-picker.js`. Mỗi module có initialization guard.
- **Regression:** `tests/frontend/service-worker.test.js` test standalone/entrypoint.

### 1.2 Disable nhầm coupon button

- **Triệu chứng:** countdown hoặc submit payment disable cả nút Apply coupon.
- **Nguyên nhân:** selector dùng chung `button[type=submit]`.
- **Fix:** checkout button có `data-payment-submit`; handler kiểm tra `event.submitter` tại `resources/js/modules/booking.js:38-63`.
- **Regression:** frontend selector test và checkout Blade `resources/views/user/bookings/checkout.blade.php:165-181`.

### 1.3 Admin refund luôn hiển thị success

- **Triệu chứng:** refund pending/unknown nhưng flash “refunded successfully”.
- **Fix:** `app/Http/Controllers/Admin/BookingController.php:63-76` phân biệt `Refunded`, `Refunding` và trạng thái cần review; warning feedback nằm tại `resources/views/components/auth/feedback.blade.php`.
- **Regression:** `tests/Feature/BookingPaymentReliabilityTest.php:110-138`.

### 1.4 Refund finalize trước provider success

- **Triệu chứng nguy hiểm:** local booking đã refunded dù Stripe refund còn pending.
- **Fix:** chỉ `StripeRefundStatus::Succeeded` được gọi `FinalizeRefund`; pending/failed/reconcile giữ trạng thái tương ứng.
- **Code:** `ProcessStripeWebhook.php:212-274`, `ReconcileRefund.php:51-80`, `FinalizeRefund.php:25-91`.

### 1.5 Giữ ghế vô hạn khi provider không rõ

- **Triệu chứng:** `requires_action`, `requires_payment_method`, `unknown` giữ hold quá lâu.
- **Fix:** expiry là booking TTL độc lập với payment status; scheduler `booking:expire-holds` chạy mỗi phút.
- **Code:** `ExpireBooking.php`, `routes/console.php:14`.

## 2. Những điều dev không được làm

1. Không gọi `PaymentGateway` khi đang giữ DB lock/transaction.
2. Không tạo PaymentIntent retry bằng random key mới nếu attempt cũ chưa được reconcile.
3. Không dùng provider response từ browser làm nguồn sự thật; webhook/reconcile phải xác nhận.
4. Không mark refund success dựa trên HTTP 200; đọc object status.
5. Không update stock trực tiếp; dùng `InventoryMovement` và idempotency.
6. Không dùng `in_array`/magic string cho payment/booking status nếu đã có enum/state machine.
7. Không bypass Policy chỉ vì UI đã ẩn button.
8. Không đặt điều kiện `bookable`, ownership hoặc availability riêng trong controller mới.
9. Không dùng SQLite concurrency test để kết luận MySQL production safe.
10. Không chạy production bằng `BOOKING_PAYMENT_PROVIDER=fake`.

## 3. Risk/monitoring cần theo dõi

### 3.1 MySQL concurrency

`tests/Feature/MySqlBookingConcurrencyTest.php` skip nếu thiếu MySQL, `pdo_mysql`, `pcntl` hoặc `BOOKING_MYSQL_CONCURRENCY=1`. CI workflow bắt buộc chạy profile này. Nếu skip trong CI, phải xem là quality gate chưa đạt.

### 3.2 Browser E2E

Playwright E2E cần fixture:

```env
E2E_BASE_URL=http://127.0.0.1:8000
E2E_MOVIE_SLUG=...
E2E_SCREENING_ID=...
E2E_EMAIL=...
E2E_PASSWORD=...
E2E_STRIPE_3DS_CARD=4000002500003155
```

Không commit credential hoặc card data live. Test thiếu fixture sẽ skip; output phải được ghi rõ, không xem là pass thực tế.

### 3.3 Alert payment anomalies

`payments:alert-stuck` log `payments.anomalies` cho:

- stuck processing;
- payment unknown quá ngưỡng;
- requires refund;
- orphan webhook.

Config tại `config/booking.php:83-90`. Log channel production phải được đưa vào hệ thống alert/log aggregation; chỉ ghi local file không đủ cho vận hành.

### 3.4 Availability polling

- Polling không thay thế backend lock.
- `Cache-Control: no-store` là bắt buộc cho tồn kho.
- Có rate limit `availability`.
- Khi request lỗi, UI phải thông báo nhưng vẫn để server validate khi submit.
- Không lưu availability lâu trong service worker/cache navigation.

## 4. Cạm bẫy timezone/clock

- Business expiry dùng `BookingClock`.
- Blade có `serverNow` để countdown bù sai lệch client clock.
- Test time-sensitive cần set timezone/Carbon clock, không dựa vào thời gian máy chạy test.
- DB timestamp và serialized payload phải cùng quy ước timezone.

## 5. Cạm bẫy transaction/queue

- Laravel queue at-least-once: mọi job phải idempotent.
- Dispatch external work sau commit (`afterCommit`) khi job phụ thuộc dữ liệu vừa ghi.
- Outbox claim phải lock/lease; worker chết giữa chừng phải reclaim được.
- Webhook event persist trước khi dispatch để duplicate Stripe event không tạo duplicate effect.
- Không giữ HTTP/provider call bên trong transaction có row lock.

## 6. Cạm bẫy frontend

- Luôn dùng `data-*` hook, không dùng class CSS làm API JavaScript.
- Selector phải scope trong component/form hiện tại.
- Khi thêm submit button, xác định rõ nó là payment hay coupon.
- Module phải có `initialized` guard.
- Khi thêm polling phải có `AbortController`, dừng khi hidden/pagehide và có `no-store`.
- UI clamp quantity chỉ là progressive enhancement; Form Request/action vẫn enforce.
- Các error message mới phải thêm cả `lang/en` và `lang/vi`.

## 7. Cạm bẫy migration/schema

- Status/currency/enum có comment trong schema và enum trong application.
- Index phải phản ánh query scheduler/reconciliation, không thêm index tùy ý.
- Khi refactor migration baseline, phải kiểm tra database fresh và database upgrade.
- Unique constraint là lớp bảo vệ cuối cùng cho idempotency, nhưng không thay thế transaction logic.

## 8. Checklist review PR

- [ ] Có action/query owner rõ ràng.
- [ ] Controller không chứa business mutation.
- [ ] Có authorization và validation.
- [ ] Có transaction/lock cho invariant cạnh tranh.
- [ ] Có idempotency cho retry/provider/outbox/inventory.
- [ ] Có test success + failure + duplicate + timeout/race.
- [ ] Có cập nhật translation.
- [ ] Có cập nhật tài liệu và line reference.
- [ ] Chạy PHPStan/Pint/Pest/frontend/build.
- [ ] Nếu payment/inventory: chạy MySQL concurrency hoặc ghi rõ chưa chạy.

