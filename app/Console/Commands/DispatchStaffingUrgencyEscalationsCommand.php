<?php

namespace App\Console\Commands;

use App\Services\StaffingUrgencyService;
use Illuminate\Console\Command;

class DispatchStaffingUrgencyEscalationsCommand extends Command
{
    protected $signature = 'mdm:dispatch-staffing-urgency-escalations';

    protected $description = 'Escalate unresolved Staffing Needed visits to Admins once the Agency urgency threshold is reached (MDM 04-29)';

    public function handle(StaffingUrgencyService $urgency): int
    {
        $count = $urgency->escalateDueUrgency();

        $this->info(sprintf(
            'Staffing urgency escalations — newly escalated:%d (threshold:%d hours before start)',
            $count,
            $urgency->thresholdHours(),
        ));

        return self::SUCCESS;
    }
}
