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
    ],

    // Page-size and preview policies for cinema booking screens.
    'listing' => [
        'concessions_per_page' => (int) env('BOOKING_CONCESSIONS_PER_PAGE', 60),
        'screening_preview_limit' => (int) env('BOOKING_SCREENING_PREVIEW_LIMIT', 3),
        'movies_per_page' => (int) env('BOOKING_MOVIES_PER_PAGE', 12),
        'screenings_per_page' => (int) env('BOOKING_SCREENINGS_PER_PAGE', 18),
        'admin_page_size' => (int) env('BOOKING_ADMIN_PAGE_SIZE', 20),
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

    'payment' => [
        // Currency and provider used by booking/payment snapshots.
        'currency' => env('BOOKING_CURRENCY', 'USD'),
        'amount_minor_units' => (int) env('BOOKING_AMOUNT_MINOR_UNITS', 0),
        'provider' => env('BOOKING_PAYMENT_PROVIDER', env('STRIPE_SECRET') ? 'stripe' : 'fake'),
        // Maximum time a payment may remain in a processing state.
        'processing_timeout_minutes' => (int) env('BOOKING_PAYMENT_PROCESSING_TIMEOUT_MINUTES', 15),
    ],

    'observability' => [
        // Queries slower than this threshold are reported to logs/metrics.
        'slow_query_ms' => (int) env('BOOKING_SLOW_QUERY_MS', 200),
    ],

    // A delivery lease can be reclaimed when a queue worker dies mid-send.
    'outbox' => [
        'delivery_lease_minutes' => (int) env('BOOKING_OUTBOX_DELIVERY_LEASE_MINUTES', 60),
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
