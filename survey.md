Mình đã review toàn bộ luồng booking: chọn ghế → hold → combo → thanh toán → webhook → ticket/QR → check-in → expire/refund.

Kết quả kiểm tra:

- Full test: 49 passed, 1 skipped, 1 warning.
- PHPStan: còn 5 lỗi.
- Có một số lỗi nghiệp vụ nghiêm trọng chưa được test bao phủ.
- Chưa thay đổi code trong lần review này.

## Các lỗi nghiêm trọng

### 1. Webhook Stripe không hoàn tất ticket giống luồng thanh toán thường

Tại [StripeWebhookController.php:41](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/Webhooks/StripeWebhookController.php:41>), webhook chỉ:

- đổi payment sang succeeded;
- đổi booking sang confirmed;
- đổi ghế sang sold;
- tạo `qr_token_hash`.

Nhưng không:

- đổi `HOLD-*` thành `TKT-*`;
- đổi ticket status sang `issued`;
- lock booking item và screening seat;
- kiểm tra booking còn hạn hay đã expired.

Hậu quả:

- Stripe thanh toán thành công qua webhook có thể vẫn tạo vé mã `HOLD-*`;
- ticket có thể được hiển thị dù booking đã hết hạn;
- trạng thái giữa thanh toán đồng bộ và webhook không nhất quán.

Nên gom logic hoàn tất booking vào một action dùng chung, ví dụ `FinalizeSuccessfulPayment`, để cả `PayBooking` và Stripe webhook cùng sử dụng.

### 2. Race condition giữa thanh toán và expire hold

Tại [PayBooking.php:70](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Booking/PayBooking.php:70>), gọi payment gateway bên ngoài transaction.

Kịch bản lỗi:

1. Booking còn `pending_payment`.
2. Stripe charge thành công nhưng request xử lý chậm.
3. Scheduler chạy `booking:expire-holds`.
4. Booking bị chuyển sang `expired`, ghế được trả lại.
5. Payment response quay lại.
6. `PayBooking` vẫn bán ghế và phát hành ticket.

Hiện tại `applyResult()` vẫn xử lý item tại dòng 96 dù booking có thể đã `expired` hoặc `cancelled`.

Đây là lỗi có thể dẫn đến:

- booking expired nhưng payment succeeded;
- ghế bị bán lại cho user khác;
- ticket tồn tại nhưng booking không hợp lệ.

Cần kiểm tra trạng thái và `expires_at` ngay trước khi finalize. Nếu payment đã thành công sau khi hold hết hạn, cần có flow refund/compensation rõ ràng.

### 3. Combo bị mất tồn kho khi booking hết hạn hoặc bị hủy

`AddConcessions` giảm stock tại [AddConcessions.php:44-45](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Cinema/AddConcessions.php:44>).

Nhưng:

- [ExpireBooking.php](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Booking/ExpireBooking.php>) không hoàn lại stock;
- [CancelBooking.php](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Booking/CancelBooking.php>) cũng không hoàn lại stock.

Kết quả: user giữ combo rồi để booking hết hạn thì tồn kho bị giảm vĩnh viễn.

Nên tạo một action giải phóng booking resources, bao gồm:

- ghế;
- combo stock;
- booking items;
- trạng thái booking.

Dùng chung cho expire và cancel.

### 4. Có thể thêm combo sau khi payment đã tạo, gây sai số tiền thanh toán

`AddConcessions` cho phép cả `held` và `pending_payment` tại [AddConcessions.php:21](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Cinema/AddConcessions.php:21>).

Nhưng khi payment đã tồn tại:

- booking total được tăng;
- payment amount không được cập nhật.

Ví dụ:

- booking ban đầu: 100.000;
- payment record: 100.000;
- user thêm combo 50.000;
- booking total: 150.000;
- Stripe vẫn charge payment amount 100.000.

Nên chọn một trong hai hướng:

- không cho thêm combo sau khi payment đã ở `pending`;
- hoặc hủy/recreate payment intent và đồng bộ lại amount.

Hướng đầu tiên an toàn và đơn giản hơn.

### 5. Route combo hiện tại sẽ lỗi runtime vì thiếu view

Controller có route:

```php
return view('user.bookings.combos', ...);
```

ở [BookingController.php:61](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/User/BookingController.php:61>), nhưng file:

```text
resources/views/user/bookings/combos.blade.php
```

không tồn tại.

Truy cập `/user/bookings/{booking}/combos` sẽ lỗi `View [user.bookings.combos] not found`.

### 6. User cancel booking paid có thể gây fatal error

[BookingController.php:78](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/User/BookingController.php:78>) bắt `InvalidBookingTransition`, nhưng controller không import class:

```php
App\Support\Booking\Exceptions\InvalidBookingTransition
```

PHP sẽ tìm:

```php
App\Http\Controllers\User\InvalidBookingTransition
```

PHPStan đã phát hiện lỗi này. Khi user cố cancel booking đã thanh toán, exception có thể dẫn đến lỗi 500 thay vì thông báo validation hợp lệ.

## Lỗi nghiệp vụ và UX

### 7. Checkout hết hạn có thể trả về 500

[BookingController.php:36-42](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/User/BookingController.php:36>) chỉ kiểm tra status là `held` hoặc `pending_payment`, không kiểm tra `expires_at`.

Scheduler chưa chắc đã chạy đúng thời điểm, nên user có thể mở checkout sau khi hold hết hạn. Khi bấm thanh toán, `PayBooking` ném `RuntimeException` tại [PayBooking.php:41-42](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Booking/PayBooking.php:41>) nhưng controller không bắt exception.

Nên:

- kiểm tra expiry ngay khi mở checkout;
- hoặc catch domain exception và redirect với message “Booking đã hết hạn”.

### 8. `minimum_lead_minutes` đang không được áp dụng

Config có:

```php
minimum_lead_minutes
```

nhưng `HoldSeats` chỉ kiểm tra screening đã bắt đầu hay chưa.

Hiện tại user có thể giữ ghế sát giờ chiếu, thậm chí trước giờ chiếu vài phút, trái với config kỳ vọng.

Cần áp dụng:

```text
starts_at >= now + minimum_lead_minutes
```

### 9. Màn hình user screening cho phép truy cập suất chiếu quá khứ

`ScreeningController@show()` chỉ kiểm tra status `scheduled`, chưa kiểm tra `starts_at` trong tương lai.

Luồng hold sau đó vẫn chặn, nhưng UI vẫn hiển thị trang chọn ghế cho suất đã qua. Nên dùng cùng điều kiện với public screening:

- status scheduled;
- starts_at > now.

### 10. Payment pending đang bị hiển thị như payment failed

`PayBooking` có thể trả về `PaymentStatus::Pending`, nhưng controller xử lý mọi trạng thái khác succeeded thành:

```php
booking.messages.payment_failed
```

Trong Stripe, payment có thể pending vì:

- 3DS;
- requires action;
- webhook chưa về;
- payment intent chưa hoàn tất.

Nên phân biệt:

- `failed`: cho retry;
- `pending`: hiển thị “Đang chờ xác nhận thanh toán”;
- `succeeded`: qua success page.

## Stripe webhook cần cải thiện

### 11. Webhook payment chưa tìm được payment sẽ bị trả 204 và có nguy cơ mất event

Tại [StripeWebhookController.php:34-36](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/Webhooks/StripeWebhookController.php:34>), nếu chưa tìm thấy payment thì return khỏi transaction, nhưng endpoint vẫn trả `204`.

Nếu webhook đến trước khi `provider_payment_id` được lưu, Stripe có thể coi event đã xử lý thành công và không retry nữa.

Nên:

- trả lỗi 5xx khi payment chưa sẵn sàng để Stripe retry;
- hoặc resolve payment bằng metadata `payable_id`;
- chỉ đánh dấu webhook `processed_at` sau khi business operation hoàn tất.

### 12. Stripe signature parser chưa xử lý nhiều chữ ký `v1`

Stripe có thể gửi nhiều `v1` signature trong header khi rotate secret. Code hiện tại chỉ lấy chữ ký đầu tiên bằng regex.

Nên parse toàn bộ signatures và accept nếu có ít nhất một signature hợp lệ.

### 13. Webhook và synchronous payment có nguy cơ phát hành ticket hai lần

Hai luồng đều có thể xử lý thành công:

- request thanh toán trực tiếp;
- webhook `payment_intent.succeeded`.

Cần đảm bảo finalize payment idempotent tuyệt đối:

- lock payment;
- chỉ finalize khi trạng thái chưa succeeded;
- không tạo outbox event lặp;
- không đổi mã ticket nhiều lần.

## Outbox và email

### 14. Outbox publisher có thể dispatch trùng job

[OutboxPublish.php:20-23](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Console/Commands/OutboxPublish.php:20>) đọc các message chưa `published_at`, sau đó dispatch job.

Trong lúc job chưa hoàn thành, scheduler chạy lần tiếp theo có thể đọc lại cùng message và dispatch lần nữa.

Hậu quả:

- email booking có thể gửi trùng;
- email payment thành công có thể gửi trùng.

Nên thêm cơ chế claim message:

- `claimed_at` / `processing_at`;
- lock rows với `skip locked`;
- hoặc unique job theo `outboxMessageId`.

### 15. Outbox event không có cơ chế retry chủ động hợp lý

Job có `$tries = 3`, nhưng sau khi fail sẽ ghi `failed_at`. Không thấy flow retry thủ công hoặc command retry failed outbox.

Nên có:

- admin retry failed messages;
- backoff;
- cảnh báo khi có `failed_at`;
- metrics/log rõ ràng.

## Hiệu năng và database

### 16. Query user bookings eager load dư dữ liệu

[UserBookingsQuery.php:15](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Queries/Cinema/UserBookingsQuery.php:15>) load:

```php
items.screeningSeat.seat
```

Nhưng trang danh sách booking hiện tại không dùng danh sách ghế/item.

Điều này tạo thêm query và memory theo số booking. Nên bỏ relation này khỏi index, chỉ load ở booking detail.

### 17. Thiếu composite index cho active hold lookup

Hai controller thường query:

```php
user_id
screening_id
status
expires_at
```

Nhưng bảng `bookings` hiện chỉ có:

- `user_id, created_at`;
- `status, expires_at`.

Nên thêm index phù hợp, ví dụ:

```text
(user_id, screening_id, status, expires_at)
```

### 18. Báo cáo theo `created_at` có thể full scan

[BookingReport.php:14](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Queries/Cinema/BookingReport.php:14>) lọc `bookings.created_at`, nhưng migration chưa có index riêng cho `created_at`.

Khi booking lớn, report tháng sẽ chậm. Nên thêm index:

```text
(created_at)
```

hoặc composite phù hợp với các report thực tế.

### 19. Trang movie load toàn bộ ghế của tất cả suất chiếu

[PublicCinemaController.php:43](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Http/Controllers/Cinema/PublicCinemaController.php:43>) eager load toàn bộ `screeningSeats` cho tất cả screening của movie chỉ để tính available/total.

Khi movie có nhiều suất chiếu và phòng lớn, response sẽ phình lên đáng kể.

Nên chuyển sang aggregate:

- `withCount`;
- query count available theo screening;
- chỉ load chi tiết ghế ở trang chọn ghế.

### 20. Expire booking có N+1 query theo từng ghế

[ExpireBooking.php:31-43](</Users/tuquoctuan/Code/Tuantq/laravel-blade/app/Actions/Booking/ExpireBooking.php:31>) query từng `ScreeningSeat` cho từng item.

Hiện giới hạn 10 ghế/booking nên chưa nghiêm trọng, nhưng command expiry xử lý nhiều booking sẽ tạo nhiều query. Có thể tối ưu bằng:

- lock toàn bộ seat IDs theo `whereIn`;
- map theo ID;
- bulk update các seat cần release.

## Test coverage còn thiếu

Hiện test tốt ở các phần:

- guest hold;
- seat conflict;
- idempotency cơ bản;
- login resume hold;
- checkout success;
- expire hold;
- check-in;
- QR rendering;
- report refund.

Nhưng chưa có test cho các nhánh quan trọng sau:

1. Stripe webhook success.
2. Stripe webhook duplicate event.
3. Stripe webhook đến trước khi payment record hoàn chỉnh.
4. Payment pending.
5. Payment failed rồi retry.
6. Payment thành công nhưng hold đã expired.
7. Combo stock được hoàn lại khi expire.
8. Combo stock được hoàn lại khi cancel.
9. Thêm combo sau khi payment đã tạo.
10. Checkout sau khi hold hết hạn.
11. User cancel booking đã thanh toán qua HTTP route.
12. Route `/user/bookings/{booking}/combos`.
13. IDOR với checkout/success/ticket.
14. Không cho booking khi còn dưới `minimum_lead_minutes`.
15. Ticket verify của booking expired/cancelled/refunded.
16. Hai request payment đồng thời.
17. Hai webhook Stripe đồng thời.
18. Outbox dispatch không trùng.

## PHPStan hiện tại

PHPStan đang fail 5 lỗi:

- undefined property `$activeHold->items` trong public screening;
- undefined property `$activeHold->items` trong user screening;
- view type tại `BookingController`;
- thiếu class `InvalidBookingTransition` import;
- lỗi liên quan view combo không tồn tại.

Lỗi thiếu import và thiếu view là lỗi runtime thực tế, không chỉ là vấn đề static analysis.

## Thứ tự nên xử lý

Ưu tiên cao nhất:

1. Gom logic finalize payment dùng chung cho PayBooking và Stripe webhook.
2. Chặn race payment-success sau khi hold expired.
3. Hoàn stock combo khi cancel/expire.
4. Không cho sửa combo sau khi payment đã bắt đầu.
5. Sửa missing view và missing exception import.
6. Xử lý checkout hết hạn không để 500.
7. Bổ sung test cho Stripe, expiry race và combo.
8. Sửa outbox duplicate dispatch.
9. Thêm index và giảm eager loading dư.
10. Áp dụng `minimum_lead_minutes`.

Tổng thể, phần seat locking và idempotency hiện tại có nền tảng tốt, nhưng payment/webhook/expiry đang có rủi ro dữ liệu không nhất quán. Đây là phần cần ưu tiên trước các cải thiện UI hoặc tối ưu nhỏ.
