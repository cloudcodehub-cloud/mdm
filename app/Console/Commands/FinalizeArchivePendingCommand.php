<?php

namespace App\Console\Commands;

use App\Services\ArchivePendingService;
use Illuminate\Console\Command;

class FinalizeArchivePendingCommand extends Command
{
    protected $signature = 'mdm:finalize-archive-pending';

    protected $description = 'Finalize Archive Pending clients, DSPs, and Supervisors whose blocking live work has ended (MDM 03)';

    public function handle(ArchivePendingService $archivePending): int
    {
        $result = $archivePending->sweep();

        $this->info(sprintf(
            'Archive Pending finalized — clients:%d employees:%d supervisors:%d',
            $result['clients'],
            $result['employees'],
            $result['supervisors'],
        ));

        return self::SUCCESS;
    }
}
