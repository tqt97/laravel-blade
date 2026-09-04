# Laravel Booking System --- Architecture & Implementation Playbook

> Tài liệu triển khai tính năng Booking theo hướng production-ready,
> đồng thời dùng bài toán Booking như một project luyện tư duy Senior /
> Tech Lead Backend.

## 1. Mục tiêu

Không xây Booking như một CRUD `bookings`. Mục tiêu là xây một module có
khả năng xử lý đúng các vấn đề thực tế:

- Business invariants.
- Overlap và availability.
- Race condition và concurrent requests.
- Transaction boundary.
- Database constraints.
- Idempotency.
- Booking lifecycle / state machine.
- Hold và expiration.
- Payment và webhook.
- Queue, retry và duplicate execution.
- Transactional Outbox.
- Cache và Redis.
- Authorization.
- Audit và observability.
- Failure recovery.
- Testing concurrency và production scenarios.

Nguyên tắc trung tâm:

> Không bao giờ được tồn tại hai booking active chiếm cùng một resource
> trong cùng một khoảng thời gian không hợp lệ.

Invariant này phải được bảo vệ ở tầng phù hợp, đặc biệt là
database/concurrency strategy, không chỉ bằng một câu `if` trong
Controller.

------------------------------------------------------------------------

## 2. Scope đề xuất cho V1

Giả sử hệ thống cho phép user booking một `Resource`: phòng họp, bàn,
sân, lịch tư vấn, nhân viên, thiết bị...

### Core entities

- `User`
- `Resource`
- `Booking`
- `Payment` nếu có thanh toán
- `BookingEvent` / Audit Log nếu cần lịch sử
- `OutboxMessage` khi áp dụng Outbox Pattern

### Chức năng

1. Xem availability.
2. Tạo booking/hold.
3. Xác nhận booking.
4. Thanh toán.
5. Hủy booking.
6. Tự động expire hold.
7. Xem booking detail/history.
8. Admin quản lý resource và booking.
9. Notification sau các transition quan trọng.

### Chưa cần trong V1

- Recurring booking.
- Coupon engine phức tạp.
- Dynamic pricing phức tạp.
- Marketplace multi-vendor.
- Waitlist.
- Multi-resource booking atomic.

Thiết kế V1 sao cho có extension point cho các chức năng trên nhưng
không over-engineer trước.

------------------------------------------------------------------------

## 3. Domain Language

Thống nhất terminology trước khi code:

  Thuật ngữ         Ý nghĩa
  ----------------- -------------------------------------------------
  Resource          Đối tượng có thể được đặt
  Booking           Một yêu cầu đặt resource
  Period            Khoảng `[start_at, end_at)`
  Availability      Resource có khả năng được booking hay không
  Hold              Giữ chỗ tạm thời
  Conflict          Hai booking tranh chấp cùng resource/time
  Active booking    Booking đang chiếm capacity
  Terminal state    Trạng thái không tiếp tục lifecycle bình thường
  Idempotency Key   Khóa nhận diện một logical request
  Transition        Chuyển trạng thái booking

Dùng ubiquitous language này xuyên suốt code, test, API và
documentation.

------------------------------------------------------------------------

## 4. Business Invariants

Viết invariants trước migration/controller.

### Time

- `start_at < end_at`.
- Persistence dùng UTC.
- Convert timezone ở system boundary.
- Khoảng booking dùng half-open interval `[start_at, end_at)`.
- 09:00--10:00 và 10:00--11:00 không overlap.
- Có minimum/maximum booking duration nếu business yêu cầu.
- Không booking quá khứ nếu policy không cho phép.
- Có booking horizon, ví dụ không đặt quá 90 ngày.
- Validate operating hours nếu resource có giờ hoạt động.

### Resource

- Resource phải tồn tại.
- Resource phải active/bookable.
- Resource có thể bị maintenance/blocked.
- Không hard-delete resource nếu cần giữ lịch sử booking.

### Capacity

Với resource capacity = 1:

> Không có hai active bookings overlap cùng resource.

Nếu tương lai capacity \> 1, invariant phải được thiết kế lại thành
capacity allocation thay vì chỉ overlap boolean.

### Status

Chỉ các trạng thái được định nghĩa là `occupying` mới chiếm resource, ví
dụ:

- `HELD`
- `PENDING_PAYMENT`
- `CONFIRMED`

Các trạng thái như `CANCELLED`, `EXPIRED`, `COMPLETED` không chiếm
future capacity.

### Hold

- Hold luôn có `expires_at`.
- Hold quá hạn không được confirm bình thường.
- Expiration phải idempotent.
- Không dựa duy nhất vào scheduler để coi hold hết hiệu lực; logic
    availability cũng phải hiểu `expires_at`.

### Cancellation

- User chỉ hủy booking được phép.
- Có cancellation deadline nếu business yêu cầu.
- Hủy nhiều lần phải an toàn/idempotent.
- Refund và cancellation là hai concern liên quan nhưng không nên nhập
    làm một DB operation mơ hồ.

### Payment

- Một payment success không được confirm booking sai trạng thái.
- Duplicate webhook không được duplicate transition.
- Out-of-order webhook phải được xử lý.
- Payment success đến sau expiration phải có policy rõ ràng.

------------------------------------------------------------------------

## 5. State Machine

Không cho phép code tùy ý:

``` php
$booking->update(['status' => 'confirmed']);
```

Đề xuất lifecycle:

``` text
                 timeout
HELD ------------------------------> EXPIRED
 |
 | checkout
 v
PENDING_PAYMENT
 |            \
 | success     \ failure/cancel
 v              v
CONFIRMED     CANCELLED
 |
 | cancellation
 v
CANCELLED

CONFIRMED ---> COMPLETED
CONFIRMED ---> NO_SHOW
```

### Transition table

  From              To                Điều kiện
  ----------------- ----------------- ------------------------------
  HELD              PENDING_PAYMENT   Hold còn hạn
  HELD              EXPIRED           `expires_at <= now()`
  HELD              CANCELLED         User/system cancel
  PENDING_PAYMENT   CONFIRMED         Payment hợp lệ
  PENDING_PAYMENT   CANCELLED         Payment fail/cancel policy
  CONFIRMED         CANCELLED         Cancellation policy cho phép
  CONFIRMED         COMPLETED         Booking đã diễn ra
  CONFIRMED         NO_SHOW           Không xuất hiện

Mọi transition nên đi qua Action/domain method rõ nghĩa.

------------------------------------------------------------------------

## 6. Kiến trúc đề xuất

Dùng **Modular Monolith + Application Actions**, không cần ép Laravel
vào Clean Architecture nhiều layer ngay từ đầu.

``` text
app/
└── Booking/
    ├── Actions/
    │   ├── CreateBooking.php
    │   ├── ConfirmBooking.php
    │   ├── CancelBooking.php
    │   ├── ExpireBooking.php
    │   └── CompleteBooking.php
    ├── DTOs/
    │   ├── CreateBookingData.php
    │   └── CancelBookingData.php
    ├── ValueObjects/
    │   ├── BookingPeriod.php
    │   └── Money.php
    ├── Enums/
    │   └── BookingStatus.php
    ├── Exceptions/
    │   ├── BookingConflict.php
    │   ├── BookingExpired.php
    │   └── InvalidBookingTransition.php
    ├── Queries/
    │   ├── GetAvailability.php
    │   ├── GetBooking.php
    │   └── GetUserBookings.php
    ├── Services/
    │   └── BookingAvailabilityService.php
    ├── Events/
    │   ├── BookingCreated.php
    │   ├── BookingConfirmed.php
    │   └── BookingCancelled.php
    └── Policies/
        └── BookingPolicy.php
```

HTTP layer:

``` text
app/Http/
├── Controllers/
│   └── BookingController.php
├── Requests/
│   └── CreateBookingRequest.php
└── Resources/
    └── BookingResource.php
```

Controller chỉ orchestration:

``` php
public function store(
    CreateBookingRequest $request,
    CreateBooking $action,
): BookingResource {
    $booking = $action->handle(
        CreateBookingData::fromRequest($request),
        $request->user(),
    );

    return new BookingResource($booking);
}
```

------------------------------------------------------------------------

## 7. Pattern nên áp dụng

### Action / Use Case

Rất phù hợp:

- `CreateBooking`
- `CancelBooking`
- `ConfirmBooking`
- `ExpireBooking`

Một Action đại diện cho một business operation.

### DTO

Không truyền `$request->all()` hoặc array không type sâu vào application
layer.

``` text
HTTP Request
    ↓
FormRequest
    ↓
DTO
    ↓
Action
```

### Value Object

Dùng khi object có invariant/value semantics:

- `BookingPeriod`
- `Money`

`BookingPeriod` nên tự đảm bảo start \< end và cung cấp behavior liên
quan đến period.

### State Machine

Booking lifecycle là use case rất phù hợp để học state transition.

Không nhất thiết cần package; explicit transition rules thường dễ đọc
hơn lúc đầu.

### Domain/Application Events

Ví dụ:

- `BookingConfirmed`
- `BookingCancelled`

Event dùng cho side effects, không nên biến listener thành nơi bí mật
bảo vệ invariant quan trọng.

### Strategy

Chỉ thêm khi thực sự có nhiều implementation:

``` text
CancellationPolicy
├── FlexibleCancellationPolicy
├── StrictCancellationPolicy
└── NonRefundableCancellationPolicy
```

Hoặc:

``` text
PricingStrategy
├── StandardPricing
├── WeekendPricing
└── PeakHourPricing
```

### Repository Pattern

**Không mặc định sử dụng.**

Không tạo repository chỉ để đổi:

``` php
Booking::find($id);
```

thành:

``` php
$bookingRepository->find($id);
```

Repository đáng dùng khi persistence abstraction thực sự đem lại giá trị
hoặc aggregate/domain cần cô lập khỏi Eloquent.

------------------------------------------------------------------------

## 8. Command / Query Separation

Không cần full CQRS nhưng nên tách tư duy.

### Commands

- CreateBooking
- ConfirmBooking
- CancelBooking
- ExpireBooking

Có thể mutate state, transaction, event.

### Queries

- GetAvailability
- GetBookingCalendar
- GetBookingHistory

Tối ưu riêng read path, eager loading/index/query shape theo nhu cầu UI.

------------------------------------------------------------------------

## 9. Overlap Rule

Với interval `[start, end)`:

``` text
existing.start < requested.end
AND
existing.end > requested.start
```

Ví dụ:

``` text
Existing : 09:00 -------- 10:00
Request  :                   10:00 -------- 11:00

=> không conflict
```

Nhưng application query chỉ là một phần của solution.

------------------------------------------------------------------------

## 10. Race Condition --- bài học quan trọng nhất

Code sau **không an toàn**:

``` php
if (! Booking::overlap($period)->exists()) {
    Booking::create(...);
}
```

Hai request:

``` text
Request A                   Request B
    |                           |
check availability          check availability
    | false                     | false
    |                           |
insert A                    insert B
    |                           |
 SUCCESS                     SUCCESS
```

Kết quả: double booking.

### Correctness strategy

``` text
Request
   ↓
Application validation
   ↓
BEGIN TRANSACTION
   ↓
Concurrency control / lock
   ↓
Validate invariant
   ↓
INSERT
   ↓
Database constraint
   ↓
COMMIT
```

Database nên là lớp bảo vệ cuối cùng.

### PostgreSQL

PostgreSQL rất phù hợp cho booking vì có range type + GiST/exclusion
constraint. Có thể biểu diễn invariant overlap ở database cho resource
capacity 1.

### MySQL

Không có exclusion constraint tương đương trực tiếp. Các hướng thường
dùng:

- Lock resource row trong transaction rồi check overlap.
- Discrete slot table + unique constraints.
- Allocation rows.
- Advisory/distributed lock như lớp bổ sung.

Không coi Redis lock là source of truth duy nhất.

------------------------------------------------------------------------

## 11. Transaction Boundary

Transaction phải ngắn.

``` text
validate request
      ↓
BEGIN
      ↓
lock/check invariant
      ↓
create/update booking
      ↓
write outbox if applicable
      ↓
COMMIT
      ↓
dispatch side effects
```

### Không làm trong transaction

- Stripe API call.
- Send email.
- External HTTP request.
- Upload file lớn.
- Slow computation không cần DB lock.

Transaction dài làm tăng contention/deadlock và giảm throughput.

------------------------------------------------------------------------

## 12. Idempotency

Các nguyên nhân duplicate request:

- Double click.
- Browser retry.
- Mobile network retry.
- Reverse proxy retry.
- Client timeout nhưng server đã xử lý.
- Job retry.
- Webhook duplicate.

API mutation quan trọng nên hỗ trợ:

``` http
Idempotency-Key: <unique-key>
```

Database cần unique constraint phù hợp.

### Semantics

- Cùng key + cùng logical request → trả lại cùng result.
- Cùng key + payload khác → reject.
- Key phải có scope rõ ràng, ví dụ user + operation.
- Không chỉ cache key trong Redis nếu cần guarantee mạnh.

------------------------------------------------------------------------

## 13. Payment

Flow:

``` text
Create Booking
      ↓
    HELD
      ↓
Create Payment Intent
      ↓
PENDING_PAYMENT
      ↓
payment provider
      ↓
webhook
      ↓
verify signature
      ↓
idempotency
      ↓
lock booking
      ↓
validate transition
      ↓
CONFIRMED
```

### Case phải quyết định trước

Booking:

``` text
expires_at = 10:05:00
```

Payment success xảy ra 10:04:59 nhưng webhook tới 10:05:02.

Các policy có thể là:

- Reject vì hệ thống nhận callback sau expiration.
- Accept dựa trên provider payment timestamp.
- Recovery/manual review.

Không có đáp án universal; quan trọng là business policy phải explicit
và test được.

------------------------------------------------------------------------

## 14. Queue

Queue nên dùng cho:

- Confirmation email.
- Notification.
- Analytics.
- Invoice.
- Calendar integration.
- Non-critical integrations.

Assume **at-least-once delivery**.

Job phải idempotent.

``` text
BookingConfirmed
      ↓
Queue Job
      ↓
worker crash after side effect
      ↓
retry
      ↓
job chạy lần hai
```

Code phải chịu được tình huống này.

------------------------------------------------------------------------

## 15. Transactional Outbox

Lỗi kinh điển:

``` text
DB COMMIT successful
       ↓
process crash
       ↓
queue dispatch chưa xảy ra
```

Booking tồn tại nhưng event bị mất.

Outbox:

``` text
BEGIN
   |
   +-- INSERT booking
   |
   +-- INSERT outbox_messages
   |
COMMIT
```

Worker:

``` text
outbox_messages
      ↓
publisher
      ↓
queue/event bus
      ↓
mark published
```

Booking và ý định publish event trở thành atomic trong cùng DB
transaction.

------------------------------------------------------------------------

## 16. Redis và Cache

Redis phù hợp:

- Availability cache.
- Rate limiting.
- Temporary coordination.
- Cached calendar/read model.
- Queue backend.

Redis **không phải lớp duy nhất bảo vệ double booking**.

``` text
GET availability
      ↓
Redis
      ↓
fast response
```

Nhưng mutation:

``` text
POST booking
      ↓
Action
      ↓
Database transaction
      ↓
Database invariant
```

Availability là snapshot; booking cuối cùng vẫn có thể trả
`409 Conflict`.

------------------------------------------------------------------------

## 17. Authorization

Policy phải cover:

- User xem booking của chính mình.
- User cancel booking của chính mình khi policy cho phép.
- Admin override.
- Staff chỉ thao tác resource thuộc scope.
- Không tin `user_id` từ client.
- Không để mass assignment thay đổi owner/status/payment state.

------------------------------------------------------------------------

## 18. Error Model

Domain exception rõ nghĩa:

``` text
BookingConflict
BookingExpired
ResourceUnavailable
InvalidBookingTransition
CancellationNotAllowed
PaymentAlreadyProcessed
```

Mapping HTTP có thể là:

  Domain condition          HTTP
  ----------------------- ------
  Validation                 422
  Unauthorized               403
  Not found                  404
  Booking conflict           409
  Invalid current state      409
  Rate limit                 429

Đừng biến mọi lỗi business thành `500`.

------------------------------------------------------------------------

## 19. Edge-case Checklist

### Time

- start == end.
- start \> end.
- Booking quá khứ.
- Boundary chính xác 10:00.
- Timezone khác nhau.
- DST nếu hệ thống global.
- Leap day.
- Client gửi timezone/offset sai.
- Booking qua ngày.
- Resource operating hours.

### Concurrency

- Hai user cùng booking một slot.
- Một user double click.
- 10/100 concurrent requests cùng slot.
- Cancel và confirm đồng thời.
- Expire và payment callback đồng thời.
- Admin override và user booking đồng thời.

### Payment

- Duplicate webhook.
- Out-of-order webhook.
- Invalid signature.
- Payment success sau expiration.
- Payment success nhưng DB unavailable.
- DB commit nhưng HTTP response timeout.
- Refund fail sau cancellation.

### Queue

- Job chạy hai lần.
- Worker chết giữa job.
- Queue unavailable.
- Email provider unavailable.
- Poison message.
- Retry sau nhiều giờ.

### Resource

- Resource disabled lúc checkout.
- Resource maintenance.
- Resource soft-deleted.
- Capacity thay đổi.
- Timezone resource thay đổi.

### Infrastructure

- Redis down.
- Payment gateway down.
- Database deadlock.
- DB connection timeout.
- Application instance crash.
- Scheduler chạy duplicate.

------------------------------------------------------------------------

## 20. Observability

Structured logs nên có:

``` text
booking_id
resource_id
user_id
request_id
correlation_id
payment_id
idempotency_key
old_status
new_status
```

### Metrics

- `booking_created_total`
- `booking_confirmed_total`
- `booking_conflict_total`
- `booking_expired_total`
- `booking_cancelled_total`
- `payment_failed_total`
- `booking_transaction_duration`
- `booking_deadlock_total`
- queue retry/failure count

### Audit

Ghi lại:

``` text
HELD -> PENDING_PAYMENT
PENDING_PAYMENT -> CONFIRMED
CONFIRMED -> CANCELLED
```

Cùng actor, reason và timestamp.

Mục tiêu: khi khách nói "đã thanh toán nhưng không có booking", team
phải reconstruct được lifecycle.

------------------------------------------------------------------------

## 21. Clean Code Rules

1. Controller mỏng.
2. FormRequest xử lý HTTP validation, không chứa toàn bộ domain.
3. Action thể hiện use case.
4. DTO thay array không type.
5. Enum thay magic string.
6. Value Object cho concept có invariant.
7. Domain exception thay generic exception.
8. Không gọi external API trong DB transaction.
9. Không update status tùy ý.
10. Không dùng float cho money; dùng integer minor unit/decimal strategy
    rõ ràng.
11. UTC ở persistence boundary.
12. Authorization cho mọi mutation.
13. Event listener không bí mật thay đổi core invariant.
14. Query tránh N+1.
15. Index phải đi theo query thực tế.
16. Method/class đặt tên theo business language.
17. Không abstraction nếu chưa có lý do.
18. Không Service/Repository chỉ để chuyển code sang file khác.

------------------------------------------------------------------------

## 22. Database & Index Review

Ít nhất xem xét index cho:

``` text
bookings(resource_id, start_at)
bookings(resource_id, end_at)
bookings(user_id, created_at)
bookings(status, expires_at)
payments(provider, provider_payment_id)
idempotency_keys(scope, key)
```

Index thực tế phải được xác nhận bằng query pattern và `EXPLAIN`, không
copy checklist máy móc.

Database schema nên có FK, unique constraint, check constraint nơi DB hỗ
trợ, timestamps và immutable historical identifiers phù hợp.

------------------------------------------------------------------------

## 23. Testing Strategy

### Unit

Test:

- `BookingPeriod`.
- State transition.
- Cancellation policy.
- Pricing strategy.
- Expiration decision.

### Feature

Test:

- API validation.
- Authorization.
- Create/cancel/confirm.
- HTTP error mapping.
- Idempotency.

### Integration

Dùng database thật cho:

- Constraint.
- Locking.
- Transactions.
- Deadlock behavior.
- PostgreSQL range/exclusion constraint.

Không dùng SQLite để chứng minh PostgreSQL concurrency correctness.

### Concurrency

Test thực sự bằng nhiều process/request:

``` text
20 requests
same resource
same period
        ↓
expected:
1 success
19 conflicts
```

### Failure tests

Chủ động mô phỏng:

- Redis unavailable.
- Queue failure.
- Provider timeout.
- Duplicate webhook.
- Worker retry.
- Exception trước/sau commit.

------------------------------------------------------------------------

## 24. API đề xuất

``` text
GET    /api/resources/{resource}/availability
POST   /api/bookings
GET    /api/bookings/{booking}
GET    /api/bookings
POST   /api/bookings/{booking}/cancel
POST   /api/bookings/{booking}/checkout
POST   /api/webhooks/payments/{provider}
```

Không nhất thiết expose endpoint kiểu:

``` text
PATCH /bookings/{id} status=confirmed
```

vì nó làm mất business semantics và dễ bypass transition rules.

------------------------------------------------------------------------

## 25. Roadmap triển khai

### V0.1 --- Domain Foundation

- Schema.
- BookingStatus.
- BookingPeriod.
- Resource.
- Booking.
- Basic Action/DTO.
- Core invariants.
- Unit/feature tests.

**Bài học:** Domain modeling và ubiquitous language.

### V0.2 --- Availability & Concurrency

- Availability query.
- Overlap rules.
- Transaction.
- Locking/database constraint.
- Concurrency tests.
- `409 Conflict`.

**Bài học:** Race condition và database correctness.

### V0.3 --- Hold & Expiration

- HELD.
- `expires_at`.
- Scheduler/job.
- ExpireBooking.
- Duplicate expiration safety.

**Bài học:** Temporal state và idempotent background processing.

### V0.4 --- Payment

- Payment model.
- Checkout.
- Webhook.
- Signature verification.
- Idempotency.
- State race handling.

**Bài học:** Distributed systems và eventual communication.

### V0.5 --- Events, Queue & Outbox

- Domain/application events.
- Notifications.
- Retry.
- Transactional Outbox.
- Failed job handling.

**Bài học:** At-least-once processing và reliable event publishing.

### V0.6 --- Cancellation & Refund

- Cancellation policies.
- Refund workflow.
- Failure recovery.
- Audit reason.

**Bài học:** Workflow orchestration và partial failure.

### V0.7 --- Cache & Performance

- Availability cache.
- Invalidation.
- Query optimization.
- EXPLAIN.
- Load testing.

**Bài học:** Correctness trước optimization.

### V0.8 --- Observability

- Structured logging.
- Metrics.
- Audit.
- Correlation ID.
- Operational dashboard.

**Bài học:** Production debugging.

### V0.9 --- Resilience

- Failure injection.
- Redis outage.
- Queue outage.
- Payment timeout.
- DB deadlock.
- Retry policies.

**Bài học:** Thiết kế cho failure thay vì happy path.

### V1.0 --- Production Hardening

- Security review.
- Authorization review.
- Query/index review.
- Concurrency review.
- Load test.
- Runbook.
- Architecture docs.
- Invariant docs.
- Deployment checklist.

------------------------------------------------------------------------

## 26. Definition of Done

Một feature booking chỉ được coi là hoàn thành khi:

- Business invariant được document.
- Happy path test pass.
- Authorization test pass.
- Conflict test pass.
- Concurrency behavior được kiểm chứng.
- Transaction boundary được review.
- DB constraint/index được review.
- Mutation quan trọng idempotent.
- Queue handler chịu duplicate execution.
- Failure path được định nghĩa.
- Logs/metrics đủ debug.
- API error semantics rõ ràng.
- Documentation cập nhật.
- Không còn core rule chỉ tồn tại trong Controller/UI.

------------------------------------------------------------------------

## 27. Các câu hỏi architecture cần tự trả lời

Trước mỗi milestone, tự trả lời:

1. Invariant nào đang được bảo vệ?
2. Layer nào chịu trách nhiệm bảo vệ nó?
3. Nếu có hai request đồng thời thì chuyện gì xảy ra?
4. Nếu process chết ngay dòng tiếp theo thì hệ thống ở trạng thái nào?
5. Nếu message/job chạy hai lần thì sao?
6. Nếu response timeout nhưng DB đã commit thì client retry thế nào?
7. Database có thể tự bảo vệ invariant này không?
8. Cache stale có phá correctness không?
9. External service unavailable thì recovery ra sao?
10. Tôi có reconstruct được sự cố production từ logs/audit không?
11. Test hiện tại đang chứng minh business correctness hay chỉ chứng
    minh code chạy?
12. Abstraction/pattern này đang giải quyết vấn đề gì?

------------------------------------------------------------------------

## 28. Những anti-pattern cần tránh

``` text
Fat Controller
God BookingService
Generic Repository không có giá trị
$status = request('status')
check-then-insert không concurrency control
Redis lock là protection duy nhất
external HTTP call trong transaction
queue job không idempotent
event listener chứa core business rule ẩn
float money
timezone xử lý rải rác
mọi exception -> HTTP 500
SQLite test được dùng để kết luận PostgreSQL locking đúng
cache được coi là source of truth
```

Đặc biệt tránh `BookingService` vài nghìn dòng chứa create, cancel,
payment, refund, email, availability và admin override. Tách theo use
case/action và policy.

------------------------------------------------------------------------

## 29. Năm bài học quý nhất

### 1. Application check không thay thế database invariant

`exists()` chỉ nói dữ liệu tại thời điểm query. Nó không đảm bảo request
khác không thay đổi dữ liệu ngay sau đó.

### 2. Transaction không tự động giải quyết race condition

Hai transaction vẫn có thể cùng đọc một state hợp lệ. Cần hiểu
isolation, lock và constraint.

### 3. Distributed systems luôn phải nghĩ đến retry

Client, queue và webhook đều có thể retry. Mutation quan trọng phải
idempotent.

### 4. Queue thực tế phải được thiết kế như at-least-once

Job có thể chạy lại. Handler phải an toàn khi duplicate execution.

### 5. Availability chỉ là snapshot

UI hiển thị "còn chỗ" không phải guarantee. Transaction cuối cùng mới
quyết định booking thành công.

------------------------------------------------------------------------

## 30. Mental Model cuối cùng

Khi nhận requirement mới, đừng bắt đầu bằng Controller hoặc migration.

``` text
Requirement
    ↓
Business Invariant
    ↓
Domain Model
    ↓
Use Case
    ↓
State Transition
    ↓
Transaction Boundary
    ↓
Concurrency Strategy
    ↓
Database Constraint
    ↓
Idempotency
    ↓
Failure Recovery
    ↓
Async / Queue
    ↓
Observability
    ↓
Production Testing
```

Đây là mental model quan trọng nhất mà project Booking nên giúp hình
thành.

------------------------------------------------------------------------

## 31. Stack khuyến nghị cho project học tập

``` text
Laravel
PostgreSQL
Redis
Laravel Horizon
Pest
Docker/Sail
React + Inertia (nếu cần frontend)
```

PostgreSQL đặc biệt đáng chọn để học range/exclusion constraints và
concurrency semantics.

Project vẫn nên giữ dạng Modular Monolith. Chỉ tách microservice khi có
organizational/runtime boundary thực sự, không tách chỉ để học
architecture.

------------------------------------------------------------------------

## 32. Mục tiêu học tập sau khi hoàn thành

Sau project này, developer nên có khả năng giải thích:

- Vì sao booking không phải CRUD.
- Vì sao `check then insert` bị race condition.
- Transaction isolation và row locking giải quyết gì.
- Constraint bảo vệ invariant như thế nào.
- Idempotency khác duplicate validation thế nào.
- Tại sao webhook phải chịu duplicate/out-of-order.
- Vì sao queue job phải idempotent.
- Transactional Outbox giải quyết failure window nào.
- Cache availability nên thiết kế ra sao.
- State machine giúp gì cho domain.
- Cách debug "đã thanh toán nhưng không có booking".
- Cách thiết kế concurrency test.
- Khi nào Action/DTO/VO/Strategy/Repository thực sự có giá trị.
- Cách biến business requirement thành invariant và production test.

Nếu có thể trả lời và chứng minh các câu trên bằng chính code/test của
project, project Booking đã hoàn thành mục tiêu quan trọng nhất: **nâng
tư duy từ implement feature sang thiết kế một hệ thống backend đúng,
chịu lỗi và có thể vận hành trong production.**
