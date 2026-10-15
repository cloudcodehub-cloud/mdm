<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Visit clock-in windows
    |--------------------------------------------------------------------------
    |
    | early_start_minutes is a temporary testing allowance before the scheduled
    | start. Tighten this value later without changing view code.
    |
    | low_accuracy_meters is a soft UI review threshold only. Poor accuracy never
    | blocks clock-in or clock-out; the value is still stored for review.
    |
    */

    'visit' => [
        'early_start_minutes' => (int) env('MDM_EARLY_START_MINUTES', 30),
        'low_accuracy_meters' => (int) env('MDM_LOW_ACCURACY_METERS', 100),
    ],

];
