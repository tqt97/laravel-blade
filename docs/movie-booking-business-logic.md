# Business Logic — Movie Booking

Tài liệu này chỉ mô tả nghiệp vụ của tính năng đặt vé xem phim. Đây là tài liệu tham chiếu để kiểm tra hành vi sản phẩm và bổ sung quy tắc mới trong tương lai. Chi tiết về controller, model, route, queue hoặc cấu trúc thư mục được ghi ở tài liệu kiến trúc riêng.

## 0. Phân chia nghiệp vụ theo domain

| Domain | Phạm vi nghiệp vụ |
|---|---|
| Movie | Catalog phim, showtime, ghế, booking, ticket, coupon và combo catalog. |
| Inventory | Tồn kho combo, reserve/release/refund stock, adjustment và audit biến động. |
| Payments | Claim/charge/finalize, webhook, provider reconciliation, payment timeout và refund. |
| Infrastructure | Outbox, email, notification, reminder, delivery retry và realtime polling. |

Movie là domain điều phối trải nghiệm đặt vé; các domain còn lại cung cấp invariant riêng. Một thay đổi nghiệp vụ phải cập nhật đúng domain owner, test liên quan và tài liệu traceability.

## 1. Mục tiêu nghiệp vụ

Hệ thống cho phép người dùng:

1. Xem phim đang hoạt động và các suất chiếu còn nhận đặt vé.
2. Chọn ghế còn trống.
3. Chọn combo bắp nước trong cùng quá trình đặt vé.
4. Giữ ghế trong một khoảng thời gian giới hạn.
5. Đăng nhập sau khi chọn ghế mà không mất lựa chọn.
6. Kiểm tra lại thông tin, áp dụng coupon và thanh toán.
7. Nhận vé điện tử có QR code.
8. Quản lý booking, nhận thông báo và được nhắc trước giờ chiếu.

Mục tiêu cốt lõi là không bán trùng ghế, không bán vượt tồn kho combo, không phát hành vé khi thanh toán chưa hợp lệ và không thu tiền cho một booking đã hết hạn.

## 2. Khái niệm nghiệp vụ

| Khái niệm | Ý nghĩa |
|---|---|
| Movie | Bộ phim được cung cấp trên hệ thống. |
| Showtime | Một suất chiếu cụ thể của một bộ phim tại một phòng chiếu. |
| Seat | Ghế vật lý thuộc phòng chiếu. |
| Screening seat | Ghế của một showtime, có trạng thái và giá riêng tại suất đó. |
| Booking | Đơn đặt vé của một người dùng cho một showtime. |
| Booking item | Một vé/ghế thuộc booking. |
| Combo | Sản phẩm ăn uống được bán kèm vé. |
| Coupon | Mã giảm giá áp dụng cho booking nếu đủ điều kiện. |
| Hold | Quyền giữ tạm thời các ghế đã chọn trước khi thanh toán. |
| Ticket | Vé được phát hành sau khi payment hợp lệ và booking được xác nhận. |

Một ghế được xem là cùng một tài nguyên nếu thuộc cùng một showtime. Cùng số ghế ở hai showtime khác nhau không xung đột.

## 3. Thời gian và timezone

- Toàn bộ thời gian nghiệp vụ tuân theo `config('app.timezone')`.
- PHP, Eloquent, database datetime, scheduler, queue worker, email và giao diện phải dùng cùng timezone này.
- Không dùng timezone của browser để quyết định booking còn hạn hay showtime còn bookable.
- Không dùng timezone riêng của phòng chiếu để tạo ra một quy tắc nghiệp vụ khác.
- Khi hiển thị thời gian, dùng cùng timezone ứng dụng để người dùng thấy thống nhất.
- Mọi so sánh thời gian phải dùng cùng một clock nghiệp vụ.

Lý do: nếu thời gian tạo hold và thời gian kiểm tra hold được diễn giải ở hai timezone khác nhau, booking mới tạo có thể bị xem là hết hạn ngay lập tức.

## 4. Điều kiện showtime được đặt vé

Một showtime chỉ được đặt khi đồng thời thỏa mãn:

- Phim đang active.
- Phòng chiếu đang active.
- Showtime ở trạng thái scheduled.
- Thời gian bắt đầu lớn hơn thời điểm hiện tại cộng thời gian tối thiểu trước giờ chiếu.
- Showtime không vượt quá khoảng thời gian cho phép đặt trước.

Showtime không còn bookable nếu:

- Đã bắt đầu hoặc nằm trong khoảng thời gian tối thiểu trước giờ chiếu.
- Đã vượt quá horizon đặt vé.
- Phim hoặc phòng chiếu bị vô hiệu hóa.
- Showtime không còn ở trạng thái scheduled.

Lý do của minimum lead time là tránh nhận booking quá sát giờ chiếu khi hệ thống không còn đủ thời gian giữ ghế, thanh toán và vận hành tại rạp.

## 5. Luồng chọn ghế và giữ ghế

### 5.1. Chọn ghế

- Người dùng có thể xem sơ đồ ghế công khai trước khi đăng nhập.
- Chỉ ghế available mới được chọn mới.
- Ghế sold không thể chọn.
- Ghế đang được người khác hold không thể chọn.
- Ghế thuộc hold đang hoạt động của chính người dùng được hiển thị là đã chọn và có thể tiếp tục.
- Trạng thái trên DOM chỉ là dữ liệu hiển thị; trạng thái cuối cùng luôn phải kiểm tra lại trên server.

### 5.2. Giới hạn

- Một booking tối đa 10 ghế.
- Frontend chặn ngay khi người dùng chọn vượt giới hạn.
- Backend vẫn phải validate lại giới hạn để chống bypass JavaScript.

### 5.3. Khi tạo hold

Hệ thống phải thực hiện nguyên tử:

1. Khóa phạm vi người dùng cần thiết để tránh hai request đồng thời tạo hold cạnh tranh.
2. Khóa showtime và các screening seat theo thứ tự ổn định.
3. Kiểm tra showtime còn bookable.
4. Kiểm tra toàn bộ ghế tồn tại trong showtime.
5. Giải phóng các hold đã hết hạn trên các ghế được yêu cầu.
6. Từ chối toàn bộ request nếu chỉ cần một ghế không còn khả dụng.
7. Tạo booking ở trạng thái `held`.
8. Đánh dấu ghế là `held` và gắn ownership trực tiếp với booking.
9. Tạo booking item cho từng ghế.
10. Đặt thời điểm hết hạn cho booking và từng ghế.

Không được tạo booking một phần. Nếu một ghế thất bại, mọi thay đổi trong request phải rollback.

### 5.4. Idempotency

Request giữ ghế phải có idempotency key.

- Gửi lại cùng key và cùng nội dung phải trả về booking cũ.
- Gửi lại cùng key nhưng khác showtime/ghế phải bị từ chối.
- Retry do mạng hoặc double-click không được tạo thêm booking.

## 6. Guest và login resume

Guest được phép chọn ghế và combo trước khi đăng nhập.

Khi yêu cầu giữ ghế cần authentication:

- Lưu showtime, danh sách ghế, combo và idempotency key vào session.
- Redirect đến login.
- Sau login quay lại đúng public URL của showtime.
- Ghế cũ phải hiển thị active.
- Nút tiếp tục phải active ngay nếu lựa chọn vẫn hợp lệ.
- Không bắt người dùng chọn lại ghế chỉ vì vừa đăng nhập.

Nếu showtime không còn tồn tại hoặc không còn bookable khi resume:

- Không tạo hold mới.
- Xóa dữ liệu resume không còn hợp lệ.
- Đưa người dùng về danh sách phim.

Resume phải atomic: hoặc tạo được booking và áp dụng combo đầy đủ, hoặc rollback toàn bộ.

## 7. Chỉnh sửa lựa chọn

Người dùng có thể quay lại public showtime để chỉnh sửa ghế và combo.

### 7.1. Giữ nguyên ghế

- Giữ nguyên booking hiện tại.
- Không tạo hold mới.
- Cập nhật combo theo lựa chọn mới.
- Không trừ tồn kho combo hai lần.
- Nút tiếp tục phải active dù người dùng không thay đổi gì.

### 7.2. Đổi ghế

- Kiểm tra ghế mới trong transaction.
- Nếu ghế mới hợp lệ, hủy hold cũ và tạo hold mới.
- Giải phóng ghế cũ.
- Booking mới không được thanh toán nhầm cho ghế cũ.
- Nếu ghế mới không hợp lệ, giữ nguyên booking và hold cũ.

Không được hủy hold cũ trước khi chắc chắn có thể giữ được lựa chọn mới; nếu không, lỗi chọn ghế mới có thể làm mất lựa chọn hợp lệ của người dùng.

### 7.3. Booking đang thanh toán

Booking ở trạng thái `pending_payment` không được chỉnh sửa ghế hoặc combo. Người dùng phải tiếp tục payment hiện tại hoặc chờ hệ thống xử lý/reconcile.

Lý do: thay đổi tài nguyên trong lúc payment đang xử lý có thể làm số tiền, ghế và dữ liệu provider không còn khớp.

## 8. Combo và tồn kho

- Combo chỉ được chọn khi đang active.
- Combo phải cùng currency với booking.
- Giá combo được snapshot vào booking khi thêm vào.
- Thay đổi giá combo sau đó không làm thay đổi booking đã tạo.
- Không được mua combo vượt tồn kho.
- Một vé được đặt tối đa 3 combo.
- Tổng số combo tối đa bằng `số vé × 3`.
- Mỗi dòng combo không vượt giới hạn số lượng cấu hình.
- Frontend tăng/giảm số lượng phải cập nhật tổng tiền ngay.
- Backend phải kiểm tra lại quantity, tồn kho và giới hạn trong transaction.

Khi cập nhật combo:

- Quantity là quantity cuối cùng mong muốn, không phải số lượng cộng thêm mù.
- Tăng quantity thì trừ phần chênh lệch tồn kho.
- Giảm quantity thì hoàn phần chênh lệch.
- Xóa dòng khi quantity về 0.
- Nếu booking hết hạn hoặc bị hủy, hoàn toàn bộ combo đã giữ.

Mọi thay đổi tồn kho phải có movement idempotency key để retry không hoàn/trừ kho hai lần.

## 9. Giá và coupon

Booking phải thể hiện rõ:

- Tiền ghế.
- Tiền combo.
- Tiền giảm giá.
- Tổng tiền phải thanh toán.
- Currency.

Coupon chỉ hợp lệ khi:

- Mã tồn tại.
- Coupon đang active.
- Nằm trong thời gian hiệu lực.
- Đúng currency.
- Chưa vượt usage limit.
- Giá trị giảm không vượt subtotal theo quy tắc sản phẩm.

Khi apply coupon:

- Tạo reservation để chống vượt usage limit dưới concurrency.
- Tính lại subtotal, discount và total atomically.
- Không áp dụng coupon hai lần cho cùng booking.
- Khi booking hết hạn/hủy, reservation phải được release.
- Khi booking confirmed, usage được giữ lại theo quy tắc coupon.

## 10. Trạng thái booking

| Trạng thái | Ý nghĩa |
|---|---|
| `held` | Ghế đang được giữ, chưa bắt đầu payment. |
| `pending_payment` | Payment đã bắt đầu; không được sửa ghế/combo. |
| `confirmed` | Payment hợp lệ và booking đã được xác nhận. |
| `cancelled` | Booking bị hủy trước khi hoàn tất. |
| `expired` | Hold hết hạn hoặc booking không còn đủ điều kiện tiếp tục. |
| `completed` | Vé đã hoàn tất sử dụng/quy trình sau suất chiếu. |
| `no_show` | Người dùng không đến sử dụng vé. |

Chuyển trạng thái hợp lệ:

```text
held -> pending_payment -> confirmed
held -> confirmed
held -> cancelled
held -> expired
pending_payment -> confirmed
pending_payment -> cancelled
pending_payment -> expired
confirmed -> cancelled
confirmed -> completed
confirmed -> no_show
```

Trạng thái terminal không được quay ngược về `held` hoặc `pending_payment`.

## 11. Hết hạn hold

Hold hết hạn khi thời điểm hiện tại lớn hơn hoặc bằng `expires_at`.

Khi hết hạn, hệ thống phải atomically:

1. Chuyển booking sang `expired`.
2. Giải phóng các ghế đang thuộc booking.
3. Hủy các booking item chưa sử dụng.
4. Hoàn tồn kho combo.
5. Release coupon reservation.
6. Phát thông báo `booking_expired` một lần.

Hết hạn có thể được phát hiện bởi:

- Scheduler.
- User truy cập lại checkout.
- Payment/finalize race.

Ba cách trên phải cho cùng một kết quả. Nếu đã expired, lần xử lý sau không được release tài nguyên hoặc gửi thông báo lần hai.

Thông báo tiếng Việt:

> Lượt giữ chỗ cho phim :movie đã hết hạn. Ghế và combo đã được giải phóng.

Nếu showtime cũng đã bắt đầu hoặc không còn bookable, action tiếp theo là danh sách phim. Nếu showtime còn hợp lệ, action tiếp theo là đúng trang showtime để chọn lại.

## 12. Payment

### 12.1. Điều kiện bắt đầu payment

- Booking thuộc user hiện tại.
- Booking ở `held` hoặc `pending_payment` hợp lệ.
- Hold chưa hết hạn.
- Showtime chưa qua thời điểm cho phép payment/finalize.
- Giá, currency và combo đã được kiểm tra lại.

### 12.2. Claim, charge và finalize

Payment gồm ba ý định nghiệp vụ riêng:

1. Claim: khóa booking/payment và quyết định request này có được charge hay đang có payment xử lý.
2. Charge: gọi payment provider với idempotency key của PaymentAttempt.
3. Finalize: kiểm tra response provider rồi xác nhận booking, chuyển ghế sang sold và phát hành ticket.

Không được đánh dấu payment thành công giả nếu provider không trả provider ID hợp lệ.

Provider response phải kiểm tra:

- Provider payment ID.
- Amount.
- Currency.
- Payable/booking metadata.
- Provider status.

Nếu payment succeeded nhưng hold đã hết hạn hoặc showtime không còn hợp lệ:

- Không phát hành vé.
- Không bán ghế.
- Chuyển payment sang trạng thái cần refund.
- Đưa vào quy trình refund/reconciliation.

### 12.3. Payment status

| Trạng thái | Ý nghĩa |
|---|---|
| `pending` | Chưa charge hoặc đang chờ bắt đầu. |
| `processing` | Đã claim và đang gọi provider. |
| `requires_action` | Cần user hoàn tất xác thực payment. |
| `succeeded` | Provider xác nhận thành công hợp lệ. |
| `failed` | Provider từ chối rõ ràng. |
| `unknown` | Không chắc kết quả, cần reconcile. |
| `requires_refund` | Đã nhận tiền nhưng không thể finalize booking. |
| `refunded` | Refund đã được provider xác nhận. |

Payment polling dừng ở trạng thái terminal như succeeded, failed, refunded hoặc requires_refund. Trạng thái unknown tiếp tục chờ reconcile.

## 13. Webhook, retry và payment orphan

- Webhook có thể đến nhiều lần và không theo thứ tự.
- Chỉ cho phép monotonic transition; webhook cũ không được ghi đè trạng thái mới hơn.
- Mỗi PaymentAttempt có idempotency key riêng.
- Mọi payment transition phải đồng bộ payment attempt tương ứng.
- Nếu charge đã claim nhưng process chết trước khi có provider ID, attempt chuyển `unknown` và được reconcile.
- Nếu provider ID đã có nhưng payment local chưa cập nhật, recovery phải backfill rồi reconcile.
- Không tự đoán payment thành công chỉ vì request charge đã được gửi.

## 14. Refund

Refund bắt buộc khi tiền đã nhận nhưng booking không thể phát hành vé hoặc user hủy booking theo chính sách.

- Refund phải idempotent theo logical refund key.
- Retry không tạo refund provider thứ hai.
- Chỉ đánh dấu refunded khi provider trả refund ID hợp lệ.
- Nếu provider đã refund nhưng local transaction lỗi, lần retry sau phải hoàn thiện local state mà không restore stock lần hai.
- Ghế, combo và ticket chỉ được cập nhật local một lần.
- Refund unknown phải được đưa vào reconciliation/retry queue.

## 15. Ticket và check-in

Ticket chỉ được phát hành khi:

- Payment succeeded hợp lệ.
- Booking confirmed.
- Ghế được chuyển từ held sang sold.
- Booking item có ticket code.

Ticket có QR code và link xác thực có thời hạn.

Ticket verify chỉ thành công nếu booking ở `confirmed` hoặc `completed`, và ticket ở `issued` hoặc `checked_in`.

Check-in chỉ được phép trong khoảng thời gian cấu hình quanh showtime. Một ticket không được check-in thành công hai lần.

## 16. Hủy booking

- Booking unpaid chỉ được hủy nếu chưa vượt cancellation deadline.
- Booking confirmed phải đi qua refund nếu chính sách cho phép hủy.
- Booking terminal không được hủy lại.
- Hủy booking phải release đúng tài nguyên còn thuộc booking.
- Hủy không được giải phóng ghế đã bị booking khác sở hữu.
- Lý do hủy phải được lưu để audit.

## 17. Thông báo

Thông báo in-app được tạo qua outbox để không mất event khi transaction chính thành công nhưng worker/email gặp lỗi.

Các thông báo hiện có:

- `booking_confirmed`: đặt vé và thanh toán thành công.
- `booking_expired`: lượt giữ chỗ hết hạn.
- `booking_reminder`: nhắc trước khoảng 2 giờ đến showtime.

Quy tắc:

- Mỗi event của một booking chỉ tạo một notification logic.
- Có badge số chưa đọc.
- User có thể đánh dấu đã đọc, xóa từng thông báo hoặc xóa tất cả.
- Notification hiển thị thời gian tạo theo timezone ứng dụng.
- Notification không được lộ thông tin booking của user khác.

## 18. Email

Email xác nhận chỉ gửi khi booking đã confirmed và payment đã thành công.

Email cần có:

- Tên phim.
- Ngày giờ chiếu.
- Phòng chiếu.
- Danh sách ghế.
- QR cho từng vé.
- Link xem vé/booking.
- Chi tiết tiền ghế, combo và tổng thanh toán.

Không gửi email “booking created” riêng nếu booking chưa thanh toán thành công. Một booking thành công được hiểu là booking đã thanh toán và xác nhận.

## 19. Quy tắc concurrency và tính toàn vẹn

Hệ thống phải an toàn trong các tình huống:

- Hai người cùng chọn một ghế.
- Một user mở hai tab chỉnh sửa cùng booking.
- User bấm submit nhiều lần.
- Scheduler expire chạy cùng lúc user mở checkout.
- Payment webhook đến cùng lúc payment request.
- Hai webhook đến ngược thứ tự.
- Hai user mua combo cuối cùng trong kho.
- Refund retry sau khi database transaction lỗi.
- Login resume chạy lặp lại.

Nguyên tắc xử lý:

- Khóa row theo thứ tự ổn định.
- Validate lại mọi dữ liệu ở server.
- Transaction bao phủ thay đổi liên quan.
- Dùng idempotency cho payment, inventory, outbox và notification.

Inventory là domain riêng: Movie sở hữu catalog combo (`concessions`), còn Inventory sở hữu ledger biến động tồn kho và audit điều chỉnh. Hai phần dùng cùng transaction với booking nhưng không trộn namespace, để có thể mở rộng nhập kho, reconciliation và báo cáo tồn kho độc lập.
- Không tin ownership cũ trong DOM.
- Không phát hành tài nguyên nếu trạng thái hiện tại không còn phù hợp.

## 20. Ma trận edge case bắt buộc

| Tình huống | Kết quả đúng |
|---|---|
| Chọn hơn 10 ghế | Frontend chặn sớm, backend từ chối nếu bị bypass. |
| Chọn hơn 3 combo/vé | Frontend giới hạn, backend từ chối. |
| Ghế vừa bị user khác giữ | Không tạo booking một phần; giữ lựa chọn cũ của owner nếu đang edit. |
| Refresh checkout | Vẫn thấy booking và countdown chính xác. |
| Guest login resume | Giữ đúng ghế và combo nếu showtime còn hợp lệ. |
| Resume sau khi showtime bị xóa | Về danh sách phim. |
| Mở checkout sau khi hold hết hạn | Release tài nguyên và hiển thị màn hình hết hạn. |
| Hold hết hạn nhưng showtime còn | Cho chọn lại đúng showtime. |
| Hold hết hạn và showtime đã bắt đầu | Về danh sách phim. |
| Payment succeeded sau expiry | Không phát hành vé; yêu cầu refund. |
| Provider không trả ID | Không confirmed; chuyển unknown/reconcile. |
| Webhook gửi lặp | Không đổi kết quả, không phát hành vé trùng. |
| Refund retry | Không double refund hoặc double restore stock. |
| Combo hết kho khi pay | Payment không finalize; booking không ghi nhận combo vượt kho. |
| Coupon hết lượt đồng thời | Chỉ request hợp lệ đầu tiên giữ được reservation. |
| User xem booking người khác | Bị từ chối truy cập. |
| Check-in lần hai | Bị từ chối. |

## 21. Checklist khi bổ sung nghiệp vụ mới

Mỗi nghiệp vụ mới phải trả lời:

1. Actor nào được thực hiện?
2. Điều kiện trước khi thực hiện là gì?
3. Trạng thái nào bị ảnh hưởng?
4. Tài nguyên nào bị giữ, trừ hoặc hoàn?
5. Có cần transaction không?
6. Có thể retry không?
7. Idempotency key nằm ở đâu?
8. Nếu request chạy đồng thời thì kết quả đúng là gì?
9. Nếu thất bại giữa chừng thì rollback/recovery thế nào?
10. User nhìn thấy thông báo/UI nào?
11. Có cần email, notification hoặc outbox không?
12. Có cần cập nhật giới hạn, translation, test và tài liệu không?

## 22. Lịch sử cập nhật

| Ngày | Nội dung |
|---|---|
| 2026-09-10 | Tạo tài liệu nghiệp vụ độc lập cho movie booking. |
| 2026-09-10 | Bổ sung guest resume, edit booking, combo limit, coupon, payment recovery, refund, notification expiry và timezone. |
| 2026-09-10 | Chuẩn hóa tài liệu theo các domain Movie, Inventory, Payments và Infrastructure. |
| 2026-09-10 | Chuẩn hóa controller/query/action boundary, dùng lại model scope và đưa giới hạn hiển thị vào booking config. |
