# Deep Review — Movie Booking

Tài liệu này là bản audit chuyên sâu của toàn bộ tính năng Movie Booking tại thời điểm 2026-09-11. Mục tiêu là đối chiếu nghiệp vụ với code thực tế, ghi nhận những gì đang hoạt động, chỉ ra invariant còn thiếu hoặc có rủi ro, và đưa ra phương án xử lý có thể trace bằng test.

Đây là tài liệu review và kế hoạch hardening; các mục được đánh dấu `Đề xuất` chưa được xem là đã triển khai chỉ vì đã được mô tả ở đây.

## 1. Phạm vi và nguyên tắc đánh giá

Phạm vi gồm:

- Catalog phim, phòng chiếu, suất chiếu và screening seat.
- Guest/authenticated booking, hold, edit, resume sau login.
- Combo, inventory, coupon và snapshot giá.
- Payment claim/charge/finalize, Stripe webhook, orphan recovery, refund.
- Ticket, QR, check-in, notification, email và outbox.
- Blade, Tailwind, JavaScript seat picker, checkout, payment polling và notification bell.
- Transaction boundary, row lock, index, query plan, authorization, accessibility và test.

Các nguyên tắc bất biến phải giữ trong mọi luồng:

1. Một screening seat chỉ có một owner hợp lệ tại một thời điểm.
2. Không phát hành ticket nếu payment chưa được provider xác minh.
3. Mọi tổng tiền được tính từ snapshot server, không tin giá từ DOM.
4. Mọi thay đổi seat, stock, coupon và payment liên quan phải atomic hoặc có recovery rõ ràng.
5. Retry cùng một business operation không tạo thêm booking, charge, refund, ticket, stock movement, email hoặc notification.
6. Quyết định về thời gian dùng `BookingClock` và `config('app.timezone')`.

## 2. Bản đồ kiến trúc hiện tại

```text
PublicMovieController / User BookingController
                    |
              Form Request
                    |
        Movie Actions + Query objects
                    |
             Eloquent models/scopes
                    |
   MySQL transaction + ordered row locks
                    |
 Booking / Inventory / Payment / Outbox tables
                    |
     Queue jobs -> email / notification / provider
```

Domain ownership hiện tại:

| Domain | Nguồn sự thật | Trách nhiệm |
|---|---|---|
| Movie | `Movie`, `Screening`, `ScreeningSeat`, `Booking`, `BookingItem` | Catalog, showtime, seat selection, booking lifecycle, ticket |
| Inventory | `Concession`, inventory movement | Stock combo, reserve/release/refund, audit movement |
| Payments | `Payment`, `PaymentAttempt`, `RefundAttempt`, webhook | Provider state, charge, reconcile, refund |
| Infrastructure | outbox, delivery, notification, mail | Side effect, retry, deduplication, delivery observability |

Điểm tốt là các action đã được gom theo `app/Actions/Movie`, config giới hạn đã tập trung và public route canonical đã tồn tại. Điểm cần tiếp tục làm rõ là boundary giữa transition booking/payment, vì hiện vẫn còn `ConfirmBooking`, observer và nhiều action cùng có khả năng tác động state.

## 3. Luồng nghiệp vụ chuẩn

### 3.1. Catalog và showtime

1. Admin tạo movie, room, seat và screening.
2. Screening phải có movie active, room active, status scheduled, start/end hợp lệ và không overlap trong cùng room.
3. Public chỉ hiển thị screening thỏa `Screening::scopeBookable()`.
4. Mọi action nhạy cảm (`show`, availability, hold, pay, checkout) phải gọi cùng quy tắc `Screening::isBookable()` hoặc một service dùng chung.
5. Khi đạt minimum lead time, screening không nhận hold mới dù chưa bắt đầu.

### 3.2. Chọn ghế và combo

1. Public page tải seat map và combo catalog.
2. JavaScript cập nhật selection, giới hạn tối đa 10 ghế và tối đa 3 combo cho mỗi vé.
3. Availability polling chỉ là trợ giúp UI; server phải lock và kiểm tra lại.
4. Guest submit lưu selection vào session rồi redirect login.
5. Authenticated submit tạo hoặc update booking atomic.
6. Combo được reserve stock và snapshot unit price/currency.

### 3.3. Checkout và payment

1. Checkout chỉ mở với booking owned by user và status Held/PendingPayment phù hợp.
2. Server kiểm tra expiry, showtime, booking items, combo, coupon và totals.
3. Claim payment tạo PaymentAttempt với idempotency key riêng.
4. Charge provider truyền đúng amount, currency, booking metadata và idempotency key.
5. Provider response hoặc webhook được validate trước khi đổi state.
6. Finalize chuyển booking Confirmed, screening seats Held → Sold và phát hành ticket QR trong transaction.
7. Nếu nhận tiền nhưng không thể finalize, payment chuyển RequiresRefund; tuyệt đối không phát hành ticket giả.

### 3.4. Sau thanh toán

Confirmation event tạo outbox message. Worker gửi một email xác nhận có phim, suất chiếu, phòng, ghế, combo, totals, QR và link detail. Notification in-app có link canonical đến booking detail, badge chưa đọc và thời gian theo app timezone. Reminder được gửi một lần trước khoảng 2 giờ.

## 4. Findings ưu tiên production

### P0 — cần xử lý trước production

| ID | Finding | Bằng chứng hiện tại | Tác động | Phương án xử lý |
|---|---|---|---|---|
| P0-01 | Webhook giữ lock Payment rồi gọi finalize, trong khi finalize/refund dùng Booking → Payment | `StripeWebhookController` lock payment trước; `FinalizeSuccessfulPayment` và `RefundBooking` lock booking trước | Có thể deadlock dưới webhook/refund race; transaction boundary khó reasoning | Tách webhook ingest khỏi finalize. Ingest event/idempotency ngắn; sau commit dispatch transition job/action dùng lock order chuẩn `Booking → Payment → Attempt`. Nếu vẫn finalize inline, phải lock Booking trước Payment và không mở nested workflow ngược order |
| P0-02 | Combo update chưa reconcile dòng bị bỏ khỏi request | `AddConcessions::executeForLockedBooking()` chỉ lặp key được gửi; line cũ không xuất hiện không bị đưa về 0 | Combo bị giữ/stock không phản ánh selection cuối; client partial payload có thể giữ combo ngoài ý muốn | Xác định payload là full replacement. Load toàn bộ active/current lines, coi key thiếu là quantity 0, trả stock phần giảm và xóa line. Với combo inactive, vẫn phải release line hiện tại nhưng không cho tăng mới |
| P0-03 | Ownership seat không được DB enforce theo cùng screening/booking | FK `held_by_booking_id` chỉ trỏ Booking; `booking_items` chỉ FK screening seat | Dữ liệu sai screening có thể lọt nếu có code path mới/import/admin bypass action | Thêm service invariant bắt buộc ở mọi write; dài hạn dùng composite FK/constraint hoặc bảng ownership riêng có key `(screening_id, booking_id)` và test migration trên MySQL |
| P0-04 | Payment unknown/no local payment cần contract recovery rõ hơn | Webhook ném RuntimeException khi provider ID không map payment; provider có thể retry nhưng HTTP behavior chưa phân biệt race với dữ liệu sai | Có thể 500 lặp vô hạn hoặc mất visibility payment provider đã charge | Ghi orphan event/attempt với provider ID, trả 202 cho race có thể recover, 4xx cho payload invalid, alert cho unknown. Reconcile phải search/retrieve theo provider ID và backfill payment trước finalize |

### P1 — xử lý ngay sau P0

| ID | Finding | Tác động | Phương án xử lý |
|---|---|---|---|
| P1-01 | Max seat và max combo line chưa được enforce đầy đủ trong action | Internal caller/job có thể bypass Form Request | Đưa mọi limit vào domain validator/action; Form Request chỉ là lớp UX sớm |
| P1-02 | Coupon switch lock thứ tự chưa canonical và chỉ xử lý reservation đầu tiên | A→B đồng thời có thể deadlock; reservation dữ liệu cũ có thể bị sót | Lock coupon IDs theo thứ tự tăng dần; load/reconcile tất cả reservation Reserved của booking; thêm unique business invariant một Reserved/booking |
| P1-03 | Payment state và PaymentAttempt chưa có một transition service duy nhất | `syncLatestAttempt()` có thể no-op theo status hiện tại, tạo drift giữa payment và attempt | Tạo `TransitionPayment` ghi payment + attempt trong một transaction, reject transition bất hợp lệ và log reason/event |
| P1-04 | `ConfirmBooking` là đường transition trùng/không thấy production caller | Dễ tạo path xác nhận bỏ qua payment/finalize nếu được gọi nhầm sau này | Xóa sau khi xác nhận không còn API sử dụng, hoặc biến thành private/internal finalize gateway; thêm architectural test không có route gọi trực tiếp |
| P1-05 | Booking time chưa tuyệt đối dùng `BookingClock` | Một số `now()` còn ở observer/controller/payment/refund/outbox/check-in | Test timezone không deterministic, dễ lệch khi DB/app timezone khác nhau | Inject clock hoặc dùng `BookingClock` toàn bộ business timestamp; chỉ dùng framework `now()` cho metadata kỹ thuật nếu đã quy định rõ |
| P1-06 | Refund provider success và local DB failure chưa có protocol provider-independent | Có thể gọi refund lặp khi provider không hỗ trợ idempotency/search | Double refund hoặc không hoàn local stock/ticket | Refund key bắt buộc trong gateway contract; lưu attempt trước call, retry cùng key, reconcile by key/provider ID, chỉ restore stock bằng movement idempotency |
| P1-07 | Outbox/email/notification chưa đảm bảo exactly-once tuyệt đối | Process chết sau provider accepted trước khi mark Sent; notify race có thể duplicate | User nhận email/notification trùng | Unique logical event key trong DB, delivery state machine, provider idempotency/header, retry metrics và dead-letter/manual replay |
| P1-08 | Chưa có concurrency test MySQL thật cho hold/payment/refund | SQLite không chứng minh row lock/deadlock | Race production chưa được chứng minh | Test profile MySQL riêng, nhiều process, barrier đồng bộ, assert one winner/no double stock/no duplicate ticket |
| P1-09 | `Screening` detail tải toàn bộ bookable showtimes | Một movie dài horizon có thể tạo payload/query lớn | TTFB/render chậm, mobile tốn dữ liệu | Paginate hoặc giới hạn theo ngày/next N showtimes; endpoint showtime dùng select cần thiết và index `(movie_id, status, starts_at)` |
| P1-10 | Legacy `/user/screenings` vẫn tồn tại cạnh public canonical | Link/redirect và authorization có nhiều entrypoint | Regression khó trace, UI có thể quay về path cũ | Giữ redirect có deprecation log trong ngắn hạn, sau đó bỏ route khi browser tests chuyển canonical |

### P2 — chất lượng và khả năng mở rộng

- Search hiện dùng `%LIKE%`; khi catalog lớn cần full-text/search service, ranking và debounce frontend.
- Availability polling 10 giây tạo tải tuyến tính theo số tab; thêm ETag/version, rate limit theo user/IP và metric latency/error.
- Notification bell hiện là polling, không phải realtime transport; nếu cần realtime thật dùng broadcast/WebSocket, vẫn giữ polling fallback.
- Notification cần index theo `notifiable_type`, `notifiable_id`, `read_at`; payment attempts cần index provider ID; refund attempts cần unique provider refund ID nếu provider contract cho phép.
- Các non-negative amount/stock, currency/status và `ends_at > starts_at` hiện phần lớn là application invariant; bổ sung DB CHECK khi tương thích production MySQL.
- Admin MovieController vẫn điều phối nhiều listing query; tách `MovieCatalogController`, `ScreeningController`, `ConcessionController`, `CouponController` khi phạm vi admin tăng.
- Cần dashboard/alert cho payment stuck, orphan, refund unknown, outbox failed, availability 5xx, slow query và duplicate delivery.

## 5. Ma trận edge case và kết quả đúng

| Nhóm | Case | Kết quả bắt buộc |
|---|---|---|
| Seat | Hai user chọn cùng seat | Một transaction thắng; transaction còn lại nhận conflict, không tạo booking một phần |
| Seat | User giữ nguyên selection ở edit | Reuse booking/hold, sync combo, không tạo hold mới |
| Seat | User đổi seat nhưng seat mới conflict | Giữ nguyên hold cũ; không release trước khi biết seat mới hợp lệ |
| Seat | Hold hết hạn đúng lúc pay | Chỉ một transition thắng; không finalize seat đã expired |
| Resume | Guest login nhiều tab | Resume key atomic; retry trả booking hiện tại, không nhân bản |
| Resume | Screening bị xóa/deactivate | Không tạo hold; hiển thị expired/unavailable và link movie list |
| Combo | Payload thiếu một combo đang có | Replacement semantics: quantity combo đó về 0 và hoàn stock |
| Combo | Combo inactive sau khi hold | Không tăng mới; release line cũ khi edit/expire |
| Combo | Hai user mua item cuối | Một người reserve; người kia nhận stock conflict |
| Coupon | A → B → A | Reservation A được reuse hoặc tạo hợp lệ duy nhất; usage không âm/đúp |
| Payment | Double click pay | Cùng attempt/provider idempotency, tối đa một charge logic |
| Payment | Provider timeout | Attempt Unknown, không fake success, reconcile về sau |
| Payment | Webhook đến trước response HTTP | Webhook/finalize idempotent, không downgrade |
| Payment | Webhook cũ đến sau success | Bỏ qua transition nhưng ghi nhận event đã xử lý |
| Payment | Amount/currency/metadata sai | Không finalize; đánh dấu suspicious/failed và alert |
| Payment | Success sau giờ chiếu | RequiresRefund, không issue ticket |
| Refund | Provider refund success, local commit fail | Retry/reconcile hoàn tất local một lần, không double restore stock |
| Ticket | QR bị gọi nhiều lần/check-in hai tab | Một lần check-in thành công; lần sau conflict/đã dùng |
| Time | Worker/DB/browser khác timezone | Business decision vẫn dùng `BookingClock` và app timezone; frontend chỉ hiển thị |
| Frontend | DOM cũ nói seat thuộc user | Availability API hiện tại quyết định selectable; DOM không có quyền sở hữu |
| Frontend | Polling nhận terminal payment | Dừng timer, hiển thị action đúng; unknown tiếp tục reconcile |
| Delivery | Worker chết sau gửi mail | Retry có logical event/provider idempotency; dashboard cho ambiguous delivery |

## 6. Review frontend và UI/UX

### Đã làm đúng

- Public seat page có movie, room, ngày/giờ và seat map; combo nằm cùng flow.
- Modal confirmation nhóm seat cùng giá, tách tiền ghế, tiền combo và total.
- Seat picker có active state cho hold của user, aria-pressed, aria-label và availability refresh.
- Giới hạn 10 seat và 3 combo/vé được phản hồi sớm ở client; checkout vẫn là server source of truth.
- Checkout có countdown, breakdown giá, coupon, combo và mobile sticky total.
- Payment polling có danh sách terminal state và dừng polling khi terminal.
- Notification bell có unread badge, read/delete action, outside click và thời gian hiển thị.

### Khoảng trống cần xử lý

1. Khi availability thay đổi, UI cần focus/announce rõ seat bị mất và giữ lại tổng tiền sau khi sync; tránh chỉ chèn alert rồi biến mất.
2. Các lỗi server cần map về field/summary có `role=alert`, focus vào vùng lỗi và không làm mất selection/session.
3. Nút tăng/giảm combo nên expose `aria-valuenow`, `aria-valuemax`, disabled state và thông báo giới hạn cho screen reader.
4. Modal cần focus trap, restore focus, đóng bằng Escape, title/description duy nhất và không phụ thuộc màu để phân biệt seat.
5. Countdown nên hiển thị trạng thái hết hạn một lần, disable pay, rồi điều hướng đến màn hình expiry phù hợp; không để user submit vào form đã hết hạn.
6. Notification list cần empty/loading/error state cân đối, keyboard navigation, confirm khi delete all và tránh text overflow trên mobile.
7. Search header cần debounce, submit bằng keyboard, trạng thái loading/no result và giữ query khi quay lại.
8. Các icon SVG inline phải có `aria-hidden` nếu chỉ trang trí; icon có ý nghĩa phải đi kèm accessible label.
9. Cần browser test thật cho login resume, edit same/change seat, two tabs, modal keyboard, mobile sticky checkout và payment terminal states.

## 7. Kế hoạch test chuyên sâu

### Feature/unit

- `Screening::isBookable/scopeBookable`: lead time, horizon, active movie/room, status, timezone, start/end.
- Hold: idempotency same/different payload, missing seat, sold/held seat, max 10, rollback.
- Edit: same seat reuse, changed seat atomic replacement, expired hold, pending payment rejection, combo replacement.
- Combo: per-ticket limit, line limit, stock race, inactive combo, omitted line, idempotent movement.
- Coupon: reserve/release/redeem, A→B→A, usage race, expiry/currency/minimum subtotal.
- Payment: claim/charge/finalize split, failed retry, timeout unknown, provider validation, late webhook, duplicate webhook.
- Refund: confirmed/required refund, retry after local failure, provider duplicate, check-in restriction, stock restore once.
- Ownership/policy: user, guest resume, admin, ticket verify/check-in.

### MySQL concurrency

Mỗi test cần dùng transaction thật và nhiều process/thread:

1. Concurrent hold cùng seat: assert exactly one Held booking.
2. Concurrent last-stock combo: assert stock không âm và chỉ một success.
3. Pay vs webhook: assert one payment transition và one ticket set.
4. Expire vs pay: assert no confirmed booking có expired hold.
5. Refund retry vs refund worker: assert one provider logical refund và one inventory movement.
6. Edit same booking ở hai tab: assert không còn orphan hold/stock leak.

### Browser/E2E và quality gate

Quality gate đề xuất:

```text
Pint -> PHPStan -> Pest feature -> MySQL concurrency profile
     -> frontend unit -> ESLint/Vite build -> browser E2E/accessibility
     -> EXPLAIN/benchmark smoke -> artifact + metrics report
```

Browser E2E phải chạy trên database reset riêng, clock/config timezone cố định và payment provider fake chỉ trong test. Production không được bật fake confirmed payment.

## 8. Traceability khi triển khai

Mỗi finding khi được sửa phải cập nhật cùng lúc:

- action/service/model hoặc migration đã thay đổi;
- Form Request và controller boundary;
- translation key nếu có thông báo mới;
- feature/concurrency/browser test;
- metrics/log/alert nếu ảnh hưởng recovery;
- `docs/movie-booking-business-logic.md` cho rule nghiệp vụ;
- `docs/movie-booking-architecture.md` cho boundary/lock/data flow;
- tài liệu này ở bảng findings và lịch sử cập nhật.

Acceptance criteria chung:

- Không có path thành công giả khi provider thiếu ID.
- Không có stock/seat/ticket duplicate sau retry.
- Không có transition downgrade do event cũ.
- Không có business timestamp dùng timezone ngoài app config.
- User luôn biết trạng thái, số tiền, thời gian hết hạn và action kế tiếp.

## 9. Thứ tự triển khai khuyến nghị

1. P0-01 đến P0-04: webhook/reconcile transaction, combo replacement, ownership integrity và orphan contract.
2. P1-01 đến P1-08: state machine/payment attempt, coupon lock, refund protocol, clock và MySQL concurrency.
3. P1-09/P1-10: query payload và canonical route cleanup.
4. Frontend accessibility/error states/browser E2E.
5. P2 observability, search, polling optimization và benchmark.

## 10. Kết quả triển khai vòng 2026-09-11

### 10.1. Đã triển khai

| Finding | Thay đổi | Bằng chứng |
|---|---|---|
| P0-02 | `AddConcessions::executeForLockedBooking()` nay coi payload là full replacement: line hiện tại bị thiếu được đặt về `0`, stock được release, line bị xóa; line combo đã inactive vẫn được release nhưng không thể tăng mới. Các concession được lock theo thứ tự ID để giữ thứ tự lock ổn định. | `MovieBookingFeatureTest`: replacement payload và inactive combo release |
| P1-01 | Đưa giới hạn vào domain action: `HoldSeats` giới hạn số ghế dù được gọi nội bộ; `AddConcessions` kiểm tra giới hạn mỗi dòng và tổng combo trước khi chạm stock. | Existing combo-limit test và full Pest suite |
| Traceability | Bổ sung translation key `seat_limit` cho EN/VI và regression coverage ở feature layer. | `lang/en/booking.php`, `lang/vi/booking.php`, `tests/Feature/MovieBookingFeatureTest.php` |

### 10.2. Đánh giá sau triển khai

- P0-02 được đánh dấu **Resolved** trong phạm vi action edit/checkout hiện tại: payload thiếu combo không còn làm rò rỉ stock hoặc giữ booking line ngoài selection cuối.
- P1-01 được đánh dấu **Resolved** ở boundary domain đã kiểm tra; Form Request vẫn giữ vai trò phản hồi sớm cho UI, nhưng không còn là lớp bảo vệ duy nhất.
- Không phát hiện regression trong full suite và frontend checks của repository.
- Các finding P0-01, P0-03, P0-04 và các P1 còn lại vẫn **Open/Partially addressed**; chưa tự đánh dấu hoàn thành vì cần thay đổi transaction protocol, schema/invariant hoặc môi trường provider/MySQL concurrency tương ứng.

### 10.3. Verification thực tế

Đã chạy trên working tree hiện tại:

- `vendor/bin/pint --dirty --format agent`: **pass**.
- `php artisan test --compact`: **103 passed, 1 skipped, 408 assertions**.
- `npm run test:frontend`: **5 passed**.
- `npm run check`: **pass** (ESLint và Vite build; Vite chỉ cảnh báo tùy chọn `fontaine`).
- `php artisan view:cache`: **pass**.
- `git diff --check`: **pass**.

## 17. Enum predicates cho các nhóm `in_array` — 2026-09-11

Rà soát toàn backend cho thấy các nhóm status lặp lại ở pay, expire, cancel, policy, finalize, ticket verification và outbox. Các nhóm này đã được đưa về enum behavior:

- `BookingStatus::isPayable()` cho `Held`/`PendingPayment`.
- `BookingStatus::isTicketAccessible()` cho `Confirmed`/`Completed`.
- `BookingStatus::isClosed()` cho `Cancelled`/`Expired`/`NoShow`.
- `PaymentStatus::isAwaitingProviderResolution()` và `isRefundable()`.
- `PaymentStatus::canTransitionTo()` làm source of truth cho transition matrix; `PaymentStateMachine` chỉ còn là boundary/delegator.
- `TicketStatus::isCheckInEligible()` cho `Issued`/`CheckedIn`.
- `PaymentAttemptStatus::isOpen()` cho attempt cần tiếp tục xử lý.
- `OutboxEventType::shouldDispatch()` cho quyết định dispatch outbox.

Sau refactor, không còn các `in_array` lặp nhóm enum tương ứng trong application code; các `in_array` còn lại nằm bên trong enum predicate hoặc là bảng transition/giá trị kỹ thuật có chủ đích. Điều này giúp controller/action đọc theo nghiệp vụ, tránh drift khi thêm status mới và giữ một nguồn truth duy nhất.

Verification sau enum predicate refactor: `php artisan test --compact` **108 passed, 3 skipped, 438 assertions**; `composer analyse` pass; Pint pass; `git diff --check` pass.

## 18. Route organization và controller boundary — 2026-09-11

- `routes/web.php` đã được chia thành locale, public catalogue/SEO, scoped seat selection, signed ticket verification và Stripe webhook.
- `routes/user.php` đã được chia thành dashboard/catalogue, ticket, notification và booking/payment/mutation.
- `routes/admin.php` đã được chia thành landing/static, booking/report, cinema management, refund/cancel, ticket/settings và user management.
- `routes/console.php` đã tách comment cho development command và recurring booking/payment maintenance.
- Các route closure có nghiệp vụ đã được chuyển thành invokable controller: `LocaleController`, `HomeController`, `TicketVerificationController`, `RedirectToMovieCatalogueController`, `Admin\\RedirectToDashboardController`.
- Route file hiện chỉ khai báo URL, middleware, name và controller boundary; nghiệp vụ/query/validation không còn nằm trực tiếp trong route closure.

Verification: `php artisan route:list` pass, full Pest/PHPStan/Pint pass, `git diff --check` pass.

### Webhook controller boundary

`StripeWebhookController` không nên sở hữu toàn bộ flow. Sau refactor, controller chỉ làm HTTP orchestration: đọc raw request, gọi `StripeWebhookSignatureVerifier`, decode JSON, kiểm tra event ID, gọi `IngestStripeWebhook` và map `StripeWebhookIngestResult` sang HTTP response. Transaction ghi `PaymentWebhookEvent`, tìm payment/orphan và validate amount/currency/metadata nằm trong action; signature HMAC nằm trong support verifier. Finalize vẫn do `ProcessStripeWebhook` xử lý sau ingest.

Verification: webhook feature tests **18 passed, 50 assertions**; PHPStan/Pint pass.

Chưa chạy trong vòng này: MySQL multi-process concurrency, browser E2E/accessibility runner, EXPLAIN trên dataset production-size và provider reconciliation thật. Vì vậy kết quả hiện tại xác nhận correctness ở feature/unit/frontend build boundary, chưa phải production sign-off.

## 11. Lịch sử cập nhật

| Ngày | Nội dung |
|---|---|
| 2026-09-11 | Audit end-to-end Movie, Booking, Inventory, Payment, Ticket, Infrastructure và frontend; bổ sung findings P0/P1/P2, edge-case matrix, test plan và remediation order. |
| 2026-09-11 | Triển khai P0-02 và P1-01: combo replacement/inactive release, domain limit enforcement, translation và regression tests; verification full suite/build được ghi nhận tại mục 10. |

## 12. Trạng thái verification của vòng review

### 12.1. Incident Stripe idempotency ngày 2026-09-11

Log ghi nhận `stripe.payment_provider_error` với `error_type=idempotency_error` và key `booking-payment-2-1`. Stripe từ chối vì key này đã được dùng với bộ parameters khác. Nguyên nhân là key cũ ghép từ payment ID và counter; đây không phải identity bất biến của provider request.

Đã xử lý bằng cách tạo `attempt_key` dạng `booking-payment-{uuid}` cho mỗi `PaymentAttempt`. Cùng một attempt giữ nguyên key để retry/reconcile; payment retry mới luôn tạo key khác. Đã thêm regression test cho hai retry và sửa raw datetime boundary trong recovery.

### 12.2. Payment `unknown` bị treo trên payment-action

Log browser ghi nhận Stripe Elements được mount bằng `client_secret` của PaymentIntent đã terminal:

```text
This PaymentIntent is in a terminal state and cannot be used to initialize Elements.
```

Payment endpoint đồng thời trả `{"status":"unknown","redirect":null}`. `unknown` là trạng thái an toàn khi provider chưa xác minh được kết quả; không được tự chuyển thành `succeeded` hoặc charge lại.

Đã xử lý:

- Không truyền `client_secret` vào Stripe Elements khi local payment đang `unknown`.
- `ReconcilePayment` retry hữu hạn với backoff khi provider tạm thời chưa trả kết quả.
- Khi payment xác định `failed`, status endpoint redirect về checkout để tạo attempt mới.
- Frontend dừng polling sau giới hạn và hiển thị thông báo rõ ràng, không để spinner treo vô hạn.
- Không redirect `unknown` về success và không tự tạo charge thứ hai khi chưa có kết quả provider.

Nếu vẫn `unknown` sau retry, operator phải kiểm tra provider ID, webhook, queue worker và `STRIPE_SECRET`. Không dùng lại PaymentIntent terminal; chỉ retry sau khi provider xác nhận failed hoặc có quy trình reconcile hợp lệ.

Các log `Table 'laravel.payments' doesn't exist` và `Table 'laravel.cache_locks' doesn't exist` là lỗi database local chưa migrate đầy đủ, độc lập với Stripe idempotency.

Đã chạy sau khi cập nhật tài liệu:

- `php artisan test --compact`: **100 passed, 1 skipped, 400 assertions** trước hotfix; `BookingPaymentReliabilityTest`: **17 passed, 46 assertions** sau hotfix; full suite sau payment-unknown fix: **101 passed, 1 skipped, 403 assertions**.
- `npm run test:frontend`: **5 passed**.
- `npm run check`: **pass** (ESLint và Vite build).
- `php artisan view:cache`: **pass**.
- `git diff --check`: **pass**.

- `composer analyse`: **pass** sau hotfix.

Chưa thể kết luận production-ready chỉ từ vòng chạy này:

- Chưa có MySQL multi-process concurrency run trong môi trường này.
- Chưa có browser E2E/accessibility runner thực thi luồng login resume, multi-tab, payment và keyboard modal.
- Chưa benchmark EXPLAIN/slow query trên dataset production-size.

## 13. Vòng review chuyên sâu bổ sung — 2026-09-11

### 13.1. Findings mới

| Mức | Finding | Bằng chứng | Tác động | Đề xuất |
|---|---|---|---|---|
| P0 | Webhook và finalize/refund vẫn có lock order ngược | `StripeWebhookController` lock Payment trước rồi gọi `FinalizeSuccessfulPayment`; finalize/refund lock Booking trước Payment | Có thể deadlock khi webhook chạy đồng thời refund/expiry/finalize; webhook transaction có thể retry/500 không ổn định | Tách webhook ingest ngắn khỏi finalize; dispatch transition sau commit; thống nhất lock order `Booking → Payment → Attempt → items/seats/inventory` |
| P0 | Webhook provider ID chưa map payment làm mất durable orphan record | Webhook ném `RuntimeException` tại payment lookup; toàn transaction rollback nên `PaymentWebhookEvent` không được lưu | Provider retry vô hạn nhưng hệ thống không có record/orphan để reconcile hoặc alert | Lưu orphan event atomically với provider/event ID, trả `202`, reconcile/backfill theo provider ID; chỉ `4xx` cho payload/signature sai |
| P1 | Có thể mutate combo/coupon sau khi hold đã hết hạn nhưng status chưa được scheduler chuyển | `AddConcessions` và `ApplyCoupon` chỉ kiểm tra `Held`; không recheck `expires_at`/`screening->isBookable()` | Giữ/trừ stock hoặc reserve coupon cho booking không còn hợp lệ; window scheduler tạo hành vi phụ thuộc timing | Tạo guard chung `assertBookingMutable()` trong transaction hoặc gọi `ExpireBooking`/reject trước mutation; thêm test không cần scheduler |
| P1 | Same-seat edit bỏ qua replacement payload rỗng | `EditBookingSelection` chỉ gọi `AddConcessions` khi `$quantities !== []`; user bỏ hết combo nhưng submit `quantities=[]` sẽ giữ line cũ | Selection UI và server booking lệch nhau, stock bị giữ đến expiry | Luôn truyền full replacement payload; `[]` phải có nghĩa xóa toàn bộ combo; thêm feature test same-seat remove-all |
| P1 | Coupon lock order và legacy reservation reconciliation chưa canonical | `ApplyCoupon` lock coupon mới trước, rồi lock reservation/coupon cũ; `ReleaseBookingResources` chỉ lấy `first()` Reserved | A→B đồng thời có thể deadlock; nhiều Reserved cũ có thể làm used_count/reservation drift | Lock tất cả coupon IDs tăng dần; load/reconcile tất cả Reserved rows; thêm unique invariant cho một Reserved/booking |
| P1 | Movie detail và sitemap đọc toàn bộ showtimes không giới hạn | `PublicMovieController::movie()` và `sitemap()` dùng eager load/get không pagination/limit | Payload/TTFB và memory tăng theo horizon/catalog; sitemap có thể vượt kích thước thực tế | Movie detail giới hạn theo ngày/next N hoặc paginate; sitemap chunk/giới hạn URL và đo query plan |
| P1 | Outbox email vẫn at-least-once nhưng chưa provider-idempotent | Worker đánh dấu Sent sau `Mail::send`; process chết giữa provider accepted và save Sent sẽ gửi lại ở retry | Email confirmation/reminder có thể duplicate dù notification đã dedupe | Dùng provider Message-ID/idempotency nếu adapter hỗ trợ; lưu delivery attempt/provider response; dashboard ambiguous delivery/manual replay |

### 13.2. Test matrix bắt buộc bổ sung

- Webhook unknown provider ID: assert event được lưu durable, response `202`, retry không tạo payment/ticket giả.
- Webhook success vs refund/finalize: MySQL multi-process test, barrier trước các lock, assert no deadlock và chỉ một transition/ticket set.
- Expired hold direct mutation: gọi combo/coupon endpoint sau `expires_at`, không chạy scheduler; assert không đổi stock/discount/reservation.
- Same-seat edit với `quantities=[]`: assert toàn bộ booking concessions bị xóa và stock trả lại.
- Coupon A→B đồng thời và booking có nhiều Reserved rows cũ: assert không âm/đúp `used_count`, chỉ một Reserved hợp lệ.
- Detail/sitemap dataset lớn: assert bounded query/payload và EXPLAIN dùng index bookable window.
- Outbox crash window: giả lập mail accepted nhưng mark Sent thất bại; assert provider idempotency hoặc trạng thái ambiguous được alert.

### 13.3. Kết quả test/check vòng này

- `php artisan test --compact`: **103 passed, 1 skipped, 408 assertions**.
- `npm run test:frontend`: **5 passed**.
- `npm run check`: **pass** (ESLint + Vite build; có cảnh báo tùy chọn `fontaine`).
- `composer analyse`: **pass**, PHPStan 0 errors.
- `git diff --check`: **pass**.
- `php artisan migrate:status`: **blocked** vì môi trường không cho kết nối MySQL `127.0.0.1:3307`; chưa thể xác nhận migration/schema hoặc concurrency bằng DB production-like.

Kết luận vòng này: feature đang có nền tảng tốt ở logic transaction đơn luồng, idempotency cơ bản và frontend build, nhưng **chưa production-ready** cho payment webhook/recovery và mutation sau expiry. Ưu tiên tiếp theo là P0 webhook/orphan, sau đó expiry guard + same-seat replacement, rồi MySQL concurrency và outbox delivery protocol.

## 14. Trạng thái triển khai vòng 2026-09-11 — sáu hạng mục hardening

| Hạng mục | Trạng thái | Triển khai |
|---|---|---|
| Tách webhook ingest/finalize và lock order | **Implemented** | `StripeWebhookController` chỉ verify/persist/dispatch; `ProcessStripeWebhook` xử lý sau commit, lock `Booking → Payment`, finalize ở transaction riêng để tránh lock ngược. |
| Orphan webhook/payment | **Implemented** | `payment_webhook_events` lưu `provider_payment_id`, `orphaned_at`, số lần thử và thời điểm thử; `payments:reconcile` dispatch lại orphan event. |
| Guard expiry trước mutation | **Implemented** | `BookingMutationGuard` dùng cho combo, coupon và same-seat edit; hết hạn hoặc screening không còn bookable sẽ bị từ chối trước khi đổi stock/discount. |
| Same-seat replacement | **Implemented** | `quantities=[]` được xử lý như full replacement, xóa toàn bộ combo line và hoàn stock. |
| MySQL multi-process concurrency | **Test profile added** | `MySqlBookingConcurrencyTest` dùng `pcntl_fork` + `SELECT ... FOR UPDATE` cho last-stock/seat ownership; mặc định skip nếu chưa bật `BOOKING_MYSQL_CONCURRENCY=1`, thiếu `pdo_mysql`/`pcntl` hoặc không dùng MySQL. |
| Outbox idempotency/observability | **Implemented baseline** | `outbox_deliveries` có logical idempotency key, attempt counter, delivery log started/sent/failed; mail đã có deterministic Message-ID. Provider-level dedupe vẫn cần xác nhận theo mail provider production. |

### Verification sau triển khai

- Pest: **106 passed, 3 skipped, 419 assertions**; 3 skipped là profile MySQL concurrency do môi trường chưa có điều kiện chạy.
- Frontend tests: **5 passed**.
- ESLint/Vite build: **pass**.
- PHPStan: **pass**, 0 errors.
- Pint: **pass**.
- `git diff --check`: **pass**.

Residual risk còn lại: chưa chạy được MySQL multi-process thực tế trong môi trường hiện tại, chưa test provider email thật trong crash window, và chưa có live Stripe webhook replay. Trước production cần chạy riêng profile MySQL với database disposable, queue worker thật và Stripe test mode.

## 15. Review/refactor magic values và single source of truth — 2026-09-11

### 15.1. Đã triển khai

| Khu vực | Quyết định chuẩn hóa | Kết quả |
|---|---|---|
| Payment provider | Thêm `PaymentProvider` (`Stripe`, `Fake`) và `Payment::forProvider()` / `PaymentWebhookEvent::forProvider()` | Không còn `where('provider', 'stripe')` lặp trong webhook/reconcile; provider runtime được resolve từ một config và map qua enum |
| Stripe webhook events | Thêm `StripeWebhookEventType` với nhóm event PaymentIntent, mapping sang `PaymentStatus` và rule `amount_received` | Controller và job dùng cùng một mapping; event type không còn được so sánh bằng các mảng text độc lập |
| Payment status/attempt | `PaymentStatus::reconciliationCandidates()`, `isRefundProtected()` và `PaymentAttemptStatus::fromPaymentStatus()` | Nhóm status và mapping attempt có một nguồn dùng chung cho reconcile/webhook |
| Webhook job | `StripeWebhookProcessingResult`, retry count/backoff và signature tolerance lấy từ config | Giảm magic number/string trong flow ingest/retry; trạng thái orphan/done/finalize rõ ràng |
| Payment idempotency | Prefix charge/refund attempt, Stripe HTTP timeout đưa về config; attempt mới dùng UUID bất biến | Cùng một attempt retry cùng key, attempt mới không tái sử dụng key cũ |
| Outbox | `OutboxEventType::channel()`, `idempotency_key`, `attempts`, `message_id`, cấu hình tries/lease/unique window | Logical idempotency, delivery attempt và message identity không bị trộn vào một field; log started/sent/failed có correlation key |
| Ticket verification | Grace period đưa về `booking.ticket.verification_grace_hours` | Controller và confirmation mail dùng cùng policy |

### 15.2. Đánh giá chuyên sâu

- Domain enum chỉ chứa các giá trị ổn định thuộc nghiệp vụ/provider contract; retry, timeout, lease và page/window là policy vận hành nên nằm trong `config/booking.php` hoặc `config/services.php`.
- Provider-specific literals như Stripe API path, Stripe response status và payload keys vẫn được giữ trong adapter/webhook boundary; không đưa chúng thành enum dùng chung cho domain vì sẽ làm rò rỉ chi tiết provider.
- Các translation key và message text vẫn là API nội bộ của UI/domain exception, không nên rải thành literal mới trong controller. Nếu số loại mutation tăng, nên gom message key của `BookingMutationGuard` vào value object/enum.
- Outbox đạt logical idempotency và có deterministic `message_id`, nhưng `Mail::send()` vẫn phụ thuộc khả năng dedupe thực tế của mail transport. Cần xác nhận adapter production hỗ trợ Message-ID/idempotency và chạy crash-window test trước khi tuyên bố exactly-once.
- Các literal trạng thái trả về từ payment provider trong `StripePaymentGateway` là external protocol; nếu hỗ trợ thêm provider thứ hai, cần tách mapper riêng thay vì mở rộng `PaymentStatus` bằng text provider.

### 15.3. Verification sau refactor

- `vendor/bin/pint --dirty --format agent`: **pass**.
- `composer analyse`: **pass**, PHPStan 0 errors.
- `php artisan test --compact`: **108 passed, 3 skipped, 438 assertions**.
- `git diff --check`: **pass**.

Residual risk vẫn còn: chưa chạy MySQL concurrency thật, chưa replay Stripe test-mode trong môi trường có queue worker thật và chưa mô phỏng crash sau mail provider accept. Đây là điều kiện release verification, không phải lý do để tiếp tục rải magic values vào application flow.

## 16. Rà soát full backend/frontend và translation — 2026-09-11

### Đã xử lý trong vòng này

- Bổ sung `lang/vi/auth.php`, `lang/vi/passwords.php` và hoàn thiện các rule Laravel trong `lang/vi/validation.php`.
- Bổ sung UI keys còn thiếu và key runtime `booking.messages.refund_in_progress` ở cả EN/VI.
- Thêm `TranslationParityTest` để bảo vệ cấu trúc key giữa `lang/en/*.php` và `lang/vi/*.php`.
- Đưa locale list và locale label về `config/app.php`; route, middleware, outbox, Blade language switcher và JS dùng cùng nguồn.
- Đưa payment terminal statuses và polling policy về `PaymentStatus`/`config/booking.php`; Blade truyền contract qua `data-*`, JS không còn sở hữu danh sách status/interval độc lập.
- Tạo [movie-booking-refactor-history.md](movie-booking-refactor-history.md) để lưu registry hàm đã refactor, nguồn chuẩn và lịch sử thay đổi.

### Kết quả rà soát

| Phạm vi | Kết quả |
|---|---|
| EN/VI PHP translation parity | Pass; không còn file/key tiếng Việt thiếu so với tiếng Anh |
| Backend enum/config references | Provider, status groups, webhook events, retry/timeout, locale và polling policy đã có nguồn chuẩn |
| Blade/frontend contract | Payment polling và language menu đọc config từ backend qua DOM contract |
| Backend tests | Pass |
| Frontend tests/lint/build | Pass |

Lưu ý: các string Stripe API status/payload key vẫn nằm ở provider adapter/webhook boundary có chủ đích; đó là external protocol, không phải domain value dùng chung. Các translation key động theo prefix (`booking.landing.steps.*`) cần tiếp tục được kiểm tra bằng contract test khi thêm step mới.

### Verification vòng 16

- `vendor/bin/pint --dirty --format agent`: **pass**.
- `composer analyse`: **pass**, PHPStan 0 errors.
- `php artisan test --compact`: **107 passed, 3 skipped, 435 assertions**.
- `TranslationParityTest` + payment reliability tests: **19 passed, 66 assertions**.
- `npm run test:frontend`: **5 passed**.
- `npm run check`: **pass** (ESLint + Vite build; Vite chỉ cảnh báo optional `fontaine`).
- `git diff --check`: **pass**.

## 17. Review kiến trúc Action/Orchestration/Transaction — 2026-09-11

### 17.1. Kết luận ngắn

Không nên tạo một `BookingService` hoặc `PaymentService` tổng quát chỉ để bọc toàn bộ Action hiện có. Cách đó chỉ đổi tên lớp, làm tăng indirection và không giải quyết transaction boundary, lock order hoặc idempotency.

Kiến trúc hiện tại đã có nhiều application workflow đúng nghĩa, nhưng tên `Action` đang chứa hai loại trách nhiệm khác nhau:

1. **Use-case/orchestration**: điều phối nhiều aggregate, transaction và/hoặc external provider.
2. **Atomic domain operation**: thực hiện một mutation cục bộ trong một transaction.

Điểm cần cải thiện là phân loại và quy ước contract, không phải chuyển tất cả sang `Service`.

Đánh giá hiện tại: **7/10 về phân lớp**, **6/10 về độ rõ transaction ownership**, **7/10 về khả năng idempotent/recovery**. Luồng nghiệp vụ đã có nền tảng tốt, nhưng chưa nên coi là hoàn toàn production-safe cho concurrency cao cho tới khi hoàn thành các thay đổi P1 và chạy MySQL multi-process thật.

### 17.2. Phân loại các workflow hiện tại

| Luồng | Đánh giá | Quyết định kiến trúc |
|---|---|---|
| `PayBooking` | Đã là orchestration: claim local → gọi provider ngoài transaction → apply result → finalize | Giữ như application workflow; không bọc thêm service. Có thể đổi tên thành `PayBookingWorkflow` ở đợt refactor riêng nếu muốn làm rõ semantics. |
| `RefundBooking` | Hai pha claim/refund/finalize, external call nằm ngoài transaction | Giữ làm workflow; bắt buộc duy trì idempotency và reconcile. |
| `ProcessStripeWebhook` | Async workflow sau ingest, cập nhật payment rồi finalize | Giữ ở Job + collaborator; controller không tham gia business flow. |
| `ReconcilePayment` | Provider lookup ngoài transaction, local state transition trong transaction, finalize sau đó | Giữ làm recovery workflow; status mapping phải dùng enum mapper chung. |
| `FinalizeSuccessfulPayment` | Atomic finalizer cho Booking/Payment/seat/item/outbox | Có thể xem là domain/application service chuyên biệt, nhưng không cần generic service. |
| `EditBookingSelection` | Orchestrates cancel old hold → hold new seats → sync concessions | Đúng là workflow, nhưng hiện đang gọi `CancelBooking` và `HoldSeats` có transaction riêng bên trong transaction ngoài. Đây là điểm cần refactor ưu tiên P1. |
| `HoldSeats`, `ApplyCoupon`, `AddConcessions`, `CancelBooking`, `ExpireBooking` | Atomic mutation với lock và invariant cục bộ | Giữ Action; bổ sung rõ public transaction boundary và internal transaction-free collaborator khi cần compose. |
| `ReleaseBookingResources` | Primitive giải phóng seat/item/stock/coupon, không tự mở transaction | Giữ làm collaborator cấp thấp; contract “caller phải sở hữu transaction và booking lock” phải được kiểm thử và ghi rõ. |

### 17.3. Vấn đề kiến trúc cần xử lý

#### P1 — Transaction lồng nhau và ownership chưa hiển thị đầy đủ

`EditBookingSelection` mở transaction ngoài nhưng gọi `CancelBooking::execute()` và `HoldSeats::execute()`, là các Action cũng mở transaction. Laravel xử lý nested transaction bằng savepoint/transaction level; điều này không tương đương với ba transaction độc lập và làm khó suy luận về:

- retry của deadlock;
- lock được giữ tới thời điểm nào;
- exception rollback ở savepoint hay rollback toàn workflow;
- observer/outbox event phát sinh trong transaction lồng;
- hành vi khi replacement hold thất bại sau khi resources cũ đã được giải phóng.

Khuyến nghị:

- Public Action độc lập vẫn tự sở hữu transaction.
- Khi compose trong workflow, dùng collaborator nội bộ có tên rõ như `cancelInOwnedTransaction()` / `holdInOwnedTransaction()` hoặc `executeForLockedBooking()`; collaborator này tuyệt đối không `begin/commit`.
- Không gọi một public Action có transaction từ một workflow đang giữ transaction, trừ khi contract đó được thiết kế và kiểm thử rõ ràng.
- Giữ thứ tự lock chuẩn: `Booking → Payment → PaymentAttempt → BookingItem/ScreeningSeat → Concession/Inventory → CouponReservation/Coupon`. Các flow không cần tất cả resource vẫn phải bắt đầu từ aggregate owner trước.

#### P1 — Observer đang che giấu business side-effect

`BookingObserver` tự tạo transition audit, outbox status event và log trong `created/updated`. Điều này tiện cho mutation đơn giản nhưng khiến mọi nơi gọi `$booking->save()` đều có thể phát sinh side-effect mà workflow không thể hiện trực tiếp.

Rủi ro:

- khó biết chính xác workflow nào phát sinh email/outbox;
- bulk update/query builder có thể bỏ qua observer;
- các lần save phụ trong một workflow có thể tạo event ngoài ý muốn;
- `Auth::id()` và locale hiện tại bị lấy ngầm từ runtime thay vì được truyền từ application boundary;
- test phải kiểm tra cả side-effect ẩn của model save.

Khuyến nghị trung hạn: chuyển status transition sang một `BookingTransitionRecorder`/`BookingEventRecorder` được gọi ngay tại nơi đổi trạng thái, hoặc phát domain event sau khi transition hợp lệ rồi để transactional outbox listener ghi record. Không nên giữ business-critical outbox chỉ dựa vào observer nếu hệ thống tiếp tục mở rộng.

#### P1 — Mapping provider status còn nguy cơ phân kỳ

`ReconcilePayment` đã dùng `PaymentAttemptStatus::fromPaymentStatus()`, nhưng `PayBooking::applyResult()` vẫn lặp mapping `PaymentStatus → PaymentAttemptStatus` bằng `match`. Nếu thêm status mới, hai nơi có thể không đồng bộ và tạo “lọt case”. Mapping provider text cũng đang tồn tại ở nhiều workflow.

Khuyến nghị:

- tạo một mapper boundary cho `PaymentResult`/`ProviderPaymentStatus` → `PaymentStatus`;
- dùng `PaymentAttemptStatus::fromPaymentStatus()` ở mọi nơi;
- provider-specific text chỉ tồn tại trong adapter/mapper, không lan sang workflow;
- thêm test exhaustive cho từng payment status và từng provider result.

#### P1 — Failure update sau external call chưa atomic hoàn toàn

Khi gateway ném exception trong `PayBooking`, payment attempt, payment và failure message được cập nhật qua nhiều `save()` riêng. Nếu process chết giữa các save, recovery có thể vẫn sửa được bằng `ReconcilePayment`, nhưng trạng thái quan sát tạm thời có thể không nhất quán.

Khuyến nghị tạo một mutation nhỏ như `MarkPaymentProviderUnknown` sở hữu một transaction duy nhất, cập nhật payment + latest attempt cùng nhau, sau đó dispatch reconcile `afterCommit`. Đây là service chuyên biệt có giá trị; không cần generic payment service.

#### P2 — Transaction retry policy đang rải literal

Nhiều Action dùng `DB::transaction(..., 3)`. Con số này hiện thống nhất về giá trị nhưng chưa có tên policy/contract. Nên gom thành config như `booking.database.transaction_attempts` hoặc một helper transaction policy khi bắt đầu cần thay đổi theo môi trường. Không cần tạo abstraction sớm nếu chưa có nhiều policy khác nhau, nhưng phải tránh để mỗi Action tự đổi số retry.

### 17.4. Mô hình phân lớp đề xuất

```text
HTTP Controller / Console Command
        |
        v
Application Use Case / Workflow
        |
        +-- transaction boundary duy nhất cho local invariant
        |       |
        |       +-- domain policy / enum / guard
        |       +-- transaction-free locked collaborators
        |       +-- repositories/Eloquent queries
        |
        +-- external provider call (ngoài DB transaction)
        |
        +-- after-commit Job/Event cho retryable side-effect
```

Quy ước folder nên được hiểu như sau, không bắt buộc mass-move ngay:

- `app/Actions/**`: application use cases và atomic mutations đang được gọi từ controller/job.
- `app/Support/**`: policy, guard, mapper, exception và adapter-independent primitive; không tự điều phối workflow lớn.
- `app/Jobs/**`: retry boundary/asynchronous workflow; không chứa HTTP concern.
- `app/Models/**`: persistence mapping, cast, relationship, query scope và state behavior nhỏ; không gọi provider/job để điều phối nghiệp vụ.
- `app/Infrastructure/**` hoặc adapter hiện hữu: Stripe/mail/queue/outbox implementation và protocol mapping.

Chỉ tạo `Services`/`Workflows` khi class đó có một boundary rõ: điều phối nhiều bước, nhiều aggregate, external IO hoặc recovery. Không tạo service chỉ để chuyển nguyên xi một method từ Action sang thư mục khác.

### 17.5. Luồng hoạt động sau khi chuẩn hóa

#### Booking hold/edit

`Controller → EditBookingSelection → transaction (lock user/booking/screening/seats theo order) → release old resources → create replacement hold → sync concessions → commit → response`.

Không gọi nested public transaction action. Nếu replacement thất bại, toàn bộ transaction rollback; không để trạng thái “old hold đã release nhưng new hold chưa tạo”.

#### Payment

`Controller/Job → PayBooking workflow → claim booking/payment/attempt → commit → gateway call → apply provider result transaction → FinalizeSuccessfulPayment transaction nếu succeeded → after-commit outbox/reconcile`.

Không giữ DB lock khi gọi Stripe. Payment `unknown` không được charge lại; chỉ reconcile bằng provider payment ID hoặc immutable attempt key.

#### Webhook

`Controller → verify/decode → IngestStripeWebhook transaction → commit → ProcessStripeWebhook job → lock Booking then Payment → apply event → commit → finalize transaction → mark event processed`.

Unknown provider payment phải giữ durable orphan record và retry/reconcile; event payload sai hoặc signature sai mới là trường hợp reject ngay.

### 17.6. Test matrix bắt buộc cho kiến trúc này

- `EditBookingSelection` rollback toàn bộ khi hold replacement thất bại sau bước release.
- Same-seat edit với `quantities=[]` không tạo nested transaction side-effect và hoàn stock đúng một lần.
- Deadlock/concurrency giữa edit, expire, pay, webhook, refund với MySQL thật; assert lock order và không có duplicate ticket/stock movement.
- Mọi status provider map được sang payment/attempt status hợp lệ; test status mới phải fail compile/test nếu chưa cập nhật mapper.
- Provider exception cập nhật payment và attempt atomically; reconcile sau commit vẫn chạy được khi process chết.
- Bulk/update trực tiếp không được là đường hợp lệ để thay đổi booking status nếu đã bỏ observer; test chỉ cho phép transition qua use case/domain transition boundary.
- Booking transition tạo đúng một audit/outbox event cho mỗi transition hợp lệ, không phụ thuộc số lần model `save()`.
- Outbox dispatch sau commit; rollback không để lại delivery job hoặc event “ma”.

### 17.7. Lộ trình triển khai đề xuất

1. **P1:** refactor `EditBookingSelection` dùng transaction-free collaborators, loại nested public Action transaction.
2. **P1:** gom toàn bộ payment result/status mapping vào enum/mapper dùng chung; thay `match` lặp trong `PayBooking`.
3. **P1:** tạo mutation atomic `MarkPaymentProviderUnknown` và test crash/partial-save boundary.
4. **P1:** chuẩn hóa và ghi rõ lock order ở các workflow payment/booking/refund/reconcile.
5. **P2:** thay `BookingObserver` business side-effect bằng explicit transition recorder/domain event + transactional outbox.
6. **P2:** gom transaction retry count thành policy config khi có nhu cầu vận hành thực tế.
7. **Release gate:** chạy MySQL multi-process, queue worker thật, Stripe test-mode replay và outbox crash-window trước production sign-off.

### 17.8. Kết luận review

Hệ thống không thiếu một “Service layer” tổng quát; hệ thống cần **orchestration có chủ đích** và **transaction contract rõ ràng**. `PayBooking`, `RefundBooking`, `ProcessStripeWebhook`, `ReconcilePayment` và `EditBookingSelection` đã là các workflow phù hợp để giữ vai trò điều phối. Việc cần làm tiếp là làm cho các workflow đó không gọi lẫn transaction boundary, không để observer che giấu side-effect và không lặp mapping/status policy.

Nếu thực hiện đúng lộ trình trên, cấu trúc sẽ dễ mở rộng hơn mà không rơi vào hai cực: Action “god class” hoặc Service layer trống chỉ đổi tên code.
