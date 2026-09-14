# 06 — Lessons learned sau khi phát triển booking

## 1. Database transaction là source of truth, không phải UI

Frontend seat map, combo max và countdown giúp user thao tác tốt hơn nhưng luôn có thể stale. Quyết định cuối phải nằm ở action với lock/transaction:

- seat: `HoldSeats.php`;
- combo: `SyncBookingConcessions.php`;
- coupon: `ApplyCoupon.php`;
- payment finalize: `FinalizeSuccessfulPayment.php`.

Bài học: mọi validation quan trọng phải tồn tại ít nhất ở domain boundary và database constraint phù hợp.

## 2. “Gọi payment rồi cập nhật DB” là chưa đủ

Provider call có thể timeout sau khi Stripe đã tạo PaymentIntent. Nếu retry với key mới, hệ thống có thể orphan/double charge.

Thiết kế hiện tại tách:

1. claim payment/attempt trong DB;
2. provider call với immutable idempotency key;
3. reconcile bằng provider ID/attempt key;
4. finalize sau provider truth.

Bài học: mọi external side effect cần có durable operation key và reconciliation path.

## 3. Webhook không phải request bình thường

Webhook có thể:

- đến trước response browser;
- đến trùng;
- đến trễ;
- đến khi local payment chưa tồn tại;
- có payload sai amount/currency/metadata.

Vì vậy webhook cần event persistence, unique event ID, retry, orphan state, signature verification và state transition chống downgrade.

Code: `IngestStripeWebhook`, `ProcessStripeWebhook`, `ReplayStripeWebhooks`.

## 4. Refund là một workflow độc lập

Refund không phải chỉ là `payment.status = refunded`. Cần phân biệt provider pending/succeeded/failed/unknown, giữ attempt, webhook, reconciliation và restore inventory/ticket/coupon idempotent.

Bài học: payment success và refund success đều phải được finalize bởi một action transaction riêng.

## 5. Lock order phải có chủ ý

Các transaction lock user/screening/seat/coupon/concession/booking. Lock không canonical có thể tạo deadlock khi hai request đổi cùng tài nguyên.

Bài học:

- sort IDs trước `lockForUpdate()`;
- giữ transaction ngắn;
- không call network trong transaction;
- test bằng MySQL process thật, không chỉ SQLite.

## 6. Query object giúp controller rõ hơn

Việc tách `ScreeningAvailabilityQuery` và `AvailableConcessionsQuery` giúp:

- public availability trả cả seat/combo contract;
- user active hold được cộng đúng vào max;
- controller không tự dựng query lock/availability;
- frontend contract có một nơi owner.

Bài học: khi controller nhận quá nhiều dependency hoặc dựng read payload phức tạp, tạo query/DTO có contract rõ thay vì thêm logic inline.

## 7. “Magic selector” cũng là business bug

Selector `button[type=submit]` tưởng chỉ là frontend detail nhưng đã làm disable nhầm coupon/payment. Tương tự duplicate listener làm total hiển thị sai.

Bài học:

- data attribute là API giữa Blade và JS;
- mỗi intent cần hook riêng;
- module phải idempotent khi init;
- test static chưa đủ, cần browser click-through.

## 8. Observability phải được thiết kế cùng nghiệp vụ

Các trạng thái `unknown`, `requires_refund`, orphan webhook và stuck payment không thể chỉ nằm trong database. Không có alert thì hệ thống có thể đúng về code nhưng thất bại về vận hành.

Bài học: mỗi trạng thái manual review cần:

- query/command phát hiện;
- structured log/metric;
- retry/reconcile path;
- admin visibility;
- runbook xử lý.

## 9. Tài liệu phải đi cùng boundary

Tài liệu cũ phình vì trộn business rule, architecture, remediation, test và lịch sử vào một file. Cấu trúc mới tách theo mục đích:

- business rules để biết hệ thống phải đúng gì;
- flows để biết request chạy qua đâu;
- test cases để QA kiểm gì;
- architecture để biết code đặt ở đâu;
- notes để tránh bug cũ;
- lessons để truyền kinh nghiệm.

Bài học: mỗi thay đổi xuyên domain nên cập nhật một code owner, một test owner và một section tài liệu; README là index duy nhất.

## 10. Điều cần cải thiện tiếp

1. Bắt buộc MySQL concurrency trong CI và không cho merge nếu bị skip.
2. Chạy browser E2E có fixture database/Stripe test thật trong pipeline riêng.
3. Tích hợp `payments.anomalies` với alerting production.
4. Bổ sung metric cho webhook latency, reconciliation age, refund pending age, stock conflict và availability 5xx.
5. Khi catalog lớn, paginate showtimes/concessions và tối ưu availability payload.
6. Khi cần realtime hơn, thay polling bằng broadcast nhưng giữ server-side revalidation.

