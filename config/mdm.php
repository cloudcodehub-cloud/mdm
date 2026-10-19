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

    /*
    |--------------------------------------------------------------------------
    | Authorization capacity warnings
    |--------------------------------------------------------------------------
    |
    | Remaining-capacity pressure is a warning/review condition (Decision 06-12 /
    | MASTER). Definitive expiration/exhaustion remains a hard block separately.
    |
    */

    'authorization' => [
        'remaining_capacity_warning_ratio' => (float) env('MDM_AUTH_REMAINING_WARNING_RATIO', 0.10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Incident management (MDM 07)
    |--------------------------------------------------------------------------
    |
    | Acknowledgment threshold semantics are owned by MDM 07. Default is 60
    | minutes (AMD-07-01 / RQ-05 / MF-05). Agency Settings may expose the value;
    | open-visit escalation (visit.open_escalation_after_minutes) is separate.
    |
    | Exact Agency Category/Subcategory catalogs remain deferred (AMD-07-02);
    | these starters are implementation defaults, not a severity model.
    |
    */

    'incident' => [
        'acknowledgment_minutes_default' => (int) env('MDM_INCIDENT_ACK_MINUTES', 60),
        'categories' => [
            'Injury / medical' => ['Fall', 'Observed injury', 'Seizure', 'Other medical'],
            'Behavioral' => ['Aggression', 'Elopement', 'Self-injury', 'Other behavioral'],
            'Medication / treatment' => ['Missed dose observed', 'Adverse reaction', 'Other'],
            'Environmental / safety' => ['Property damage', 'Unsafe condition', 'Other'],
            'Other' => ['Other'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Staffing Needed urgency (MDM 04-29 / MDM 09)
    |--------------------------------------------------------------------------
    |
    | MDM 04 does not hard-code a universal escalation window. The Agency sets
    | staffing_needed_urgency_hours in Settings; this is only the seed default.
    |
    | Unit: HOURS BEFORE THE SCHEDULED START (evaluated in the Agency Operational
    | Timezone, DST-aware). Future Staffing Needed stays Supervisor-owned; once
    | now >= scheduled_start - threshold the visit is "urgent" and Admins get a
    | single escalation. At/past scheduled start the visit is Overdue Staffing
    | Needed (MDM 04-05) on the Operations board.
    |
    */

    'staffing' => [
        'urgency_hours_default' => (int) env('MDM_STAFFING_URGENCY_HOURS', 24),
    ],

];
