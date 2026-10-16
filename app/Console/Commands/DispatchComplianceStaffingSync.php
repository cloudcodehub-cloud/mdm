<?php

namespace App\Console\Commands;

use App\Services\ComplianceStaffingService;
use Illuminate\Console\Command;

class DispatchComplianceStaffingSync extends Command
{
    protected $signature = 'mdm:dispatch-compliance-staffing-sync';

    protected $description = 'Apply MDM 04-18 Grace Visit / At Risk markings for newly effective Hard Stop compliance issues';

    public function handle(ComplianceStaffingService $staffing): int
    {
        $count = $staffing->syncEmployeesWithHardStops();

        $this->info(sprintf('Compliance staffing sync — employees evaluated with Hard Stop action: %d', $count));

        return self::SUCCESS;
    }
}
