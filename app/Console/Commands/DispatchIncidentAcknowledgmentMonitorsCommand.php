<?php

namespace App\Console\Commands;

use App\Services\IncidentService;
use Illuminate\Console\Command;

class DispatchIncidentAcknowledgmentMonitorsCommand extends Command
{
    protected $signature = 'mdm:dispatch-incident-acknowledgment-monitors';

    protected $description = 'Escalate submitted Incidents that exceed the Agency acknowledgment threshold';

    public function handle(IncidentService $incidents): int
    {
        $count = $incidents->escalateOverdueAcknowledgments();

        $this->info(sprintf(
            'Incident acknowledgment monitors — overdue escalations:%d (threshold:%d minutes)',
            $count,
            $incidents->acknowledgmentThresholdMinutes(),
        ));

        return self::SUCCESS;
    }
}
