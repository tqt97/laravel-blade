<?php

return [
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 10),
    'minimum_lead_minutes' => (int) env('BOOKING_MINIMUM_LEAD_MINUTES', 15),
    'maximum_horizon_days' => (int) env('BOOKING_MAXIMUM_HORIZON_DAYS', 90),
];
