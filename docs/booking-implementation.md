# Booking implementation

Booking đã được chuyển hoàn toàn sang domain đặt vé phim. Tài liệu canonical về model, quan hệ, UML, concurrency, payment, ticket, UI, testing và vận hành nằm tại [movie-booking-architecture.md](movie-booking-architecture.md).

Không sử dụng lại flow `BookableResource` hoặc booking theo period; mọi order mới phải gắn với một `Screening` và có một hoặc nhiều `BookingItem`.

## Domain map

- Booking/Catalog/Commerce: `app/Actions/{Booking,Catalog,Commerce}`, `app/Models/{Booking,Catalog,Commerce}`, `app/Enums/{Booking,Catalog,Commerce}`, `app/Policies/Booking`, `app/Queries/{Booking,Commerce}`.
- Inventory: `app/Models/Inventory`, `app/Enums/Inventory`, migration `2026_09_10_130002_create_inventory_domain_schema.php`.
- Payment: `app/Models/Payment`, `app/Enums/Payment`, migration `2026_09_10_130003_create_payment_domain_schema.php`.
- Infrastructure: `app/Models/Infrastructure`, `app/Enums/Infrastructure`, migration `2026_09_10_130004_create_infrastructure_domain_schema.php`.

Read/query boundary:

- `AvailableConcessionsQuery`: catalog combo khả dụng và live stock payload.
- `ScreeningBookingContextQuery`: active hold và seat ownership lấy từ database.
- `UserBookingsQuery`: danh sách booking, dashboard upcoming và recent booking.

Mutation boundary:

- `EditBookingSelection`, `HoldSeats`, `SyncBookingConcessions`, `PayBooking`, `FinalizeSuccessfulPayment`, `CancelBooking`, `ExpireBooking` và `RefundBooking` sở hữu các invariant booking.
- `CreateConcession` và `UpdateConcession` sở hữu transaction inventory ledger/audit khi admin tạo hoặc điều chỉnh combo.
- Controller chỉ authorize, nhận dữ liệu đã validate, gọi boundary phù hợp và trả response.

Tài liệu chi tiết:

- [Architecture và boundary](movie-booking-architecture.md)
- [Business logic thuần](movie-booking-business-logic.md)
- [Database và quan hệ](movie-booking-database.md)
