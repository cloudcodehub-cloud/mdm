<?php

namespace App\Console\Commands;

use App\Services\ComplianceExpirationReminderService;
use Illuminate\Console\Command;

class DispatchComplianceExpirationReminders extends Command
{
    protected $signature = 'mdm:dispatch-compliance-expiration-reminders';

    protected $description = 'Dispatch in-app compliance expiration reminders at 30/15/5/0 day milestones';

    public function handle(ComplianceExpirationReminderService $reminders): int
    {
        $counts = $reminders->dispatchDue();

        $this->info(sprintf(
            'Compliance expiration reminders — credentials: %d, trainings: %d',
            $counts['credentials'],
            $counts['trainings'],
        ));

        return self::SUCCESS;
    }
}
