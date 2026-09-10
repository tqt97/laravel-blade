# Database — Movie Booking

Tài liệu này mô tả database hiện tại của project: bảng, cột, khóa, index, quan hệ và mục đích sử dụng. Đây là tài liệu đối chiếu schema; nghiệp vụ chi tiết nằm trong `movie-booking-business-logic.md`.

## 1. Quy ước chung

- Tên bảng dùng `snake_case`, khóa chính thường là `id`.
- Khóa ngoại dùng `foreignId` và được ràng buộc bằng foreign key nếu quan hệ là bắt buộc.
- Tiền lưu bằng số nguyên trong đơn vị nhỏ nhất của currency, ví dụ VND lưu trực tiếp theo đồng.
- `currency` lưu mã ISO 4217 dạng chữ hoa, thường là `VND` hoặc `USD`.
- Thời gian nghiệp vụ dùng timezone tại `config('app.timezone')`.
- Các cột `timestamps()` gồm `created_at`, `updated_at`.
- Các trạng thái lưu dạng string và được ứng dụng map qua PHP enum.
- JSON dùng cho payload/provider metadata/snapshot, không dùng cho dữ liệu cần query thường xuyên.

## 2. Sơ đồ quan hệ tổng quát

```text
users
 ├── bookings ── screenings ── movies
 │       │             └────── screening_rooms ── seats
 │       │                           └──── screening_seats
 │       ├── booking_items ── screening_seats
 │       ├── booking_concessions ── concessions
 │       ├── payments ── payment_attempts
 │       │             └── refund_attempts
 │       ├── coupon_reservations ── coupons
 │       ├── booking_transition_audits
 │       └── outbox_messages ── outbox_deliveries
 ├── notifications
 ├── passkeys
 ├── user_management_audits
 ├── concession_inventory_movements / concession_stock_adjustment_audits (Inventory)

 payment_webhook_events (độc lập theo provider event)
 cache, cache_locks, jobs, job_batches, failed_jobs, sessions, password_reset_tokens
 (hạ tầng Laravel)
```

`payments` dùng quan hệ polymorphic `payable_type/payable_id`, hiện booking là payment owner chính. Vì polymorphic relation không tạo foreign key trực tiếp đến `bookings`, code phải kiểm tra owner và authorization.

## 3. Nhóm người dùng và authentication

### 3.1. `users`

Lưu tài khoản người dùng và admin.

| Cột | Kiểu/ý nghĩa |
|---|---|
| `id` | Khóa chính. |
| `name` | Tên hiển thị. |
| `email` | Email đăng nhập, unique. |
| `email_verified_at` | Thời điểm xác thực email, nullable. |
| `password` | Mật khẩu đã hash. |
| `remember_token` | Token duy trì đăng nhập. |
| `is_admin` | Cờ quyền quản trị, thêm bởi migration riêng. |
| `two_factor_secret` | Secret 2FA đã mã hóa, nullable. |
| `two_factor_recovery_codes` | Recovery codes 2FA, nullable. |
| `two_factor_confirmed_at` | Thời điểm xác nhận 2FA, nullable. |
| `deleted_at` | Soft delete tài khoản. |
| `created_at`, `updated_at` | Audit thời gian. |

Quan hệ: một user có nhiều booking, notification, passkey, audit record và có thể là actor trong các audit/inventory movement.

### 3.2. `passkeys`

Lưu credential WebAuthn/passkey của user.

- `user_id`: foreign key đến `users`, cascade khi user bị xóa.
- `name`: tên passkey do user đặt.
- `credential_id`: credential duy nhất.
- `credential`: JSON credential WebAuthn.
- `last_used_at`: lần sử dụng gần nhất.

### 3.3. `password_reset_tokens`

Token reset mật khẩu theo email. `email` là khóa chính; `created_at` dùng kiểm tra thời hạn token.

### 3.4. `sessions`

Lưu session khi dùng database session driver.

- `id`: khóa chính.
- `user_id`: nullable, có index; guest session không có user.
- `payload`: dữ liệu session.
- `last_activity`: Unix timestamp dùng dọn session cũ.

## 4. Catalog phim và suất chiếu

### 4.1. `movies`

Danh mục phim được công khai.

| Cột | Ý nghĩa |
|---|---|
| `id` | Khóa chính. |
| `title` | Tên phim. |
| `slug` | URL thân thiện, unique. |
| `synopsis` | Mô tả phim, nullable. |
| `duration_minutes` | Thời lượng phim. |
| `rating` | Phân loại độ tuổi/nội dung, nullable. |
| `poster_path` | Đường dẫn poster, nullable. |
| `release_date` | Ngày phát hành, nullable. |
| `is_active` | Có được hiển thị/đặt vé hay không. |
| `deleted_at` | Soft delete. |

Một movie có nhiều screening. Movie inactive không được xem là showtime bookable.

### 4.2. `screening_rooms`

Phòng chiếu và cấu hình ghế.

| Cột | Ý nghĩa |
|---|---|
| `id` | Khóa chính. |
| `name` | Tên phòng. |
| `code` | Mã phòng, unique. |
| `timezone` | Dữ liệu legacy/metadata; nghiệp vụ booking hiện theo `config('app.timezone')`. |
| `is_active` | Phòng có thể nhận showtime hay không. |

Một room có nhiều seat và screening. Không xóa room nếu còn screening tham chiếu do `restrictOnDelete`.

### 4.3. `seats`

Ghế vật lý thuộc một phòng chiếu.

- `screening_room_id`: foreign key đến `screening_rooms`.
- `row_label`, `seat_number`: định danh vị trí; unique trong một room.
- `seat_type`: regular, VIP hoặc loại được enum hỗ trợ.
- `price_minor_units`: giá mặc định của ghế.
- `is_active`: ghế có được dùng cho showtime mới hay không.

Seat là template vật lý. Giá/trạng thái tại một suất chiếu nằm ở `screening_seats`.

### 4.4. `screenings`

Một suất chiếu cụ thể của một movie tại một room.

| Cột | Ý nghĩa |
|---|---|
| `id` | Khóa chính. |
| `movie_id` | Foreign key đến `movies`, restrict delete. |
| `screening_room_id` | Foreign key đến `screening_rooms`, restrict delete. |
| `starts_at`, `ends_at` | Thời gian bắt đầu/kết thúc theo app timezone. |
| `status` | scheduled hoặc trạng thái catalog tương ứng. |
| `base_price_minor_units` | Giá cơ sở. |
| `currency` | Currency của suất chiếu. |

Index chính:

- `(screening_room_id, starts_at, ends_at)` để kiểm tra overlap.
- `(movie_id, starts_at)` để lấy lịch phim.
- `(status, starts_at)` để lọc showtime bookable.

Một screening có nhiều screening seat, booking và screening price.

### 4.5. `screening_prices`

Bảng giá theo loại ghế trong một screening.

- `screening_id`: foreign key đến `screenings`.
- `seat_type`: loại ghế.
- `price_minor_units`, `currency`: giá snapshot tại screening.
- Unique `(screening_id, seat_type)`.

Lý do tách bảng: cùng một room/seat type có thể có giá khác nhau theo suất chiếu.

### 4.6. `screening_seats`

Bản ghi tài nguyên ghế trong từng screening; đây là bảng trung tâm chống bán trùng ghế.

| Cột | Ý nghĩa |
|---|---|
| `screening_id` | Foreign key đến screening. |
| `seat_id` | Foreign key đến seat. |
| `status` | `available`, `held`, `sold`. |
| `hold_token` | Token của lần hold. |
| `held_by_booking_id` | Booking đang sở hữu hold, nullable. |
| `held_until` | Thời điểm hold hết hạn. |
| `price_minor_units`, `currency` | Giá snapshot của ghế tại screening. |
| `sold_at` | Thời điểm ghế được bán. |

Ràng buộc và index:

- Unique `(screening_id, seat_id)` bảo đảm mỗi ghế chỉ có một row trong một screening.
- Index `(screening_id, status, held_until)` cho availability/expiry.
- Index `(held_by_booking_id, status)` cho ownership/release.
- Index `(hold_token, status)` cho thao tác release.

`held_by_booking_id` là ownership trực tiếp và phải được dùng thay vì tin dữ liệu DOM hoặc chỉ tin `hold_token` cũ.

## 5. Booking và ticket

### 5.1. `bookings`

Đơn đặt vé và aggregate chính của booking.

| Cột | Ý nghĩa |
|---|---|
| `id` | Khóa chính. |
| `user_id` | User sở hữu; nullable để hỗ trợ dữ liệu guest/legacy. |
| `coupon_id` | Coupon đã gắn, nullable. |
| `coupon_code` | Snapshot mã coupon. |
| `screening_id` | Screening được đặt. |
| `status` | `held`, `pending_payment`, `confirmed`, `cancelled`, `expired`, `completed`, `no_show`. |
| `expires_at` | Hạn của hold, nullable sau khi confirmed. |
| `reminder_sent_at` | Đánh dấu đã gửi reminder 2 giờ trước. |
| `amount_minor_units` | Amount payment snapshot ban đầu. |
| `subtotal_minor_units` | Tổng trước discount. |
| `discount_minor_units` | Số tiền giảm. |
| `total_minor_units` | Tổng cuối cùng cần thanh toán. |
| `currency`, `pricing_currency` | Currency của booking/pricing snapshot. |
| `idempotency_key` | Chống tạo booking trùng trong request retry. |
| `idempotency_hash` | Hash nội dung request tương ứng với key. |
| `cancellation_reason` | Lý do hủy. |

Unique/index:

- Unique `(user_id, idempotency_key)`.
- Index `(status, expires_at)` cho expire.
- Index `(user_id, screening_id, status, expires_at, id)` cho active hold.
- Index `(screening_id, status, created_at)` cho báo cáo/truy vấn screening.
- Index `(status, reminder_sent_at)` cho reminder.

Một booking có nhiều item/combo, tối đa một payment polymorphic, nhiều audit, coupon reservation và outbox event.

### 5.2. `booking_items`

Một vé/ghế thuộc booking.

- `booking_id`: foreign key đến bookings.
- `screening_seat_id`: ghế của screening.
- `ticket_code`: mã vé unique.
- `price_minor_units`, `currency`: giá snapshot.
- `status`: issued, checked_in, refunded hoặc cancelled.
- `checked_in_at`, `checked_in_by`: dữ liệu check-in.

Unique `(booking_id, screening_seat_id)` ngăn cùng một ghế được thêm hai lần vào một booking. `screening_seat_id` restrict delete để không xóa tài nguyên lịch sử vé.

### 5.3. `booking_transition_audits`

Audit mọi chuyển trạng thái booking.

- `booking_id`: booking bị thay đổi.
- `actor_id`: user/admin thực hiện, nullable cho scheduler/webhook.
- `from_status`, `to_status`: trạng thái trước/sau.
- `reason`: lý do.
- `created_at`: thời điểm transition.

Dùng để trace tranh chấp, payment race, expire và thao tác admin.

## 6. Movie domain — combo catalog

Combo (`concessions`) là sản phẩm thuộc Movie domain. Tồn kho và lịch sử biến động được tách thành Inventory domain để sau này mở rộng nhập kho, điều chỉnh, reservation, reconciliation và báo cáo mà không làm phình Movie domain.

### 6.1. `concessions`

Catalog combo bán kèm vé.

- `name`, `sku`: thông tin sản phẩm; SKU unique.
- `price_minor_units`, `currency`: giá hiện tại.
- `stock`: tồn kho; nullable nghĩa là không giới hạn.
- `is_active`: combo có thể bán hay không.

### 6.2. `booking_concessions`

Combo được snapshot trong một booking.

- `booking_id`, `concession_id`: quan hệ booking và combo.
- `quantity`: số lượng.
- `unit_price_minor_units`, `total_minor_units`, `currency`: giá tại lúc chọn.
- Unique `(booking_id, concession_id)`.

Lý do snapshot là giá/tồn kho catalog có thể thay đổi sau khi booking được tạo.

## 7. Inventory domain — stock ledger

Inventory chỉ sở hữu biến động tồn kho và audit điều chỉnh. `booking_concessions` vẫn thuộc Movie vì đó là snapshot sản phẩm trong order; Inventory chỉ ghi nhận delta stock phát sinh từ snapshot đó.

### 7.1. `concession_inventory_movements`

Ledger thay đổi tồn kho combo.

| Cột | Ý nghĩa |
|---|---|
| `concession_id` | Combo bị thay đổi. |
| `booking_id` | Booking liên quan, nullable với adjustment độc lập. |
| `actor_id` | User/admin thực hiện, nullable với tự động release. |
| `type` | hold, release, sale, adjustment hoặc enum tương ứng. |
| `quantity_delta` | Delta tồn kho, âm là trừ, dương là hoàn. |
| `stock_before`, `stock_after` | Snapshot trước/sau. |
| `reference` | Mã tham chiếu. |
| `idempotency_key` | Unique key chống double restore/double deduction. |
| `metadata` | Thông tin bổ sung. |

### 7.2. `concession_stock_adjustment_audits`

Audit điều chỉnh kho thủ công.

- Bắt buộc có `concession_id` và `actor_id`.
- Lưu delta, stock trước/sau và `reason`.
- Không thay thế inventory movement; đây là audit lý do của thao tác admin.

## 8. Movie domain — coupon

### 8.1. `coupons`

Catalog coupon.

- `code`: mã unique.
- `type`: percentage hoặc fixed.
- `value`: giá trị giảm.
- `maximum_discount_minor_units`: trần giảm, nullable.
- `currency`: currency áp dụng, nullable nếu coupon không giới hạn currency.
- `usage_limit`, `used_count`: giới hạn và số đã sử dụng.
- `starts_at`, `ends_at`: thời gian hiệu lực.
- `is_active`: bật/tắt coupon.

Index `(is_active, starts_at, ends_at)` phục vụ validate coupon đang hoạt động.

### 8.2. `coupon_reservations`

Reservation usage của coupon trong booking.

- `coupon_id`, `booking_id`: quan hệ.
- `status`: reserved, consumed hoặc released.
- Unique `(coupon_id, booking_id)` ngăn một booking reserve nhiều lần.
- Index `(coupon_id, status)` cho kiểm tra usage/concurrency.

Reservation được release khi hold expired/cancelled và được giữ lại khi booking confirmed theo quy tắc sản phẩm.

## 9. Payment domain

### 9.1. `payments`

Payment aggregate gắn với booking.

| Cột | Ý nghĩa |
|---|---|
| `payable_type`, `payable_id` | Polymorphic owner; thường là Booking. |
| `provider` | stripe hoặc provider được cấu hình. |
| `provider_payment_id` | ID provider, nullable khi chưa có; unique khi có. |
| `status` | pending, processing, requires_action, succeeded, failed, unknown, requires_refund, refunding, refunded. |
| `attempts` | Số lần claim/charge. |
| `processing_started_at`, `last_attempt_at` | Theo dõi payment stuck. |
| `reconciliation_attempted_at`, `reconciliation_attempts` | Backoff và audit việc tìm lại provider intent sau timeout. |
| `amount_minor_units`, `currency` | Amount/currency cần đối chiếu provider. |
| `metadata` | Provider/client metadata. |
| `paid_at`, `refunded_at` | Thời điểm terminal tương ứng. |
| `failure_message` | Lỗi hoặc lý do cần recovery. |

Unique `(payable_type, payable_id)` đảm bảo một payable có tối đa một payment aggregate. Index `(provider, status)` và `(status, processing_started_at)` phục vụ reconcile.

### 9.2. `payment_attempts`

Mỗi lần thử charge độc lập.

- `payment_id`: foreign key đến payments.
- `attempt_key`: idempotency key unique gửi provider.
- `status`: trạng thái của attempt.
- `provider_payment_id`: ID provider nếu đã nhận được.
- `amount_minor_units`, `currency`: snapshot dùng validate.
- `metadata`, `failure_message`: dữ liệu xử lý.
- `started_at`, `completed_at`: lifecycle.

PaymentAttempt tồn tại để recover trường hợp provider đã nhận request nhưng process local chết trước khi cập nhật payment.

### 9.3. `refund_attempts`

Mỗi lần thử refund.

- `payment_id`: payment cần refund.
- `attempt_key`: logical idempotency key unique.
- `status`: processing, succeeded, failed, unknown.
- `provider_refund_id`: ID refund provider.
- `metadata`, `failure_message`, `started_at`, `completed_at`.

Refund unknown không được xem là thành công; cần retry/reconciliation với cùng logical attempt key.

### 9.4. `payment_webhook_events`

Lưu webhook provider để chống xử lý lặp.

- `(provider, event_id)` unique.
- `payload`: JSON payload gốc để audit/replay.
- `processed_at`: xử lý thành công.
- `failed_at`, `failure_message`: lỗi cần retry/review.

Bảng này không thay thế `payments`; nó là inbox/audit của event bên ngoài.

## 10. Infrastructure domain — outbox và notification

### 10.1. `outbox_messages`

Transactional outbox cho side effect sau transaction booking/payment.

| Cột | Ý nghĩa |
|---|---|
| `aggregate_type`, `aggregate_id` | Aggregate phát event. |
| `event_type` | booking.created, booking.payment_succeeded, booking.reminder_due, booking.expired... |
| `payload` | JSON payload và locale. |
| `available_at` | Thời điểm được publish. |
| `claimed_at` | Lease của worker đang xử lý. |
| `published_at` | Đã hoàn tất publish. |
| `attempts` | Số lần retry. |
| `failed_at`, `last_error` | Lỗi cuối cùng. |

Index claim/publish gồm `(published_at, failed_at, claimed_at, available_at)`; index aggregate gồm `(aggregate_type, aggregate_id)`.

### 10.2. `outbox_deliveries`

Theo dõi từng side-effect channel của một outbox message.

- `outbox_message_id`: foreign key.
- `channel`: booking-expired, booking-reminder, payment-success hoặc channel tương ứng.
- `status`: pending, sending, sent, failed.
- `claimed_at`, `sent_at`, `last_error`.
- Unique `(outbox_message_id, channel)` chống gửi cùng channel hai lần.

### 10.3. `notifications`

Laravel database notifications hiển thị trên chuông.

- `id`: UUID khóa chính.
- `type`: notification class.
- `notifiable_type`, `notifiable_id`: polymorphic owner, hiện là user.
- `data`: JSON/text chứa key idempotent, title, message, event và URL.
- `read_at`: null là chưa đọc.
- `created_at`, `updated_at`: thời gian notification.

Không có foreign key trực tiếp đến user do polymorphic structure; endpoint phải scope theo authenticated user.

## 10. Bảng hạ tầng Laravel

### 10.1. `cache`, `cache_locks`

Database cache và distributed lock. `expiration` là Unix timestamp; không thuộc nghiệp vụ booking nhưng có thể được dùng cho rate limit/lock.

### 10.2. `jobs`

Queue chưa xử lý:

- `queue`, `payload`, `attempts`.
- `reserved_at`, `available_at`, `created_at` là Unix timestamp.

Queue worker dùng bảng này để xử lý outbox, reconcile và notification side effect.

### 10.3. `job_batches`

Theo dõi Laravel batch: tổng job, pending, failed, cancelled, created/finished time.

### 10.4. `failed_jobs`

Lưu payload và exception của job thất bại để retry/điều tra.

### 10.5. `user_management_audits`

Audit thao tác admin trên user.

- `actor_id`: admin thực hiện, nullable.
- `target_user_id`: user bị tác động, nullable.
- `action`, `target_snapshot`, `metadata`.
- Index `(action, created_at)` và `(target_user_id, created_at)`.

## 11. Quy tắc xóa dữ liệu

| Quan hệ | Chính sách |
|---|---|
| User → booking | User nullable/nullOnDelete để giữ lịch sử booking. |
| User → passkey | Cascade. |
| Movie → screening | Restrict, không xóa movie còn suất chiếu. |
| Room → seat/screening | Seat cascade theo room; screening restrict. |
| Screening → screening seat | Cascade. |
| Booking → item/combo/audit/coupon reservation | Cascade. |
| Screening seat → booking item | Restrict để bảo toàn lịch sử vé. |
| Concession → booking/inventory/audit | Restrict. |
| Payment → attempts/refunds | Cascade. |
| Outbox → delivery | Cascade. |
| Coupon → booking/coupon reservation | Coupon nullable trong booking; reservation cascade theo coupon/booking. |

Không dùng hard delete cho dữ liệu cần audit, payment, ticket hoặc inventory nếu chưa có chính sách lưu trữ rõ ràng.

## 12. Checklist khi thay đổi database

1. Thay đổi này thuộc domain nào: Movie catalog/booking/ticket/coupon, Inventory, Payment hay Infrastructure?
2. Có cần foreign key, unique constraint hoặc index mới không?
3. Dữ liệu có cần snapshot để giữ lịch sử giá/trạng thái không?
4. Có ảnh hưởng transaction/lock order/concurrency không?
5. Có cần enum hoặc translation tương ứng không?
6. Có cần backfill dữ liệu cũ không?
7. Có ảnh hưởng timezone của datetime không?
8. Có cần cập nhật model relationship, scope, query và authorization không?
9. Có test migration/schema, feature và concurrency không?
10. Cập nhật file này cùng tài liệu business logic và architecture.

## 13. Nguồn schema

Schema này được tổng hợp trong bốn migration theo domain: `2026_09_10_130001_create_movie_domain_schema.php`, `2026_09_10_130002_create_inventory_domain_schema.php`, `2026_09_10_130003_create_payment_domain_schema.php` và `2026_09_10_130004_create_infrastructure_domain_schema.php`. Bốn migration này thay thế các migration movie booking tạo bảng, thêm field và thêm index rời rạc trước đó.

- `0001_01_01` và `2026_09_03`–`2026_09_04`: migration nền Laravel, authentication và user management.
- `2026_09_10_130001`: catalog, booking, seating, combo catalog, coupon và booking audit.
- `2026_09_10_130002`: inventory ledger và stock adjustment audits.
- `2026_09_10_130003`: payment, payment attempts, refunds và webhook events.
- `2026_09_10_130004`: outbox, delivery và notification.

Khi migration mới thay đổi bảng, phải cập nhật bảng tương ứng, quan hệ, index và phần lịch sử thay đổi trong tài liệu này.

## 14. Lịch sử cập nhật

| Ngày | Nội dung |
|---|---|
| 2026-09-10 | Tạo tài liệu database cho toàn bộ movie booking và các bảng hạ tầng liên quan. |
| 2026-09-10 | Tách và phân loại rõ schema theo domain Movie, Inventory, Payments và Infrastructure. |
