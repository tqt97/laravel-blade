# 04 — Kiến trúc tổng thể, flow chart và UML

## 1. Tổng quan kiến trúc

Hệ thống là modular monolith Laravel. Booking là orchestration context; catalog, commerce, inventory, payment và infrastructure sở hữu invariant riêng.

```mermaid
flowchart TB
    Browser[Blade + JS + Stripe.js]
    Routes[Web/User routes]
    Controllers[Controllers + Form Requests]
    Queries[Query objects]
    Actions[Domain actions]
    Models[Eloquent models + scopes]
    DB[(MySQL/SQLite database)]
    Queue[Queue workers]
    Scheduler[Laravel scheduler]
    Stripe[Stripe API + Webhooks]
    Outbox[Transactional outbox]
    Notifications[Mail/in-app notifications]

    Browser --> Routes
    Routes --> Controllers
    Controllers --> Queries
    Controllers --> Actions
    Queries --> Models
    Actions --> Models
    Models --> DB
    Actions --> Queue
    Actions --> Outbox
    Queue --> Stripe
    Stripe --> Routes
    Scheduler --> Queue
    Scheduler --> Actions
    Outbox --> Queue
    Queue --> Notifications
```

## 2. Code boundary

```text
app/
├── Actions/
│   ├── Booking/Checkout|Lifecycle|Payment
│   ├── Commerce/Concessions|Coupons
│   ├── Payment
│   └── Ticketing
├── DTO/Booking
├── Enums/
│   ├── Booking|Catalog|Commerce|Ticketing
│   ├── Inventory
│   ├── Payment
│   └── Infrastructure
├── Http/
│   ├── Controllers/Admin|User + public MovieController
│   ├── Requests
│   └── Middleware
├── Jobs/
├── Models/
│   ├── Booking|Catalog|Commerce
│   ├── Inventory|Payment|Infrastructure
│   └── User
├── Policies/Booking
├── Queries/Booking|Catalog|Commerce
└── Support/Booking|Payment
```

Boundary rules:

- Controller: authorize, validate, call query/action, return view/redirect/JSON.
- Query: read-only, eager loading/selected columns/payload.
- Action: mutation, transaction, lock, idempotency, state invariant.
- Job: at-least-once, idempotent handler, retry/backoff.
- Model scope: reusable query predicate, no side effect.
- Provider adapter: Stripe REST only; không quyết định booking lifecycle trực tiếp.

## 3. UML domain class

```mermaid
classDiagram
    User "1" --> "*" Booking
    Movie "1" --> "*" Screening
    ScreeningRoom "1" --> "*" Seat
    Screening "1" --> "*" ScreeningSeat
    Screening "1" --> "*" Booking
    Booking "1" --> "*" BookingItem
    ScreeningSeat "1" --> "0..1" BookingItem
    Booking "1" --> "0..1" Payment
    Payment "1" --> "*" PaymentAttempt
    Payment "1" --> "*" RefundAttempt
    Booking "1" --> "*" BookingConcession
    Concession "1" --> "*" BookingConcession
    Booking "1" --> "*" CouponReservation
    Booking "1" --> "*" BookingTransitionAudit
    Booking "1" --> "*" OutboxMessage

    class ScreeningSeat {
        screening_id
        seat_id
        status
        held_until
        held_by_booking_id
        price_minor_units
    }
    class Booking {
        user_id
        screening_id
        status
        expires_at
        subtotal_minor_units
        discount_minor_units
        total_minor_units
        idempotency_key
    }
    class Payment {
        payable_type
        payable_id
        provider_payment_id
        status
        amount_minor_units
        currency
        reconciliation_deadline
    }
```

Database schema source: `database/migrations/2026_09_10_130001_create_movie_domain_schema.php:1-400`, `130002_create_inventory_domain_schema.php`, `130007_create_payment_domain_schema.php:1-130`.

## 4. Booking sequence UML

```mermaid
sequenceDiagram
    participant U as User browser
    participant C as Controller
    participant A as Action
    participant DB as Database
    participant Q as Queue
    participant S as Stripe

    U->>C: Hold / pay / coupon / cancel
    C->>C: Authorize + FormRequest
    C->>A: Typed input/DTO
    A->>DB: Transaction + row locks
    DB-->>A: Aggregate state
    A-->>C: Domain result/exception
    C-->>U: View/redirect/JSON
    A->>Q: afterCommit job/outbox
    Q->>S: Provider request/reconcile
    S->>C: Signed webhook
    C->>Q: Persist event + dispatch
    Q->>A: Finalize/reconcile idempotently
```

## 5. Payment state UML

```mermaid
stateDiagram-v2
    [*] --> pending
    pending --> processing
    pending --> requires_payment_method
    pending --> requires_action
    pending --> succeeded
    processing --> requires_action
    processing --> requires_payment_method
    processing --> succeeded
    processing --> unknown
    requires_action --> processing
    requires_action --> succeeded
    requires_action --> unknown
    requires_payment_method --> processing
    requires_payment_method --> succeeded
    unknown --> processing
    unknown --> succeeded
    unknown --> requires_refund
    succeeded --> requires_refund
    succeeded --> refunding
    refunding --> refunded
    refunding --> requires_refund
    requires_refund --> refunding
```

Implementation: `app/Enums/Payment/PaymentStatus.php`, `app/Enums/Payment/PaymentAttemptStatus.php`, `app/Actions/Payment/TransitionPayment.php`.

## 6. Deployment/runtime topology

### Local

```text
composer run dev
├─ php artisan dev
│  ├─ Laravel HTTP server
│  ├─ schedule:work
│  └─ queue:work --tries=3 --timeout=90
└─ npm run dev:stripe
   └─ stripe listen --forward-to http://127.0.0.1:8000/webhooks/stripe
```

Script source: `composer.json:scripts.dev`, `package.json:scripts.dev:stack`, `routes/console.php:22-23`.

### Production

- HTTP workers xử lý request.
- Queue worker xử lý webhook, reconciliation, outbox và notification.
- Một scheduler leader chạy các command định kỳ.
- MySQL/PostgreSQL phải hỗ trợ transaction/row lock đúng production engine.
- Stripe Dashboard webhook endpoint hoặc relay ổn định; không phụ thuộc Stripe CLI local.

## 7. Availability data contract

Public screening availability response:

```json
{
  "seats": {
    "101": {
      "available": true,
      "owned_by_current_booking": false
    }
  },
  "concessions": {
    "5": {
      "stock": 4,
      "selected": 0,
      "max": 4
    }
  },
  "availability_version": "sha256...",
  "server_now": "2026-09-14T...+07:00"
}
```

Endpoint: `routes/web.php:24-26`; implementation `MovieController@availability` and `ScreeningAvailabilityQuery`; response `Cache-Control: no-store`.

Booking combo availability contract tương tự nhưng `selected` lấy từ booking hiện tại: `routes/user.php:43`, `BookingConcessionController.php:21-35`.

