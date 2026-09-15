# 03 — Test case và edge case

## 1. Test pyramid

| Tầng | Mục tiêu | Bộ test hiện tại |
|---|---|---|
| Unit/static | Money, enum mapping, frontend format/selector | `tests/frontend/*.test.js`, unit tests |
| Feature | HTTP boundary, policy, transaction, webhook, payment | `tests/Feature/MovieBookingFeatureTest.php`, `BookingPaymentReliabilityTest.php` |
| Concurrency | Row lock và race trong database thật | `tests/Feature/MySqlBookingConcurrencyTest.php` |
| Browser E2E | DOM, modal, responsive, payment element, 3DS | `tests/e2e/booking.spec.js` |
| Build/quality | PHPStan, Pint, ESLint, Vite, Blade cache | `composer.json` scripts và CI workflow |

## 2. Booking/seat test cases

| ID | Điều kiện | Kết quả mong đợi | Test/code tham chiếu |
|---|---|---|---|
| BK-SEAT-01 | Chọn một seat available | Tạo một held booking và booking item | `MovieBookingFeatureTest.php:132` |
| BK-SEAT-02 | Hai user cùng seat | Chỉ một user thắng; user còn lại conflict | `MovieBookingFeatureTest.php:894`, MySQL test `:41-64` |
| BK-SEAT-03 | Submit cùng idempotency key | Reuse booking, không nhân bản | `MovieBookingFeatureTest.php:204` |
| BK-SEAT-04 | Submit vượt max seats | Validation error, không ghi partial | `HoldSeats.php:24-151` |
| BK-SEAT-05 | Edit seat mới bị chiếm | Hold cũ không mất | `MovieBookingFeatureTest.php:174` |
| BK-SEAT-06 | Hold hết hạn trước pay | Booking expired, seat available, không issue ticket | `MovieBookingFeatureTest.php:707`, `BookingPaymentReliabilityTest.php` expiry cases |
| BK-SEAT-07 | Guest login sau khi chọn | Session selection resume đúng một lần | `MovieBookingFeatureTest.php:269-317` |
| BK-SEAT-08 | Guest resume combo đã hết | Rollback resume, không tạo booking một phần | `MovieBookingFeatureTest.php:1022` |
| BK-SEAT-09 | Seat expired hold trên availability | API báo available | `MovieBookingFeatureTest.php:844` |
| BK-SEAT-10 | User own hold trên availability | API báo `owned_by_current_booking` | `MovieBookingFeatureTest.php:869` |

## 3. Combo/inventory test cases

| ID | Điều kiện | Kết quả mong đợi | Tham chiếu |
|---|---|---|---|
| BK-COMBO-01 | Finite stock quantity 1 | Stock giảm và ledger tạo | `MovieBookingFeatureTest.php:229` |
| BK-COMBO-02 | Unlimited stock | Không decrement stock NULL, vẫn enforce max | `MovieBookingFeatureTest.php:229` |
| BK-COMBO-03 | Hai process mua item cuối | Chỉ một success, stock không âm | `MySqlBookingConcurrencyTest.php:24-39` |
| BK-COMBO-04 | Final quantity 2 rồi giảm 1 | Hoàn đúng delta | `MovieBookingFeatureTest.php:569` |
| BK-COMBO-05 | Omit line trong replacement | Quantity về 0, stock hoàn | `MovieBookingFeatureTest.php:649` |
| BK-COMBO-06 | Booking expire/cancel | Combo stock hoàn một lần | `MovieBookingFeatureTest.php:518,693` |
| BK-COMBO-07 | Refund retry | Không restore stock hai lần | `MovieBookingFeatureTest.php:399` |
| BK-COMBO-08 | Live availability public | Payload có stock/selected/max | `MovieBookingFeatureTest.php:844,869` |
| BK-COMBO-09 | Standalone combo page | Có `data-combo-availability-url` và polling | `MovieBookingFeatureTest.php:320-346`, `resources/js/modules/booking.js:117-162` |

## 4. Coupon test cases

| ID | Điều kiện | Kết quả mong đợi | Tham chiếu |
|---|---|---|---|
| BK-COUPON-01 | Coupon valid | Reservation và discount được tạo | `MovieBookingFeatureTest.php:904` |
| BK-COUPON-02 | Apply lại coupon đã release | Reuse reservation hợp lệ | `MovieBookingFeatureTest.php:919` |
| BK-COUPON-03 | Booking expire | Reservation release/usage không tăng | `MovieBookingFeatureTest.php:1008` |
| BK-COUPON-04 | Coupon hết hạn/inactive | Validation error, total giữ nguyên | `ApplyCoupon.php:20-154` |
| BK-COUPON-05 | Đồng thời đổi A → B | Lock order canonical, không deadlock/duplicate reserved | `ApplyCoupon.php:25-83` |
| BK-COUPON-06 | Currency/scope/minimum subtotal sai | Reject trước khi mutate | `ApplyCoupon.php:85-154` |

## 5. Payment/Stripe test matrix

| Case | Mô phỏng | Local expected | Test tham chiếu |
|---|---|---|---|
| Success | PaymentIntent `succeeded` | Confirm booking, sold seat, issue ticket | `MovieBookingFeatureTest.php:320`, `BookingPaymentReliabilityTest.php:431` |
| 3DS | `requires_action` | Payment action page, Payment Element, chờ webhook | `BookingPaymentReliabilityTest.php:602`, `tests/e2e/booking.spec.js:59` |
| Missing method | `requires_payment_method` | Cho nhập lại method, không charge mù | `BookingPaymentReliabilityTest.php:564,695` |
| Processing | `processing` | Pending/reconcile, chưa issue ticket | `BookingPaymentReliabilityTest.php:538` |
| Delayed webhook | Provider success trước webhook | Reconcile/sync finalize đúng một lần | `BookingPaymentReliabilityTest.php:431` |
| Duplicate webhook | Gửi cùng event hai lần | Event/payment/ticket không nhân đôi | `MovieBookingFeatureTest.php:450` |
| Orphan webhook | Payment local chưa có | Persist orphan, retry/replay về sau | `BookingPaymentReliabilityTest.php:772` |
| Amount mismatch | Webhook amount khác booking | Unknown/manual review, không finalize | `BookingPaymentReliabilityTest.php:739` |
| API timeout | Gateway throw/network timeout | Unknown/processing + reconcile, không tạo charge thứ hai | `BookingPaymentReliabilityTest.php:507` |
| Provider idempotency | Retry cùng attempt | Cùng `Idempotency-Key` | `BookingPaymentReliabilityTest.php:538` |
| Late success | Success sau expiry | `requires_refund`, không issue ticket | `MovieBookingFeatureTest.php:476` |

## 6. Refund test matrix

| Case | Kết quả mong đợi | Tham chiếu |
|---|---|---|
| Refund pending | Payment `refunding`, chưa release/finalize | `BookingPaymentReliabilityTest.php:140` |
| Refund succeeded | Finalize payment/ticket/seat/combo/coupon | `BookingPaymentReliabilityTest.php:162` |
| Refund failed | Không mark refunded, giữ manual review | `BookingPaymentReliabilityTest.php:632` |
| Refund webhook duplicate | Finalize idempotent | `ProcessStripeWebhook.php:201-274` |
| Refund provider timeout | Retry/reconcile cùng logical key | `ReconcileRefund.php:23-90` |
| Unknown refund không có provider ID | Retry `RefundBooking` | `BookingPaymentReliabilityTest.php:87` |
| Admin pending status | UI warning, không flash success | `BookingPaymentReliabilityTest.php:110` |

## 7. Frontend/browser test cases

| ID | Thao tác | Expected |
|---|---|---|
| UI-01 | Seat picker init nhiều lần | Không duplicate combo listener |
| UI-02 | Click Apply coupon | Chỉ coupon request, không disable payment buttons |
| UI-03 | Click Pay desktop/mobile | Cả payment buttons disable, coupon không bị disable |
| UI-04 | Combo stock thay đổi | Input max/disabled/sold-out cập nhật |
| UI-05 | Availability request fail | Alert accessible xuất hiện, submit vẫn server-safe |
| UI-06 | Countdown về zero | Payment buttons disabled, expiry notice xuất hiện |
| UI-07 | Payment unknown | Polling có giới hạn, hiển thị manual/retry state |
| UI-08 | 3DS challenge | Payment Element submit, return/polling không duplicate |
| UI-09 | Mobile | Sticky total/payment không che nội dung và touch target đủ lớn |
| UI-10 | Keyboard | Modal Escape/focus trap/restore focus hoạt động |

## 8. MySQL concurrency gate

Local SQLite không chứng minh row locking production. Chạy:

```bash
BOOKING_MYSQL_CONCURRENCY=1 \
DB_CONNECTION=mysql \
php artisan test --compact tests/Feature/MySqlBookingConcurrencyTest.php
```

CI tự chạy test với MySQL 8.4 tại `.github/workflows/booking-quality.yml`. Nếu test skip, đó là **chưa đạt gate**, không được ghi là passed.

## 9. Quality commands

```bash
php artisan test --compact
vendor/bin/phpstan analyse --no-progress --debug
vendor/bin/pint --dirty --format agent
npm run test:frontend
npm run lint
npm run build
php artisan view:cache
npm run test:e2e
```

Browser E2E yêu cầu app, database fixture, user fixture và Stripe test configuration. Không đánh đồng test bị skip vì thiếu fixture với test passed.

