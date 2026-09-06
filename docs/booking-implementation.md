# Booking implementation

Booking đã được chuyển hoàn toàn sang domain đặt vé phim. Tài liệu canonical về model, quan hệ, UML, concurrency, payment, ticket, UI, testing và vận hành nằm tại [movie-booking-architecture.md](movie-booking-architecture.md).

Không sử dụng lại flow `BookableResource` hoặc booking theo period; mọi order mới phải gắn với một `Screening` và có một hoặc nhiều `BookingItem`.
