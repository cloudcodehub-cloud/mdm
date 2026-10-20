<?php

namespace App\Console\Commands;

use App\Services\SupportTicketService;
use Illuminate\Console\Command;

class CloseExpiredSupportTicketsCommand extends Command
{
    protected $signature = 'mdm:close-expired-support-tickets';

    protected $description = 'Close Resolved support tickets whose 7-day reopen window has elapsed (MDM 12)';

    public function handle(SupportTicketService $tickets): int
    {
        $closed = $tickets->closeExpiredResolved();
        $this->info("Closed {$closed} support ticket(s).");

        return self::SUCCESS;
    }
}
