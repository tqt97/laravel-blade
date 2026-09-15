# Movie Booking Refactor History

Tài liệu này là sổ đăng ký refactor cho toàn bộ luồng Movie Booking. Mục tiêu là giữ một nguồn sự thật duy nhất cho domain values, runtime policy và frontend contract; mỗi thay đổi phải ghi rõ file, hàm, lý do và bằng chứng kiểm thử.

## Nguyên tắc nguồn sự thật

| Loại giá trị | Nguồn chuẩn | Cách sử dụng |
| --- | --- | --- |
| Booking/payment/ticket/inventory status | `app/Enums/**` | Model cast, action, controller, query và Blade dùng enum/value của enum |
| Stripe webhook event | `App\Enums\Payment\StripeWebhookEventType` | Controller/job cùng dùng một mapping event → payment status |
| Payment provider | `App\Enums\Payment\PaymentProvider` | `Payment::forProvider()` và `PaymentWebhookEvent::forProvider()` |
| Giới hạn, timeout, retry, polling, lease | `config/booking.php` và `config/services.php` | Backend đọc config; Blade expose data attributes; JS không tự định nghĩa policy riêng |
| Locale được hỗ trợ | `config/app.php` (`supported_locales`, `locale_labels`) | Route, middleware, outbox, Blade và language menu dùng chung |
| User-facing text | `lang/en`, `lang/vi` | Không thêm text trực tiếp vào controller/action/JS nếu có thể truyền qua translation/data attribute |
| Frontend contract | Blade `data-*` attributes | JS đọc contract từ DOM, không lặp status/interval/config bằng literal |

## Registry các hàm đã refactor

| Ngày | Hàm/class | Thay đổi | Nguồn chuẩn mới | Verification |
| --- | --- | --- | --- | --- |
| 2026-09-11 | `Payment::scopeForProvider()` | Thay các `where('provider', 'stripe')` lặp lại | `PaymentProvider` | Payment/webhook feature tests, PHPStan |
| 2026-09-11 | `PaymentWebhookEvent::scopeForProvider()` | Chuẩn hóa truy vấn webhook theo provider | `PaymentProvider` | Webhook/orphan tests |
| 2026-09-11 | `StripeWebhookController` | Dùng enum event/provider, lưu orphan event và dispatch sau ingest | `StripeWebhookEventType`, `PaymentProvider`, `booking.payment.*` | `BookingPaymentReliabilityTest` |
| 2026-09-11 | `ProcessStripeWebhook::handle()` | Bỏ mảng event type/status mapping magic; retry/backoff lấy config | `StripeWebhookEventType`, `PaymentStatus`, `PaymentAttemptStatus` | Webhook idempotency/late-event tests |
| 2026-09-11 | `PaymentStatus::reconciliationCandidates()` | Gom nhóm status dùng cho reconcile | `PaymentStatus` | `ReconcilePayments` path và PHPStan |
| 2026-09-11 | `PaymentAttemptStatus::fromPaymentStatus()` | Gom mapping payment → attempt status | `PaymentAttemptStatus` | Payment reliability tests |
| 2026-09-11 | `AppServiceProvider::register()` | Provider factory không còn match string rải rác | `PaymentProvider::configured()` | Full Pest/PHPStan |
| 2026-09-11 | `PayBooking`, `Payment::syncLatestAttempt`, `StripePaymentGateway`, `RefundBooking` | Chuẩn hóa provider và idempotency key prefix/HTTP timeout | `booking.payment.*`, `services.stripe.timeout_seconds` | Stripe gateway/retry tests |
| 2026-09-11 | `OutboxEventType::channel()` | Delivery channel không còn match rải trong job | `OutboxEventType` | Full Pest/outbox delivery tests |
| 2026-09-11 | `PublishOutboxMessage::handle()` | Tách logical idempotency key, message ID, attempts và observability | `booking.outbox.*`, `OutboxEventType` | Full Pest, log path review |
| 2026-09-11 | `TicketController`, `BookingConfirmationMail` | Dùng chung verification grace period | `booking.ticket.verification_grace_hours` | View cache/full tests |
| 2026-09-11 | `routes/web.php`, `SetLocale`, `PublishOutboxMessage` | Không còn hardcode `['en', 'vi']` ở backend | `config('app.supported_locales')` | Translation parity check |
| 2026-09-11 | `language-switcher.blade.php`, `language-menus.js` | Locale label/options render từ config/DOM, JS không sở hữu danh sách locale | `config/app.php` | Frontend test/build |
| 2026-09-11 | `PaymentStatus::browserTerminalValues()` | Terminal status frontend lấy từ enum, loại bỏ `canceled` không tồn tại trong local payment status | `PaymentStatus` | Payment status frontend test/build |
| 2026-09-11 | `payment-action.blade.php`, `payment-status.js` | Poll interval, retry interval, max unknown attempts và delayed notice đọc từ config data attributes | `booking.payment.status_*` | Frontend test/build |
| 2026-09-11 | `BookingStatus`, `PaymentStatus`, `TicketStatus`, `PaymentAttemptStatus`, `OutboxEventType` | Gom các nhóm status lặp thành predicate/action method; chuyển payment transition matrix vào `PaymentStatus::canTransitionTo()` | Enum-owned domain behavior | Full Pest/PHPStan |
| 2026-09-11 | `routes/web.php`, `routes/user.php`, `routes/admin.php`, `routes/console.php` | Chia route theo phạm vi/comment; chuyển locale, home, ticket verification và redirect closure sang invokable controller | Controller boundary + route groups | `route:list`, full Pest/PHPStan |
| 2026-09-11 | `StripeWebhookController::__invoke()` | Thay return code `0/1/2/3` bằng `StripeWebhookIngestResult` có tên nghĩa rõ ràng | `StripeWebhookIngestResult` | Webhook reliability tests/PHPStan |
| 2026-09-11 | `StripeWebhookController`, `IngestStripeWebhook`, `StripeWebhookSignatureVerifier` | Controller chỉ còn HTTP orchestration; ingest transaction/payment matching và signature verification đã tách khỏi controller | Action + support service boundary | Webhook reliability tests/PHPStan |
| 2026-09-11 | `PayBooking::execute()` | Hai lỗi nghiệp vụ không còn ném `RuntimeException` kỹ thuật; chuyển sang `BookingOperationFailed` để controller trả lỗi form đã dịch | `BookingOperationFailed` + `booking.messages.*` | Feature tests/PHPStan |
| 2026-09-11 | `resources/views/errors/{404,419,429,500,503}.blade.php` | Thêm fallback page cho lỗi HTTP web; không catch toàn bộ exception trong controller | `booking.errors.*` | `ErrorPagesTest`, view cache |
| 2026-09-11 | `EditBookingSelection`, `PayBooking`, `ProcessStripeWebhook`, `ReconcilePayment`, `BookingObserver` | Review kiến trúc Action/orchestration: xác định workflow nào giữ vai trò điều phối, transaction boundary nào cần tách và side-effect observer nào cần thay thế dần | Application workflow + transaction-free collaborators + explicit transition/outbox boundary | Architecture review mục 17; chưa triển khai code ở vòng review |

## Translation parity audit

Đã kiểm tra parity giữa `lang/en` và `lang/vi`:

- Bổ sung `lang/vi/auth.php`.
- Bổ sung `lang/vi/passwords.php`.
- Bổ sung các key UI còn thiếu: `users.select_user_disabled`, `users.self_admin_warning`.
- Bổ sung các rule validation Laravel còn thiếu trong `lang/vi/validation.php`.
- Bổ sung `booking.messages.refund_in_progress` ở cả EN/VI vì action check-in có sử dụng key này.
- Các key dynamic như `booking.landing.steps.*` được kiểm tra theo prefix động; không coi prefix chưa hoàn chỉnh trong static grep là key bị thiếu.

## Lịch sử triển khai

### 2026-09-11 — Hardening và domain refactor

- Tách webhook ingest khỏi finalize.
- Lưu orphan webhook/payment để reconcile.
- Thêm expiry mutation guard.
- Sửa same-seat replacement semantics, bao gồm payload rỗng.
- Bổ sung MySQL multi-process concurrency profile.
- Hoàn thiện outbox attempt/idempotency/observability baseline.
- Chuẩn hóa provider, webhook event, payment status, attempt status và outbox channel bằng enum.
- Chuẩn hóa runtime policy bằng config.
- Chuẩn hóa locale backend/frontend bằng một config source.
- Bổ sung translation parity cho EN/VI.

## Release verification checklist

- [x] `vendor/bin/pint --dirty --format agent`
- [x] `composer analyse`
- [x] `php artisan test --compact`
- [x] `npm run test:frontend`
- [x] `npm run check`
- [x] `git diff --check`
- [x] Web 404 fallback page có nội dung dịch theo locale mặc định
- [ ] MySQL multi-process concurrency với MySQL thật và `BOOKING_MYSQL_CONCURRENCY=1`
- [ ] Stripe test-mode webhook replay với queue worker thật
- [ ] Email provider crash-window/idempotency verification
- [ ] Browser accessibility/E2E cho locale switch, payment polling và expired booking

## Exception translation policy

- Dịch exception khi exception đại diện cho một lỗi nghiệp vụ mà user có thể sửa hoặc cần biết để tiếp tục: booking expired, seat conflict, coupon invalid, payment failed, refund state.
- Exception nghiệp vụ nên dùng domain exception rõ nghĩa (`BookingOperationFailed`, `BookingExpired`, `SeatHoldConflict`, `InvalidBookingTransition`) và message lấy từ translation key.
- Controller/request boundary chịu trách nhiệm đổi exception nghiệp vụ thành validation error, redirect error hoặc JSON error phù hợp.
- Không dịch hoặc lộ message của exception kỹ thuật như database, provider credentials, money invariant, queue/mail failure hay logic/configuration. Các lỗi này phải log/monitor và web response dùng message generic.
- Không tạo một controller catch-all để hứng mọi exception. Laravel tự render `resources/views/errors/{status}.blade.php`; các fallback page hiện có cho 404, 419, 429, 500 và 503.
- API/JSON request tiếp tục nhận JSON theo `shouldRenderJsonWhen()` trong `bootstrap/app.php`; không trả HTML error page cho API.

## Cập nhật tiếp theo

Khi thêm provider, locale, payment status hoặc một policy runtime mới:

1. Thêm vào enum/config source tương ứng.
2. Cập nhật model cast/scope hoặc Blade data contract.
3. Không thêm bản sao literal vào action/controller/JS.
4. Bổ sung EN/VI translation nếu text hiển thị cho người dùng.
5. Thêm test behavior và ghi lại hàm thay đổi trong registry này.
