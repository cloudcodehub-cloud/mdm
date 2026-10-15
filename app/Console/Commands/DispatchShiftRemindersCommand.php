<?php

namespace App\Console\Commands;

use App\Services\ShiftReminderService;
use Illuminate\Console\Command;

class DispatchShiftRemindersCommand extends Command
{
    protected $signature = 'mdm:dispatch-shift-reminders';

    protected $description = 'Dispatch in-app DSP shift reminders (upcoming, ready, start/late attention)';

    public function handle(ShiftReminderService $reminders): int
    {
        $counts = $reminders->dispatchDue();

        $this->info(sprintf(
            'Shift reminders dispatched — upcoming: %d, ready: %d, start_attention: %d',
            $counts['upcoming'],
            $counts['ready'],
            $counts['start_attention'],
        ));

        return self::SUCCESS;
    }
}
