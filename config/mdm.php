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
    */

    'visit' => [
        'early_start_minutes' => (int) env('MDM_EARLY_START_MINUTES', 30),
    ],

];
