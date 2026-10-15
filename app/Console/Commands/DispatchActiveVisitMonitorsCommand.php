<?php

namespace App\Console\Commands;

use App\Services\ActiveVisitAttentionService;
use Illuminate\Console\Command;

class DispatchActiveVisitMonitorsCommand extends Command
{
    protected $signature = 'mdm:dispatch-active-visit-monitors';

    protected $description = 'Evaluate timed safety checks and dispatch Active Visit wrap-up / past-end attention';

    public function handle(ActiveVisitAttentionService $attention): int
    {
        $counts = $attention->dispatchDue();

        $this->info(sprintf(
            'Active visit monitors — safety created:%d late:%d missed:%d notified:%d · wrap_up:%d past_end:%d forgotten:%d escalations:%d',
            $counts['safety']['created'],
            $counts['safety']['late'],
            $counts['safety']['missed'],
            $counts['safety']['notified'],
            $counts['wrap_up'],
            $counts['past_end'],
            $counts['forgotten'],
            $counts['escalations'],
        ));

        return self::SUCCESS;
    }
}
