<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsappBridgeProcessService;
use Illuminate\Console\Command;

class WhatsappBridgeStatus extends Command
{
    protected $signature = 'whatsapp:bridge-status';

    protected $description = 'Show the PM2 status of the WhatsApp bridge process';

    public function handle(WhatsappBridgeProcessService $service): int
    {
        if (! $service->pm2Exists()) {
            $this->error('PM2 not found at: ' . $service->getPm2Bin());

            return self::FAILURE;
        }

        $this->info('PM2 binary: ' . $service->getPm2Bin());

        $diag = $service->getDiagnostics();

        $this->table(['Key', 'Value'], [
            ['PM2 Found', $diag['pm2_found'] ? 'Yes' : 'No'],
            ['PM2 Binary', $diag['pm2_bin']],
            ['Node Version', $diag['node_version']],
            ['Bridge Status', $diag['bridge_status']],
            ['PID', $diag['pid'] ?? '-'],
            ['Restart Count', $diag['restart_count'] ?? '-'],
            ['Uptime', $diag['uptime'] ?? '-'],
            ['Memory', $diag['memory'] ?? '-'],
            ['Auth Folder', $diag['auth_exists'] ? 'Exists' : 'Missing'],
        ]);

        $status = $service->getStatus();

        if ($status['output']) {
            $this->line('');
            $this->info('PM2 describe output:');
            $this->line($status['output']);
        }

        if ($status['error']) {
            $this->warn($status['error']);
        }

        return $status['success'] ? self::SUCCESS : self::FAILURE;
    }
}
