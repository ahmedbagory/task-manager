<?php

namespace App\Console\Commands;

use App\Services\Notifications\ScheduledNotificationRunner;
use Illuminate\Console\Command;

class RunScheduledNotifications extends Command
{
    protected $signature = 'notifications:run-scheduled';

    protected $description = 'Send due scheduled notification rules';

    public function handle(ScheduledNotificationRunner $runner): int
    {
        $count = $runner->runDueRules();

        if ($count > 0) {
            $this->info("Executed {$count} scheduled notification rule(s).");
        } else {
            $this->info('No due scheduled notification rules.');
        }

        return self::SUCCESS;
    }
}
