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
        // Frozen MDM 01 §2.39–2.41: explanation at 30+, supervisor escalation at 60+.
        'overrun_reason_after_minutes' => (int) env('MDM_OVERRUN_REASON_AFTER_MINUTES', 30),
        'open_escalation_after_minutes' => (int) env('MDM_OPEN_ESCALATION_AFTER_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timed safety / supervision checks
    |--------------------------------------------------------------------------
    |
    | Intervals and grace match frozen MDM 01 §2.19–2.21. Care plans may set an
    | interval of 5, 10, 15, or 30 minutes; null disables timed checks.
    |
    */

    'safety_check' => [
        'allowed_intervals' => [5, 10, 15, 30],
        'grace_minutes' => (int) env('MDM_SAFETY_CHECK_GRACE_MINUTES', 2),
    ],

];
