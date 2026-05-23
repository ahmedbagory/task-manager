<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsappBridgeProcessService;
use Illuminate\Console\Command;

class WhatsappBridgeRestart extends Command
{
    protected $signature = 'whatsapp:bridge-restart';

    protected $description = 'Restart the WhatsApp bridge PM2 process';

    public function handle(WhatsappBridgeProcessService $service): int
    {
        if (! $service->pm2Exists()) {
            $this->error('PM2 not found at: ' . $service->getPm2Bin());

            return self::FAILURE;
        }

        $this->info('Restarting WhatsApp bridge...');

        $result = $service->restart();

        if ($result['success']) {
            $this->info('Bridge restarted successfully.');

            if ($result['output']) {
                $this->line($result['output']);
            }

            return self::SUCCESS;
        }

        $this->error('Failed to restart bridge.');

        if ($result['error']) {
            $this->error($result['error']);
        }

        if ($result['output']) {
            $this->line($result['output']);
        }

        return self::FAILURE;
    }
}
