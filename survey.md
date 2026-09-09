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
- [user/screenings/show.blade.php:49](/Users/tuquoctuan/Code/Tuantq/laravel-blade/resources/views/user/screenings/show.blade.php:49)

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
