# Booking documentation hub

Bộ tài liệu này là điểm vào chính cho tính năng movie booking hiện tại. Nội dung được viết theo code đang chạy trong repository, không theo flow legacy `BookableResource`.

## Đọc nhanh theo vai trò

| Vai trò | Đọc trước | Sau đó |
|---|---|---|
| Developer mới | [Business rules](01-business-rules.md) | [Flows](02-booking-flows.md), [Architecture/UML](04-architecture-and-uml.md) |
| Developer sửa payment | [Stripe/3DS operations](07-stripe-3ds-and-operations.md) | [Test cases](03-test-cases-edge-cases.md), `app/Jobs/ProcessStripeWebhook.php` |
| QA/Tester | [Test cases và edge cases](03-test-cases-edge-cases.md) | [Flows](02-booking-flows.md), [Development notes](05-development-notes-and-known-bugs.md) |
| Frontend developer | [Flows](02-booking-flows.md) | [Architecture/UML](04-architecture-and-uml.md), [Development notes](05-development-notes-and-known-bugs.md) |
| Operator/DevOps | [Stripe/3DS operations](07-stripe-3ds-and-operations.md) | [Architecture/UML](04-architecture-and-uml.md), [Test cases](03-test-cases-edge-cases.md) |

## Mục lục

1. [01 — Logic nghiệp vụ](01-business-rules.md)
2. [02 — Flow từng luồng booking](02-booking-flows.md)
3. [03 — Test case và edge case](03-test-cases-edge-cases.md)
4. [04 — Kiến trúc tổng thể, flow chart và UML](04-architecture-and-uml.md)
5. [05 — Notes, bug và điểm cần lưu ý](05-development-notes-and-known-bugs.md)
6. [06 — Lessons learned](06-lessons-learned.md)
7. [07 — Stripe, 3DS và vận hành payment](07-stripe-3ds-and-operations.md)
8. [08 — Kỹ thuật triển khai và code minh họa](08-engineering-techniques.md)

## Source of truth trong code

| Nội dung | Code chính |
|---|---|
| Routes | `routes/web.php:15-33`, `routes/user.php:36-48` |
| Hold ghế | `app/Actions/Booking/Checkout/HoldSeats.php:24-151` |
| Edit selection | `app/Actions/Booking/Checkout/EditBookingSelection.php:36-119` |
| Combo/inventory | `app/Actions/Commerce/Concessions/SyncBookingConcessions.php:23-136` |
| Coupon | `app/Actions/Commerce/Coupons/ApplyCoupon.php:20-154` |
| Payment claim/charge | `app/Actions/Booking/Checkout/PayBooking.php:37-255` |
| Payment finalize | `app/Actions/Booking/Payment/FinalizeSuccessfulPayment.php:34-225` |
| Refund | `app/Actions/Booking/Payment/RefundBooking.php:29-143`, `FinalizeRefund.php:25-91` |
| Stripe webhook | `app/Jobs/ProcessStripeWebhook.php:44-314` |
| Reconciliation | `app/Jobs/ReconcilePayment.php:48-206`, `ReconcileRefund.php:23-90` |
| Availability | `app/Queries/Catalog/ScreeningAvailabilityQuery.php:19-42`, `app/Queries/Commerce/AvailableConcessionsQuery.php:18-65` |
| Frontend seat picker | `resources/js/modules/seat-picker.js:4-470` |
| Frontend checkout/combo | `resources/js/modules/booking.js:4-180` |
| Frontend payment/3DS | `resources/js/modules/payment-status.js:2-154` |
| Scheduler | `routes/console.php:14-20` |

## Kỹ thuật cần hiểu trước khi sửa code

Transaction, `lockForUpdate`, deterministic lock order, idempotency key, state machine, compensation, outbox, retry/backoff, reconciliation, webhook deduplication, inventory ledger, server clock và frontend polling được giải thích tập trung tại [08 — Kỹ thuật triển khai](08-engineering-techniques.md).

## Tài liệu cũ

Các file ở `docs/` root vẫn được giữ để truy vết lịch sử, nhưng không nên dùng làm điểm bắt đầu:

- `docs/movie-booking-architecture.md` — tài liệu cũ, quá rộng; nội dung đã được tách vào các file trong thư mục này.
- `docs/movie-booking-business-logic.md` — nội dung nghiệp vụ cũ; đối chiếu với `01-business-rules.md`.
- `docs/movie-booking-deep-review.md` — audit/history, dùng khi cần xem lý do của các quyết định cũ.
- `docs/stripe-laravel-webhook-3ds-guide.md` — hướng dẫn Stripe chi tiết cũ; flow vận hành canonical hiện nằm ở `07-stripe-3ds-and-operations.md`.

Khi có thay đổi nghiệp vụ, phải cập nhật code, test và file tương ứng trong thư mục `docs/booking/`. Không thêm phần mới vào architecture legacy nếu nội dung đã có thể đặt vào một file chuyên biệt.

## Quy ước trích dẫn code

Các tham chiếu dùng dạng `path/to/file.php:line`. Dòng là snapshot tại thời điểm viết tài liệu; khi refactor làm thay đổi dòng, phải cập nhật lại link/tham chiếu trong tài liệu cùng commit.
