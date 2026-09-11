<?php

return [
    // Single source of truth for booking limits. Keep backend validation and
    // frontend data attributes mapped to these values; all durations are minutes.
    'limits' => [
        // How long a held booking remains reservable before expiry.
        'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 10),

        // Maximum number of seats allowed in one booking request.
        'max_seats' => (int) env('BOOKING_MAX_SEATS', 10),

        // Total combo quantity is capped at tickets x this value.
        'max_combos_per_ticket' => (int) env('BOOKING_MAX_COMBOS_PER_TICKET', 3),

        // Safety cap for one concession line, even when stock is unlimited.
        'max_combo_quantity' => (int) env('BOOKING_MAX_COMBO_QUANTITY', 20),

        // Maximum seats generated for one row when an admin creates a room.
        'max_seats_per_row' => (int) env('BOOKING_MAX_SEATS_PER_ROW', 50),
    ],

    // Page-size and preview policies for cinema booking screens.
    'listing' => [
        'concessions_per_page' => (int) env('BOOKING_CONCESSIONS_PER_PAGE', 60),
        'screening_preview_limit' => (int) env('BOOKING_SCREENING_PREVIEW_LIMIT', 3),
        'movies_per_page' => (int) env('BOOKING_MOVIES_PER_PAGE', 12),
        'screenings_per_page' => (int) env('BOOKING_SCREENINGS_PER_PAGE', 18),
        'admin_page_size' => (int) env('BOOKING_ADMIN_PAGE_SIZE', 20),
        'user_bookings_per_page' => (int) env('BOOKING_USER_BOOKINGS_PER_PAGE', 10),
        'dashboard_recent_bookings' => (int) env('BOOKING_DASHBOARD_RECENT_BOOKINGS', 5),
        'notification_preview_limit' => (int) env('BOOKING_NOTIFICATION_PREVIEW_LIMIT', 10),
    ],

    // A screening must start after this buffer to remain bookable.
    // Set to 0 to allow booking until the screening start time.
    'minimum_lead_minutes' => (int) env('BOOKING_MINIMUM_LEAD_MINUTES', 15),

    // Do not allow booking screenings beyond this number of days from now.
    'maximum_horizon_days' => (int) env('BOOKING_MAXIMUM_HORIZON_DAYS', 90),

    // Unpaid bookings can be cancelled before screening start minus this buffer.
    // 0 means no cancellation deadline is applied.
    'cancellation_deadline_minutes' => (int) env('BOOKING_CANCELLATION_DEADLINE_MINUTES', 0),

    // Check-in opens this many minutes before the screening starts.
    'check_in_open_minutes' => (int) env('BOOKING_CHECK_IN_OPEN_MINUTES', 120),

    'ticket' => [
        'verification_grace_hours' => (int) env('BOOKING_TICKET_VERIFICATION_GRACE_HOURS', 24),
    ],

    'payment' => [
        // Currency and provider used by booking/payment snapshots.
        'currency' => env('BOOKING_CURRENCY', 'USD'),
        'amount_minor_units' => (int) env('BOOKING_AMOUNT_MINOR_UNITS', 0),
        // Explicit provider selection. Fake is only valid in local/testing;
        // production must fail closed when Stripe is not configured.
        'provider' => env('BOOKING_PAYMENT_PROVIDER', 'stripe'),
        'attempt_key_prefix' => env('BOOKING_PAYMENT_ATTEMPT_KEY_PREFIX', 'booking-payment-'),
        'webhook_tries' => (int) env('BOOKING_PAYMENT_WEBHOOK_TRIES', 10),
        'webhook_backoff_seconds' => [5, 10, 20, 30, 60],
        'webhook_signature_tolerance_seconds' => (int) env('BOOKING_PAYMENT_WEBHOOK_SIGNATURE_TOLERANCE_SECONDS', 300),
        // Maximum time a payment may remain in a processing state.
        'processing_timeout_minutes' => (int) env('BOOKING_PAYMENT_PROCESSING_TIMEOUT_MINUTES', 15),
        'reconciliation_tries' => (int) env('BOOKING_PAYMENT_RECONCILIATION_TRIES', 10),
        'reconciliation_backoff_seconds' => [5, 10, 20, 30, 60],
        'refund_idempotency_key_prefix' => env('BOOKING_PAYMENT_REFUND_IDEMPOTENCY_KEY_PREFIX', 'booking-refund-'),
        'status_poll_interval_ms' => (int) env('BOOKING_PAYMENT_STATUS_POLL_INTERVAL_MS', 3000),
        'status_error_retry_interval_ms' => (int) env('BOOKING_PAYMENT_STATUS_ERROR_RETRY_INTERVAL_MS', 5000),
        'status_max_unknown_attempts' => (int) env('BOOKING_PAYMENT_STATUS_MAX_UNKNOWN_ATTEMPTS', 20),
        'status_delayed_notice_ms' => (int) env('BOOKING_PAYMENT_STATUS_DELAYED_NOTICE_MS', 45000),
    ],

    'observability' => [
        // Queries slower than this threshold are reported to logs/metrics.
        'slow_query_ms' => (int) env('BOOKING_SLOW_QUERY_MS', 200),
    ],

    // A delivery lease can be reclaimed when a queue worker dies mid-send.
    'outbox' => [
        'delivery_lease_minutes' => (int) env('BOOKING_OUTBOX_DELIVERY_LEASE_MINUTES', 60),
        'tries' => (int) env('BOOKING_OUTBOX_TRIES', 3),
        'unique_for_seconds' => (int) env('BOOKING_OUTBOX_UNIQUE_FOR_SECONDS', 3600),
    ],

    // Default cinema operating hours. Values use 24-hour HH:MM notation.
    // Screening creation can apply these rules when operating-hours validation is enabled.
    'operating_hours' => [
        'monday' => ['open' => '00:00', 'close' => '24:00'],
        'tuesday' => ['open' => '00:00', 'close' => '24:00'],
        'wednesday' => ['open' => '00:00', 'close' => '24:00'],
        'thursday' => ['open' => '00:00', 'close' => '24:00'],
        'friday' => ['open' => '00:00', 'close' => '24:00'],
        'saturday' => ['open' => '00:00', 'close' => '24:00'],
        'sunday' => ['open' => '00:00', 'close' => '24:00'],
    ],
];
