Đã review chuyên sâu toàn bộ flow booking hiện tại. Kết luận: nền tảng hiện khá tốt về transaction, idempotency, lock seat, inventory và payment reconciliation, nhưng vẫn còn một số lỗi nghiệp vụ thực tế cần ưu tiên sửa.

Đã kiểm tra:

- Route public/user/admin.
- Hold seat, edit seat, combo inventory.
- Payment, webhook, refund, expiry.
- Guest → login → resume.
- Screening bookable window.
- Policy/security.
- UI seat picker, combo picker, checkout.
- Database indexes.
- Feature test, frontend test, PHPStan.

Kết quả kiểm thử hiện tại: 68 test pass, 1 skipped, 292 assertions. Tuy nhiên PHPStan đang fail 11 lỗi.

## Lỗi cần ưu tiên sửa

### P1 — Cấu hình combo chưa dùng một nguồn duy nhất

Hai request vẫn hard-code `max:20`:

- [AddConcessionsRequest.php:18](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Requests/User/AddConcessionsRequest.php:18)
- [PayBookingRequest.php:28](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Requests/User/PayBookingRequest.php:28)

Trong khi config đã có:

```php
config('booking.limits.max_combo_quantity')
```

Hệ quả: thay đổi `.env` không đồng bộ giữa frontend và backend. Ví dụ config cho phép 30 combo nhưng request vẫn chặn ở 20.

Nên thay toàn bộ bằng:

```php
'max:'.config('booking.limits.max_combo_quantity')
```

### P1 — API availability thiếu `held_until`

Tại [PublicCinemaController.php:136](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/Cinema/PublicCinemaController.php:136), query chỉ lấy:

```php
get(['seat_id', 'status'])
```

Nhưng `isAvailableForSelection()` cần `held_until`:

- [ScreeningSeat.php:40](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Models/Cinema/ScreeningSeat.php:40)

Hệ quả:

1. Hold của người khác đã hết hạn.
2. Scheduler chưa chạy.
3. API availability vẫn không có `held_until`.
4. Ghế bị đánh dấu unavailable dù thực tế đã có thể chọn.
5. Seat picker không tự enable ghế đó.

Cần lấy thêm:

```php
->get(['seat_id', 'status', 'held_until'])
```

Đây là lỗi ảnh hưởng trực tiếp đến trải nghiệm chọn ghế.

### P1 — Payment provider timeout có thể tạo booking bị kẹt

Trong [PayBooking.php:107-116](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Booking/PayBooking.php:107), nếu payment gateway ném exception:

- Payment vẫn ở trạng thái `processing`.
- Attempt chuyển thành `unknown`.
- Có thể không có `provider_payment_id`.
- Booking vẫn giữ seat.
- `payments:reconcile` chỉ xử lý payment có `provider_payment_id`.

Kết quả: booking có thể kẹt ở `pending_payment`, user không retry được vì PayBooking coi payment đang processing là không cần charge lại.

Cần có cơ chế:

- timeout payment không có `provider_payment_id`;
- đánh dấu `failed` hoặc `unknown_timeout`;
- cho phép retry an toàn;
- hoặc tạo reconciliation theo `payment_attempt`, không phụ thuộc hoàn toàn vào provider ID.

### P1 — Edit booking ở trạng thái `pending_payment` chưa nhất quán

Trong [ScreeningController.php:55](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/User/ScreeningController.php:55), chỉ xử lý đổi ghế khi booking ở trạng thái `held`:

```php
if ($activeHold?->getRawOriginal('status') === 'held' ...)
```

Nhưng `activeHold()` lại lấy cả:

```php
held, pending_payment
```

Hệ quả nếu booking đang `pending_payment`:

- User đổi ghế.
- Booking cũ không bị cancel.
- Request dùng lại idempotency key cũ.
- `HoldSeats` có thể báo `idempotency_key_reused`.

Nên quyết định rõ nghiệp vụ:

- Hoặc cấm edit khi payment đang processing và hiển thị trạng thái rõ ràng.
- Hoặc cho phép edit nhưng phải cancel payment intent cũ, release resources và tạo payment attempt mới.

Không nên để UI cho phép thao tác nhưng backend xử lý không nhất quán.

### P1 — Checkout/dashboard có thể hiển thị booking đã hết hạn

Dashboard lấy booking `pending_payment` chỉ dựa vào thời gian suất chiếu:

- [DashboardController.php:17-22](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/User/DashboardController.php:17)

Không kiểm tra:

```php
expires_at > now()
```

Hệ quả booking đã hết hạn nhưng scheduler chưa chạy vẫn xuất hiện như booking sắp tới.

Nên thêm điều kiện:

```php
->where(function ($query): void {
    $query
        ->where('status', BookingStatus::Confirmed->value)
        ->orWhere(function ($query): void {
            $query
                ->where('status', BookingStatus::PendingPayment->value)
                ->where('expires_at', '>', now()->utc());
        });
})
```

## Lỗi nghiệp vụ/rủi ro mức trung bình

### M2 — Public và user seat page đang có hai logic availability khác nhau

Public page dùng:

```php
$screeningSeat->isAvailableForSelection()
```

Nhưng user page dùng:

```php
$screeningSeat->status === Available
```

Tại:

- [PublicCinemaController.php:81](/Users/tuquoctuan/Code/Tuantq/laravel-blade/resources/views/cinema/screenings/show.blade.php:81)
- [cinema/screenings/show.blade.php:81](/Users/tuquoctuan/Code/Tuantq/laravel-blade/resources/views/cinema/screenings/show.blade.php:81)

Hệ quả khi hold hết hạn nhưng database chưa được scheduler release:

- Public có thể xem ghế available.
- User page lại xem unavailable.
- Hai UI cho cùng một suất chiếu hiển thị khác nhau.

Nên dùng chung một method:

```php
$available = $screeningSeat->isAvailableForSelection() || $isOwnHold;
```

### M2 — Public resume với screening bị xóa có thể gây 500

Tại [PublicCinemaController.php:148](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/Cinema/PublicCinemaController.php:148):

```php
$screening = Screening::query()->findOrFail(...)
```

Nếu suất chiếu bị xóa hoặc dữ liệu session cũ tồn tại lâu, user sẽ nhận lỗi 500 thay vì quay về danh sách phim.

Nên xử lý:

```php
$screening = Screening::query()->find(...);

if ($screening === null) {
    return to_route('cinema.movies.index')
        ->withErrors(['booking' => __('booking.messages.screening_unavailable')]);
}
```

### M2 — AddConcessions có thể nhận action trực tiếp ngoài FormRequest

Action đã kiểm tra quota tổng và stock khá tốt:

- [AddConcessions.php:44-57](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Cinema/AddConcessions.php:44)

Tuy nhiên giới hạn số lượng âm, số lượng quá lớn và dữ liệu sai chỉ được đảm bảo ở request layer. Nếu action được gọi từ command/job/controller khác, validation có thể bị bỏ qua.

Nên normalize và validate thêm ở action:

- quantity phải là integer;
- quantity >= 0;
- quantity không vượt `max_combo_quantity`;
- tổng combo không vượt `ticket_count × max_combos_per_ticket`.

### M2 — Combo availability chưa trả quota tổng theo số vé

Endpoint:

- [BookingController.php:99-125](/Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/User/BookingController.php:99)

Đang trả `max` theo từng concession và stock, nhưng không trả:

```php
ticket_count * max_combos_per_ticket
```

Frontend phải tự tính quota tổng theo thứ tự DOM. Điều này dễ gây lệch giữa:

- combo availability API;
- seat picker;
- AddConcessions;
- PayBooking.

API nên trả thêm:

```json
{
  "ticket_count": 2,
  "max_total_combos": 6,
  "selected_total_combos": 3,
  "remaining_total_combos": 3
}
```

### M2 — Có hai public booking flow

Hiện tồn tại đồng thời:

```text
/movies/{movie}/showtimes/{screening}
/user/screenings/{screening}
```

Điều này làm tăng khả năng:

- UI khác nhau giữa hai flow;
- logic active hold khác nhau;
- bug chỉ xảy ra trên một route;
- khó tracking analytics;
- khó duy trì tài liệu.

Nên chọn public nested route làm canonical:

```text
/movies/{movie-slug}/showtimes/{id}
```

Route `/user/screenings/{screening}` nên redirect về public URL hoặc loại bỏ sau khi migrate.

## Payment và refund

### Điểm tốt

- Có idempotency key.
- Có unique payment theo booking.
- Có payment attempts.
- Có webhook event deduplication.
- Có xử lý payment thành công sau khi hold hết hạn.
- Có trạng thái `requires_refund`.
- Có transaction và row lock ở các điểm quan trọng.
- Có release combo stock khi expire/cancel/refund.

### Rủi ro cần bổ sung

1. Webhook đang tự parse Stripe signature. Nên cân nhắc dùng Stripe SDK chính thức khi tích hợp thật để giảm rủi ro sai format header.
2. Webhook event có thể bị đánh dấu failed vĩnh viễn khi mismatch. Cần có admin/reconciliation workflow cho trường hợp dữ liệu provider được sửa hoặc local snapshot sai.
3. Khi payment failed/canceled, booking vẫn giữ ghế đến khi hết hold. Đây có thể là chủ ý, nhưng UI cần cho user biết:
   - có thể retry;
   - hold còn bao lâu;
   - payment đã fail hay đang pending.
4. Refund provider timeout chuyển attempt thành `unknown`, nhưng cần job retry/reconciliation riêng cho refund unknown.

## Kiến trúc

Cấu trúc hiện tại tương đối tốt:

```text
Controller
  → FormRequest
  → Action
  → Transaction
  → Model / PaymentGateway
```

Các Action quan trọng đã được tách đúng:

- `HoldSeats`
- `AddConcessions`
- `ExpireBooking`
- `ReleaseBookingResources`
- `PayBooking`
- `FinalizeSuccessfulPayment`
- `RefundBooking`

Điểm cần cải thiện:

- Controller vẫn có nhiều logic điều phối edit booking.
- Availability calculation bị lặp ở Blade/controller/model.
- Booking state và payment state chưa được đóng gói thành domain service/state machine rõ ràng.
- `BookingStatus` đang có transition tốt nhưng chưa có invariant đầy đủ, ví dụ:
  - `PendingPayment` phải luôn có payment processing;
  - `Confirmed` phải có payment succeeded;
  - booking expired/cancelled không được còn seat held;
  - booking confirmed phải có ticket issued/checked-in.

Nên có một lớp kiểm tra invariant hoặc domain service:

```php
BookingInvariant::assertPayable($booking);
BookingInvariant::assertFinalizable($booking);
BookingInvariant::assertResourcesConsistent($booking);
```

## Hiệu năng và database

### Đang làm tốt

- Có index cho screening seat theo screening/status/held_until.
- Có index lookup active hold.
- Có index payment processing.
- Có eager loading tương đối đầy đủ.
- User booking có pagination.
- Admin booking có pagination.
- Concession listing có limit.
- Có `withoutOverlapping()` và `onOneServer()` cho scheduler.

### Cần tối ưu thêm

1. `PublicCinemaController::movie()` load toàn bộ screening bookable của movie. Nếu movie có nhiều suất chiếu, nên giới hạn theo ngày hoặc paginate.
2. `with(['screenings' => ... limit(3)])` cần kiểm tra kỹ behavior trên dữ liệu nhiều movie và database production.
3. `BookingReport` dùng nhiều query tổng hợp độc lập; khi dữ liệu lớn nên dùng aggregate query hoặc reporting table.
4. `ReleaseBookingResources` load toàn bộ items/concessions trong transaction. Với giới hạn 10 ghế hiện tại không nghiêm trọng, nhưng nên vẫn có invariant giới hạn.
5. Có index tốt nhưng nên kiểm tra bằng `EXPLAIN` trên production-like dataset, đặc biệt:
   - `screenings(movie_id, starts_at)`
   - `screenings(status, starts_at)`
   - `bookings(user_id, screening_id, status, expires_at)`
   - `screening_seats(screening_id, status, held_until)`

## UI/UX

### Điểm tốt

- Seat picker có giới hạn ghế frontend.
- Combo có quota theo số vé.
- Có countdown hold.
- Có modal xác nhận.
- Có tổng tiền ghế/combo/total.
- Có trạng thái seat owned hold.
- Có polling availability.
- Có thông báo expired booking riêng.
- Checkout đã compact hơn và có hierarchy giá rõ ràng.
- Có icon SVG cho action chính.
- Có hỗ trợ responsive.

### Cần cải thiện

1. Khi seat availability refresh thất bại, UI chỉ hiển thị cảnh báo nhưng vẫn cho submit. Nên cân nhắc disable submit sau nhiều lần refresh thất bại hoặc yêu cầu refresh lại.
2. Quota combo đang clamp theo thứ tự DOM. User có thể thấy combo sau bị giảm dù đang thao tác combo đó, gây khó hiểu.
3. Nên hiển thị rõ:
   - `Đã chọn 4/10 ghế`
   - `Combo 5/12`
   - `Còn được chọn 7 combo`
4. Checkout đang có promo UI nhưng disabled và ghi “coming soon”. Nếu chưa hỗ trợ nghiệp vụ coupon, nên ẩn khỏi production hoặc hiển thị rõ “Tính năng sắp ra mắt”.
5. Khi payment `processing`, user cần trạng thái rõ ràng thay vì chỉ redirect sang payment-action.
6. Trạng thái `held`, `pending_payment`, `expired`, `requires_refund` nên có thông báo riêng, không dùng chung validation error.

## Test coverage còn thiếu

Hiện test tốt ở feature-level nhưng còn thiếu các nhóm sau:

### Race condition

- Hai user cùng hold một ghế.
- Một user submit hold đúng lúc scheduler release.
- Payment success đến đồng thời với `ExpireBooking`.
- Refund đồng thời với check-in.
- Hai webhook cùng event.
- Hai request add combo cùng lúc.

### Browser/UI

Chưa có browser test thực sự cho:

- Guest chọn ghế → login → resume.
- Resume giữ đúng URL.
- Seat cũ active.
- Continue enabled ngay lập tức.
- Edit giữ ghế cũ.
- Edit đổi ghế.
- Combo 3 combo/vé.
- Combo vượt quota.
- Countdown hết hạn.
- Seat availability polling.
- Mobile modal/checkout layout.
- Keyboard focus trap trong modal.

### Payment recovery

- Gateway timeout không có provider ID.
- Webhook đến sau timeout.
- Payment success sau expiry.
- Refund timeout.
- Retry refund unknown.
- Payment failed rồi retry với combo mới.

## PHPStan

PHPStan hiện fail 11 lỗi, nổi bật ở:

- AddConcessions model type inference.
- PublicCinema builder không nhận diện `bookable()`.
- Undefined dynamic properties.
- ScreeningBookingContextQuery return type.
- Stripe metadata type.
- Ticket/payment relationship typing.

Đây là dấu hiệu type safety chưa hoàn chỉnh. Nên xử lý trước khi mở rộng thêm nghiệp vụ, đặc biệt các lỗi liên quan đến payment và model relationship.

## Đánh giá tổng quan

| Hạng mục | Đánh giá |
|---|---:|
| Seat locking/concurrency | Tốt |
| Idempotency | Tốt |
| Combo inventory | Khá tốt |
| Payment reliability | Khá, còn lỗi timeout |
| Refund safety | Khá |
| Booking state machine | Khá |
| Route/domain boundary | Khá, còn duplicate flow |
| UI/UX | Khá tốt |
| Frontend/backend config consistency | Chưa đạt |
| Test coverage | Tốt ở feature, thiếu browser/race |
| Static analysis | Chưa đạt |
| Production readiness | Cần xử lý P1 trước |

## Thứ tự xử lý đề xuất

1. Sửa `held_until` trong availability query.
2. Đồng bộ toàn bộ hard-code `max:20` về config.
3. Xử lý payment timeout không có provider ID.
4. Quyết định rõ edit behavior với `pending_payment`.
5. Đồng bộ availability logic public/user.
6. Bổ sung `expires_at` vào dashboard pending booking.
7. Xử lý resume khi screening không còn tồn tại.
8. Bổ sung browser test cho guest resume/edit/payment.
9. Bổ sung concurrency tests.
10. Sửa toàn bộ 11 lỗi PHPStan.
11. Chuẩn hóa một booking route canonical.
12. Chạy `EXPLAIN` trên dữ liệu lớn và đo slow query thực tế.

Tôi không thay đổi mã nguồn trong lượt review này. Worktree hiện vẫn giữ nguyên các thay đổi trước đó của project.

-----
10/09
Đã review toàn bộ luồng movie booking hiện tại. Tổng thể kiến trúc đã tiến bộ tốt: module Movie được gom rõ theo `Actions`, `Models`, `Queries`, có hold seat, combo, coupon, payment attempt, webhook, outbox, notification và ticket QR. Tuy nhiên chưa nên đưa production trước khi xử lý nhóm lỗi payment, timezone và consistency dưới đây.

## Kết quả kiểm tra

- `MovieBookingFeatureTest`: 38 tests passed, 183 assertions.
- PHPStan: 0 errors.
- Frontend tests: 3 passed.
- ESLint: passed.
- `git diff --check`: passed.
- Full suite: 84 passed, 2 errors, 1 skipped.

Hai lỗi full suite hiện tại liên quan trực tiếp timezone:

- Check-in bị báo “not open”.
- Reminder booking bị lỗi “screening outside booking window”.

Điều này cho thấy test và runtime hiện vẫn phụ thuộc timezone môi trường.

## P0 — cần xử lý trước production

### 1. Có thể tạo payment thành công giả không có provider ID

Trong [`PayBooking.php:37`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Movie/Booking/PayBooking.php:37>), nếu booking đã `Confirmed`, hệ thống dùng `firstOrCreate()` và tự tạo payment với trạng thái `Succeeded`:

```php
'status' => PaymentStatus::Succeeded,
'paid_at' => now(),
```

Payment này không có:

- `provider_payment_id`
- payment attempt
- xác nhận từ Stripe/provider

Nếu dữ liệu booking bị lệch hoặc payment bị mất, người dùng có thể gọi lại endpoint và hệ thống tạo một payment thành công giả.

Khuyến nghị:

- Không tự tạo payment `Succeeded`.
- Nếu booking đã confirmed, chỉ trả payment đã tồn tại.
- Nếu payment không tồn tại, ghi integrity error và yêu cầu reconcile/manual repair.
- Mọi payment thành công bắt buộc phải có provider ID hợp lệ.

### 2. Payment provider thành công nhưng local transaction lỗi thì không recover được

Luồng hiện tại:

1. Gọi provider ở [`PayBooking.php:119`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Movie/Booking/PayBooking.php:119>).
2. Lưu `PaymentAttempt` ở dòng 139.
3. Cập nhật payment chính ở dòng 140.
4. Finalize booking.

Nếu bước 2 thành công nhưng bước 3 lỗi DB/process crash:

- `PaymentAttempt` có provider ID.
- Payment chính vẫn `processing`, provider ID có thể null.
- `RecoverStuckPayment` chỉ nhìn payment chính ở [`RecoverStuckPayment.php:22`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Movie/Booking/RecoverStuckPayment.php:22>).
- Reconcile chỉ tìm payment có provider ID ở [`ReconcilePayment.php:28`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Jobs/ReconcilePayment.php:28>).

Kết quả là payment thật trên Stripe có thể bị đánh dấu `Unknown` nhưng không còn đường tự động tìm lại provider ID.

Khuyến nghị:

- Recover phải kiểm tra `PaymentAttempt.provider_payment_id`.
- Reconcile được phép bắt đầu từ attempt, không chỉ payment cha.
- Tách rõ các bước `claim → charge → persist provider result → finalize`.
- Có command reconcile payment attempt bị orphan.

### 3. Refund `Unknown` bị khóa vĩnh viễn

Trong [`RefundBooking.php:44`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Movie/Booking/RefundBooking.php:44>), nếu refund attempt đang `Processing` hoặc `Unknown`, hệ thống return ngay:

```php
return ['payment' => $payment, 'attempt' => null, ...];
```

Nếu Stripe đã refund thành công nhưng response bị mất:

- Local attempt thành `Unknown`.
- Payment chưa thành `Refunded`.
- Lần retry tiếp theo không gọi provider, không reconcile, không cho retry.

Đây là lỗi nghiệp vụ nghiêm trọng vì tiền có thể đã refund nhưng booking và inventory chưa được đồng bộ.

Khuyến nghị:

- Có `RefundStatusRetriever`.
- Reconcile bằng `provider_refund_id` hoặc provider payment ID.
- Cho phép retry có kiểm soát.
- Tách trạng thái `Unknown` thành `NeedsReconciliation`, không coi là kết thúc.

### 4. Outbox email chưa đảm bảo idempotent tuyệt đối

[`PublishOutboxMessage.php:97-124`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Jobs/PublishOutboxMessage.php:97>) đang:

1. Đánh dấu delivery là `Sending`.
2. Gửi email.
3. Đánh dấu `Sent`.

Nếu SMTP nhận email thành công nhưng process chết trước bước 3, lease sẽ hết hạn và job gửi lại. `ShouldBeUnique` không giải quyết được crash window này.

Hiện tại hệ thống chỉ đảm bảo at-least-once, có khả năng gửi trùng.

Khuyến nghị:

- Dùng provider hỗ trợ idempotency key.
- Key nên dựa trên `outbox_delivery_id`.
- Hoặc xây dựng bảng delivery có trạng thái và cơ chế provider acknowledgement rõ ràng.
- Không nên gửi SMTP trực tiếp trong job outbox nếu cần độ tin cậy cao.

## P1 — cần xử lý ngay sau P0

### 5. Reconcile chưa validate đầy đủ provider payment

Webhook có kiểm tra amount/currency, nhưng [`ReconcilePayment.php:38-59`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Jobs/ReconcilePayment.php:38>) chỉ lấy provider status rồi cập nhật local.

Chưa kiểm tra lại:

- Provider amount.
- Currency.
- Metadata booking.
- Provider ID có đúng payment hiện tại không.

Nếu provider ID bị gán sai hoặc dữ liệu local bị corrupt, reconcile có thể finalize booking nhầm.

### 6. Lock order không đồng nhất, có nguy cơ deadlock

`RefundBooking` lock payment trước booking:

```text
Payment → Booking → Items → Seats → Concession
```

Trong khi payment flow thường lock booking trước payment:

```text
Booking → Payment
```

Ví dụ:

- Worker A đang pay: lock booking, chờ payment.
- Worker B đang refund: lock payment, chờ booking.

Database transaction có retry 3 lần nhưng đây chỉ giảm lỗi, không giải quyết nguyên nhân.

Khuyến nghị chuẩn hóa lock order toàn hệ thống, ví dụ:

```text
Booking → Payment → BookingItems → ScreeningSeats → Concessions
```

### 7. Có thể thanh toán sau khi suất chiếu đã bắt đầu

`checkout()` chỉ kiểm tra `booking.expires_at` tại [`BookingController.php:50`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/User/BookingController.php:50>).

`PayBooking` cũng chỉ kiểm tra thời gian hold tại [`PayBooking.php:51`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Movie/Booking/PayBooking.php:51>).

`FinalizeSuccessfulPayment` kiểm tra hold expiry nhưng chưa kiểm tra `screening.starts_at` tại [`FinalizeSuccessfulPayment.php:119`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Movie/Booking/FinalizeSuccessfulPayment.php:119>).

Nếu hold 10 phút nhưng suất chiếu sắp bắt đầu, payment có thể được hoàn tất sau giờ chiếu.

Cần centralize:

```text
Screening::isBookable()
Screening::canAcceptPayment()
Screening::canFinalizePayment()
```

và sử dụng thống nhất ở checkout, pay, edit, webhook, reconcile và finalization.

### 8. Logic timezone đang không nhất quán

Screening được convert về UTC khi tạo ở [`CreateScreening.php:23`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Movie/Catalog/CreateScreening.php:23>), nhưng nhiều nơi lại parse raw DB timestamp bằng `config('app.timezone')`:

- [`Screening.php:68`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Models/Movie/Screening.php:68>)
- [`ScreeningSeat.php:57`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Models/Movie/ScreeningSeat.php:57>)
- [`CheckInTicket.php:40`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Movie/Ticketing/CheckInTicket.php:40>)
- [`SendBookingReminders.php:43`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Console/Commands/SendBookingReminders.php:43>)
- [`BookingPolicy.php:64`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Policies/Movie/BookingPolicy.php:64>)

Đây là nguyên nhân phù hợp với hai lỗi full test hiện tại.

Khuyến nghị:

- Lưu DB bằng UTC.
- So sánh bằng Carbon object/instant, không parse raw string nhiều lần.
- Chỉ convert sang timezone phòng chiếu khi hiển thị hoặc nhận input.
- Set `APP_TIMEZONE` rõ ràng trong `phpunit.xml`.
- Không ép MySQL timezone bằng named timezone nếu database chưa cài timezone tables.

### 9. `Screening::bookable()` chưa bao phủ movie/room active

[`Screening.php:47-53`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Models/Movie/Screening.php:47>) chỉ kiểm tra:

- Status screening.
- Khoảng thời gian.

Nhưng chưa kiểm tra:

- Movie có `is_active`.
- Screening room có `is_active`.

Ngoài ra [`Movie.php:37-43`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Models/Movie/Movie.php:37>) đang lặp lại một phần business logic bookable riêng.

Điều này có thể dẫn đến public list và endpoint chi tiết trả kết quả khác nhau.

### 10. Coupon percentage chưa giới hạn tối đa 100

[`SaveCouponRequest.php:38`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Requests/Admin/SaveCouponRequest.php:38>) chỉ validate `min:1`.

Trong khi `ApplyCoupon` âm thầm giới hạn:

```php
min(100, $couponValue)
```

Admin có thể tạo coupon 500%, hệ thống không báo lỗi mà silently biến thành 100%. Đây là lỗi cấu hình nghiệp vụ.

Nên validate conditional:

- Percentage: `1..100`.
- Fixed amount: lớn hơn 0.
- Coupon currency phải phù hợp booking currency.

### 11. Guest resume chưa hoàn toàn atomic

[`PublicMovieController.php:175`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/Movie/PublicMovieController.php:175>) dùng `session()->pull()` trước khi execute booking.

Nếu xảy ra exception ngoài hai loại đã bắt, dữ liệu guest selection bị mất khỏi session.

Nên:

- Đọc session trước.
- Chỉ `forget()` sau khi resume thành công.
- Hoặc bảo đảm mọi exception recoverable đều restore lại payload.

### 12. Payment polling coi `unknown` là terminal

[`payment-status.js:4`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/resources/js/modules/payment-status.js:4>) coi `unknown` là terminal:

```js
new Set(['failed', 'refunded', 'requires_refund', 'canceled', 'unknown'])
```

Trong backend, `unknown` lại là trạng thái cần reconcile. UI dừng polling quá sớm có thể khiến payment sau đó reconcile thành công nhưng người dùng không nhận thấy.

Nên tách:

- `terminal`: failed, refunded, canceled.
- `reconciling`: unknown.
- Có nút refresh/retry/manual status.

## Review frontend và UI/UX

### Điểm tốt

- Modal đã compact hơn, có nhóm ghế cùng giá.
- Có chi tiết ghế, combo, tiền ghế, tiền combo, tổng tiền.
- Có icon SVG cho action.
- Có focus trap, Escape, backdrop click và restore focus.
- Ghế ownership lấy từ server `owned_by_current_booking`, không chỉ tin DOM cũ.
- Giới hạn 10 ghế và combo theo số vé đã được chặn sớm bằng JavaScript.
- Notification bell có unread badge, delete all, click outside và animation.

### Các vấn đề còn lại

1. Modal dynamic chưa đủ accessibility:

[`seat-picker.js:204-208`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/resources/js/modules/seat-picker.js:204>) có `role="dialog"` nhưng chưa có:

- `aria-labelledby`.
- `aria-describedby`.
- ID liên kết tới title/description.

2. Payment error có thể gây JS exception nếu markup thiếu:

[`payment-status.js:43`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/resources/js/modules/payment-status.js:43>):

```js
error.textContent = message;
```

`error` có thể là `null`.

3. Chưa có browser E2E cho các luồng quan trọng:

- Guest chọn ghế → login → resume.
- Edit giữ nguyên ghế.
- Edit đổi ghế.
- Multiple tabs.
- Payment requires action.
- Payment timeout.
- Notification bell.
- Responsive modal ở 320px/375px.
- Combo limit theo số vé.

4. Notification hiện là polling 15 giây, chưa phải realtime thực sự. Nếu cần realtime đúng nghĩa nên dùng broadcast/WebSocket hoặc SSE.

5. Composer quality chưa chạy frontend test:

[`composer.json`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/composer.json>) chạy lint/build nhưng chưa chạy `npm run test:frontend`. Có thể bổ sung vào CI/quality pipeline.

6. Layout movie đã rõ hơn nhưng vẫn có route legacy:

[`routes/user.php:14-17`](</Users/tuquoctuan/Code/Tuantq/laravel-blade/routes/user.php:14>) vẫn tồn tại `/user/screenings/...` song song với canonical:

```text
/movies/{movie}/showtimes/{screening}
```

Nên giữ redirect tạm thời, đánh dấu deprecated rồi loại bỏ để tránh hai flow khác nhau.

## Đánh giá kiến trúc

| Khu vực | Đánh giá |
|---|---|
| Module organization | Tốt, đã gom theo Movie |
| Eloquent/model scope | Khá tốt nhưng còn logic trùng bookable |
| Seat hold | Tốt, có transaction và ownership |
| Combo/inventory | Tốt, có snapshot và movement |
| Coupon | Đạt cơ bản, thiếu domain validation percentage |
| Payment | Chưa production-safe |
| Webhook | Có signature/idempotency nhưng transition cần state machine rõ hơn |
| Refund | Chưa có recovery hoàn chỉnh |
| Outbox | Có nền tảng tốt nhưng chưa exactly-once |
| Notification | Đủ chức năng, chưa realtime |
| UI/UX | Khá tốt, cần cải thiện accessibility và E2E |
| Test | Feature coverage tốt, thiếu browser/concurrency/payment-failure tests |
| Timezone | Rủi ro cao, hiện đang gây lỗi full suite |
| Performance | Chưa có benchmark/EXPLAIN thực tế trong lần review này |

## Thứ tự ưu tiên đề xuất

1. Chuẩn hóa timezone và làm full test deterministic.
2. Sửa payment provider ID và loại bỏ fake confirmed payment.
3. Thiết kế recovery cho payment attempt orphan.
4. Thiết kế refund reconciliation/retry.
5. Validate provider amount/currency/metadata trong reconcile.
6. Chuẩn hóa lock order.
7. Chặn payment/finalize sau giờ chiếu.
8. Bổ sung browser E2E và concurrency tests.
9. Bổ sung accessibility cho modal/payment UI.
10. Đưa frontend test vào quality pipeline.
11. Sau đó mới benchmark query, availability polling và notification realtime.

Kết luận: phần booking hiện tại có nền tảng tốt và test nghiệp vụ khá rộng, nhưng các vấn đề payment recovery, refund recovery, timezone và lock order vẫn là rủi ro production thực sự. Full suite hiện chưa xanh hoàn toàn, vì vậy chưa nên xem hệ thống là hoàn thiện cho production.
