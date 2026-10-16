<?php

namespace App\Console\Commands;

use App\Services\CarePlanReviewService;
use Illuminate\Console\Command;

class DispatchCarePlanReviewWarnings extends Command
{
    protected $signature = 'mdm:dispatch-care-plan-review-warnings';

    protected $description = 'Notify supervisors/admins about approaching or overdue Care Plan reviews';

    public function handle(CarePlanReviewService $reviews): int
    {
        $count = $reviews->dispatchDue();
        $this->info("Care Plan review notifications processed: {$count}");

        return self::SUCCESS;
    }
}
