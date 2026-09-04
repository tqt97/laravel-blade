<?php

return [
    'user_management_retention_days' => (int) env('USER_MANAGEMENT_AUDIT_RETENTION_DAYS', 365),

    'monitoring' => [
        'log_channel' => env('AUDIT_LOG_CHANNEL', 'stack'),
    ],
];
