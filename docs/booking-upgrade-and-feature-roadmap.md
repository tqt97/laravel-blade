# Kế hoạch nâng cấp hệ thống Movie Booking và học nghiệp vụ thực tế

## 1. Mục tiêu

Tài liệu này là roadmap triển khai tiếp theo cho project Laravel Movie Booking. Mục tiêu không chỉ là thêm tính năng mà là dùng từng tính năng để học nghiệp vụ thực tế, transaction, concurrency, state machine, inventory ledger, payment, moderation, reporting và vận hành production.

## 2. Nguyên tắc

1. Viết business rule trước khi code.
2. Database là lớp bảo vệ cuối cho invariant cạnh tranh.
3. Frontend chỉ hỗ trợ UX; backend quyết định cuối cùng.
4. Tiền dùng integer minor units, không dùng float.
5. Queue là at-least-once; job quan trọng phải idempotent.
6. Không gọi provider bên trong transaction đang giữ lock.
7. Mỗi behavior change phải có test và tài liệu.
8. Không tạo Service/Repository chung chỉ để bọc Action.
9. Chưa cần microservice khi modular monolith vẫn phù hợp.
10. Sau mỗi phase phải có demo và checklist.

## 3. Nền tảng hiện tại

Đã có:

- Movie, cinema, room, seat, screening.
- Screening seat inventory.
- Hold ghế và expiry.
- Combo/concession, stock finite/unlimited.
- Coupon percentage/fixed.
- Payment, PaymentAttempt, RefundAttempt.
- Stripe PaymentIntent, 3DS, webhook, reconcile.
- Ticket QR, check-in.
- Outbox, queue, notification.
- Enum, DTO, Action, Query, Policy.
- Feature test booking/payment và MySQL concurrency profile.

Cần hoàn thiện trước feature lớn:

- Chạy concurrency bằng MySQL/PostgreSQL thật.
- Hoàn thiện invariant booking và screening seat.
- Refactor nested transaction trong edit selection.
- Chuẩn hóa coupon lock order.
- Release toàn bộ coupon reservation khi cancel/expire.
- Dashboard payment unknown/orphan/refund.
- Test Stripe test-mode webhook thực tế.

## 4. Roadmap

~~~mermaid
flowchart TD
    A[Hardening booking] --> B[Combo inventory nâng cao]
    B --> C[Rating phim]
    C --> D[Comment và moderation]
    D --> E[Reschedule và partial refund]
    E --> F[Operations và analytics]
~~~

| Phase | Nội dung | Kỹ năng học |
| --- | --- | --- |
| 0 | Hardening booking | Correctness, invariant, concurrency |
| 1 | Fake inventory combo | Stock ledger, reservation, adjustment |
| 2 | Rating phim | Aggregate, permission, summary |
| 3 | Comment | UGC, moderation, report, notification |
| 4 | Reschedule/refund | Compensation, payment allocation |
| 5 | Operations | Recovery, audit, dashboard |
| 6 | Analytics | Read model, metrics, reporting |

# Phase 0 — Hardening nền tảng

## 0.1. Database invariant

Kiểm tra và bổ sung:

- unique screening_id + seat_id.
- unique provider + event_id.
- unique provider_payment_id.
- unique inventory idempotency key.
- Một booking không có nhiều coupon reservation Reserved.
- Stock finite không được âm.
- ends_at lớn hơn starts_at.
- Amount và quantity không âm.
- Booking item thuộc đúng screening của booking.
- Currency hợp lệ.

## 0.2. Lock order

Chuẩn hóa thứ tự:

~~~text
Booking
  → Payment
  → PaymentAttempt
  → BookingItem
  → ScreeningSeat
  → Concession
  → Coupon
  → InventoryMovement
~~~

Không để workflow này lock Payment trước Booking trong khi workflow khác lock Booking trước Payment.

## 0.3. Refactor transaction

Không để workflow gọi public Action tự mở transaction:

~~~text
EditBookingSelection
  → một transaction duy nhất
  → lock booking/screening/seats
  → release old resources
  → hold new resources
  → sync combos
  → commit
~~~

Tách các hàm nội bộ:

~~~text
CancelBooking::execute()
  → transaction
    → cancelLocked()

HoldSeats::execute()
  → transaction
    → holdLocked()
~~~

## 0.4. Test bắt buộc

- Hold cùng ghế ở hai process.
- Hold ghế cuối và expire đồng thời.
- Pay và expire đồng thời.
- Pay và webhook đồng thời.
- Refund và check-in đồng thời.
- Hai user mua combo cuối.
- Hai user apply coupon cuối.
- Duplicate webhook đồng thời.
- Retry queue sau commit.

# Phase 1 — Fake inventory cho combo

## 1.1. Mục tiêu nghiệp vụ

Xây kho giả lập để học:

- Nhập kho.
- Xuất kho.
- Giữ hàng tạm thời.
- Hoàn hàng.
- Điều chỉnh tồn.
- Kiểm kê.
- Hết hàng.
- Lô hàng/hạn sử dụng.
- FIFO/FEFO.
- Audit người thao tác.
- Đối soát số dư.

## 1.2. Tách các khái niệm tồn kho

~~~text
on_hand   = số lượng vật lý
reserved  = số lượng đang giữ cho booking
available = on_hand - reserved - damaged - expired
damaged   = số lượng hỏng
expired   = số lượng hết hạn
sold      = số lượng đã bán
~~~

Không nên dùng một cột stock duy nhất cho mọi nghiệp vụ khi bắt đầu mở rộng.

## 1.3. Database đề xuất

### concessions

~~~text
id, name, sku, unit, currency
selling_price_minor_units
is_active, track_inventory
minimum_stock_alert
~~~

### inventory_locations

~~~text
id, cinema_id, name, code
type: warehouse/counter/kiosk
is_active
~~~

### inventory_batches

~~~text
id, concession_id, location_id
batch_number
quantity_received, quantity_remaining
unit_cost_minor_units
manufactured_at, expires_at
status: active/expired/closed
~~~

### inventory_movements

Ledger bất biến:

~~~text
id, concession_id, location_id, batch_id, booking_id
type: receipt/reserve/release/sale/refund
      adjustment_in/adjustment_out
      transfer_in/transfer_out/damage/expire
quantity_delta
quantity_before, quantity_after
unit_cost_minor_units
reference_type, reference_id
idempotency_key
actor_id, reason, created_at
~~~

Không sửa hoặc xóa movement cũ. Nếu sai thì tạo movement ngược.

### inventory_reservations

~~~text
id, booking_id, concession_id, location_id
quantity
status: reserved/consumed/released/expired
expires_at, idempotency_key
~~~

### inventory_adjustment_audits

~~~text
id, concession_id, location_id
system_quantity, counted_quantity, difference
reason, actor_id, approved_by
status: pending/approved/rejected
~~~

## 1.4. Folder structure

~~~text
app/
├── Actions/Inventory/
│   ├── ReceiveStock.php
│   ├── ReserveStock.php
│   ├── ReleaseReservedStock.php
│   ├── ConsumeReservedStock.php
│   ├── AdjustStock.php
│   ├── TransferStock.php
│   ├── MarkStockDamaged.php
│   └── ExpireStock.php
├── DTO/Inventory/
├── Enums/Inventory/
├── Models/Inventory/
├── Policies/Inventory/
├── Queries/Inventory/
└── Jobs/Inventory/
~~~

## 1.5. Flow nhập kho

~~~mermaid
flowchart TD
    A[Admin tạo phiếu nhập] --> B[Validate SKU batch quantity]
    B --> C[Lock concession và location]
    C --> D[Tạo inventory batch]
    D --> E[Ghi receipt movement]
    E --> F[Commit và audit]
~~~

Invariant:

- Quantity nhập lớn hơn 0.
- SKU tồn tại.
- Cost và currency hợp lệ.
- Batch không trùng trong cùng SKU/location.
- Batch và movement commit cùng transaction.
- Không gửi email trong transaction.

## 1.6. Flow reserve combo

~~~text
Booking Held
  → Lock booking
  → Lock concession
  → Lock reservation hiện tại
  → Tính quantity delta
  → Kiểm tra available
  → Ghi reserve movement
  → Tạo/update reservation
  → Commit
~~~

Invariant:

- Available không âm.
- Cùng booking/concession/location chỉ có một reservation active.
- Retry không tạo movement trùng.
- Giảm quantity tạo release movement.
- Expiry/cancel tạo release.
- Payment success chuyển reservation thành consumed.
- Payment failure không làm mất stock.

## 1.7. Code mẫu reserve tối giản

~~~php
DB::transaction(function () use ($booking, $concession, $quantity): void {
    $concession = Concession::query()
        ->whereKey($concession->id)
        ->lockForUpdate()
        ->firstOrFail();

    if ($concession->stock !== null && $concession->stock < $quantity) {
        throw new BookingOperationFailed('Not enough stock.');
    }

    if ($concession->stock !== null) {
        $concession->decrement('stock', $quantity);
    }

    InventoryReservation::query()->create([
        'booking_id' => $booking->id,
        'concession_id' => $concession->id,
        'quantity' => $quantity,
        'status' => InventoryReservationStatus::Reserved,
    ]);

    InventoryMovement::query()->create([
        'concession_id' => $concession->id,
        'booking_id' => $booking->id,
        'type' => InventoryMovementType::Reserve,
        'quantity_delta' => -$quantity,
        'idempotency_key' => 'reserve:'.$booking->id.':'.$concession->id,
    ]);
});
~~~

Không dùng code trên nếu flow hiện tại đã trừ stock theo cách khác; phải chọn một source of truth để tránh trừ hai lần.

## 1.8. FIFO/FEFO

- FIFO: xuất lô nhập trước.
- FEFO: xuất lô sắp hết hạn trước.
- Lô hết hạn không được bán.
- Nếu lô đầu thiếu, tiếp tục lấy lô kế tiếp.
- Mỗi batch allocation cần được ghi nhận để refund/reconcile chính xác.

## 1.9. UI admin

- Inventory overview.
- Tồn theo rạp/quầy.
- Tồn theo batch.
- Nhập kho.
- Điều chỉnh.
- Điều chuyển.
- Ledger.
- Low-stock alert.
- Expiring-soon alert.
- Approval adjustment.
- Inventory discrepancy report.

## 1.10. Test inventory

- Reserve last stock.
- Reserve vượt stock.
- Release một lần.
- Release retry.
- Consume một lần.
- Refund không restore hai lần.
- Adjustment concurrent.
- Transfer concurrent.
- Expired batch không xuất được.
- FIFO/FEFO chọn đúng batch.
- Ledger balance khớp snapshot.

# Phase 2 — Rating phim

## 2.1. Mục tiêu

Cho user đánh giá phim từ 1 đến 5 sao. Có thể chia:

1. User đăng nhập được rating.
2. Chỉ user có booking confirmed được rating.
3. Chỉ user đã check-in mới có verified rating.

Nên triển khai cấp 2 trước, sau đó cấp 3.

## 2.2. Database

### movie_ratings

~~~text
id, movie_id, user_id, booking_id nullable
score
title nullable, body nullable
status: published/hidden/pending
is_verified
created_at, updated_at
~~~

Index/unique:

~~~text
unique(movie_id, user_id)
index(movie_id, status, created_at)
~~~

### movie_rating_summaries

~~~text
movie_id
rating_count, rating_sum
average_score_decimal
one_star_count ... five_star_count
updated_at
~~~

## 2.3. Business rules

- Score từ 1 đến 5.
- Một user có một rating active cho mỗi movie.
- Booking bị refund/cancel không đủ điều kiện verified.
- Check-in thành công thì có verified badge.
- User được sửa rating của mình.
- Delete là soft delete hoặc chuyển hidden.
- Admin được hide/restore.
- Movie inactive không nhận rating mới.
- Rating không được thay đổi booking/payment.

## 2.4. Folder structure

~~~text
app/Actions/Review/
├── CreateMovieRating.php
├── UpdateMovieRating.php
├── HideMovieRating.php
└── RebuildMovieRatingSummary.php

app/Queries/Review/
├── MovieRatingListQuery.php
└── MovieRatingSummaryQuery.php

app/Policies/Review/MovieRatingPolicy.php
app/Models/Review/MovieRating.php
app/Models/Review/MovieRatingSummary.php
~~~

## 2.5. Flow rating

~~~mermaid
flowchart TD
    A[User mở movie detail] --> B[Kiểm tra rating hiện tại]
    B --> C[Kiểm tra booking eligibility]
    C --> D{Đủ điều kiện?}
    D -- Không --> E[Hiển thị lý do]
    D -- Có --> F[Lưu rating]
    F --> G[Cập nhật summary/cache]
    G --> H[Hiển thị verified badge nếu có]
~~~

## 2.6. API/UI

~~~text
GET    /movies/{movie}/ratings
POST   /movies/{movie}/ratings
PATCH  /ratings/{rating}
DELETE /ratings/{rating}
POST   /admin/ratings/{rating}/hide
~~~

UI:

- Average rating.
- Histogram 1–5 sao.
- Rating count.
- Verified badge.
- Form create/edit.
- Empty/loading/error state.
- Pagination/cursor.
- Pending moderation state.

## 2.7. Test

- Score ngoài range.
- User chưa login.
- User chưa mua vé.
- Booking đã refund.
- User tạo hai lần.
- Sửa rating người khác.
- Admin hide.
- Concurrent create cùng movie/user.
- Summary không đếm hai lần.
- Cache invalidation.

# Phase 3 — Comment phim và moderation

## 3.1. MVP

- List comment.
- Create comment.
- Edit/delete comment của mình.
- Reply một cấp.
- Auth/policy.
- Pagination.
- Rate limit.
- Soft delete.

## 3.2. Database

### movie_comments

~~~text
id, movie_id, user_id, parent_id nullable
body
status: pending/published/hidden/deleted/rejected
edited_at, deleted_at, published_at
created_at, updated_at
~~~

### comment_reports

~~~text
id, comment_id, reporter_id
reason: spam/abuse/spoiler/misinformation/other
status: open/reviewing/resolved/rejected
reviewed_by, reviewed_at, resolution_note
~~~

### comment_reactions

~~~text
id, comment_id, user_id
type: like/dislike
unique(comment_id, user_id, type)
~~~

### comment_moderation_actions

~~~text
id, comment_id, actor_id
from_status, to_status, reason, created_at
~~~

## 3.3. Business rules

- Body không rỗng.
- Giới hạn độ dài.
- Parent phải cùng movie.
- Giới hạn depth reply, ví dụ 2.
- Không sửa/xóa comment người khác.
- Sửa phải lưu edited_at.
- Delete dùng soft delete.
- Rate limit theo user/IP.
- Không cho spam nội dung giống nhau liên tục.
- Report một comment không tạo vô hạn report trùng.
- Spoiler có thể ẩn nội dung.
- Admin action phải audit.
- User bị banned không được comment.

## 3.4. Flow moderation

~~~mermaid
flowchart TD
    A[User gửi comment] --> B[Validate và rate limit]
    B --> C[Spam/abuse screening]
    C --> D{Được publish ngay?}
    D -- Có --> E[Published]
    D -- Không --> F[Pending]
    F --> G[Admin review]
    G --> H[Publish, hide hoặc reject]
    E --> I[Notify người liên quan]
~~~

## 3.5. Tính năng nâng cao

- Report comment.
- Admin moderation queue.
- Like comment.
- Spoiler toggle.
- Notification reply.
- Trust score.
- Shadow ban.
- Ban user khỏi comment.
- Search comment.
- Auto moderation rule.
- Moderation SLA report.

## 3.6. Test

- Parent khác movie.
- Reply vượt depth.
- Sửa comment người khác.
- Xóa comment có reply.
- Spam vượt rate limit.
- Duplicate content.
- Report trùng.
- Admin hide/restore.
- Notification không gửi trùng.
- User banned.
- Concurrent like/unlike.

# Phase 4 — Reschedule booking

## 4.1. Rule

- Chỉ đổi trước cutoff.
- Không đổi sau check-in.
- Screening mới chưa bắt đầu.
- Screening mới còn ghế.
- Có thể giới hạn cùng movie/cinema.
- Coupon phải revalidate.
- Giá tăng thì charge phần chênh.
- Giá giảm thì refund phần chênh.
- Hold ghế mới trước khi release ghế cũ.
- Payment thất bại không làm mất booking cũ.

## 4.2. Flow

~~~text
Confirmed booking
  → Request reschedule
  → Lock old booking
  → Validate cutoff
  → Hold new seats
  → Calculate price difference
  → Charge/refund difference
  → Commit new booking state
  → Release old seats
  → Issue new ticket
  → Cancel old ticket
~~~

Nên có bảng booking_reschedules để lưu lịch sử và trạng thái workflow.

# Phase 5 — Partial refund

## 5.1. Use case

- Hoàn một ghế.
- Hoàn combo.
- Hoàn phí dịch vụ.
- Hoàn do lỗi rạp.
- Hoàn một phần do promotion.

## 5.2. Database

~~~text
refunds
- payment_id, booking_id, provider_refund_id
- amount_minor_units, currency
- reason, status, idempotency_key, created_by

refund_items
- refund_id
- booking_item_id nullable
- booking_concession_id nullable
- amount_minor_units, quantity nullable
~~~

Invariant:

~~~text
total_refunded <= total_paid
item_refund <= item_paid
một logical refund chỉ xử lý một lần
~~~

## 5.3. Test

- Refund một item.
- Refund vượt số tiền.
- Refund trùng.
- Refund sau check-in.
- Provider timeout.
- Webhook đến trước response.
- Local commit fail.
- Restore stock đúng một lần.

# Phase 6 — Operations dashboard

## 6.1. Dashboard

- Payment unknown.
- Payment requires refund.
- Orphan webhook.
- Failed webhook.
- Failed jobs.
- Stuck payment.
- Refund pending.
- Coupon reservation bất thường.
- Inventory mismatch.
- Booking expired chưa release resource.
- Duplicate notification.
- Check-in conflict.

## 6.2. Operator actions

Mỗi action cần:

- Policy.
- Permission riêng.
- Confirmation.
- Reason bắt buộc.
- Audit.
- Idempotency.
- Preview.
- Không cho sửa status tùy ý.

Actions:

~~~text
Reconcile payment
Replay webhook
Retry refund
Release orphan hold
Rebuild inventory snapshot
Retry outbox
Hide/restore comment
~~~

## 6.3. Runbook payment unknown

~~~text
1. Xem PaymentAttempt
2. Kiểm tra provider_payment_id
3. Query provider
4. Kiểm tra webhook events
5. Nếu succeeded → finalize
6. Nếu failed → cho retry
7. Nếu chưa xác định → manual review
~~~

# Phase 7 — Analytics và read model

## 7.1. Báo cáo

Booking:

- Conversion hold → payment.
- Hold expiry rate.
- Occupancy.
- Average booking value.
- No-show rate.

Payment:

- Success/failure/unknown rate.
- 3DS rate.
- Payment latency.
- Refund rate.
- Webhook delay.

Combo:

- Best-selling combo.
- Stock turnover.
- Expiry loss.
- Reservation conflict.
- Revenue per combo.

Rating/comment:

- Average rating.
- Rating distribution.
- Verified ratio.
- Comment volume.
- Report rate.
- Moderation resolution time.

## 7.2. Read model

Có thể tạo:

~~~text
daily_booking_metrics
daily_payment_metrics
daily_inventory_metrics
movie_rating_summaries
daily_comment_metrics
~~~

Dashboard chấp nhận eventual consistency; transaction booking không nên phụ thuộc dashboard aggregate.

# Phase 8 — Tính năng nâng cao

## Membership

- Membership tier.
- Giá vé riêng.
- Combo ưu đãi.
- Loyalty points.
- Expiry.
- Upgrade/downgrade.

## Loyalty points

- Earn sau payment success.
- Refund reverse points.
- Không dùng vượt balance.
- Points ledger bất biến.
- Idempotency theo booking/payment.

## Gift card

- Activate.
- Redeem một phần.
- Lock balance.
- Expiry.
- Refund về gift card.
- Chống double spend.

## Dynamic pricing

Giá theo:

- Ghế.
- Ngày/giờ.
- Cuối tuần.
- Tỷ lệ lấp đầy.
- Phim.
- Rạp.
- Membership.

Snapshot giá lúc hold; không đổi giá booking đã tạo.

## Seat map nâng cao

- Couple seat.
- Wheelchair/companion seat.
- Blocked/maintenance seat.
- Adjacency rule.
- Seat recommendation.
- Accessibility rule.

## Promotion stacking

Cố định thứ tự:

~~~text
gross subtotal
→ promotion
→ coupon
→ membership discount
→ loyalty points
→ service fee
→ tax
→ final total
~~~

# 9. Quy trình triển khai mỗi feature

~~~text
Business rules
  → State diagram
  → Database schema
  → Invariants
  → DTO/Form Request
  → Action/workflow
  → Policy
  → Query/read model
  → UI states
  → Feature tests
  → Concurrency tests
  → Browser tests
  → Observability
  → Documentation
~~~

Template:

~~~text
Tên feature:
Actor:
Mục tiêu:
Pre-condition:
Input:
Success state:
Failure state:
State transitions:
Data ownership:
Transaction boundary:
External side effects:
Idempotency key:
Concurrency risk:
Authorization:
Audit requirement:
Metrics:
Edge cases:
~~~

# 10. Thứ tự triển khai khuyến nghị

## Sprint 1 — Correctness

- Database invariant.
- Lock order.
- Nested transaction.
- Coupon reservation.
- Refund ownership.
- Concurrency MySQL/PostgreSQL.

## Sprint 2 — Fake inventory

- Ledger.
- Receive stock.
- Reserve/release/consume.
- Adjustment.
- Batch/FIFO/FEFO.
- Low-stock.
- Inventory audit.

## Sprint 3 — Rating

- Verified rating.
- Unique user/movie.
- Summary.
- Admin moderation.
- Cache invalidation.

## Sprint 4 — Comment

- Comment/reply.
- Policy.
- Soft delete.
- Report.
- Moderation.
- Notification.
- Rate limit.

## Sprint 5 — Advanced booking

- Reschedule.
- Partial refund.
- Price difference.
- Ticket replacement.

## Sprint 6 — Operations

- Recovery dashboard.
- Audit timeline.
- Webhook replay.
- Payment/reconcile tools.
- Metrics.

## Sprint 7 — Advanced commerce

- Membership.
- Loyalty.
- Gift card.
- Dynamic pricing.
- Promotion stacking.

# 11. Mục tiêu học được

| Năng lực | Feature |
| --- | --- |
| Transaction/concurrency | Hold ghế, inventory reservation |
| Idempotency | Payment, webhook, stock movement |
| State machine | Booking, payment, refund, comment |
| Ledger design | Inventory, loyalty, gift card |
| Compensation | Refund, reschedule |
| Authorization | Admin inventory, moderation |
| UGC moderation | Rating, comment, report |
| Eventual consistency | Dashboard, analytics |
| Query optimization | Rating summary, reporting |
| Operational recovery | Unknown payment, orphan webhook |
| Auditability | Inventory, moderation, refund |
| UX state design | Expiry, stock conflict, 3DS |
| Testing | Race, retry, duplicate, crash-window |

# 12. Definition of Done

Một feature hoàn thành khi:

- Có business document.
- Có schema/index.
- Có enum/state transition nếu cần.
- Có transaction boundary rõ.
- Có policy và validation.
- Có idempotency/retry.
- Có audit nếu mutation nhạy cảm.
- Có loading/empty/error/success UI.
- Có unit/feature/edge-case test.
- Có concurrency test nếu dữ liệu cạnh tranh.
- Có browser test nếu ảnh hưởng flow chính.
- Có log/metric/alert.
- Có runbook.
- Có cập nhật changelog/tài liệu.

# 13. Kết luận

Nên triển khai theo thứ tự:

1. Hardening booking hiện tại.
2. Fake inventory cho combo.
3. Rating phim.
4. Comment và moderation.
5. Reschedule/partial refund.
6. Operations dashboard và analytics.
7. Membership, loyalty, gift card và dynamic pricing.

Fake inventory là bước tiếp theo phù hợp nhất vì kết nối trực tiếp với combo đang có, giúp bạn học sâu về stock, reservation, ledger, adjustment, audit, FIFO/FEFO và concurrency trước khi đi vào các workflow payment phức tạp hơn.
