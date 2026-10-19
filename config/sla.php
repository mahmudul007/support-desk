<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SLA Timezone
    |--------------------------------------------------------------------------
    |
    | The default timezone used to evaluate business days and hours.
    |
    */
    'timezone' => env('SLA_TIMEZONE', 'Asia/Dhaka'),

    /*
    |--------------------------------------------------------------------------
    | SLA Business Days
    |--------------------------------------------------------------------------
    |
    | Days of the week considered standard working days.
    | Carbon::dayOfWeek: 0 = Sunday, 1 = Monday, 2 = Tuesday, 3 = Wednesday, 4 = Thursday.
    |
    */
    'business_days' => [0, 1, 2, 3, 4],

    /*
    |--------------------------------------------------------------------------
    | SLA Working Hours
    |--------------------------------------------------------------------------
    |
    | Working hour window during standard business days.
    |
    */
    'business_start' => env('SLA_BUSINESS_START', '09:00'),
    'business_end' => env('SLA_BUSINESS_END', '18:00'),

    /*
    |--------------------------------------------------------------------------
    | Default SLA Warning Threshold
    |--------------------------------------------------------------------------
    |
    | Percentage of resolution time elapsed before flagging SLA as approaching.
    |
    */
    'default_warning_percent' => (int) env('SLA_WARNING_PERCENT', 80),
];
