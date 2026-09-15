# 01 — Logic nghiệp vụ booking

Tài liệu này mô tả quy tắc phải đúng bất kể request đến từ Blade, JavaScript, queue, webhook hay command. Form Request và frontend chỉ là lớp phản hồi sớm; domain action và database mới là nơi quyết định cuối cùng.

## 1. Domain model

```text
Movie
 └─ Screening (suất chiếu)
     ├─ ScreeningSeat (ghế của riêng suất chiếu)
     └─ Booking
         ├─ BookingItem (mỗi item = một ghế/vé)
         ├─ BookingConcession (combo)
         ├─ CouponReservation
         └─ Payment -> PaymentAttempt / RefundAttempt
```

- `Seat` là ghế vật lý trong phòng.
- `ScreeningSeat` là bản ghế theo từng suất, là inventory cạnh tranh thực sự.
- `Booking` là aggregate của một user và một screening.
- `BookingItem` là một vé/ghế; không tạo booking không có item hợp lệ.
- Combo có thể finite stock hoặc unlimited stock.
- Payment là aggregate riêng nhưng liên kết polymorphic qua `payable_type/payable_id`.

Code: `app/Models/Booking/*`, `app/Models/Catalog/*`, `app/Models/Commerce/*`, `app/Models/Payment/*`; schema tại `database/migrations/2026_09_10_130001_create_movie_domain_schema.php` và `130007_create_payment_domain_schema.php`.

## 2. Điều kiện screening bookable

Screening chỉ nhận booking khi:

1. Movie active.
2. Screening ở trạng thái cho phép đặt.
3. Thời điểm bắt đầu còn sau minimum lead time.
4. Không vượt maximum horizon.
5. Screening/room còn tồn tại và đúng binding.

Code canonical: `app/Models/Catalog/Screening.php` các scope `bookable`, `startsAfter`, `withinBookingHorizon`; query trang tại `app/Queries/Catalog/ScreeningPageQuery.php:24-48`.

Không copy điều kiện này vào controller. Public route kiểm tra movie-screening relation tại `app/Http/Controllers/MovieController.php:103-112,136-141`.

## 3. Hold ghế

### Invariant

- Một screening seat chỉ thuộc tối đa một active hold hoặc một booking đã bán.
- Seat ID được normalize, unique và sort trước khi lock.
- User chỉ có active hold phù hợp cho screening.
- Hold có TTL từ `config('booking.limits.hold_minutes')`.
- Idempotency key lặp lại với cùng selection trả về booking hiện có; không nhân bản booking.
- Tối đa seat/request lấy từ `config('booking.limits.max_seats')`.

### Transaction order

`HoldSeats::execute()` (`app/Actions/Booking/Checkout/HoldSeats.php:24-151`):

1. Validate screening và request limit.
2. Mở transaction; retry deadlock giới hạn.
3. Lock user, screening và các `ScreeningSeat` theo `seat_id` tăng dần.
4. Release row đã hết hạn nếu cần.
5. Kiểm tra available/owned seat.
6. Tạo hoặc reuse booking hold.
7. Tạo booking items và reserve combo nếu có.
8. Ghi outbox event.
9. Commit.

Không gọi Stripe, email hoặc queue provider trong transaction hold.

## 4. Edit selection

`EditBookingSelection` (`app/Actions/Booking/Checkout/EditBookingSelection.php:36-119`) dùng replacement semantics:

- Submit lại cùng seat: giữ booking/hold hiện tại.
- Submit seat mới: khóa toàn bộ seat cũ và mới, chỉ thay đổi sau khi seat mới hợp lệ.
- Conflict seat mới: rollback hoàn toàn, hold cũ vẫn còn.
- Payload combo bị thiếu line: quantity line đó trở về 0 và stock được trả.
- Booking đã payment started hoặc expired: không cho edit.

Đây là lý do không được tự release seat trước khi validate seat mới.

## 5. Combo và inventory

`SyncBookingConcessions` (`app/Actions/Commerce/Concessions/SyncBookingConcessions.php:23-136`) nhận **final quantity**, không nhận delta.

- Quantity âm hoặc vượt line limit bị reject.
- Tổng combo không vượt `max_combos_per_ticket × số booking item`.
- Stock finite được lock và decrement atomically.
- Stock `NULL` là unlimited nhưng vẫn bị giới hạn bởi application max quantity.
- Mọi delta tạo `InventoryMovement` với idempotency key.
- Hủy/expire/refund dùng movement ngược chiều, không update stock trực tiếp thiếu audit.
- Giá/currency được snapshot vào booking concession line.

Availability payload:

- Public seat page: `MovieController@availability` → `ScreeningAvailabilityQuery`.
- Booking combo page: `BookingConcessionController@availability`.
- Query dùng `AvailableConcessionsQuery::availabilityForCurrency()` để tính `stock`, `selected`, `max`.

Frontend chỉ clamp/disable để UX; backend mới quyết định có reserve được hay không.

## 6. Coupon

`ApplyCoupon` (`app/Actions/Commerce/Coupons/ApplyCoupon.php:20-154`):

- Lock booking trước.
- Lock coupon và reservation liên quan.
- Kiểm tra active, thời hạn, currency, minimum subtotal, scope và usage limit.
- Chỉ một reservation `Reserved` hợp lệ cho booking/user.
- Apply lại cùng coupon phải idempotent.
- Đổi coupon phải release reservation cũ, sau đó reserve coupon mới.
- Recalculate subtotal/discount/total trong backend.
- Expire/cancel/payment finalize cập nhật reservation theo state machine.

Không tin `discount` hoặc `total` từ browser.

## 7. Payment và 3DS

`PayBooking` (`app/Actions/Booking/Checkout/PayBooking.php:37-255`) tách thành hai pha:

### Pha 1 — claim trong database

- Lock booking.
- Kiểm tra booking còn payable và hold chưa hết hạn.
- Sync combo nếu request có quantities.
- Không tạo PaymentIntent mới nếu payment cũ đang `pending`, `processing`, `requires_action`, `unknown` hoặc có provider ID cần reconcile.
- Tạo immutable `PaymentAttempt` và idempotency key.
- Commit claim trước khi gọi provider.

### Pha 2 — gọi provider ngoài transaction

- Stripe sử dụng attempt key làm `Idempotency-Key`.
- Kết quả được ghi trong transaction riêng.
- Timeout/network error chuyển payment về `unknown`/`processing` và dispatch reconciliation.
- `requires_action` chuyển tới Payment Element/3DS.
- `requires_payment_method` yêu cầu user nhập lại payment method.
- Chỉ `succeeded` mới gọi `FinalizeSuccessfulPayment`.

`FinalizeSuccessfulPayment` (`app/Actions/Booking/Payment/FinalizeSuccessfulPayment.php:34-225`) khóa payment, booking, items, seats, coupon và combo. Nếu payment thành công sau khi hold hết hạn, không issue ticket; chuyển `RequiresRefund`.

## 8. Refund

`RefundBooking` (`app/Actions/Booking/Payment/RefundBooking.php:29-143`) claim refund bằng lock và fixed refund idempotency key.

`FinalizeRefund` (`app/Actions/Booking/Payment/FinalizeRefund.php:25-91`) chỉ chạy khi provider/webhook/reconciliation xác nhận refund `succeeded`:

1. Payment → `refunded`.
2. Refund attempt → `succeeded`.
3. Ticket → `refunded`.
4. Release seat.
5. Restore combo stock bằng inventory movement idempotent.
6. Release coupon reservation.
7. Booking → `cancelled`.

`pending`, `requires_action`, `unknown` không được finalize.

## 9. Booking state và payment state

Booking state được điều khiển bởi `TransitionBooking`; payment state bởi `TransitionPayment` và `PaymentStatus`.

Các trạng thái cần đặc biệt lưu ý:

| Trạng thái | Ý nghĩa | Hành động |
|---|---|---|
| `held` | Ghế đang được giữ | Cho edit combo/ghế, cho pay trước expiry |
| `pending_payment` | Đã claim payment | Không tạo charge thứ hai |
| `confirmed` | Đã thanh toán và issue ticket | Không cancel trực tiếp; đi qua refund |
| `expired` | Hold đã hết hạn | Release seat/combo/coupon |
| `requires_action` | User cần hoàn tất 3DS | Giữ payment resolution, không issue ticket |
| `unknown` | Provider response không chắc chắn | Reconcile, không retry charge mù |
| `requires_refund` | Provider đã success nhưng local booking không còn finalize được | Refund provider, alert manual |
| `refunded` | Refund provider success và local finalize xong | Đã release tài nguyên |

## 10. Quy tắc không được phá vỡ

- Không xác nhận booking từ frontend redirect.
- Không issue ticket trước payment success đã được backend xác thực.
- Không gọi provider trong database transaction đang giữ lock.
- Không trừ/hoàn stock trực tiếp ngoài inventory movement.
- Không dùng `Seat` để quyết định availability; dùng `ScreeningSeat`.
- Không dùng `now()` khác timezone để quyết định expiry; dùng booking clock/application timezone.
- Không bỏ signature verification của Stripe webhook.
- Không coi HTTP 200 từ Stripe refund là `refunded` nếu object status chưa `succeeded`.

