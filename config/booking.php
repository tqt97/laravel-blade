<?php

return [
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 10),
    'minimum_lead_minutes' => (int) env('BOOKING_MINIMUM_LEAD_MINUTES', 15),
    'maximum_horizon_days' => (int) env('BOOKING_MAXIMUM_HORIZON_DAYS', 90),
    'cancellation_deadline_minutes' => (int) env('BOOKING_CANCELLATION_DEADLINE_MINUTES', 0),
    'check_in_open_minutes' => (int) env('BOOKING_CHECK_IN_OPEN_MINUTES', 120),
    'payment' => ['currency' => env('BOOKING_CURRENCY', 'USD'), 'amount_minor_units' => (int) env('BOOKING_AMOUNT_MINOR_UNITS', 0), 'provider' => env('BOOKING_PAYMENT_PROVIDER', env('STRIPE_SECRET') ? 'stripe' : 'fake'), 'processing_timeout_minutes' => (int) env('BOOKING_PAYMENT_PROCESSING_TIMEOUT_MINUTES', 15)],
    'observability' => ['slow_query_ms' => (int) env('BOOKING_SLOW_QUERY_MS', 200)],
    'operating_hours' => [
        'monday' => ['open' => '00:00', 'close' => '24:00'], 'tuesday' => ['open' => '00:00', 'close' => '24:00'],
        'wednesday' => ['open' => '00:00', 'close' => '24:00'], 'thursday' => ['open' => '00:00', 'close' => '24:00'],
        'friday' => ['open' => '00:00', 'close' => '24:00'], 'saturday' => ['open' => '00:00', 'close' => '24:00'], 'sunday' => ['open' => '00:00', 'close' => '24:00'],
    ],
];
